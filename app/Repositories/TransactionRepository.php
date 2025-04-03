<?php

namespace App\Repositories;

use App\Repositories\Interfaces\TransactionRepositoryInterface;
use App\Services\DynamicLogger;
use App\Services\ExchangeRateService;
use App\Services\Transaction\TransactionTotalsCalculator;
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
     * Create a new TransactionRepository instance.
     *
     * @param ExchangeRateService $exchangeRateService Service for exchange rate operations
     * @param TransactionTotalsCalculator $totalsCalculator Service for transaction totals calculation
     * @param DynamicLogger $logger Logging service for transaction-related events
     */
    public function __construct(
        private ExchangeRateService        $exchangeRateService,
        private TransactionTotalsCalculator $totalsCalculator,
        private DynamicLogger              $logger
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
        return $this->totalsCalculator->calculateTotals($transactions, $exchangeRates, $merchantId);
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
        return $this->exchangeRateService->getExchangeRates($dateRange, $currencies);
    }
}
