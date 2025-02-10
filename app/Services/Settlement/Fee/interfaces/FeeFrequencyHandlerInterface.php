<?php

namespace App\Services\Settlement\Fee\interfaces;

/**
 * Interface for handling fee application frequency checks
 * Defines the contract for determining whether fees should be applied
 * based on their frequency type and application history
 */
interface FeeFrequencyHandlerInterface
{
    /**
     * Determines if a fee should be applied based on its frequency type and application history
     *
     * @param  string  $frequencyType  Type of frequency. Valid values:
     *                                 - 'transaction': Applied to every transaction
     *                                 - 'daily': Applied daily
     *                                 - 'weekly': Applied weekly
     *                                 - 'monthly': Applied in first week of month
     *                                 - 'yearly': Applied in first week of year
     *                                 - 'one_time': Applied only once ever
     * @param  int  $merchantId  ID of the merchant
     * @param  int  $feeTypeId  Type of fee being checked
     * @param  array  $dateRange  Array containing date range with keys:
     *                            - 'start': Start date (Y-m-d format)
     *                            - 'end': End date (Y-m-d format)
     * @return bool Returns true if the fee should be applied, false otherwise
     */
    public function shouldApplyFee(string $frequencyType, int $merchantId, int $feeTypeId, array $dateRange): bool;
}
