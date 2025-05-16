<?php

namespace App\Services\Settlement\Fee;

use App\DTO\TransactionData;
use App\Models\FeeType;
use App\Repositories\ShopRepository;
use App\Repositories\ShopSettingRepository;
use App\Services\DynamicLogger;
use App\Services\Settlement\Fee\Configurations\FeeCondition;
use App\Services\Settlement\Fee\Configurations\FeeConfiguration;
use App\Services\Settlement\Fee\Configurations\FeeRegistry;
use App\Services\Settlement\Fee\Factories\FeeCalculatorFactory;
use App\Services\Settlement\Fee\interfaces\ShopStandardFeeHandlerInterface;
use App\Repositories\Interfaces\FeeRepositoryInterface;

/**
 * Handles the calculation and processing of standard fees for shops
 */
class ShopStandardFeeHandler implements ShopStandardFeeHandlerInterface
{
    private FeeRegistry $feeRegistry;
    private FeeCalculatorFactory $calculatorFactory;
    private bool $initialized = false;

    public function __construct(
        private readonly ShopSettingRepository $shopSettingRepository,
        private readonly ShopRepository $shopRepository,
        private readonly DynamicLogger $logger,
        private readonly FeeRepositoryInterface $feeRepository
    ) {
        $this->feeRegistry = new FeeRegistry;
        $this->calculatorFactory = new FeeCalculatorFactory;
    }

    /**
     * Calculate all applicable standard fees for a shop based on its settings and transaction data
     */
    public function getStandardFees(int $merchantId, int $shopId, array $rawTransactionData): array
    {
        $this->initializeFeeConfigurations();
        if (!$this->initialized) {
            return [];
        }

        try {
            // Get shop settings using external shop ID and merchant account ID
            $internalShopId = $this->shopRepository->getInternalIdByExternalId($shopId, $merchantId);
            $settings = $this->shopSettingRepository->findByShop($internalShopId);

            if (!$settings) {
                $this->logger->log('warning', 'Shop settings not found', [
                    'merchant_id' => $merchantId,
                    'shop_id' => $shopId,
                    'internal_shop_id' => $internalShopId,
                ]);

                return [];
            }

            $transactionData = TransactionData::fromArray($rawTransactionData);
            $standardFees = [];

            // Process each configured fee type
            foreach ($this->feeRegistry->all() as $feeConfig) {
                try {
                    // Special handling for setup fee
                    if ($feeConfig->key === 'setup_fee') {
                        $feeTypeId = $this->getFeeTypeId($feeConfig->key);
                        $setupFeeHistory = $this->feeRepository->getLastShopFeeApplication($internalShopId, $feeTypeId);

                        if ($setupFeeHistory || $settings->setup_fee_charged) {
                            $this->logger->log('info', 'Setup fee already applied for shop, skipping', [
                                'shop_id' => $internalShopId,
                                'setup_fee_charged_flag' => $settings->setup_fee_charged,
                                'has_fee_history' => $setupFeeHistory !== null,
                            ]);
                            continue;
                        }
                    }

                    // Check if fee should be processed based on conditions
                    if ($feeConfig->condition === null || $this->shouldProcessFee($feeConfig, $settings)) {
                        $amount = $this->getFeeAmount($settings, $feeConfig->key);

                        // Create appropriate calculator based on fee configuration
                        $calculator = $this->calculatorFactory->createCalculator(
                            $feeConfig->frequency,
                            $feeConfig->isPercentage,
                            $feeConfig->key
                        );

                        // Calculate the actual fee amount
                        $feeAmount = $calculator->calculate($transactionData, $amount);

                        // Always include all standard fees, even if zero
                        $standardFees[] = [
                            'fee_type' => $feeConfig->name,
                            'fee_type_id' => $this->getFeeTypeId($feeConfig->key),
                            'fee_rate' => $this->formatRate($amount, $feeConfig->isPercentage),
                            'fee_amount' => $feeAmount,
                            'frequency' => $feeConfig->frequency,
                            'is_percentage' => $feeConfig->isPercentage,
                            'transactionData' => $rawTransactionData,
                            'shop_id' => $internalShopId,
                        ];
                    }
                } catch (\Exception $e) {
                    $this->logger->log('error', 'Error processing shop fee', [
                        'merchant_id' => $merchantId,
                        'shop_id' => $shopId,
                        'fee_key' => $feeConfig->key,
                        'error' => $e->getMessage(),
                    ]);

                    continue;
                }
            }

            return $standardFees;
        } catch (\Exception $e) {
            $this->logger->log('error', 'Failed to calculate shop standard fees', [
                'merchant_id' => $merchantId,
                'shop_id' => $shopId,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Initialize fee configurations from the database
     */
    private function initializeFeeConfigurations(): void
    {
        if ($this->initialized) {
            return;
        }

        try {
            $feeTypes = FeeType::all();
            foreach ($feeTypes as $feeType) {
                $condition = $this->getFeeCondition($feeType->key);
                $feeConfig = new FeeConfiguration(
                    $feeType->key,
                    $feeType->name,
                    0,
                    $feeType->is_percentage,
                    $feeType->frequency_type,
                    $condition
                );

                $this->feeRegistry->register($feeConfig);
            }
            $this->initialized = true;
        } catch (\Exception $e) {
            $this->logger->log('error', 'Failed to initialize fee configurations', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Get the condition for a specific fee type
     */
    private function getFeeCondition(string $key): ?FeeCondition
    {
        return match ($key) {
            'mastercard_high_risk_fee' => new FeeCondition(
                fn($settings) => $settings->mastercard_high_risk_fee_applied > 0
            ),
            'visa_high_risk_fee' => new FeeCondition(
                fn($settings) => $settings->visa_high_risk_fee_applied > 0
            ),
            default => null
        };
    }

    /**
     * Determine if a fee should be processed based on its configuration and shop settings
     */
    private function shouldProcessFee(FeeConfiguration $config, $settings): bool
    {
        return !$config->condition || $config->meetsCondition($settings);
    }

    /**
     * Get the fee amount from shop settings based on fee type
     */
    private function getFeeAmount($settings, string $key): int
    {
        return match ($key) {
            'mdr_fee' => $settings->mdr_percentage,
            'transaction_fee' => $settings->transaction_fee,
            'payout_fee' => $settings->payout_fee,
            'refund_fee' => $settings->refund_fee,
            'declined_fee' => $settings->declined_fee,
            'chargeback_fee' => $settings->chargeback_fee,
            'monthly_fee' => $settings->monthly_fee,
            'setup_fee' => $settings->setup_fee,
            'mastercard_high_risk_fee' => $settings->mastercard_high_risk_fee_applied,
            'visa_high_risk_fee' => $settings->visa_high_risk_fee_applied,
            default => 0
        };
    }

    /**
     * Get the fee type ID for a given fee key
     */
    private function getFeeTypeId(string $key): int
    {
        return match ($key) {
            'mdr_fee' => 1,
            'transaction_fee' => 2,
            'monthly_fee' => 3,
            'setup_fee' => 4,
            'payout_fee' => 5,
            'refund_fee' => 6,
            'declined_fee' => 7,
            'chargeback_fee' => 8,
            'mastercard_high_risk_fee' => 9,
            'visa_high_risk_fee' => 10,
            default => 0
        };
    }

    /**
     * Format the fee rate for display
     */
    private function formatRate(int $amount, bool $isPercentage): string
    {
        return $isPercentage ?
            number_format($amount / 100, 2) . '%' :
            number_format($amount / 100, 2);
    }
}
