<?php

namespace App\Services\Chargeback\Interfaces;

use Carbon\CarbonPeriod;

/**
 * Interface for handling chargeback settlements
 */
interface ChargebackSettlementInterface
{
    /**
     * Processes settlements for a given period
     *
     * @return array Settlement results including amounts and processed counts
     */
    public function processSettlementsChargeback(int $merchantId, array $dateRange): array;
}
