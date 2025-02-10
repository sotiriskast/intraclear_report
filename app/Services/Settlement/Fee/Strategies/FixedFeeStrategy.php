<?php

namespace App\Services\Settlement\Fee\Strategies;

use App\DTO\TransactionData;
use App\Services\Settlement\Fee\Strategies\interfaces\FeeCalculationStrategy;

/**
 * Strategy for calculating fixed fees
 * Applies a constant fee amount regardless of transaction volume or other factors
 * Used for fees like monthly fees, setup fees, or other flat-rate charges
 */
class FixedFeeStrategy implements FeeCalculationStrategy
{
    /**
     * Calculate a fixed fee amount
     * Simply converts the amount from smallest currency unit (cents) to base currency
     *
     * @param  TransactionData  $transactionData  DTO containing transaction details (unused in fixed fee calculation)
     * @param  int  $amount  Fee amount in smallest currency unit (cents)
     * @return float Fixed fee amount in base currency
     */
    public function calculate(TransactionData $transactionData, int $amount): float
    {
        return $amount / 100;
    }
}
