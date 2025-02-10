<?php

namespace App\Services\Settlement\Fee\Strategies\interfaces;

use App\DTO\TransactionData;

/**
 * Interface for fee calculation strategies
 * Defines a contract for different fee calculation implementations
 * Used in the Strategy pattern to allow for different fee calculation methods
 */
interface FeeCalculationStrategy
{
    /**
     * Calculate a fee amount based on transaction data and fee configuration
     *
     * @param  TransactionData  $transactionData  DTO containing relevant transaction information
     *                                            (e.g., amounts, counts, currency)
     * @param  int  $amount  Fee amount or rate in smallest currency unit (cents)
     *                       For percentage fees, this represents basis points
     *                       For fixed fees, this represents the amount in cents
     * @return float Calculated fee amount in base currency
     */
    public function calculate(TransactionData $transactionData, int $amount): float;
}
