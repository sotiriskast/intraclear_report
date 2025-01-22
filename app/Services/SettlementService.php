<?php

namespace App\Services;

use App\Repositories\Interfaces\{
    FeeRepositoryInterface,
    RollingReserveRepositoryInterface,
    TransactionRepositoryInterface
};
use Carbon\Carbon;
use Illuminate\Support\Collection;

class SettlementService
{
    protected $feeRepository;
    protected $reserveRepository;
    protected $transactionRepository;

    public function __construct(
        FeeRepositoryInterface $feeRepository,
        RollingReserveRepositoryInterface $reserveRepository,
        TransactionRepositoryInterface $transactionRepository
    ) {
        $this->feeRepository = $feeRepository;
        $this->reserveRepository = $reserveRepository;
        $this->transactionRepository = $transactionRepository;
    }

    public function generateSettlement(int $merchantId, array $dateRange, string $currency = null)
    {
        // Get all transactions for the period
        $transactions = $this->transactionRepository->getMerchantTransactions($merchantId, $dateRange, $currency);

        // Get exchange rates
        $currencies = $transactions->pluck('currency')->unique()->toArray();
        $exchangeRates = $this->transactionRepository->getExchangeRates($dateRange, $currencies);

        // Calculate totals and convert to EUR
        $totals = $this->calculateTotals($transactions, $exchangeRates);

        // Calculate fees
        $fees = $this->calculateFees($merchantId, $totals, $dateRange);

        // Calculate rolling reserve
        $rollingReserve = $this->calculateRollingReserve($merchantId, $totals, $dateRange);

        // Get releaseable reserve
        $releaseableReserve = $this->getReleaseableReserve($merchantId, $dateRange['end']);

        return [
            'transactions' => $totals,
            'fees' => $fees,
            'rolling_reserve' => $rollingReserve,
            'releaseable_reserve' => $releaseableReserve
        ];
    }

    protected function calculateTotals(Collection $transactions, array $exchangeRates)
    {
        $totals = [];

        foreach ($transactions as $transaction) {
            $date = Carbon::parse($transaction->added)->format('Y-m-d');
            $currency = $transaction->currency;
            $cardType = strtoupper($transaction->card_type);

            // Get exchange rate
            $rateKey = "{$currency}_{$cardType}_{$date}";
            $rate = $exchangeRates[$rateKey] ?? null;

            if (!$rate && $currency !== 'EUR') {
                \Log::error("Missing exchange rate for {$rateKey}");
                continue;
            }

            $amountEur = $currency === 'EUR' ? $transaction->amount : ($transaction->amount * $rate);

            // Initialize period totals if needed
            if (!isset($totals[$currency])) {
                $totals[$currency] = [
                    'currency' => $currency,
                    'total_sales' => 0,
                    'total_sales_eur' => 0,
                    'total_declines' => 0,
                    'total_declines_eur' => 0,
                    'total_refunds' => 0,
                    'total_refunds_eur' => 0,
                    'transaction_count' => 0,
                    'average_rate' => 0,
                ];
            }

            // Update totals based on transaction type and status
            switch (true) {
                case $transaction->transaction_type === 'sale' && $transaction->transaction_status === 'APPROVED':
                    $totals[$currency]['total_sales'] += $transaction->amount;
                    $totals[$currency]['total_sales_eur'] += $amountEur;
                    break;

                case $transaction->transaction_type === 'sale' && $transaction->transaction_status === 'DECLINED':
                    $totals[$currency]['total_declines'] += $transaction->amount;
                    $totals[$currency]['total_declines_eur'] += $amountEur;
                    break;

                case in_array($transaction->transaction_type, ['Refund', 'Partial Refund']):
                    $totals[$currency]['total_refunds'] += $transaction->amount;
                    $totals[$currency]['total_refunds_eur'] += $amountEur;
                    break;
            }

            $totals[$currency]['transaction_count']++;

            // Update average rate
            if ($currency !== 'EUR' && $rate) {
                $totals[$currency]['average_rate'] =
                    $totals[$currency]['total_sales_eur'] / $totals[$currency]['total_sales'];
            }
        }

        return $totals;
    }

    protected function calculateFees(int $merchantId, array $totals, array $dateRange)
    {
        $fees = [];
        $merchantFees = $this->feeRepository->getMerchantFees($merchantId, $dateRange['start']);

        foreach ($merchantFees as $fee) {
            $feeAmount = 0;

            switch ($fee->feeType->frequency_type) {
                case 'transaction':
                    foreach ($totals as $currencyTotals) {
                        if ($fee->is_percentage) {
                            $feeAmount += $currencyTotals['total_sales_eur'] * ($fee->amount / 100);
                        } else {
                            $feeAmount += $fee->amount * $currencyTotals['transaction_count'];
                        }
                    }
                    break;

                case 'daily':
                    $days = Carbon::parse($dateRange['start'])->diffInDays(Carbon::parse($dateRange['end'])) + 1;
                    $feeAmount = $fee->amount * $days;
                    break;

                case 'monthly':
                    $months = Carbon::parse($dateRange['start'])->diffInMonths(Carbon::parse($dateRange['end'])) + 1;
                    $feeAmount = $fee->amount * $months;
                    break;

                case 'yearly':
                case 'one_time':
                    $feeAmount = $fee->amount;
                    break;
            }

            if ($feeAmount > 0) {
                $fees[] = [
                    'type' => $fee->feeType->name,
                    'amount' => $feeAmount,
                    'frequency' => $fee->feeType->frequency_type
                ];

                // Log fee application
                $this->feeRepository->logFeeApplication([
                    'merchant_id' => $merchantId,
                    'fee_type_id' => $fee->fee_type_id,
                    'base_amount' => array_sum(array_column($totals, 'total_sales_eur')),
                    'fee_amount_eur' => $feeAmount,
                    'applied_date' => $dateRange['start']
                ]);
            }
        }

        return $fees;
    }

    protected function calculateRollingReserve(int $merchantId, array $totals, array $dateRange)
    {
        $reserves = [];

        foreach ($totals as $currency => $currencyTotals) {
            $reserveSettings = $this->reserveRepository->getMerchantReserveSettings(
                $merchantId,
                $currency,
                $dateRange['start']
            );

            if (!$reserveSettings) {
                continue;
            }

            $reserveAmount = $currencyTotals['total_sales_eur'] * ($reserveSettings->percentage / 100);

            if ($reserveAmount > 0) {
                $releaseDate = Carbon::parse($dateRange['end'])
                    ->addDays($reserveSettings->holding_period_days);

                $reserves[] = [
                    'currency' => $currency,
                    'original_amount' => $currencyTotals['total_sales'],
                    'reserve_amount_eur' => $reserveAmount,
                    'percentage' => $reserveSettings->percentage,
                    'release_date' => $releaseDate->format('Y-m-d')
                ];

                // Create reserve entry
                $this->reserveRepository->createReserveEntry([
                    'merchant_id' => $merchantId,
                    'original_amount' => $currencyTotals['total_sales'],
                    'original_currency' => $currency,
                    'reserve_amount_eur' => $reserveAmount,
                    'exchange_rate' => $currencyTotals['average_rate'],
                    'transaction_date' => $dateRange['start'],
                    'release_date' => $releaseDate,
                    'status' => 'pending'
                ]);
            }
        }

        return $reserves;
    }

    protected function getReleaseableReserve(int $merchantId, string $date)
    {
        return $this->reserveRepository->getReleaseableFunds($merchantId, $date);
    }
}
