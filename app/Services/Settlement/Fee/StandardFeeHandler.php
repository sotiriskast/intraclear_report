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

class StandardFeeHandler
{
    private FeeRegistry $feeRegistry;
    private FeeCalculatorFactory $calculatorFactory;
    private bool $initialized = false;
    public function __construct(
        private readonly MerchantSettingRepository $merchantSettingRepository,
        private readonly MerchantRepository        $merchantRepository,
        private readonly DynamicLogger             $logger
    )
    {
        $this->feeRegistry = new FeeRegistry();
        $this->calculatorFactory = new FeeCalculatorFactory();

    }

    private function initializeFeeConfigurations(): void
    {
        if ($this->initialized) {
            return;
        }
        try {
            // Get all fee types from database
            $feeTypes = FeeType::all();
            foreach ($feeTypes as $feeType) {
                $condition = $this->getFeeCondition($feeType->key);
                $feeConfig = new FeeConfiguration(
                    $feeType->key,
                    $feeType->name,
                    0, // Amount will be set from settings
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
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
    private function getFeeCondition(string $key): ?FeeCondition
    {
        return match ($key) {
            'setup_fee' => new FeeCondition(fn($settings) => !$settings->setup_fee_charged),
            'mastercard_high_risk_fee' => new FeeCondition(
                fn($settings) => $settings->mastercard_high_risk_fee_applied > 0
            ),
            'visa_high_risk_fee' => new FeeCondition(
                fn($settings) => $settings->visa_high_risk_fee_applied > 0
            ),
            default => null
        };
    }
    public function getStandardFees(int $merchantId, array $rawTransactionData): array
    {
        $this->initializeFeeConfigurations();
        if (!$this->initialized) {
            return [];
        }
        try {
            $settings = $this->merchantSettingRepository->findByMerchant($this->merchantRepository->getMerchantIdByAccountId($merchantId));
            if (!$settings) {
                $this->logger->log('warning', 'Merchant settings not found', [
                    'merchant_id' => $merchantId
                ]);
                return [];
            }
            $transactionData = TransactionData::fromArray($rawTransactionData);
            $standardFees = [];

            foreach ($this->feeRegistry->all() as $feeConfig) {
                try {
                    // For fees without conditions, always process them
                    if ($feeConfig->condition === null || $this->shouldProcessFee($feeConfig, $settings)) {
                        $amount = $this->getFeeAmount($settings, $feeConfig->key);

                        if ($amount > 0) {
                            $calculator = $this->calculatorFactory->createCalculator(
                                $feeConfig->frequency,
                                $feeConfig->isPercentage,
                                $feeConfig->key
                            );

                            $feeAmount = $calculator->calculate($transactionData, $amount);

                            if ($feeAmount > 0) {
                                $standardFees[] = [
                                    'fee_type' => $feeConfig->name,
                                    'fee_type_id' => $this->getFeeTypeId($feeConfig->key),
                                    'fee_rate' => $this->formatRate($amount, $feeConfig->isPercentage),
                                    'fee_amount' => $feeAmount,
                                    'frequency' => $feeConfig->frequency,
                                    'is_percentage' => $feeConfig->isPercentage,
                                    'transactionData' => $rawTransactionData
                                ];
                            }
                        }
                    }
                } catch (\Exception $e) {
                    $this->logger->log('error', 'Error processing fee', [
                        'merchant_id' => $merchantId,
                        'fee_key' => $feeConfig->key,
                        'error' => $e->getMessage()
                    ]);
                    continue;
                }
            }

            return $standardFees;
        } catch (\Exception $e) {
            $this->logger->log('error', 'Failed to calculate standard fees', [
                'merchant_id' => $merchantId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    private function shouldProcessFee(FeeConfiguration $config, $settings): bool
    {
        return !$config->condition || $config->meetsCondition($settings);
    }

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

    private function formatRate(int $amount, bool $isPercentage): string
    {
        return $isPercentage ?
            number_format($amount / 100, 2) . '%' :
            number_format($amount / 100, 2);
    }
}
