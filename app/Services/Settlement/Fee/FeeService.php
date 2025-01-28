<?php

namespace App\Services\Settlement\Fee;

use App\Classes\Fees\FeeCalculatorFactory;
use App\Repositories\Interfaces\FeeRepositoryInterface;
use App\Services\DynamicLogger;
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
    private $calculatorFactory;
    private $frequencyHandler;

    public function __construct(
        private readonly FeeRepositoryInterface $feeRepository,
        private readonly DynamicLogger          $logger
    )
    {
        $this->calculatorFactory = new FeeCalculatorFactory();
        $this->frequencyHandler = new FeeFrequencyHandler($feeRepository);
    }

    public function calculateFees(int $merchantId, array $transactionData, array $dateRange): array
    {
        $merchantFees = $this->feeRepository->getMerchantFees($merchantId, $dateRange['start']);
        $calculatedFees = [];

        foreach ($merchantFees as $fee) {
            try {
                // Check if the fee should be applied based on its frequency
                if (!$this->frequencyHandler->shouldApplyFee(
                    $fee->feeType->frequency_type,
                    $merchantId,
                    $fee->fee_type_id,
                    $dateRange
                )) {
                    $this->logger->log('info', 'Fee skipped due to frequency rules', [
                        'merchant_id' => $merchantId,
                        'fee_type' => $fee->feeType->frequency_type,
                        'fee_id' => $fee->id
                    ]);
                    continue;
                }

                $calculator = $this->calculatorFactory->create(
                    $fee->feeType->frequency_type,
                    [
                        'amount' => $fee->amount,
                        'is_percentage' => $fee->feeType->is_percentage
                    ],
                    $dateRange
                );

                $feeAmount = $calculator->calculate($transactionData);

                if ($feeAmount > 0) {
                    $calculatedFees[] = [
                        'fee_type' => $fee->feeType->name,
                        'fee_rate' => $this->formatRate($fee),
                        'fee_amount' => $feeAmount,
                        'frequency' => $fee->feeType->frequency_type,
                        'is_percentage' => $fee->feeType->is_percentage,
                    ];

                    $this->logFeeApplication($merchantId, $fee, $transactionData, $feeAmount, $dateRange);
                }
            } catch (\Exception $e) {
                $this->logger->log('error', 'Error calculating fee', [
                    'merchant_id' => $merchantId,
                    'fee_type' => $fee->feeType->frequency_type,
                    'error' => $e->getMessage()
                ]);
                continue;
            }
        }

        return $calculatedFees;
    }

    private function formatRate($fee): string
    {
        if ($fee->feeType->is_percentage) {
            return number_format($fee->amount / 100, 2) . '%';
        }
        return number_format($fee->amount / 100, 2);
    }

    private function logFeeApplication($merchantId, $fee, $transactionData, $feeAmount, $dateRange): void
    {
        $this->feeRepository->logFeeApplication([
            'merchant_id' => $merchantId,
            'fee_type_id' => $fee->fee_type_id,
            'base_amount' => $transactionData['total_sales_amount'] ?? 0,
            'base_currency' => $transactionData['currency'] ?? 'EUR',
            'fee_amount_eur' => $feeAmount,
            'exchange_rate' => $transactionData['exchange_rate'] ?? 1.0,
            'applied_date' => $dateRange['start']
        ]);
    }
}
