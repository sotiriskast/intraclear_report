<?php

namespace App\Repositories;

use App\DTO\ChargebackData;
use App\Enums\TransactionStatusEnum;
use App\Enums\TransactionTypeEnum;
use App\Repositories\Interfaces\TransactionRepositoryInterface;
use App\Services\DynamicLogger;
use App\Services\Settlement\Chargeback\Interfaces\ChargebackProcessorInterface;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Query\Builder;
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
     * Default exchange rate markup if not found in settings
     */
    private const float DEFAULT_EXCHANGE_RATE_MARKUP = 1.01;

    /**
     * Default precision for financial calculations
     */
    private const int DEFAULT_PRECISION = 8;

    /**
     * Currency-specific precision settings
     */
    private const array CURRENCY_PRECISION = [
        'JPY' => 4,
        'DEFAULT' => 6
    ];

    /**
     * Create a new TransactionRepository instance.
     *
     * @param ChargebackProcessorInterface $chargebackProcessor Service for processing chargebacks
     * @param DynamicLogger $logger Logging service for transaction-related events
     * @param MerchantRepository $merchantRepository Repository for merchant data access
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
     * @throws Exception
     */
    public function getMerchantTransactions(int $merchantId, array $dateRange, ?string $currency = null): Collection
    {
        try {
            // Retrieve all unresolved chargebacks for this merchant
            $trackedChargebacks = $this->getTrackedChargebacks($merchantId);

            // Build and execute the query
            $query = $this->buildTransactionQuery($merchantId, $dateRange, $currency);
            $results = $query->get();

            $this->logger->log('info', 'Found transactions', [
                'merchant_id' => $merchantId,
                'count' => $results->count(),
                'date_range' => $dateRange
            ]);

            return $results;
        } catch (Exception $e) {
            $this->logger->log('error', 'Failed to retrieve merchant transactions', [
                'merchant_id' => $merchantId,
                'date_range' => $dateRange,
                'currency' => $currency,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * Get tracked but unresolved chargebacks for a merchant
     *
     * @param int $merchantId The merchant ID
     * @return array Array of chargeback transaction IDs
     */
    private function getTrackedChargebacks(int $merchantId): array
    {
        return DB::table('chargeback_trackings')
            ->where('merchant_id', $merchantId)
            ->where('settled', false)
            ->pluck('transaction_id')
            ->toArray();
    }

    /**
     * Build the query to retrieve transactions
     *
     * @param int $merchantId The merchant ID
     * @param array $dateRange Date range with 'start' and 'end' keys
     * @param string|null $currency Optional currency filter
     * @return Builder The query builder
     */
    private function buildTransactionQuery(int $merchantId, array $dateRange, ?string $currency = null): Builder
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
                DB::raw('CAST(transactions.bank_amount AS DECIMAL(12,2)) as amount'),
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
            ->whereBetween('transactions.added', [$dateRange['start'], $dateRange['end']]);

        if ($currency) {
            $query->where('transactions.bank_currency', $currency);
        }

        return $query;
    }

    /**
     * Calculates comprehensive transaction totals across different currencies.
     *
     * @param mixed $transactions Collection of transaction records
     * @param array $exchangeRates Lookup of exchange rates by currency and date
     * @param int $merchantId The merchant ID
     * @return array Associative array of transaction totals per currency
     * @throws Exception
     */
    public function calculateTransactionTotals(mixed $transactions, array $exchangeRates, int $merchantId): array
    {
        try {
            // Initialize totals array with all currencies in the transactions
            $totals = $this->initializeTotalsArray($transactions);

            // Process each transaction
            foreach ($transactions as $transaction) {
                $this->processTransaction($totals, $transaction, $exchangeRates);
            }
            // Round up all EUR values
            $this->roundUpAllEurValues($totals);
            // Calculate final exchange rates with merchant-specific markup
            $this->calculateFinalExchangeRates($totals, $exchangeRates, $merchantId);

            return $totals;
        } catch (Exception $e) {
            $this->logger->log('error', 'Failed to calculate transaction totals', [
                'merchant_id' => $merchantId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * Round up all EUR values in the totals array
     *
     * @param array $totals Reference to totals array
     */
    private function roundUpAllEurValues(array &$totals): void
    {
        foreach ($totals as $currency => &$currencyData) {
            // List of all fields containing EUR values
            $eurFields = [
                'total_sales_eur',
                'total_declined_sales_eur',
                'total_refunds_eur',
                'processing_chargeback_amount_eur',
                'approved_chargeback_amount_eur',
                'declined_chargeback_amount_eur',
                'total_payout_amount_eur',
                'processing_payout_amount_eur',
                'approved_payout_amount_eur',
                'declined_payout_amount_eur'
            ];

            // Round up each EUR field using ceil() to ensure no decimals and round up
            foreach ($eurFields as $field) {
                if (isset($currencyData[$field])) {
                    $currencyData[$field] = round($currencyData[$field]);
                }
            }
        }
    }

    /**
     * Process a single transaction and update relevant totals
     *
     * @param array $totals Reference to totals array
     * @param mixed $transaction The transaction object
     * @param array $exchangeRates Exchange rates lookup
     */
    private function processTransaction(array &$totals, mixed $transaction, array $exchangeRates): void
    {
        $currency = $transaction->currency;
        $rate = $this->getDailyExchangeRate($transaction, $exchangeRates);

        // Normalize amount to numeric value
        $amount = $this->normalizeAmount($transaction->amount);

        // Convert to standard units (divide by 100)
        $amountInStandardUnits = $amount * 0.01;

        // Calculate EUR amount with precision
        $amountInEur = $amountInStandardUnits * $rate;

        // Process based on transaction type and status
        $this->processTransactionByType(
            $totals[$currency],
            $transaction,
            $amountInStandardUnits,
            $amountInEur,
            $rate
        );
    }

    /**
     * Normalize transaction amount to float for standard math operations
     *
     * @param mixed $amount The transaction amount
     * @return float Normalized amount as float
     */
    private function normalizeAmount(mixed $amount): float
    {
        return is_string($amount) ? (float)$amount : $amount;
    }

    /**
     * Initialize the totals array structure with default values
     *
     * @param mixed $transactions Collection of transactions
     * @return array Initialized totals array
     */
    private function initializeTotalsArray(mixed $transactions): array
    {
        $totals = [];
        $uniqueCurrencies = collect($transactions)->pluck('currency')->unique();

        foreach ($uniqueCurrencies as $currency) {
            $totals[$currency] = [
                'total_sales' => 0.0,
                'total_sales_eur' => 0.0,
                'transaction_sales_count' => 0,
                'total_declined_sales' => 0.0,
                'total_declined_sales_eur' => 0.0,
                'transaction_declined_count' => 0,
                'total_refunds' => 0.0,
                'total_refunds_eur' => 0.0,
                'transaction_refunds_count' => 0,
                'total_chargeback_count' => 0,
                'processing_chargeback_count' => 0,
                'processing_chargeback_amount' => 0.0,
                'processing_chargeback_amount_eur' => 0.0,
                'approved_chargeback_count' => 0,
                'approved_chargeback_amount' => 0.0,
                'approved_chargeback_amount_eur' => 0.0,
                'declined_chargeback_count' => 0,
                'declined_chargeback_amount' => 0.0,
                'declined_chargeback_amount_eur' => 0.0,
                'total_payout_count' => 0,
                'total_payout_amount' => 0.0,
                'total_payout_amount_eur' => 0.0,
                'processing_payout_count' => 0,
                'processing_payout_amount' => 0.0,
                'processing_payout_amount_eur' => 0.0,
                'approved_payout_count' => 0,
                'approved_payout_amount' => 0.0,
                'approved_payout_amount_eur' => 0.0,
                'declined_payout_count' => 0,
                'declined_payout_amount' => 0.0,
                'declined_payout_amount_eur' => 0.0,
                'currency' => $currency,
                'exchange_rate' => 0.0,
            ];
        }

        return $totals;
    }

    /**
     * Process a transaction based on its type and status
     *
     * @param array $currencyTotals Reference to currency totals array
     * @param mixed $transaction The transaction object
     * @param float $amount Amount in standard units
     * @param float $amountInEur Amount in EUR
     * @param float $rate Exchange rate
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
            case TransactionTypeEnum::CHARGEBACK->value:
                $this->processChargebackTransaction(
                    $currencyTotals,
                    $transaction,
                    $amount,
                    $amountInEur,
                    $transactionStatus,
                    $rate
                );
                break;

            case TransactionTypeEnum::PAYOUT->value:
                $this->processPayoutTransaction(
                    $currencyTotals,
                    $amount,
                    $amountInEur,
                    $transactionStatus
                );
                break;

            case TransactionTypeEnum::SALE->value:
                $this->processSaleTransaction(
                    $currencyTotals,
                    $amount,
                    $amountInEur,
                    $transactionStatus
                );
                break;

            case TransactionTypeEnum::REFUND->value:
            case TransactionTypeEnum::PARTIAL_REFUND->value:
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
     * @param array $currencyTotals Reference to currency totals
     * @param mixed $transaction The transaction object
     * @param float $amount Amount in standard units
     * @param float $amountInEur Amount in EUR
     * @param string $transactionStatus Transaction status
     * @param float $rate Exchange rate
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
        // Create and process chargeback data
        $chargebackData = ChargebackData::fromTransaction($transaction, $rate);
        $this->chargebackProcessor->processChargeback($transaction->account_id, $chargebackData);

        // Update appropriate totals based on status
        if ($transactionStatus === TransactionStatusEnum::PROCESSING->value) {
            $currencyTotals['processing_chargeback_count']++;
            $currencyTotals['processing_chargeback_amount'] += $amount;
            $currencyTotals['processing_chargeback_amount_eur'] += $amountInEur;
        } elseif ($transactionStatus === TransactionStatusEnum::APPROVED->value) {
            $currencyTotals['approved_chargeback_count']++;
            $currencyTotals['approved_chargeback_amount'] += $amount;
            $currencyTotals['approved_chargeback_amount_eur'] += $amountInEur;
        } else {
            $currencyTotals['declined_chargeback_count']++;
            $currencyTotals['declined_chargeback_amount'] += $amount;
            $currencyTotals['declined_chargeback_amount_eur'] += $amountInEur;
        }

        $currencyTotals['total_chargeback_count']++;
    }

    /**
     * Process a payout transaction and update totals
     *
     * @param array $currencyTotals Reference to currency totals
     * @param float $amount Amount in standard units
     * @param float $amountInEur Amount in EUR
     * @param string $transactionStatus Transaction status
     */
    private function processPayoutTransaction(
        array  &$currencyTotals,
        float  $amount,
        float  $amountInEur,
        string $transactionStatus
    ): void
    {
        // Update payout totals based on status
        if ($transactionStatus === TransactionStatusEnum::PROCESSING->value) {
            $currencyTotals['processing_payout_count']++;
            $currencyTotals['processing_payout_amount'] += $amount;
            $currencyTotals['processing_payout_amount_eur'] += $amountInEur;
        } elseif ($transactionStatus === TransactionStatusEnum::APPROVED->value) {
            $currencyTotals['approved_payout_count']++;
            $currencyTotals['approved_payout_amount'] += $amount;
            $currencyTotals['approved_payout_amount_eur'] += $amountInEur;
        } elseif ($transactionStatus === TransactionStatusEnum::DECLINED->value) {
            $currencyTotals['declined_payout_count']++;
            $currencyTotals['declined_payout_amount'] += $amount;
            $currencyTotals['declined_payout_amount_eur'] += $amountInEur;
        }

        // Always update total payout counters regardless of status
        $currencyTotals['total_payout_count']++;
        $currencyTotals['total_payout_amount'] += $amount;
        $currencyTotals['total_payout_amount_eur'] += $amountInEur;
    }

    /**
     * Process a sale transaction and update totals
     *
     * @param array $currencyTotals Reference to currency totals
     * @param float $amount Amount in standard units
     * @param float $amountInEur Amount in EUR
     * @param string $transactionStatus Transaction status
     */
    private function processSaleTransaction(
        array  &$currencyTotals,
        float  $amount,
        float  $amountInEur,
        string $transactionStatus
    ): void
    {
        if ($transactionStatus === TransactionStatusEnum::APPROVED->value) {
            $currencyTotals['total_sales'] += $amount;
            $currencyTotals['total_sales_eur'] += $amountInEur;
            $currencyTotals['transaction_sales_count']++;
        } elseif ($transactionStatus === TransactionStatusEnum::DECLINED->value) {
            $currencyTotals['total_declined_sales'] += $amount;
            $currencyTotals['total_declined_sales_eur'] += $amountInEur;
            $currencyTotals['transaction_declined_count']++;
        }
    }

    /**
     * Process a refund transaction and update totals
     *
     * @param array $currencyTotals Reference to currency totals
     * @param float $amount Amount in standard units
     * @param float $amountInEur Amount in EUR
     * @param string $transactionStatus Transaction status
     */
    private function processRefundTransaction(
        array  &$currencyTotals,
        float  $amount,
        float  $amountInEur,
        string $transactionStatus
    ): void
    {
        // Only process approved refunds
        if ($transactionStatus === TransactionStatusEnum::APPROVED->value) {
            $currencyTotals['total_refunds'] += $amount;
            $currencyTotals['total_refunds_eur'] += $amountInEur;
            $currencyTotals['transaction_refunds_count']++;
        }
    }

    /**
     * Calculate final exchange rates for each currency with merchant-specific markup
     *
     * @param array $totals Reference to totals array
     * @param array $exchangeRates Exchange rates lookup
     * @param int $merchantId The merchant ID
     */
    private function calculateFinalExchangeRates(array &$totals, array $exchangeRates, int $merchantId): void
    {
        // Get merchant-specific exchange rate markup
        $rateMarkup = $this->getMerchantExchangeRateMarkup($merchantId);

        foreach ($totals as $currency => &$currencyData) {
            // Set appropriate decimal precision based on currency
            $precision = $this->getPrecisionForCurrency($currency);

            if ($currencyData['total_sales'] > 0) {
                // Calculate the exchange rate from existing data
                $netSales = $currencyData['total_sales'] - ($currencyData['total_refunds'] ?? 0);
                $netSalesEur = $currencyData['total_sales_eur'] - ($currencyData['total_refunds_eur'] ?? 0);

                // Avoid division by zero
                if ($netSalesEur > 0) {
                    $rawExchangeRate = $netSales / $netSalesEur;
                    $currencyData['raw_exchange_rate'] = $rawExchangeRate;

                    // Apply markup for non-EUR currencies
                    if ($currency !== 'EUR') {
                        $finalRate = round($rawExchangeRate * $rateMarkup, $precision);
                        $currencyData['exchange_rate'] = $finalRate;
                        $this->recalculateEurValues($currencyData);
                    } else {
                        $currencyData['exchange_rate'] = 1.0;
                    }
                } else {
                    $this->handleZeroDivisionCase($currencyData, $currency, $exchangeRates, $rateMarkup, $precision);
                }
            } else {
                $this->handleZeroDivisionCase($currencyData, $currency, $exchangeRates, $rateMarkup, $precision);
            }
        }
    }

    /**
     * Get the appropriate decimal precision for a currency
     *
     * @param string $currency The currency code
     * @return int The precision to use
     */
    private function getPrecisionForCurrency(string $currency): int
    {
        return self::CURRENCY_PRECISION[$currency] ?? self::CURRENCY_PRECISION['DEFAULT'];
    }

    /**
     * Handle exchange rate calculation for cases with zero sales or division by zero
     *
     * @param array $currencyData Reference to currency data
     * @param string $currency The currency code
     * @param array $exchangeRates Exchange rates lookup
     * @param float $rateMarkup Exchange rate markup
     * @param int $precision Decimal precision for rounding
     */
    private function handleZeroDivisionCase(
        array  &$currencyData,
        string $currency,
        array  $exchangeRates,
        float  $rateMarkup,
        int    $precision
    ): void
    {
        // Use last known rate or default to 1.0 for EUR
        $baseRate = ($currency === 'EUR') ? 1.0 : $this->getLastKnownExchangeRate($currency, $exchangeRates);
        $currencyData['raw_exchange_rate'] = $baseRate;

        if ($currency !== 'EUR') {
            $currencyData['exchange_rate'] = round($baseRate * $rateMarkup, $precision);
            $this->recalculateEurValues($currencyData);
        } else {
            $currencyData['exchange_rate'] = 1.0;
        }
    }

    /**
     * Recalculate all EUR values based on the final exchange rate
     *
     * @param array $currencyData Reference to currency data
     */
    private function recalculateEurValues(array &$currencyData): void
    {
        $exchangeRate = $currencyData['exchange_rate'];

        // Skip if exchange rate is 0 to avoid division by zero
        if ($exchangeRate <= 0) {
            return;
        }

        // Recalculate each EUR value using the standardized exchange rate
        // This ensures consistency across all converted values
        $fieldsToRecalculate = [
            'total_sales' => 'total_sales_eur',
            'total_declined_sales' => 'total_declined_sales_eur',
            'total_refunds' => 'total_refunds_eur',
            'processing_chargeback_amount' => 'processing_chargeback_amount_eur',
            'approved_chargeback_amount' => 'approved_chargeback_amount_eur',
            'declined_chargeback_amount' => 'declined_chargeback_amount_eur',
            'total_payout_amount' => 'total_payout_amount_eur',
            'processing_payout_amount' => 'processing_payout_amount_eur',
            'approved_payout_amount' => 'approved_payout_amount_eur',
            'declined_payout_amount' => 'declined_payout_amount_eur',
        ];

        foreach ($fieldsToRecalculate as $originalField => $eurField) {
            if (isset($currencyData[$originalField]) && $currencyData[$originalField] != 0) {
                $currencyData[$eurField] = round($currencyData[$originalField] / $exchangeRate, 2);
            }
        }
    }

    /**
     * Get exchange rate markup for a specific merchant
     *
     * @param int $merchantId The merchant ID
     * @return float The exchange rate markup
     */
    private function getMerchantExchangeRateMarkup(int $merchantId): float
    {
        try {
            $internalMerchantId = $this->merchantRepository->getMerchantIdByAccountId($merchantId);

            $markup = DB::table('merchant_settings')
                ->where('merchant_id', $internalMerchantId)
                ->value('exchange_rate_markup');

            return $markup ?? self::DEFAULT_EXCHANGE_RATE_MARKUP;
        } catch (Exception $e) {
            $this->logger->log('warning', 'Failed to retrieve exchange rate markup, using default', [
                'merchant_id' => $merchantId,
                'error' => $e->getMessage()
            ]);

            return self::DEFAULT_EXCHANGE_RATE_MARKUP;
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
     * @throws Exception
     */
    public function getExchangeRates(array $dateRange, array $currencies): array
    {
        try {
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

            // Create lookup array with formatted keys
            return $this->formatExchangeRates($rates);
        } catch (Exception $e) {
            $this->logger->log('error', 'Failed to retrieve exchange rates', [
                'currencies' => $currencies,
                'date_range' => $dateRange,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * Format exchange rates into a lookup array
     *
     * @param Collection $rates Collection of rate records
     * @return array Formatted lookup array
     */
    private function formatExchangeRates(Collection $rates): array
    {
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
        // EUR always has a rate of 1.0
        if ($transaction->currency === 'EUR') {
            return 1.0;
        }

        // Normalize amount
        $transaction->amount = $this->normalizeAmount($transaction->amount);

        // Get transaction date
        $date = Carbon::parse($transaction->added)->format('Y-m-d');
        $transactionType = mb_strtoupper($transaction->transaction_type);

        // Determine rate type based on transaction type
        $rateType = $this->determineRateType($transactionType);

        // Try to get rate for specific card type
        $rate = $this->getRateForCardType($transaction, $date, $rateType, $exchangeRates);

        if ($rate !== null) {
            return $rate;
        }

        // Try fallback strategies
        return $this->getFallbackRate($transaction, $date, $rateType, $exchangeRates);
    }

    /**
     * Determine the rate type (BUY/SELL) based on transaction type
     *
     * @param string $transactionType The transaction type
     * @return string The rate type prefix
     */
    private function determineRateType(string $transactionType): string
    {
        return match ($transactionType) {
            TransactionTypeEnum::REFUND->value,
            TransactionTypeEnum::PARTIAL_REFUND->value,
            TransactionTypeEnum::CHARGEBACK->value => 'BUY_',
            default => 'SELL_'
        };
    }

    /**
     * Get exchange rate for a specific card type
     *
     * @param mixed $transaction The transaction object
     * @param string $date Transaction date
     * @param string $rateType Rate type (BUY_/SELL_)
     * @param array $exchangeRates Exchange rates lookup
     * @return float|null Exchange rate or null if not found
     */
    private function getRateForCardType(mixed $transaction, string $date, string $rateType, array $exchangeRates): ?float
    {
        // Only attempt if card_type is available
        if (!empty($transaction->card_type)) {
            $cardType = strtoupper($transaction->card_type);
            $key = $rateType . "{$transaction->currency}_{$cardType}_{$date}";

            if (isset($exchangeRates[$key])) {
                return $exchangeRates[$key];
            }

            $this->logger->log('debug', 'No specific exchange rate found for card type', [
                'currency' => $transaction->currency,
                'card_type' => $cardType,
                'date' => $date
            ]);
        }

        return null;
    }

    /**
     * Get fallback exchange rate when specific card type rate is not available
     *
     * @param mixed $transaction The transaction object
     * @param string $date Transaction date
     * @param string $rateType Rate type (BUY_/SELL_)
     * @param array $exchangeRates Exchange rates lookup
     * @return float Fallback exchange rate
     */
    private function getFallbackRate(mixed $transaction, string $date, string $rateType, array $exchangeRates): float
    {
        // First fallback: try any card type for this currency and date
        foreach ($exchangeRates as $rateKey => $rate) {
            if (str_contains($rateKey, "{$transaction->currency}_") &&
                str_contains($rateKey, "_{$date}") &&
                str_starts_with($rateKey, $rateType)) {
                return $rate;
            }
        }

        // Second fallback: try any rate for this currency and rate type
        foreach ($exchangeRates as $rateKey => $rate) {
            if (str_starts_with($rateKey, $rateType . $transaction->currency . '_')) {
                return $rate;
            }
        }

        // Last resort fallback
        $this->logger->log('warning', 'Using default exchange rate (1.0) for transaction', [
            'currency' => $transaction->currency,
            'date' => $date,
            'transaction_id' => $transaction->tid ?? 'unknown',
        ]);

        return 1.0;
    }
}
