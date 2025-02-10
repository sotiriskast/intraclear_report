<?php

namespace App\Services\Settlement\Fee\interfaces;

/**
 * Interface for handling custom fee calculations
 * Defines the contract for processing and retrieving merchant-specific
 * custom fees that may vary based on specific merchant arrangements
 */
interface CustomFeeHandlerInterface
{
    /**
     * Retrieves and calculates custom fees for a specific merchant
     *
     * @param  int  $merchantId  ID of the merchant to calculate custom fees for
     * @param  array  $rawTransactionData  Array containing transaction details including:
     *                                     - total_sales_amount
     *                                     - currency
     *                                     - exchange_rate
     *                                     - other transaction-specific data
     * @param  string  $startDate  Starting date for fee calculation (Y-m-d format)
     * @return array Array of calculated custom fees, each containing:
     *               - fee_type_id: int
     *               - fee_amount: float
     *               - frequency: string
     *               - other fee-specific configurations
     */
    public function getCustomFees(int $merchantId, array $rawTransactionData, string $startDate): array;
}
