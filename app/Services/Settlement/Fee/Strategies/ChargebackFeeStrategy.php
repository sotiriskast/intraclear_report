<?php

namespace App\Services\Settlement\Fee\Strategies;

use App\Services\Settlement\Fee\Strategies\interfaces\FeeCalculationStrategy;
use App\DTO\TransactionData;

/**
 * Strategy for calculating chargeback fees
 * Applies a fixed fee amount for each chargeback in the transaction data
 */
class ChargebackFeeStrategy implements FeeCalculationStrategy
{
    /**
     * Calculate the total chargeback fee based on number of chargebacks
     *
     * Formula: (fee amount in base currency) * (number of chargebacks)
     * The fee amount is converted from cents to base currency by dividing by 100
     *
     * @param TransactionData $transactionData DTO containing transaction details including chargeback count
     * @param int $amount Fee amount in smallest currency unit (cents)
     * @return float Total chargeback fee in base currency
     */
    public function calculate(TransactionData $transactionData, int $amount): float
    {
        return ($amount / 100) * $transactionData->chargebackCount ?? 0;
    }
}
