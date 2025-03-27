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
        private DynamicLogger                $logger,
        private MerchantRepository           $merchantRepository

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
//                'transactions.bank_amount as amount',
                DB::raw('CAST(transactions.bank_amount AS DECIMAL(12,2)) as amount'),

//                DB::raw('transactions.bank_amount / 100 as amount'), // Convert from cents directly in SQL

                'transactions.bank_currency as currency',
                'transactions.transaction_type',
                'transactions.transaction_status',

                'shop.owner_name as shop_owner_name',
                'customer_card.card_type',
                DB::raw('DATE(transactions.added) as transaction_date'),
            ])
            ->join('shop', 'transactions.shop_id', '=', 'shop.id')
            ->leftJoin('customer_card', 'transactions.card_id', '=', 'customer_card.card_id')
            ->where('transactions.account_id', $merchantId)
//            ->where('customer_card.card_type', '!=', null)
            ->whereBetween('transactions.added', [$dateRange['start'], $dateRange['end']]);

        if ($currency) {
            $query->where('transactions.bank_currency', $currency);
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
            ->where('merchant_id', $this->merchantRepository->getMerchantIdByAccountId($merchantId))
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
        // Get the merchant-specific exchange rate markup but don't apply it yet
        $totals = $this->initializeTotalsArray($transactions);
        foreach ($transactions as $transaction) {
            $currency = $transaction->currency;
            $rate = $this->getDailyExchangeRate($transaction, $exchangeRates);

            // Ensure amount is properly converted to numeric value
            if (is_string($transaction->amount)) {
                $transaction->amount = (float)$transaction->amount;
            }

            // Convert to standard units (dividing by 100) with proper decimal precision
            $amountInStandardUnits = bcmul($transaction->amount, '0.01', 8);

            // Format EUR amount with proper decimal precision from the start
            $amountInEur = bcmul($amountInStandardUnits, (string)$rate, 8);

            // Process transaction with both original and EUR amounts
            $this->processTransactionByType(
                $totals[$currency],
                $transaction,
                $amountInStandardUnits,
                $amountInEur,
                $rate
            );
        }

        // Calculate final exchange rates with markup
        $this->calculateFinalExchangeRates($totals, $exchangeRates, $merchantId);

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
                'total_sales' => '0',
                'total_sales_eur' => '0',
                'transaction_sales_count' => '0',
                'total_declined_sales' => '0',
                'total_declined_sales_eur' => '0',
                'transaction_declined_count' => '0',
                'total_refunds' => '0',
                'total_refunds_eur' => '0',
                'transaction_refunds_count' => '0',
                'total_chargeback_count' => '0',
                'processing_chargeback_count' => '0',
                'processing_chargeback_amount' => '0',
                'processing_chargeback_amount_eur' => '0',
                'approved_chargeback_count' => '0',
                'approved_chargeback_amount' => '0',
                'declined_chargeback_count' => '0',
                'declined_chargeback_amount' => '0',
                'total_payout_count' => '0',
                'total_payout_amount' => '0',
                'total_payout_amount_eur' => '0',
                'processing_payout_count' => '0',
                'processing_payout_amount' => '0',
                'processing_payout_amount_eur' => '0',
                'approved_payout_amount' => '0',
                'approved_payout_amount_eur' => '0',
                'declined_payout_count' => '0',
                'declined_payout_amount' => '0',
                'declined_payout_amount_eur' => '0',
                'currency' => $currency,
                'exchange_rate' => '0',
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
        float $amountInEur,
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
                    $amountInEur,
                    $transactionStatus,
                    $rate
                );
                break;

            case 'PAYOUT':
                $this->processPayoutTransaction(
                    $currencyTotals,
                    $amount,
                    $amountInEur,
                    $transactionStatus
                );
                break;

            case 'SALE':
                $this->processSaleTransaction(
                    $currencyTotals,
                    $amount,
                    $amountInEur,
                    $transactionStatus
                );
                break;

            case 'REFUND':
            case 'PARTIAL REFUND':
                $this->processRefundTransaction(
                    $currencyTotals,
                    $amount,
                    $amountInEur,
                    $transactionStatus
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
        float  $amountInEur,
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
            $currencyTotals['processing_chargeback_amount'] = bcadd($currencyTotals['processing_chargeback_amount'], $amount, 8);
            // Use pre-calculated EUR amount
            $currencyTotals['processing_chargeback_amount_eur'] = bcadd($currencyTotals['processing_chargeback_amount_eur'], $amountInEur, 8);
        } elseif ($transactionStatus === 'APPROVED') {
            $currencyTotals['approved_chargeback_count']++;
            $currencyTotals['approved_chargeback_amount'] = bcadd($currencyTotals['approved_chargeback_amount'], $amount, 8);
            // Use pre-calculated EUR amount
            $currencyTotals['approved_chargeback_amount_eur'] = bcadd($currencyTotals['approved_chargeback_amount_eur'], $amountInEur, 8);
        } else {
            $currencyTotals['declined_chargeback_count']++;
            $currencyTotals['declined_chargeback_amount'] = bcadd($currencyTotals['declined_chargeback_amount'], $amount, 8);
            // Use pre-calculated EUR amount
            $currencyTotals['declined_chargeback_amount_eur'] = bcadd($currencyTotals['declined_chargeback_amount_eur'], $amountInEur, 8);
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
        float  $amountInEur,
        string $transactionStatus
    ): void
    {
        if ($transactionStatus === 'PROCESSING') {
            $currencyTotals['processing_payout_amount'] = bcadd($currencyTotals['processing_payout_amount'], $amount, 8);
            // Use pre-calculated EUR amount
            $currencyTotals['processing_payout_amount_eur'] = bcadd($currencyTotals['processing_payout_amount_eur'], $amountInEur, 8);
            $currencyTotals['processing_payout_count']++;
        } elseif ($transactionStatus === 'APPROVED') {
            $currencyTotals['approved_payout_count']++;
            $currencyTotals['approved_payout_amount'] = bcadd($currencyTotals['approved_payout_amount'], $amount, 8);
            $currencyTotals['approved_payout_amount_eur'] = bcadd($currencyTotals['approved_payout_amount_eur'], $amountInEur, 8);
        } else {
            $currencyTotals['declined_payout_count']++;
            $currencyTotals['declined_payout_amount'] = bcadd($currencyTotals['declined_payout_amount'], $amount, 8);
            $currencyTotals['declined_payout_amount_eur'] = bcadd($currencyTotals['declined_payout_amount_eur'], $amountInEur, 8);
        }

        $currencyTotals['total_payout_count']++;
        $currencyTotals['total_payout_amount'] = bcadd($currencyTotals['total_payout_amount'], $amount, 8);
        $currencyTotals['total_payout_amount_eur'] = bcadd($currencyTotals['total_payout_amount_eur'], $amountInEur, 8);
    }

    /**
     * Process a sale transaction and update totals
     *
     * @param array $currencyTotals
     * @param float $amount
     * @param float $amountInEur
     * @param string $transactionStatus
     */
    private function processSaleTransaction(
        array  &$currencyTotals,
        float  $amount,
        float  $amountInEur,
        string $transactionStatus
    ): void
    {
        if ($transactionStatus === 'APPROVED') {
            $currencyTotals['total_sales'] = bcadd($currencyTotals['total_sales'], $amount, 8);
            // Use pre-calculated EUR amount
            $currencyTotals['total_sales_eur'] = bcadd($currencyTotals['total_sales_eur'], $amountInEur, 8);
            $currencyTotals['transaction_sales_count']++;
        } elseif ($transactionStatus === 'DECLINED') {
            $currencyTotals['total_declined_sales'] = bcadd($currencyTotals['total_declined_sales'], $amount, 8);
            $currencyTotals['total_declined_sales_eur'] = bcadd($currencyTotals['total_declined_sales_eur'], $amountInEur, 8);
            $currencyTotals['transaction_declined_count']++;
        }
    }

    /**
     * Process a refund transaction and update totals
     *
     * @param array $currencyTotals
     * @param float $amount
     * @param float $amountInEur
     * @param string $transactionStatus
     */
    private function processRefundTransaction(
        array  &$currencyTotals,
        float  $amount,
        float  $amountInEur,
        string $transactionStatus
    ): void
    {
        // Only process approved refunds
        if ($transactionStatus === 'APPROVED') {
            $currencyTotals['total_refunds'] = bcadd($currencyTotals['total_refunds'], $amount, 8);
            // Use pre-calculated EUR amount
            $currencyTotals['total_refunds_eur'] = bcadd($currencyTotals['total_refunds_eur'], $amountInEur, 8);
            $currencyTotals['transaction_refunds_count']++;
        }
    }

    /**
     * Calculate final exchange rates for each currency
     *
     * @param array $totals
     * @param array $exchangeRates
     */
    private function calculateFinalExchangeRates(array &$totals, array $exchangeRates, int $merchantId): void
    {
        // Get the merchant-specific exchange rate markup
        $rateMarkup = $this->getMerchantExchangeRateMarkup($merchantId);

        foreach ($totals as $currency => &$currencyData) {
            // Determine the decimal precision for this currency's exchange rate
            $precision = ($currency === 'JPY') ? 2 : 4;

            if ($currencyData['total_sales'] > 0) {
                // Calculate the raw exchange rate from totals
                $total_sales = bcsub($currencyData['total_sales'], ($currencyData['total_refunds'] ?? 0), 8);
                $total_sales_eur = bcsub($currencyData['total_sales_eur'], ($currencyData['total_refunds_eur'] ?? 0), 8);
                $rawExchangeRate = bcdiv($total_sales, $total_sales_eur, 8);
                // Store the raw exchange rate (for reference)
                $currencyData['raw_exchange_rate'] = $rawExchangeRate;

                if ($currency !== 'EUR') {
                    // Apply the markup to the final exchange rate and round to the currency-specific precision
                    $currencyData['exchange_rate'] = round($rawExchangeRate * $rateMarkup, $precision);

                    // Recalculate all EUR values using the final exchange rate
                    $this->recalculateEurValues($currencyData);
                } else {
                    // For EUR, keep the exchange rate as 1.0
                    $currencyData['exchange_rate'] = 1.0;
                }
            } else {
                // If there are no sales, use the last known exchange rate or default to 1.0 for EUR
                $baseRate = $currency === 'EUR' ? 1.0 : $this->getLastKnownExchangeRate($currency, $exchangeRates);
                $currencyData['raw_exchange_rate'] = $baseRate;

                if ($currency !== 'EUR') {
                    // Round to the currency-specific precision
                    $currencyData['exchange_rate'] = round($baseRate * $rateMarkup, $precision);
                } else {
                    $currencyData['exchange_rate'] = 1.0;
                }
            }
        }
    }

    private function recalculateEurValues(array &$currencyData): void
    {
        // Use the final exchange rate to recalculate all EUR values
        $exchangeRate = (string)$currencyData['exchange_rate'];

        // Recalculate all EUR values based on their original currency values
        $currencyData['total_sales_eur'] = bcdiv($currencyData['total_sales'], $exchangeRate, 8);
        $currencyData['total_declined_sales_eur'] = bcdiv($currencyData['total_declined_sales'], $exchangeRate, 8);
        $currencyData['total_refunds_eur'] = bcdiv($currencyData['total_refunds'], $exchangeRate, 8);
        $currencyData['processing_chargeback_amount_eur'] = bcdiv($currencyData['processing_chargeback_amount'], $exchangeRate, 8);
        $currencyData['approved_chargeback_amount_eur'] = bcdiv($currencyData['approved_chargeback_amount'], $exchangeRate, 8);
        $currencyData['declined_chargeback_amount_eur'] = bcdiv($currencyData['declined_chargeback_amount'], $exchangeRate, 8);
        $currencyData['total_payout_amount_eur'] = bcdiv($currencyData['total_payout_amount'], $exchangeRate, 8);
        $currencyData['processing_payout_amount_eur'] = bcdiv($currencyData['processing_payout_amount'], $exchangeRate, 8);
        $currencyData['approved_payout_amount_eur'] = bcdiv($currencyData['approved_payout_amount'], $exchangeRate, 8);
        $currencyData['declined_payout_amount_eur'] = bcdiv($currencyData['declined_payout_amount'], $exchangeRate, 8);

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
            ->orderBy('brand', 'desc')
            ->get();

        // Create a lookup array with currency_brand_date as key
        $rateMap = [];
        foreach ($rates as $rate) {
            $key = $rate->from_currency . '_' . strtoupper($rate->brand) . '_' . $rate->rate_date;
            $rateMap['BUY_' . $key] = $rate->buy;
            $rateMap['SELL_' . $key] = $rate->sell;
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

        // Ensure amount is properly converted to numeric value
        if (is_string($transaction->amount)) {
            $transaction->amount = (float)$transaction->amount;
        }

        $date = Carbon::parse($transaction->added)->format('Y-m-d');
        $transactionType = mb_strtoupper($transaction->transaction_type);

        // Determine rate type based on transaction type
        $rateType = match ($transactionType) {
            'REFUND', 'PARTIAL REFUND', 'CHARGEBACK' => 'BUY_',
            default => 'SELL_'
        };

        // For transactions with a card_type (we're now confident it won't be null)
        $cardType = strtoupper($transaction->card_type);
        $key = $rateType . "{$transaction->currency}_{$cardType}_{$date}";

        if (isset($exchangeRates[$key])) {
            return $exchangeRates[$key];
        }

        $this->logger->log('warning', 'No specific exchange rate found for transaction', [
            'currency' => $transaction->currency,
            'card_type' => $cardType,
            'date' => $date,
            'rate_type' => $rateType,
        ]);

        // Try fallback to any card type for this currency and date
        foreach ($exchangeRates as $rateKey => $rate) {
            if (str_contains($rateKey, "{$transaction->currency}_") &&
                str_contains($rateKey, "_{$date}") &&
                str_starts_with($rateKey, $rateType)) {
                return $rate;
            }
        }

        // If still no match, try any rate for this currency and rate type
        foreach ($exchangeRates as $rateKey => $rate) {
            if (str_starts_with($rateKey, $rateType . $transaction->currency . '_')) {
                return $rate;
            }
        }

        return 1.0; // Ultimate fallback
    }
}
