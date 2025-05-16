<?php

namespace App\Services\Settlement\Fee\interfaces;

/**
 * Interface for handling custom shop fee calculations
 */
interface ShopCustomFeeHandlerInterface
{
    /**
     * Retrieves and calculates custom fees for a specific shop
     *
     * @param  int  $merchantId  ID of the merchant (account ID)
     * @param  int  $shopId  ID of the shop (external)
     * @param  array  $rawTransactionData  Array containing transaction details
     * @param  string  $startDate  Starting date for fee calculation (Y-m-d format)
     * @return array Array of calculated custom fees
     */
    public function getCustomFees(int $merchantId, int $shopId, array $rawTransactionData, string $startDate): array;
}
