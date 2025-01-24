<?php

namespace App\Services\Settlement;

use App\Services\DynamicLogger;
use App\Classes\Fees\{Calculators\DailyFeeCalculator, FeeCalculatorFactory};
use App\Classes\Fees\Calculators\MonthlyFeeCalculator;
use App\Classes\Fees\Calculators\OneTimeFeeCalculator;
use App\Classes\Fees\Calculators\TransactionFeeCalculator;
use App\Classes\Fees\Calculators\WeeklyFeeCalculator;
use App\Classes\Fees\Calculators\YearlyFeeCalculator;
use App\Repositories\Interfaces\{FeeRepositoryInterface, TransactionRepositoryInterface};
use App\Services\Settlement\Reserve\RollingReserveHandler;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SettlementService
{
    private $feeCalculatorFactory;
    public function __construct(
        private FeeRepositoryInterface         $feeRepository,
        private TransactionRepositoryInterface $transactionRepository,
        private RollingReserveHandler          $rollingReserveHandler,
        private DynamicLogger                  $logger
    )
    {
        $this->feeCalculatorFactory = new FeeCalculatorFactory();
    }

    public function generateSettlement(int $merchantId, array $dateRange, string $currency): array
    {
        try {
            $this->logger->log('info', 'Starting settlement generation', [
                'merchant_id' => $merchantId,
                'currency' => $currency,
                'date_range' => $dateRange
            ]);

            // Get transactions
            $transactions = $this->transactionRepository->getMerchantTransactions(
                $merchantId,
                $dateRange,
                $currency
            );

            // Calculate exchange rates and totals
            $exchangeRates = $this->transactionRepository->getExchangeRates(
                $dateRange,
                [$currency]
            );

            $totals = $this->calculateTransactionTotals($transactions, $exchangeRates);

            // Calculate fees
            $fees = $this->calculateFees($merchantId, $totals[$currency] ?? [], $dateRange);

            // Process rolling reserve (both new reserves and releases)
            $reserveProcessing = $this->rollingReserveHandler->processSettlementReserve(
                $merchantId,
                $totals[$currency] ?? [],
                $currency,
                $dateRange
            );

            return [
                'total_sales_amount' => $totals[$currency]['total_sales'] ?? 0,
                'total_sales_amount_eur' => $totals[$currency]['total_sales_eur'] ?? 0,
                'total_refunds_amount' => $totals[$currency]['total_refunds'] ?? 0,
                'total_refunds_amount_eur' => $totals[$currency]['total_refunds_eur'] ?? 0,
                'total_sales_transaction' => $totals[$currency]['transaction_count'] ?? 0,
                'total_refunds_transaction' => $totals[$currency]['refund_count'] ?? 0,
                'fees' => $fees,
                'rolling_reserve' => $reserveProcessing['new_reserve'],
                'releaseable_reserve' => $reserveProcessing['released_reserves'],
                'exchange_rate' => $totals[$currency]['exchange_rate'] ?? 1.0
            ];

        } catch (\Exception $e) {
            $this->logger->log('error', 'Settlement generation failed', [
                'merchant_id' => $merchantId,
                'currency' => $currency,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
    private function calculateTransactionTotals($transactions, $exchangeRates): array
    {
        $totals = [];

        foreach ($transactions as $transaction) {
            $currency = $transaction->currency;
            if (!isset($totals[$currency])) {
                $totals[$currency] = [
                    'total_sales' => 0,
                    'total_sales_eur' => 0,
                    'total_declined_sales' => 0,
                    'total_decline_sales_eur' => 0,
                    'transaction_declined_count' => 0,
                    'total_refunds' => 0,
                    'total_refunds_eur' => 0,
                    'transaction_count' => 0,
                    'refund_count' => 0,
                    'exchange_rate' => 0
                ];
            }

            $rate = $this->getExchangeRate($transaction, $exchangeRates);
            $amount = $transaction->amount / 100; // Convert from cents

            if (mb_strtoupper($transaction->transaction_type) === 'SALE' &&
                mb_strtoupper($transaction->transaction_status) === 'APPROVED') {
                $totals[$currency]['total_sales'] += $amount;
                $totals[$currency]['total_sales_eur'] += $amount * $rate;
                $totals[$currency]['transaction_count']++;
            } elseif (mb_strtoupper($transaction->transaction_type) === 'SALE' &&
                mb_strtoupper($transaction->transaction_status) === 'DECLINED') {
                $totals[$currency]['total_declined_sales'] += $amount;
                $totals[$currency]['total_decline_sales_eur'] += $amount * $rate;
                $totals[$currency]['transaction_declined_count']++;
            } elseif (in_array(mb_strtoupper($transaction->transaction_type), ['REFUND', 'PARTIAL REFUND'])) {
                $totals[$currency]['total_refunds'] += $amount;
                $totals[$currency]['total_refunds_eur'] += $amount * $rate;
                $totals[$currency]['refund_count']++;
            }

            $totals[$currency]['exchange_rate'] = $rate;
        }

        return $totals;
    }

    private function calculateFees($merchantId, $currencyTotals, $dateRange): array
    {
        $fees = [];
        $merchantFees = $this->feeRepository->getMerchantFees($merchantId, $dateRange['start']);

        foreach ($merchantFees as $fee) {
            try {
                $calculator = $this->feeCalculatorFactory->create(
                    $fee->feeType->frequency_type,
                    [
                        'amount' => $fee->amount,
                        'is_percentage' => $fee->is_percentage
                    ],
                    $dateRange
                );

                $feeAmount = $calculator->calculate($currencyTotals);

                if ($feeAmount > 0) {
                    $fees[] = $this->createFeeEntry($fee, $currencyTotals, $feeAmount);
                    $this->logFeeApplication($merchantId, $fee, $currencyTotals, $feeAmount, $dateRange);
                }
            } catch (\InvalidArgumentException $e) {
                $this->logger->log('warning', "Fee calculation skipped", [
                    'merchant_id' => $merchantId,
                    'fee_type' => $fee->feeType->frequency_type,
                    'error' => $e->getMessage()
                ]);
                continue;
            }
        }
        return $fees;
    }

    private function calculateRollingReserve($merchantId, $currencyTotals, $currency): array
    {
        $reservePercentage = 10; // This could come from configuration or database

        $reserve = $this->rollingReserveHandler->calculateNewReserve(
            [
                'total_sales' => $currencyTotals['total_sales'] ?? 0,
                'total_sales_eur' => $currencyTotals['total_sales_eur'] ?? 0
            ],
            $reservePercentage
        );

        if ($reserve['reserve_amount_eur'] > 0) {
            $this->rollingReserveHandler->createReserveEntry(
                $merchantId,
                $reserve['original_amount'],
                $reserve['reserve_amount_eur'],
                $currency,
                $currencyTotals['exchange_rate'] ?? 1.0
            );
        }

        return [$reserve];
    }

    private function getExchangeRate($transaction, $exchangeRates): float
    {
        if ($transaction->currency === 'EUR') {
            return 1.0;
        }

        $date = Carbon::parse($transaction->added)->format('Y-m-d');
        $cardType = strtoupper($transaction->card_type ?? 'UNKNOWN');
        $key = "{$transaction->currency}_{$cardType}_{$date}";

        return $exchangeRates[$key] ?? 1.0;
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
}
