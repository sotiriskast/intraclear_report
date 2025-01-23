<?php

namespace App\Repositories;

use App\Services\DynamicLogger;
use Illuminate\Support\Facades\DB;
use App\Repositories\Interfaces\TransactionRepositoryInterface;
use Carbon\Carbon;

class TransactionRepository implements TransactionRepositoryInterface
{
    public function __construct(private DynamicLogger $logger)
    {
    }

    public function getMerchantTransactions(int $merchantId, array $dateRange, string $currency = null)
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
        $this->logger->log('info',"Found transactions", ['count' => $results->count()]);

        return $results;
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

    public function getDailyTotals(int $merchantId, array $dateRange, string $currency = null)
    {
        $query = DB::connection('payment_gateway_mysql')
            ->table('transactions')
            ->select([
                DB::raw('DATE(added) as date'),
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

        return $query->groupBy('date', 'currency')->get();
    }
}
