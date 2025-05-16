<?php

namespace App\Services\Settlement\Fee;

use App\Repositories\Interfaces\FeeRepositoryInterface;
use App\Repositories\MerchantRepository;
use App\Repositories\ShopRepository;
use App\Repositories\ShopSettingRepository;
use App\Services\DynamicLogger;
use App\Services\Settlement\Fee\interfaces\FeeFrequencyHandlerInterface;
use App\Services\Settlement\Fee\interfaces\ShopCustomFeeHandlerInterface;
use App\Services\Settlement\Fee\interfaces\ShopStandardFeeHandlerInterface;

/**
 * Main service class responsible for calculating and managing shop-level fees
 */
readonly class ShopFeeService
{
    public function __construct(
        private FeeRepositoryInterface $feeRepository,
        private DynamicLogger $logger,
        private FeeFrequencyHandlerInterface $frequencyHandler,
        private ShopCustomFeeHandlerInterface $customFeeHandler,
        private ShopStandardFeeHandlerInterface $standardFeeHandler,
        private MerchantRepository $merchantRepository,
        private ShopRepository $shopRepository,
        private ShopSettingRepository $shopSettingRepository,
    ) {}

    /**
     * Calculate all applicable fees for a shop within a given date range
     */
    public function calculateFees(int $merchantId, int $shopId, array $transactionData, array $dateRange): array
    {
        try {
            $calculatedFees = [];
            $internalShopId = $this->shopRepository->getInternalIdByExternalId($shopId, $merchantId);
            $actualMerchantId = $this->merchantRepository->getMerchantIdByAccountId($merchantId);
            $isSetupFeeApplied = false;
            $setupFeeId = 4;

            // Check if this is a new shop with no setup fee charged yet
            $isNewShop = $this->isNewShop($internalShopId);

            // Process standard fees (e.g., MDR, transaction fees, etc.)
            $standardFees = $this->standardFeeHandler->getStandardFees($merchantId, $shopId, $transactionData);

            // First pass to check if setup fee is being applied
            foreach ($standardFees as $fee) {
                if ($fee['fee_type_id'] === $setupFeeId &&
                    $this->frequencyHandler->shouldApplyShopFee(
                        $fee['frequency'],
                        $internalShopId,
                        $fee['fee_type_id'],
                        $dateRange
                    )) {
                    $isSetupFeeApplied = true;
                    break;
                }
            }

            // Process all standard fees
            foreach ($standardFees as $fee) {
                $shouldApply = $this->frequencyHandler->shouldApplyShopFee(
                    $fee['frequency'],
                    $internalShopId,
                    $fee['fee_type_id'],
                    $dateRange
                );

                // If setup fee is being applied, apply all standard fees regardless of frequency
                // but only for new shops
                if ($isSetupFeeApplied && $isNewShop) {
                    $shouldApply = true;
                    $this->logger->log('info', 'Applying fee as part of initial shop setup', [
                        'shop_id' => $internalShopId,
                        'merchant_account_id' => $merchantId,
                        'fee_type_id' => $fee['fee_type_id'],
                        'fee_type' => $fee['fee_type'],
                    ]);
                }

                if ($shouldApply) {
                    $calculatedFees[] = $fee;
                    $this->logFeeApplication($actualMerchantId, $internalShopId, $fee, $transactionData, $fee['fee_amount'], $dateRange);

                    // Update setup fee status if it's the setup fee
                    if ($fee['fee_type'] === 'Setup Fee' || $fee['fee_type_id'] === $setupFeeId) {
                        $this->updateSetupFeeStatus($internalShopId);
                        $this->logger->log('info', 'Setup fee applied and marked as charged for shop', [
                            'shop_id' => $internalShopId,
                            'merchant_account_id' => $merchantId,
                        ]);
                    }
                }
            }

            // Only process custom fees if we're not doing initial setup
            if (!$isSetupFeeApplied || !$isNewShop) {
                // Process custom fees (shop-specific fees)
                $customFees = $this->customFeeHandler->getCustomFees($merchantId, $shopId, $transactionData, $dateRange['start']);
                foreach ($customFees as $fee) {
                    // Check if custom fee should be applied based on its frequency
                    if ($this->frequencyHandler->shouldApplyShopFee(
                        $fee['frequency'],
                        $internalShopId,
                        $fee['fee_type_id'],
                        $dateRange
                    )) {
                        $calculatedFees[] = $fee;
                        $this->logFeeApplication($actualMerchantId, $internalShopId, $fee, $transactionData, $fee['fee_amount'], $dateRange);
                    }
                }
            }

            // Log summary of fee calculation
            $this->logger->log('info', 'Shop fee calculation completed', [
                'shop_id' => $internalShopId,
                'merchant_account_id' => $merchantId,
                'standard_fees_count' => count($standardFees),
                'custom_fees_count' => count($customFees ?? []),
                'total_fees' => count($calculatedFees),
                'is_setup_fee_applied' => $isSetupFeeApplied,
                'is_new_shop' => $isNewShop,
            ]);

            return $calculatedFees;
        } catch (\Exception $e) {
            $this->logger->log('error', 'Failed to calculate shop fees', [
                'merchant_id' => $merchantId,
                'shop_id' => $shopId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Check if this is a new shop with no previous fee applications
     */
    private function isNewShop(int $shopId): bool
    {
        try {
            $shopSettings = $this->shopSettingRepository->findByShop($shopId);

            // Check if setup fee has been charged before
            if ($shopSettings && $shopSettings->setup_fee_charged) {
                return false;
            }

            // Check if any fees have been applied before
            $anyFeeApplications = $this->feeRepository->hasAnyShopFeeApplications($shopId);

            // It's a new shop if setup_fee_charged is false and no fees applied before
            $isNew = !$anyFeeApplications;

            $this->logger->log('info', 'Checked if shop is new', [
                'shop_id' => $shopId,
                'setup_fee_charged' => $shopSettings->setup_fee_charged ?? 'not found',
                'has_previous_fees' => $anyFeeApplications,
                'is_new_shop' => $isNew,
            ]);

            return $isNew;
        } catch (\Exception $e) {
            $this->logger->log('error', 'Failed to check if shop is new', [
                'shop_id' => $shopId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Update the shop settings to mark setup fee as charged
     */
    private function updateSetupFeeStatus(int $shopId): void
    {
        try {
            $shopSettings = $this->shopSettingRepository->findByShop($shopId);

            if ($shopSettings) {
                $result = $this->shopSettingRepository->update($shopSettings->id, [
                    'setup_fee_charged' => true
                ]);

                $this->logger->log('info', 'Shop setup fee status updated', [
                    'shop_id' => $shopId,
                    'new_setup_fee_charged' => $result->setup_fee_charged,
                    'update_successful' => $result->wasChanged('setup_fee_charged'),
                ]);
            } else {
                $this->logger->log('warning', 'Cannot update setup fee status - shop settings not found', [
                    'shop_id' => $shopId,
                ]);
            }
        } catch (\Exception $e) {
            $this->logger->log('error', 'Failed to update shop setup fee status', [
                'shop_id' => $shopId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Log the application of a fee for audit and tracking purposes
     */
    private function logFeeApplication(int $merchantId, int $shopId, array $fee, array $transactionData, float|int $feeAmount, array $dateRange): void
    {
        $this->feeRepository->logFeeApplication([
            'merchant_id' => $merchantId,
            'shop_id' => $shopId,
            'fee_type_id' => $fee['fee_type_id'],
            'base_amount' => (int)($transactionData['total_sales'] * 100) ?? 0,
            'base_currency' => $transactionData['currency'] ?? 'EUR',
            'fee_amount_eur' => (int)round($feeAmount * 100, 0, PHP_ROUND_HALF_UP),
            'exchange_rate' => $transactionData['exchange_rate'] ?? 1.0,
            'applied_date' => $dateRange['start'],
        ]);
    }
}
