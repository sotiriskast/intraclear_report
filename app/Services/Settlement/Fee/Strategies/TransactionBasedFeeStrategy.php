<?php

namespace App\Services\Settlement\Fee\Strategies;

use App\Services\Settlement\Fee\Strategies\interfaces\FeeCalculationStrategy;
use App\DTO\TransactionData;

/**
 * Strategy for calculating transaction-based fees
 * Applies a fixed fee amount for each successful sales transaction
 * Used for per-transaction processing fees regardless of transaction amount
 */
class TransactionBasedFeeStrategy implements FeeCalculationStrategy
{
    /**
     * Calculate the total transaction-based fees
     *
     * Formula: (fee amount in base currency) * (number of successful sales transactions)
     * The fee amount is converted from cents to base currency by dividing by 100
     *
     * @param TransactionData $transactionData DTO containing transaction details including sales count
     * @param int $amount Fee amount per transaction in smallest currency unit (cents)
     * @return float Total transaction fees in base currency
     */
    public function calculate(TransactionData $transactionData, int $amount): float
    {
        return ($amount / 100) * ($transactionData->transactionSalesCount ?? 0);
    }
}
