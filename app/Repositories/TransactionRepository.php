<?php

namespace App\Repositories;

use App\DTO\ChargebackData;
use App\Repositories\Interfaces\TransactionRepositoryInterface;
use App\Services\DynamicLogger;
use App\Services\Settlement\Chargeback\Interfaces\ChargebackProcessorInterface;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * TransactionRepository handles database operations related to merchant transactions.
 *
 * This repository provides methods for retrieving, calculating, and analyzing
 * transaction data across different currencies and time ranges.
 */
readonly class TransactionRepository implements TransactionRepositoryInterface
{
    /**
     * Create a new TransactionRepository instance.
     *
     * @param DynamicLogger $logger Logging service for transaction-related events
     */
    public function __construct(
        private ChargebackProcessorInterface $chargebackProcessor,
        private DynamicLogger                $logger

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
     * @return Collection A collection of transaction records
     */
    public function getMerchantTransactions(int $merchantId, array $dateRange, ?string $currency = null): Collection
    {
        // First, get all tracked chargeback IDs from our local database for this merchant
        $trackedChargebacks = DB::table('chargeback_trackings')
            ->where('merchant_id', $merchantId)
            ->where('settled', false)  // Only get unresolved chargebacks
            ->pluck('transaction_id')
            ->toArray();
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
                DB::raw('DATE(transactions.added) as transaction_date'),
            ])
            ->join('shop', 'transactions.shop_id', '=', 'shop.id')
            ->leftJoin('customer_card', 'transactions.card_id', '=', 'customer_card.card_id')
            ->where('transactions.account_id', $merchantId)
            ->whereBetween('transactions.added', [$dateRange['start'], $dateRange['end']]);

        if ($currency) {
            $query->where('transactions.currency', $currency);
        }
        $results = $query->get();
        $this->logger->log('info', 'Found transactions', ['count' => $results->count()]);

        return $results;
    }

    /**
     * Get exchange rate markup for a specific merchant
     *
     * @param int $merchantId The merchant ID
     * @return float The exchange rate markup
     */
    private function getMerchantExchangeRateMarkup(int $merchantId): float
    {
        $markup = DB::table('merchant_settings')
            ->where('merchant_id', $merchantId)
            ->value('exchange_rate_markup');

        // Fallback to default if not found
        return $markup ?? 1.01;
    }

    /**
     * Calculates comprehensive transaction totals across different currencies.
     *
     * @param mixed $transactions Collection of transaction records
     * @param array $exchangeRates Lookup of exchange rates by currency and date
     * @param int $merchantId The merchant ID
     * @return array Associative array of transaction totals per currency
     */
    public function calculateTransactionTotals(mixed $transactions, array $exchangeRates, int $merchantId): array
    {
        // Get the merchant-specific exchange rate markup
        $rateMarkup = $this->getMerchantExchangeRateMarkup($merchantId);

        $totals = $this->initializeTotalsArray($transactions);

        foreach ($transactions as $transaction) {
            $currency = $transaction->currency;
            $rate = $this->getDailyExchangeRate($transaction, $exchangeRates);
            $amount = $transaction->amount / 100; // Convert from cents

            // Apply the merchant-specific rate markup instead of the constant
            $effectiveRate = $currency === 'EUR' ? $rate : ($rate * $rateMarkup);

            $this->processTransactionByType(
                $totals[$currency],
                $transaction,
                $amount,
                $effectiveRate,
                $rate
            );
        }

        $this->calculateFinalExchangeRates($totals, $exchangeRates);

        return $totals;
    }

    /**
     * Initialize the totals array structure
     *
     * @param mixed $transactions
     * @return array
     */
    private function initializeTotalsArray(mixed $transactions): array
    {
        $totals = [];
        $uniqueCurrencies = collect($transactions)->pluck('currency')->unique();

        foreach ($uniqueCurrencies as $currency) {
            $totals[$currency] = [
                'total_sales' => 0,
                'total_sales_eur' => 0,
                'transaction_sales_count' => 0,
                'total_declined_sales' => 0,
                'total_declined_sales_eur' => 0,
                'transaction_declined_count' => 0,
                'total_refunds' => 0,
                'total_refunds_eur' => 0,
                'transaction_refunds_count' => 0,
                'total_chargeback_count' => 0,
                'processing_chargeback_count' => 0,
                'processing_chargeback_amount' => 0,
                'processing_chargeback_amount_eur' => 0,
                'approved_chargeback_count' => 0,
                'approved_chargeback_amount' => 0,
                'declined_chargeback_count' => 0,
                'declined_chargeback_amount' => 0,
                'total_payout_count' => 0,
                'total_payout_amount' => 0,
                'total_payout_amount_eur' => 0,
                'processing_payout_count' => 0,
                'processing_payout_amount' => 0,
                'processing_payout_amount_eur' => 0,
                'approved_payout_amount' => 0,
                'approved_payout_amount_eur' => 0,
                'declined_payout_amount' => 0,
                'declined_payout_amount_eur' => 0,
                'currency' => $currency,
                'exchange_rate' => 0,
            ];
        }

        return $totals;
    }

    /**
     * Process a transaction based on its type and status
     *
     * @param array $currencyTotals
     * @param mixed $transaction
     * @param float $amount
     * @param float $effectiveRate
     * @param float $rate
     */
    private function processTransactionByType(
        array &$currencyTotals,
        mixed $transaction,
        float $amount,
        float $effectiveRate,
        float $rate
    ): void
    {
        $transactionType = mb_strtoupper($transaction->transaction_type);
        $transactionStatus = mb_strtoupper($transaction->transaction_status);

        switch ($transactionType) {
            case 'CHARGEBACK':
                $this->processChargebackTransaction(
                    $currencyTotals,
                    $transaction,
                    $amount,
                    $effectiveRate,
                    $transactionStatus,
                    $rate
                );
                break;

            case 'PAYOUT':
                $this->processPayoutTransaction(
                    $currencyTotals,
                    $amount,
                    $effectiveRate,
                    $transactionStatus
                );
                break;

            case 'SALE':
                $this->processSaleTransaction(
                    $currencyTotals,
                    $amount,
                    $effectiveRate,
                    $transactionStatus
                );
                break;

            case 'REFUND':
            case 'PARTIAL REFUND':
                $this->processRefundTransaction(
                    $currencyTotals,
                    $amount,
                    $effectiveRate
                );
                break;
        }
    }

    /**
     * Process a chargeback transaction and update totals
     *
     * @param array $currencyTotals
     * @param mixed $transaction
     * @param float $amount
     * @param float $effectiveRate
     * @param string $transactionStatus
     * @param float $rate
     */
    private function processChargebackTransaction(
        array  &$currencyTotals,
        mixed  $transaction,
        float  $amount,
        float  $effectiveRate,
        string $transactionStatus,
        float  $rate
    ): void
    {
        // Create ChargebackData and process it
        $chargebackData = ChargebackData::fromTransaction($transaction, $rate);
        $this->chargebackProcessor->processChargeback($transaction->account_id, $chargebackData);

        // Handle chargebacks based on status
        if ($transactionStatus === 'PROCESSING') {
            $currencyTotals['processing_chargeback_count']++;
            $currencyTotals['processing_chargeback_amount'] += $amount;
            $currencyTotals['processing_chargeback_amount_eur'] += ($amount * $effectiveRate);
        } elseif ($transactionStatus === 'APPROVED') {
            $currencyTotals['approved_chargeback_count']++;
            $currencyTotals['approved_chargeback_amount'] += $amount;
            $currencyTotals['approved_chargeback_amount_eur'] += ($amount * $effectiveRate);
        } else {
            $currencyTotals['declined_chargeback_count']++;
            $currencyTotals['declined_chargeback_amount'] += $amount;
            $currencyTotals['declined_chargeback_amount_eur'] += ($amount * $effectiveRate);
        }

        $currencyTotals['total_chargeback_count']++;
    }

    /**
     * Process a payout transaction and update totals
     *
     * @param array $currencyTotals
     * @param float $amount
     * @param float $effectiveRate
     * @param string $transactionStatus
     */
    private function processPayoutTransaction(
        array  &$currencyTotals,
        float  $amount,
        float  $effectiveRate,
        string $transactionStatus
    ): void
    {
        if ($transactionStatus === 'PROCESSING') {
            $currencyTotals['processing_payout_count']++;
            $currencyTotals['processing_payout_amount'] += $amount;
            $currencyTotals['processing_payout_amount_eur'] += ($amount * $effectiveRate);
        } elseif ($transactionStatus === 'APPROVED') {
            $currencyTotals['approved_payout_count']++;
            $currencyTotals['approved_payout_amount'] += $amount;
            $currencyTotals['approved_payout_amount_eur'] += ($amount * $effectiveRate);
        } else {
            $currencyTotals['declined_payout_count']++;
            $currencyTotals['declined_payout_amount'] += $amount;
            $currencyTotals['declined_payout_amount_eur'] += ($amount * $effectiveRate);
        }

        $currencyTotals['total_payout_count']++;
        $currencyTotals['total_payout_amount'] += $amount;
        $currencyTotals['total_payout_amount_eur'] += ($amount * $effectiveRate);
    }

    /**
     * Process a sale transaction and update totals
     *
     * @param array $currencyTotals
     * @param float $amount
     * @param float $effectiveRate
     * @param string $transactionStatus
     */
    private function processSaleTransaction(
        array  &$currencyTotals,
        float  $amount,
        float  $effectiveRate,
        string $transactionStatus
    ): void
    {
        if ($transactionStatus === 'APPROVED') {
            $currencyTotals['total_sales'] += $amount;
            $currencyTotals['total_sales_eur'] += ($amount * $effectiveRate);
            $currencyTotals['transaction_sales_count']++;
        } elseif ($transactionStatus === 'DECLINED') {
            $currencyTotals['total_declined_sales'] += $amount;
            $currencyTotals['total_declined_sales_eur'] += ($amount * $effectiveRate);
            $currencyTotals['transaction_declined_count']++;
        }
    }

    /**
     * Process a refund transaction and update totals
     *
     * @param array $currencyTotals
     * @param float $amount
     * @param float $effectiveRate
     */
    private function processRefundTransaction(
        array &$currencyTotals,
        float $amount,
        float $effectiveRate
    ): void
    {
        $currencyTotals['total_refunds'] += $amount;
        $currencyTotals['total_refunds_eur'] += ($amount * $effectiveRate);
        $currencyTotals['transaction_refunds_count']++;
    }

    /**
     * Calculate final exchange rates for each currency
     *
     * @param array $totals
     * @param array $exchangeRates
     */
    private function calculateFinalExchangeRates(array &$totals, array $exchangeRates): void
    {
        foreach ($totals as $currency => &$currencyData) {
            if ($currencyData['total_sales'] > 0) {
                $currencyData['exchange_rate'] = $currencyData['total_sales_eur'] / $currencyData['total_sales'];
            } else {
                // If there are no sales, use the last known exchange rate or default to 1.0 for EUR
                $currencyData['exchange_rate'] = $currency === 'EUR'
                    ? 1.0
                    : $this->getLastKnownExchangeRate($currency, $exchangeRates);
            }
        }
    }

    /**
     * Gets the last known exchange rate for a currency from the provided rates
     *
     * @param string $currency The currency code
     * @param array $exchangeRates Array of exchange rates
     * @return float The exchange rate or 1.0 if not found
     */
    private function getLastKnownExchangeRate(string $currency, array $exchangeRates): float
    {
        // Try to find any rate for this currency
        foreach ($exchangeRates as $key => $rate) {
            if (str_starts_with($key, $currency . '_')) {
                return $rate;
            }
        }

        // Default to 1.0 if no rate found
        $this->logger->log('warning', 'No exchange rate found for currency', [
            'currency' => $currency,
        ]);

        return 1.0;
    }

    /**
     * Retrieves exchange rates for specified currencies within a date range.
     *
     * Fetches exchange rates from the scheme_rates table, creating a lookup
     * map with currency, brand, and date as the key.
     *
     * @param array $dateRange Associative array with 'start' and 'end' date keys
     * @param array $currencies Array of currency codes to fetch rates for
     * @return array Associative array of exchange rates
     */
    public function getExchangeRates(array $dateRange, array $currencies): array
    {
        $rates = DB::connection('payment_gateway_mysql')
            ->table('scheme_rates')
            ->select([
                'from_currency',
                'brand',
                'buy',
                'sell',
                DB::raw('DATE(added) as rate_date'),
            ])
            ->whereIn('from_currency', $currencies)
            ->where('to_currency', 'EUR')
            ->whereBetween('added', [$dateRange['start'], $dateRange['end']])
            ->get();

        // Create a lookup array with currency_brand_date as key
        $rateMap = [];
        foreach ($rates as $rate) {
            $key = $rate->from_currency . '_' . strtoupper($rate->brand) . '_' . $rate->rate_date;
            $rateMap['buy' . $key] = $rate->buy;
            $rateMap['sell' . $key] = $rate->sell;
        }

        return $rateMap;
    }

    /**
     * Determines the daily exchange rate for a specific transaction.
     * Uses 'BUY' rate for sales and 'SELL' rate for refunds/chargebacks.
     *
     * @param mixed $transaction Transaction record
     * @param array $exchangeRates Lookup of exchange rates
     * @return float Exchange rate for the transaction
     */
    private function getDailyExchangeRate(mixed $transaction, array $exchangeRates): float
    {
        if ($transaction->currency === 'EUR') {
            return 1.0;
        }

        $date = Carbon::parse($transaction->added)->format('Y-m-d');
        $cardType = strtoupper($transaction->card_type ?? 'UNKNOWN');
        $transactionType = mb_strtoupper($transaction->transaction_type);

        // Determine rate type based on transaction type
        $rateType = match ($transactionType) {
            'REFUND', 'PARTIAL REFUND', 'CHARGEBACK' => 'sell',
            default => 'buy' // Default to buy rate for other transaction types
        };

        $key = $rateType . "{$transaction->currency}_{$cardType}_{$date}";

        if (!isset($exchangeRates[$key])) {
            $this->logger->log('warning', 'No specific exchange rate found for transaction', [
                'currency' => $transaction->currency,
                'card_type' => $cardType,
                'date' => $date,
                'rate_type' => $rateType,
            ]);

            // Try fallback to any rate for this currency+date combination
            foreach ($exchangeRates as $rateKey => $rate) {
                if (str_contains($rateKey, "{$transaction->currency}_") &&
                    str_contains($rateKey, "_{$date}") &&
                    str_starts_with($rateKey, $rateType)) {
                    return $rate;
                }
            }
        }

        return $exchangeRates[$key] ?? 1.0;
    }
}
