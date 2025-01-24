<?php

namespace App\Services\Settlement\Fee;

use App\Repositories\Interfaces\FeeRepositoryInterface;
use App\Classes\Fees\Calculators\{
    TransactionFeeCalculator,
    DailyFeeCalculator,
    WeeklyFeeCalculator,
    MonthlyFeeCalculator,
    YearlyFeeCalculator,
    OneTimeFeeCalculator
};

class FeeService
{
    private $feeCalculators;

    public function __construct(private readonly FeeRepositoryInterface $feeRepository)
    {
        $this->initializeFeeCalculators();
    }

    private function initializeFeeCalculators()
    {
        $this->feeCalculators = [
            'transaction' => TransactionFeeCalculator::class,
            'daily' => DailyFeeCalculator::class,
            'weekly' => WeeklyFeeCalculator::class,
            'monthly' => MonthlyFeeCalculator::class,
            'yearly' => YearlyFeeCalculator::class,
            'one_time' => OneTimeFeeCalculator::class,
        ];
    }

    public function calculateFees(int $merchantId, array $transactionData, array $dateRange): array
    {
        $merchantFees = $this->feeRepository->getMerchantFees($merchantId, $dateRange['start']);
        $calculatedFees = [];

        foreach ($merchantFees as $fee) {
            $calculatorClass = $this->feeCalculators[$fee->feeType->frequency_type];

            $calculator = new $calculatorClass(
                [
                    'amount' => $fee->amount,
                    'is_percentage' => $fee->is_percentage
                ],
                $dateRange
            );

            $feeAmount = $calculator->calculate($transactionData);

            if ($feeAmount > 0) {
                $calculatedFees[] = [
                    'name' => $fee->feeType->name,
                    'rate' => $this->formatRate($fee),
                    'terminal' => null,
                    'count' => $this->getTransactionCount($fee, $transactionData),
                    'base_amount' => $transactionData['total_sales_amount'],
                    'amount' => $feeAmount,
                    'amount_eur' => $feeAmount
                ];

                $this->logFeeApplication($merchantId, $fee, $transactionData, $feeAmount, $dateRange);
            }
        }

        return $calculatedFees;
    }

    private function formatRate($fee): string
    {
        if ($fee->is_percentage) {
            return number_format($fee->amount / 100, 2) . '%';
        }
        return number_format($fee->amount, 2);
    }

    private function getTransactionCount($fee, $transactionData): ?int
    {
        return $fee->feeType->frequency_type === 'transaction'
            ? ($transactionData['total_sales_transaction'] ?? 0)
            : null;
    }

    private function logFeeApplication($merchantId, $fee, $transactionData, $feeAmount, $dateRange): void
    {
        $this->feeRepository->logFeeApplication([
            'merchant_id' => $merchantId,
            'fee_type_id' => $fee->fee_type_id,
            'transaction_reference' => null,
            'base_amount' => $transactionData['total_sales_amount'],
            'base_currency' => $transactionData['currency'],
            'fee_amount_eur' => $feeAmount,
            'exchange_rate' => $transactionData['exchange_rate'],
            'applied_date' => $dateRange['start']
        ]);
    }
}
