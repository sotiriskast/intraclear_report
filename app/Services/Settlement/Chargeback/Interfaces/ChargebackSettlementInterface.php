<?php

namespace App\Services\Settlement\Chargeback\Interfaces;

/**
 * Interface for handling chargeback settlements
 */
interface ChargebackSettlementInterface
{
    /**
     * Processes settlements for a given period (merchant-level, backward compatibility)
     *
     * @return array Settlement results including amounts and processed counts
     */
    public function processSettlementsChargeback(int $merchantId, array $dateRange): array;

    /**
     * Processes settlements for a specific shop within a given period
     *
     * @return array Settlement results including amounts and processed counts
     */
    public function processShopSettlementsChargeback(int $shopId, array $dateRange): array;
    /**
     * Updates chargebacks with the final exchange rate for a specific shop and currency
     *
     * @param int $merchantId The merchant ID
     * @param int $shopId The shop ID
     * @param string $currency The currency to update
     * @param float $finalExchangeRate The final calculated exchange rate
     * @param array $dateRange The date range for the settlement period
     * @return int Number of chargebacks updated
     */
    public function updateShopChargebacksWithFinalRate(
        int $merchantId,
        int $shopId,
        string $currency,
        float $finalExchangeRate,
        array $dateRange
    ): int;
}
