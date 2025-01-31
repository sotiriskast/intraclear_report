<?php

namespace App\Repositories;

use App\Services\DynamicLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Repositories\Interfaces\TransactionRepositoryInterface;
use Carbon\Carbon;


/**
 * TransactionRepository handles database operations related to merchant transactions.
 *
 * This repository provides methods for retrieving, calculating, and analyzing
 * transaction data across different currencies and time ranges.
 *
 * @package App\Repositories
 */
readonly class TransactionRepository implements TransactionRepositoryInterface
{
    private const RATE = 1.005;

    /**
     * Create a new TransactionRepository instance.
     *
     * @param DynamicLogger $logger Logging service for transaction-related events
     */
    public function __construct(
        private DynamicLogger $logger
    )
    {
    }

    /**
     * Retrieves merchant transactions based on specified criteria.
     *
     * This method fetches transactions for a specific merchant within a given date range,
     * with optional currency filtering. It joins additional tables to provide comprehensive
     * transaction details.
     *
     * @param int $merchantId The unique identifier of the merchant
     * @param array $dateRange Associative array with 'start' and 'end' date keys
     * @param string|null $currency Optional currency filter
     *
     * @return Collection A collection of transaction records
     */
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

    /**
     * Calculates comprehensive transaction totals across different currencies.
     *
     * This method processes a collection of transactions and computes various
     * totals including sales, declined sales, refunds, and their EUR equivalents.
     * It also calculates transaction counts and average exchange rates.
     *
     * @param mixed $transactions Collection of transaction records
     * @param array $exchangeRates Lookup of exchange rates by currency and date
     *
     * @return array Associative array of transaction totals per currency
     */
    public function calculateTransactionTotals(mixed $transactions, array $exchangeRates): array
    {
        $totals = [];
        foreach ($transactions as $transaction) {
            $currency = $transaction->currency;
            if (!isset($totals[$currency])) {
                $totals[$currency] = [
                    'total_sales' => 0,
                    'total_sales_eur' => 0,
                    'transaction_sales_count' => 0,

                    // Declined metrics
                    'total_declined_sales' => 0,
                    'total_declined_sales_eur' => 0,
                    'transaction_declined_count' => 0,

                    // Refund metrics
                    'total_refunds' => 0,
                    'total_refunds_eur' => 0,
                    'transaction_refunds_count' => 0,

                    // Additional data
                    'currency' => $currency,
                    'exchange_rate' => 0
                ];
            }

            $rate = $this->getDailyExchangeRate($transaction, $exchangeRates);
            $amount = $transaction->amount / 100; // Convert from cents

            if (mb_strtoupper($transaction->transaction_type) === 'SALE' &&
                mb_strtoupper($transaction->transaction_status) === 'APPROVED') {
                $totals[$currency]['total_sales'] += $amount;
                $totals[$currency]['total_sales_eur'] += ($amount * ($rate * self::RATE));
                $totals[$currency]['transaction_sales_count']++;
            } elseif (mb_strtoupper($transaction->transaction_type) === 'SALE' &&
                mb_strtoupper($transaction->transaction_status) === 'DECLINED') {
                $totals[$currency]['total_declined_sales'] += $amount;
                $totals[$currency]['total_declined_sales_eur'] += ($amount * ($rate * self::RATE));
                $totals[$currency]['transaction_declined_count']++;
            } elseif (in_array(mb_strtoupper($transaction->transaction_type), ['REFUND', 'PARTIAL REFUND'])) {
                $totals[$currency]['total_refunds'] += $amount;
                $totals[$currency]['total_refunds_eur'] += ($amount * ($rate * self::RATE));
                $totals[$currency]['transaction_refunds_count']++;
            }
        }
        //Get the average exchange rate
        $totals[$currency]['exchange_rate'] = $totals[$currency]['total_sales_eur'] / $totals[$currency]['total_sales'];
        return $totals;
    }

    /**
     * Retrieves exchange rates for specified currencies within a date range.
     *
     * Fetches exchange rates from the scheme_rates table, creating a lookup
     * map with currency, brand, and date as the key.
     *
     * @param array $dateRange Associative array with 'start' and 'end' date keys
     * @param array $currencies Array of currency codes to fetch rates for
     *
     * @return array Associative array of exchange rates
     */
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
    /**
     * Determines the daily exchange rate for a specific transaction.
     *
     * Retrieves the appropriate exchange rate based on transaction currency,
     * card type, and transaction date. Defaults to 1.0 if no rate is found.
     *
     * @param mixed $transaction Transaction record
     * @param array $exchangeRates Lookup of exchange rates
     *
     * @return float Exchange rate for the transaction
     */
    private function getDailyExchangeRate(mixed $transaction, array $exchangeRates): float
    {
        if ($transaction->currency === 'EUR') {
            return 1.0;
        }

        $date = Carbon::parse($transaction->added)->format('Y-m-d');
        $cardType = strtoupper($transaction->card_type ?? 'UNKNOWN');
        $key = "{$transaction->currency}_{$cardType}_{$date}";

        return $exchangeRates[$key] ?? 1.0;
    }
}
