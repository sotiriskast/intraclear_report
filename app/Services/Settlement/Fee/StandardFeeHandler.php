<?php

namespace App\Services\Settlement\Fee;

use App\DTO\TransactionData;
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

    public function __construct(
        private readonly MerchantSettingRepository $merchantSettingRepository,
        private readonly MerchantRepository        $merchantRepository,
        private readonly DynamicLogger             $logger
    )
    {
        $this->feeRegistry = new FeeRegistry();
        $this->calculatorFactory = new FeeCalculatorFactory();
        $this->initializeFeeConfigurations();
    }

    private function initializeFeeConfigurations(): void
    {
        $standardFees = [
            new FeeConfiguration(
                'mdr_fee',
                'MDR Fee',
                0,
                true,
                'transaction'
            ),
            new FeeConfiguration(
                'transaction_fee',
                'Transaction Fee',
                0,
                false,
                'transaction'
            ),
            new FeeConfiguration(
                'payout_fee',
                'Payout Fee',
                0,
                false,
                'transaction'
            ),
            new FeeConfiguration(
                'refund_fee',
                'Refund Fee',
                0,
                false,
                'transaction'
            ),
            new FeeConfiguration(
                'declined_fee',
                'Declined Fee',
                0,
                false,
                'transaction'
            ),
            new FeeConfiguration(
                'chargeback_fee',
                'Chargeback Fee',
                0,
                false,
                'transaction'
            ),
            new FeeConfiguration(
                'monthly_fee',
                'Monthly Fee',
                0,
                false,
                'monthly'
            ),
            new FeeConfiguration(
                'setup_fee',
                'Setup Fee',
                0,
                false,
                'one_time',
                new FeeCondition(fn($settings) => !$settings->setup_fee_charged)
            ),
            new FeeConfiguration(
                'mastercard_high_risk_fee',
                'Mastercard High Risk Fee',
                0,
                false,
                'monthly',
                new FeeCondition(fn($settings) => $settings->mastercard_high_risk_fee_applied > 0)
            ),
            new FeeConfiguration(
                'visa_high_risk_fee',
                'Visa High Risk Fee',
                0,
                false,
                'monthly',
                new FeeCondition(fn($settings) => $settings->visa_high_risk_fee_applied > 0)
            ),
        ];

        foreach ($standardFees as $fee) {
            $this->feeRegistry->register($fee);
        }
    }

    public function getStandardFees(int $merchantId, array $rawTransactionData, array $dateRange): array
    {
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
                    if (!$this->shouldProcessFee($feeConfig, $settings)) {
                        continue;
                    }

                    $amount = $this->getFeeAmount($settings, $feeConfig->key);
                    if (!$amount) {
                        continue;
                    }

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
        return $config->meetsCondition($settings);
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
