<?php

namespace App\Services\Settlement\Fee\Strategies;

use App\Services\Settlement\Fee\Strategies\interfaces\FeeCalculationStrategy;
use App\DTO\TransactionData;

/**
 * Strategy for calculating refund processing fees
 * Applies a fixed fee amount for each refund transaction processed
 */
class RefundFeeStrategy implements FeeCalculationStrategy
{
    /**
     * Calculate the total refund processing fees
     *
     * Formula: (fee amount in base currency) * (number of refunds)
     * The fee amount is converted from cents to base currency by dividing by 100
     *
     * @param TransactionData $transactionData DTO containing transaction details including refund count
     * @param int $amount Fee amount per refund in smallest currency unit (cents)
     * @return float Total refund processing fees in base currency
     */
    public function calculate(TransactionData $transactionData, int $amount): float
    {
        return ($amount / 100) * $transactionData->transactionRefundsCount ?? 0;
    }
}
