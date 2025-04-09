<?php

namespace App\Services\Settlement\Fee;

use App\Repositories\Interfaces\FeeRepositoryInterface;
use App\Repositories\MerchantRepository;
use App\Repositories\MerchantSettingRepository;
use App\Services\DynamicLogger;
use App\Services\Settlement\Fee\interfaces\CustomFeeHandlerInterface;
use App\Services\Settlement\Fee\interfaces\FeeFrequencyHandlerInterface;
use App\Services\Settlement\Fee\interfaces\StandardFeeHandlerInterface;

/**
 * Main service class responsible for calculating and managing merchant fees
 * Orchestrates the calculation of both standard and custom fees while handling
 * frequency checks and logging of fee applications
 */
readonly class FeeService
{
    /**
     * Initialize the fee service with required dependencies
     *
     * @param FeeRepositoryInterface $feeRepository Repository for fee persistence
     * @param DynamicLogger $logger Service for logging operations
     * @param FeeFrequencyHandlerInterface $frequencyHandler Handles fee application frequency
     * @param CustomFeeHandlerInterface $customFeeHandler Handles custom fee calculations
     * @param StandardFeeHandlerInterface $standardFeeHandler Handles standard fee calculations
     */
    public function __construct(
        private FeeRepositoryInterface       $feeRepository,
        private DynamicLogger                $logger,
        private FeeFrequencyHandlerInterface $frequencyHandler,
        private CustomFeeHandlerInterface    $customFeeHandler,
        private StandardFeeHandlerInterface  $standardFeeHandler,
        private MerchantRepository           $merchantRepository,
        private MerchantSettingRepository    $merchantSettingRepository, // Add this dependency

    )
    {
    }

    /**
     * Calculate all applicable fees for a merchant within a given date range
     *
     * This method:
     * 1. Calculates standard fees based on merchant settings
     * 2. Calculates custom fees specific to the merchant
     * 3. Checks if each fee should be applied based on its frequency
     * 4. Logs all applied fees
     *
     * @param int $merchantId ID of the merchant
     * @param array $transactionData Transaction data for fee calculation
     * @param array $dateRange Start and end dates for fee calculation period
     * @return array Array of calculated fees that should be applied
     *
     * @throws \Exception If fee calculation fails
     */
    public function calculateFees(int $merchantId, array $transactionData, array $dateRange): array
    {
        try {
            $calculatedFees = [];
            $actualMerchantId = $this->merchantRepository->getMerchantIdByAccountId($merchantId);

            // Process standard fees (e.g., MDR, transaction fees, etc.)
            $standardFees = $this->standardFeeHandler->getStandardFees($merchantId, $transactionData);
            foreach ($standardFees as $fee) {
                // Check if fee should be applied based on its frequency
                if ($this->frequencyHandler->shouldApplyFee(
                    $fee['frequency'],
                    $merchantId,
                    $fee['fee_type_id'],
                    $dateRange
                )) {
                    $calculatedFees[] = $fee;
                    $this->logFeeApplication($this->merchantRepository->getMerchantIdByAccountId($merchantId), $fee, $transactionData, $fee['fee_amount'], $dateRange);
                    if ($fee['fee_type'] === 'Setup Fee' || $fee['fee_type_id'] === 4) {
                        $this->updateSetupFeeStatus($actualMerchantId);
                        $this->logger->log('info', 'Setup fee applied and marked as charged', [
                            'merchant_id' => $actualMerchantId,
                            'merchant_account_id' => $merchantId,
                        ]);
                    }
                }
            }

            // Process custom fees (merchant-specific fees)
            $customFees = $this->customFeeHandler->getCustomFees($merchantId, $transactionData, $dateRange['start']);
            foreach ($customFees as $fee) {
                // Check if custom fee should be applied based on its frequency
                if ($this->frequencyHandler->shouldApplyFee(
                    $fee['frequency'],
                    $merchantId,
                    $fee['fee_type_id'],
                    $dateRange
                )) {
                    $calculatedFees[] = $fee;
                    $this->logFeeApplication($this->merchantRepository->getMerchantIdByAccountId($merchantId), $fee, $transactionData, $fee['fee_amount'], $dateRange);
                }
            }

            // Log summary of fee calculation
            $this->logger->log('info', 'Fee calculation completed', [
                'merchant_id' => $this->merchantRepository->getMerchantIdByAccountId($merchantId),
                'merchant_account_id' => $merchantId,
                'standard_fees_count' => count($standardFees),
                'custom_fees_count' => count($customFees),
                'total_fees' => count($calculatedFees),
            ]);

            return $calculatedFees;
        } catch (\Exception $e) {
            $this->logger->log('error', 'Failed to calculate fees', [
                'merchant_id' => $merchantId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Update the merchant settings to mark setup fee as charged
     *
     * @param int $merchantId ID of the merchant
     */
    private function updateSetupFeeStatus(int $merchantId): void
    {
        try {
            $merchantSettings = $this->merchantSettingRepository->findByMerchant($merchantId);

            // Log the before state
            $this->logger->log('info', 'Before update setup fee status', [
                'merchant_id' => $merchantId,
                'current_setup_fee_charged' => $merchantSettings->setup_fee_charged ?? 'not found',
            ]);

            if ($merchantSettings) {
                // Update via repository to ensure correct persistence
                $result = $this->merchantSettingRepository->update($merchantSettings->id, [
                    'setup_fee_charged' => true
                ]);

                // Log the update result
                $this->logger->log('info', 'Setup fee status updated', [
                    'merchant_id' => $merchantId,
                    'new_setup_fee_charged' => $result->setup_fee_charged,
                    'update_successful' => $result->wasChanged('setup_fee_charged'),
                ]);
            } else {
                $this->logger->log('warning', 'Cannot update setup fee status - merchant settings not found', [
                    'merchant_id' => $merchantId,
                ]);
            }
        } catch (\Exception $e) {
            $this->logger->log('error', 'Failed to update setup fee status', [
                'merchant_id' => $merchantId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Log the application of a fee for audit and tracking purposes
     *
     * Records details about the fee application including:
     * - Base amount and currency
     * - Fee amount in EUR
     * - Exchange rate used
     * - When the fee was applied
     *
     * @param int $merchantId ID of the merchant
     * @param array $fee Fee configuration and details
     * @param array $transactionData Transaction data used for fee calculation
     * @param float|int $feeAmount Amount of the fee being applied
     * @param array $dateRange Period for which the fee is being applied
     */
    private function logFeeApplication(int $merchantId, array $fee, array $transactionData, float|int $feeAmount, array $dateRange): void
    {
        $this->feeRepository->logFeeApplication([
            'merchant_id' => $merchantId,
            'fee_type_id' => $fee['fee_type_id'],
            'base_amount' => (int)($transactionData['total_sales'] * 100) ?? 0,
            'base_currency' => $transactionData['currency'] ?? 'EUR',
            'fee_amount_eur' => (int)round($feeAmount * 100, 0, PHP_ROUND_HALF_UP),
            'exchange_rate' => $transactionData['exchange_rate'] ?? 1.0,
            'applied_date' => $dateRange['start'],
        ]);
    }
}
