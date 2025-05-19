<?php

namespace App\Services\Settlement\Fee\interfaces;

/**
 * Interface for handling fee application frequency checks (both merchant and shop level)
 */
interface FeeFrequencyHandlerInterface
{
    /**
     * Determines if a merchant-level fee should be applied based on its frequency type and application history
     *
     * @param  string  $frequencyType  Type of frequency (transaction, daily, weekly, monthly, yearly, one_time)
     * @param  int  $merchantId  ID of the merchant
     * @param  int  $feeTypeId  Type of fee being checked
     * @param  array  $dateRange  Array containing date range with keys 'start' and 'end'
     * @return bool Returns true if the fee should be applied, false otherwise
     */
    public function shouldApplyFee(string $frequencyType, int $merchantId, int $feeTypeId, array $dateRange): bool;

    /**
     * Determines if a shop-level fee should be applied based on its frequency type and application history
     *
     * @param  string  $frequencyType  Type of frequency (transaction, daily, weekly, monthly, yearly, one_time)
     * @param  int  $shopId  ID of the shop (internal)
     * @param  int  $feeTypeId  Type of fee being checked
     * @param  array  $dateRange  Array containing date range with keys 'start' and 'end'
     * @return bool Returns true if the fee should be applied, false otherwise
     */
    public function shouldApplyShopFee(string $frequencyType, int $shopId, int $feeTypeId, array $dateRange): bool;
}
