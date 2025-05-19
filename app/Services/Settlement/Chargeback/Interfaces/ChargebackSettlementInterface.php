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
}
