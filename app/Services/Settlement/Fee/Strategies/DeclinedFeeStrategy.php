<?php

namespace App\Services\Settlement\Fee\Strategies;

use App\DTO\TransactionData;
use App\Services\Settlement\Fee\Strategies\interfaces\FeeCalculationStrategy;

/**
 * Strategy for calculating fees for declined transactions
 * Applies a fixed fee amount for each declined transaction in the period
 */
class DeclinedFeeStrategy implements FeeCalculationStrategy
{
    /**
     * Calculate the total fee for declined transactions
     *
     * Formula: (fee amount in base currency) * (number of declined transactions)
     * The fee amount is converted from cents to base currency by dividing by 100
     *
     * @param  TransactionData  $transactionData  DTO containing transaction details including declined count
     * @param  int  $amount  Fee amount in smallest currency unit (cents)
     * @return float Total declined transactions fee in base currency
     */
    public function calculate(TransactionData $transactionData, int $amount): float
    {
        return ($amount / 100) * $transactionData->transactionDeclinedCount ?? 0;
    }
}
