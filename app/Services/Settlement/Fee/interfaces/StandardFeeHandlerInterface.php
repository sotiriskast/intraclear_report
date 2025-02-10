<?php

namespace App\Services\Settlement\Fee\interfaces;

/**
 * Interface for handling standard fee calculations
 * Defines the contract for processing and retrieving standard merchant fees
 * such as MDR (Merchant Discount Rate), transaction fees, etc.
 */
interface StandardFeeHandlerInterface
{
    /**
     * Retrieves and calculates standard fees for a given merchant
     *
     * @param  int  $merchantId  ID of the merchant to calculate fees for
     * @param  array  $rawTransactionData  Array containing transaction details including:
     *                                     - total_sales_amount
     *                                     - currency
     *                                     - exchange_rate
     *                                     - other transaction-specific data
     * @return array Array of calculated standard fees, each containing:
     *               - fee_type_id: int
     *               - fee_amount: float
     *               - frequency: string
     *               - other fee-specific details
     */
    public function getStandardFees(int $merchantId, array $rawTransactionData): array;
}
