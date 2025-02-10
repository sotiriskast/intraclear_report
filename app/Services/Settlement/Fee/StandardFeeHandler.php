<?php

namespace App\Services\Settlement\Fee;

use App\DTO\TransactionData;
use App\Models\FeeType;
use App\Repositories\MerchantRepository;
use App\Repositories\MerchantSettingRepository;
use App\Services\DynamicLogger;
use App\Services\Settlement\Fee\Configurations\FeeCondition;
use App\Services\Settlement\Fee\Configurations\FeeConfiguration;
use App\Services\Settlement\Fee\Configurations\FeeRegistry;
use App\Services\Settlement\Fee\Factories\FeeCalculatorFactory;
use App\Services\Settlement\Fee\interfaces\StandardFeeHandlerInterface;

/**
 * Handles the calculation and processing of standard fees for merchants
 * This service is responsible for determining applicable fees based on
 * merchant settings and transaction data
 */
class StandardFeeHandler implements StandardFeeHandlerInterface
{
    private FeeRegistry $feeRegistry;

    private FeeCalculatorFactory $calculatorFactory;

    private bool $initialized = false;

    /**
     * Initialize the fee handler with required dependencies
     *
     * @param  MerchantSettingRepository  $merchantSettingRepository  Repository for merchant settings
     * @param  MerchantRepository  $merchantRepository  Repository for merchant data
     * @param  DynamicLogger  $logger  Logger for tracking operations
     */
    public function __construct(
        private readonly MerchantSettingRepository $merchantSettingRepository,
        private readonly MerchantRepository $merchantRepository,
        private readonly DynamicLogger $logger
    ) {
        $this->feeRegistry = new FeeRegistry;
        $this->calculatorFactory = new FeeCalculatorFactory;
    }

    /**
     * Calculate all applicable standard fees for a merchant based on their settings and transaction data
     *
     * @param  int  $merchantId  The merchant's ID
     * @param  array  $rawTransactionData  Transaction data for fee calculation
     * @return array Array of calculated fees with their details
     */
    public function getStandardFees(int $merchantId, array $rawTransactionData): array
    {
        $this->initializeFeeConfigurations();
        if (! $this->initialized) {
            return [];
        }

        try {
            // Get merchant settings using account ID
            $settings = $this->merchantSettingRepository->findByMerchant(
                $this->merchantRepository->getMerchantIdByAccountId($merchantId)
            );

            if (! $settings) {
                $this->logger->log('warning', 'Merchant settings not found', [
                    'merchant_id' => $merchantId,
                ]);

                return [];
            }

            $transactionData = TransactionData::fromArray($rawTransactionData);
            $standardFees = [];

            // Process each configured fee type
            foreach ($this->feeRegistry->all() as $feeConfig) {
                try {
                    // Check if fee should be processed based on conditions
                    if ($feeConfig->condition === null || $this->shouldProcessFee($feeConfig, $settings)) {
                        $amount = $this->getFeeAmount($settings, $feeConfig->key);

                        if ($amount >= 0) {
                            // Create appropriate calculator based on fee configuration
                            $calculator = $this->calculatorFactory->createCalculator(
                                $feeConfig->frequency,
                                $feeConfig->isPercentage,
                                $feeConfig->key
                            );

                            // Calculate the actual fee amount
                            $feeAmount = $calculator->calculate($transactionData, $amount);

                            if ($feeAmount >= 0) {
                                // Prepare fee details for return
                                $standardFees[] = [
                                    'fee_type' => $feeConfig->name,
                                    'fee_type_id' => $this->getFeeTypeId($feeConfig->key),
                                    'fee_rate' => $this->formatRate($amount, $feeConfig->isPercentage),
                                    'fee_amount' => $feeAmount,
                                    'frequency' => $feeConfig->frequency,
                                    'is_percentage' => $feeConfig->isPercentage,
                                    'transactionData' => $rawTransactionData,
                                ];
                            }
                        }
                    }
                } catch (\Exception $e) {
                    $this->logger->log('error', 'Error processing fee', [
                        'merchant_id' => $merchantId,
                        'fee_key' => $feeConfig->key,
                        'error' => $e->getMessage(),
                    ]);

                    continue; // Continue processing other fees if one fails
                }
            }

            return $standardFees;
        } catch (\Exception $e) {
            $this->logger->log('error', 'Failed to calculate standard fees', [
                'merchant_id' => $merchantId,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Initialize fee configurations from the database
     * This loads all fee types and their configurations into the registry
     *
     * @throws \Exception If initialization fails
     */
    private function initializeFeeConfigurations(): void
    {
        if ($this->initialized) {
            return;
        }

        try {
            // Load all fee types from database
            $feeTypes = FeeType::all();
            foreach ($feeTypes as $feeType) {
                $condition = $this->getFeeCondition($feeType->key);
                $feeConfig = new FeeConfiguration(
                    $feeType->key,
                    $feeType->name,
                    0, // Amount will be set from merchant settings
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
     * Defines when specific fees should be applied based on merchant settings
     *
     * @param  string  $key  The fee type key
     * @return FeeCondition|null Condition object or null if no specific conditions
     */
    private function getFeeCondition(string $key): ?FeeCondition
    {
        return match ($key) {
            'setup_fee' => new FeeCondition(fn ($settings) => ! $settings->setup_fee_charged),
            'mastercard_high_risk_fee' => new FeeCondition(
                fn ($settings) => $settings->mastercard_high_risk_fee_applied > 0
            ),
            'visa_high_risk_fee' => new FeeCondition(
                fn ($settings) => $settings->visa_high_risk_fee_applied > 0
            ),
            default => null
        };
    }

    /**
     * Determine if a fee should be processed based on its configuration and merchant settings
     *
     * @param  FeeConfiguration  $config  Fee configuration
     * @param  object  $settings  Merchant settings
     * @return bool Whether the fee should be processed
     */
    private function shouldProcessFee(FeeConfiguration $config, $settings): bool
    {
        return ! $config->condition || $config->meetsCondition($settings);
    }

    /**
     * Get the fee amount from merchant settings based on fee type
     *
     * @param  object  $settings  Merchant settings
     * @param  string  $key  Fee type key
     * @return int Fee amount in smallest currency unit (e.g., cents)
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
     * Maps fee keys to their corresponding database IDs
     *
     * @param  string  $key  Fee type key
     * @return int Fee type ID
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
     * Converts internal rate representation to human-readable format
     *
     * @param  int  $amount  Fee amount in smallest currency unit
     * @param  bool  $isPercentage  Whether the fee is a percentage
     * @return string Formatted rate with appropriate suffix
     */
    private function formatRate(int $amount, bool $isPercentage): string
    {
        return $isPercentage ?
            number_format($amount / 100, 2).'%' :
            number_format($amount / 100, 2);
    }
}
