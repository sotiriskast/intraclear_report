<?php

namespace App\Repositories\Interfaces;

interface TransactionRepositoryInterface
{
    /**
     * Get merchant transactions for a date range and optional currency
     *
     * @param int $merchantId Merchant account ID
     * @param array $dateRange Date range with 'start' and 'end' keys
     * @param string|null $currency Optional currency filter
     * @return mixed Collection of transactions
     */
    public function getMerchantTransactions(int $merchantId, array $dateRange, ?string $currency = null);

    /**
     * Get merchant transactions for a specific shop
     *
     * @param int $merchantId Merchant account ID
     * @param int $shopId Shop ID (external)
     * @param array $dateRange Date range with 'start' and 'end' keys
     * @param string|null $currency Optional currency filter
     * @return mixed Collection of transactions
     */
    public function getMerchantShopTransactions(int $merchantId, int $shopId, array $dateRange, ?string $currency = null);

    /**
     * Calculate transaction totals for a merchant
     *
     * @param mixed $transactions Collection of transactions
     * @param array $exchangeRates Exchange rates lookup
     * @param int $merchantId Merchant account ID
     * @return array Calculated totals per currency
     */
    public function calculateTransactionTotals(mixed $transactions, array $exchangeRates, int $merchantId): array;

    /**
     * Calculate transaction totals for a specific shop
     *
     * @param mixed $transactions Collection of transactions
     * @param array $exchangeRates Exchange rates lookup
     * @param int $merchantId Merchant account ID
     * @param int $shopId Shop ID (external)
     * @return array Calculated totals per currency
     */
    public function calculateShopTransactionTotals(mixed $transactions, array $exchangeRates, int $merchantId, int $shopId): array;

    /**
     * Get exchange rates for currencies within a date range
     *
     * @param array $dateRange Date range with 'start' and 'end' keys
     * @param array $currencies Array of currency codes
     * @return array Exchange rates lookup
     */
    public function getExchangeRates(array $dateRange, array $currencies);
}
