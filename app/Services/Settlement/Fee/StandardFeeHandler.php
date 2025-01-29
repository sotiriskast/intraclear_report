<?php

namespace App\Services\Settlement\Fee;

use App\Repositories\MerchantRepository;
use App\Repositories\MerchantSettingRepository;
use App\Repositories\FeeTypeRepository;
use App\Repositories\FeeRepository;

class StandardFeeHandler
{
    private array $feeTypeMap;

    public function __construct(
        private readonly MerchantSettingRepository $merchantSettingRepository,
        private readonly MerchantRepository        $merchantRepository,
        private readonly FeeTypeRepository         $feeTypeRepository,
        private readonly FeeRepository             $feeRepository
    )
    {
        $this->feeTypeMap = $this->feeTypeRepository->getStandardFeeTypes();
    }

    public function getStandardFees(int $merchantId, array $transactionData, array $dateRange): array
    {
        $settings = $this->merchantSettingRepository->findByMerchant($this->merchantRepository->getMerchantIdByAccountId($merchantId));
        if (!$settings) {
            return [];
        }

        $standardFees = [];
        // Map of fees to process
        $feesToProcess = [
            [
                'key' => 'mdr_fee',
                'name' => 'MDR Fee',
                'amount' => $settings->mdr_percentage,
                'is_percentage' => true,
                'frequency' => 'transaction',
                'calculateAmount' => fn() => ($transactionData['total_sales_eur'] ?? 0) * ($settings->mdr_percentage / 10000)
            ],
            [
                'key' => 'transaction_fee',
                'name' => 'Transaction Fee',
                'amount' => $settings->transaction_fee,
                'is_percentage' => false,
                'frequency' => 'transaction',
                'calculateAmount' => fn() => ($settings->transaction_fee / 100) * ($transactionData['transaction_sales_count'] ?? 0)
            ],
            [
                'key' => 'payout_fee',
                'name' => 'Payout Fee',
                'amount' => $settings->payout_fee,
                'is_percentage' => false,
                'frequency' => 'transaction',
                'calculateAmount' => fn() => $settings->payout_fee / 100
            ],
            [
                'key' => 'refund_fee',
                'name' => 'Refund Fee',
                'amount' => $settings->refund_fee,
                'is_percentage' => false,
                'frequency' => 'transaction',
                'calculateAmount' => fn() => ($settings->refund_fee / 100) * ($transactionData['refund_count'] ?? 0)
            ],
            [
                'key' => 'declined_fee',
                'name' => 'Declined Fee',
                'amount' => $settings->declined_fee,
                'is_percentage' => false,
                'frequency' => 'transaction',
                'calculateAmount' => fn() => ($settings->declined_fee / 100) * ($transactionData['transaction_declined_count'] ?? 0)
            ],
            [
                'key' => 'chargeback_fee',
                'name' => 'Chargeback Fee',
                'amount' => $settings->chargeback_fee,
                'is_percentage' => false,
                'frequency' => 'transaction',
                'calculateAmount' => fn() => ($settings->chargeback_fee / 100) * ($transactionData['chargeback_count'] ?? 0)
            ],
            [
                'key' => 'monthly_fee',
                'name' => 'Monthly Fee',
                'amount' => $settings->monthly_fee,
                'is_percentage' => false,
                'frequency' => 'monthly',
                'calculateAmount' => fn() => $settings->monthly_fee / 100
            ],
            [
                'key' => 'mastercard_high_risk_fee',
                'name' => 'Mastercard High Risk Fee',
                'amount' => $settings->mastercard_high_risk_fee_applied,
                'is_percentage' => false,
                'frequency' => 'monthly',
                'calculateAmount' => fn() => $settings->mastercard_high_risk_fee_applied / 100
            ],
            [
                'key' => 'visa_high_risk_fee',
                'name' => 'Visa High Risk Fee',
                'amount' => $settings->visa_high_risk_fee_applied,
                'is_percentage' => false,
                'frequency' => 'monthly',
                'calculateAmount' => fn() => $settings->visa_high_risk_fee_applied / 100
            ],
            [
                'key' => 'setup_fee',
                'name' => 'Setup Fee',
                'amount' => $settings->setup_fee,
                'is_percentage' => false,
                'frequency' => 'one_time',
                'calculateAmount' => fn() => $settings->setup_fee / 100,
                'condition' => fn() => !$settings->setup_fee_charged
            ]
        ];

        foreach ($feesToProcess as $feeConfig) {
            if (!isset($this->feeTypeMap[$feeConfig['key']])) {
                continue;
            }

            // Skip if fee amount is 0
//            if ($feeConfig['amount'] <= 0) {
//                continue;
//            }

            // Check additional conditions if they exist
            if (isset($feeConfig['condition']) && !$feeConfig['condition']()) {
                continue;
            }

            $feeAmount = $feeConfig['calculateAmount']();
//            if ($feeAmount <= 0) {
//                continue;
//            }

            $fee = [
                'fee_type' => $feeConfig['name'],
                'fee_type_id' => $this->feeTypeMap[$feeConfig['key']]['id'],
                'fee_rate' => $this->formatRate($feeConfig['amount'], $feeConfig['is_percentage']),
                'fee_amount' => $feeAmount,
                'frequency' => $feeConfig['frequency'],
                'is_percentage' => $feeConfig['is_percentage'],
                'transactionData' => $transactionData,
            ];

            $this->logFeeApplication($merchantId, $fee, $transactionData, $dateRange);
            $standardFees[] = $fee;
        }

        return $standardFees;
    }

    private function formatRate(int $amount, bool $isPercentage): string
    {
        if ($isPercentage) {
            return number_format($amount / 100, 2) . '%';
        }
        return number_format($amount / 100, 2);
    }

    private function logFeeApplication(int $merchantId, array $fee, array $transactionData, array $dateRange): void
    {
        $this->feeRepository->logFeeApplication([
            'merchant_id' => $merchantId,
            'fee_type_id' => $fee['fee_type_id'],
            'base_amount' => $transactionData['total_sales_amount'] ?? 0,
            'base_currency' => $transactionData['currency'] ?? 'EUR',
            'fee_amount_eur' => $fee['fee_amount'],
            'exchange_rate' => $transactionData['exchange_rate'] ?? 1.0,
            'applied_date' => $dateRange['start'],
        ]);
    }
}
