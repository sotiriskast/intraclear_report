<?php

namespace App\Services\Settlement\Fee\Strategies;

use App\DTO\TransactionData;
use App\Services\Settlement\Fee\Strategies\interfaces\FeeCalculationStrategy;

/**
 * Strategy for calculating percentage-based fees
 * Calculates fees as a percentage of the total sales amount
 * Commonly used for fees like MDR (Merchant Discount Rate) or commission fees
 */
class PercentageFeeStrategy implements FeeCalculationStrategy
{
    /**
     * Calculate a percentage-based fee amount
     *
     * Formula: (total sales in EUR) * (percentage rate)
     * The percentage rate is converted from basis points to decimal:
     * - Input amount is in basis points (e.g., 250 = 2.50%)
     * - Divided by 10000 to convert to decimal (e.g., 250/10000 = 0.025)
     *
     * @param  TransactionData  $transactionData  DTO containing total sales amount in EUR
     * @param  int  $amount  Percentage rate in basis points (e.g., 250 for 2.50%)
     * @return float Calculated fee amount in EUR
     */
    public function calculate(TransactionData $transactionData, int $amount): float
    {
        return ($transactionData->totalSalesEur ?? 0) * ($amount / 10000);
    }
}
