<?php

namespace App\Services\Settlement\Fee\Strategies;

use App\DTO\TransactionData;
use App\Services\Settlement\Fee\Strategies\interfaces\FeeCalculationStrategy;

/**
 * Strategy for calculating payout processing fees
 * Applies a fixed fee amount for each payout transaction processed
 */
class PayoutFeeStrategy implements FeeCalculationStrategy
{
    /**
     * Calculate the total payout processing fees
     *
     * Formula: (fee amount in base currency) * (number of payouts)
     * The fee amount is converted from cents to base currency by dividing by 100
     *
     * @param  TransactionData  $transactionData  DTO containing transaction details including payout count
     * @param  int  $amount  Fee amount per payout in smallest currency unit (cents)
     * @return float Total payout processing fees in base currency
     */
    public function calculate(TransactionData $transactionData, int $amount): float
    {
        return ($amount / 100) * $transactionData->totalPayoutCount ?? 0;
    }
}
