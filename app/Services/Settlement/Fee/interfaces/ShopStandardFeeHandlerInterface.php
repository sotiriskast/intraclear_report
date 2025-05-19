<?php

namespace App\Services\Settlement\Fee\interfaces;

/**
 * Interface for handling standard shop fee calculations
 */
interface ShopStandardFeeHandlerInterface
{
    /**
     * Retrieves and calculates standard fees for a given shop
     *
     * @param  int  $merchantId  ID of the merchant (account ID)
     * @param  int  $shopId  ID of the shop (external)
     * @param  array  $rawTransactionData  Array containing transaction details
     * @return array Array of calculated standard fees
     */
    public function getStandardFees(int $merchantId, int $shopId, array $rawTransactionData): array;
}
