<?php

namespace App\Repositories;

use App\Services\DynamicLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Repositories\Interfaces\TransactionRepositoryInterface;
use Carbon\Carbon;

class TransactionRepository implements TransactionRepositoryInterface
{
    public function __construct(
        private readonly DynamicLogger $logger
    )
    {
    }

    public function getMerchantTransactions(int $merchantId, array $dateRange, string $currency = null): Collection
    {
        $query = DB::connection('payment_gateway_mysql')
            ->table('transactions')
            ->select([
                'transactions.account_id',
                'transactions.tid',
                'transactions.card_id',
                'transactions.shop_id',
                'transactions.customer_id',
                'transactions.added',
                'transactions.amount',
                'transactions.currency',
                'transactions.transaction_type',
                'transactions.transaction_status',

                'shop.owner_name as shop_owner_name',
                'customer_card.card_type',
                DB::raw('DATE(transactions.added) as transaction_date')
            ])
            ->join('shop', 'transactions.shop_id', '=', 'shop.id')
            ->leftJoin('customer_card', 'transactions.card_id', '=', 'customer_card.card_id')
            ->where('transactions.account_id', $merchantId)
            ->whereBetween('transactions.added', [$dateRange['start'], $dateRange['end']]);

        if ($currency) {
            $query->where('transactions.currency', $currency);
        }
        $results = $query->get();
        $this->logger->log('info', "Found transactions", ['count' => $results->count()]);
        return $results;
    }

    public function calculateTransactionTotals($transactions, $exchangeRates): array
    {
        $totals = [];
        $rateCount = 0;
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
                    'transaction_sales_count' => 0,
                    'refund_count' => 0,
                    'exchange_rate' => 0
                ];
            }

            $rate = $this->getDailyExchangeRate($transaction, $exchangeRates);
            $rateCount++;
            $amount = $transaction->amount / 100; // Convert from cents

            if (mb_strtoupper($transaction->transaction_type) === 'SALE' &&
                mb_strtoupper($transaction->transaction_status) === 'APPROVED') {
                $totals[$currency]['total_sales'] += $amount;
                $totals[$currency]['total_sales_eur'] += $amount * $rate;
                $totals[$currency]['transaction_sales_count']++;
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
        }
        //Get the average exchange rate
        $totals[$currency]['exchange_rate'] = $totals[$currency]['total_sales_eur']/$totals[$currency]['total_sales'];
        return $totals;
    }

    public function getExchangeRates(array $dateRange, array $currencies)
    {
        $rates = DB::connection('payment_gateway_mysql')
            ->table('scheme_rates')
            ->select([
                'from_currency',
                'brand',
                'sell as rate',
                DB::raw('DATE(added) as rate_date')
            ])
            ->whereIn('from_currency', $currencies)
            ->where('to_currency', 'EUR')
            ->whereBetween('added', [$dateRange['start'], $dateRange['end']])
            ->get();

        // Create a lookup array with currency_brand_date as key
        $rateMap = [];
        foreach ($rates as $rate) {
            $key = $rate->from_currency . '_' . strtoupper($rate->brand) . '_' . $rate->rate_date;
            $rateMap[$key] = $rate->rate;
        }

        return $rateMap;
    }

    private function getDailyExchangeRate($transaction, $exchangeRates): float
    {
        if ($transaction->currency === 'EUR') {
            return 1.0;
        }

        $date = Carbon::parse($transaction->added)->format('Y-m-d');
        $cardType = strtoupper($transaction->card_type ?? 'UNKNOWN');
        $key = "{$transaction->currency}_{$cardType}_{$date}";

        return $exchangeRates[$key] ?? 1.0;
    }

    /**
     * This function is deprecated and should not be used.
     * Use the calculateTransactionTotals() instead.
     *
     * @return Collection
     * @deprecated This function is deprecated since version 2.0.0.
     */
    public function getDailyTotals(int $merchantId, array $dateRange, string $currency = null)
    {
        $query = DB::connection('payment_gateway_mysql')
            ->table('transactions')
            ->select([
                'currency',
                DB::raw('SUM(CASE WHEN transaction_type = "sale" AND transaction_status = "APPROVED" THEN amount ELSE 0 END) as sales_amount'),
                DB::raw('SUM(CASE WHEN transaction_type = "sale" AND transaction_status = "DECLINED" THEN amount ELSE 0 END) as declined_amount'),
                DB::raw('SUM(CASE WHEN transaction_type IN ("Refund", "Partial Refund") AND transaction_status = "APPROVED" THEN amount ELSE 0 END) as refund_amount'),
                DB::raw('COUNT(CASE WHEN transaction_type = "sale" AND transaction_status = "APPROVED" THEN 1 END) as sales_count'),
                DB::raw('COUNT(CASE WHEN transaction_type = "sale" AND transaction_status = "DECLINED" THEN 1 END) as declined_count'),
                DB::raw('COUNT(CASE WHEN transaction_type IN ("Refund", "Partial Refund") AND transaction_status = "APPROVED" THEN 1 END) as refund_count')
            ])
            ->where('account_id', $merchantId)
            ->whereBetween('added', [$dateRange['start'], $dateRange['end']]);

        if ($currency) {
            $query->where('currency', $currency);
        }
        trigger_error('Function getDailyTotals() is deprecated. Use calculateTransactionTotals() instead.', E_USER_DEPRECATED);
        return $query->groupBy('currency')->get();
    }
}
