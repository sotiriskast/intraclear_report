<?php

namespace App\Services\Settlement\Fee\Strategies;

use App\Services\Settlement\Fee\Strategies\interfaces\FeeCalculationStrategy;
use App\DTO\TransactionData;
class TransactionBasedFeeStrategy implements FeeCalculationStrategy
{

    public function calculate(TransactionData $transactionData, int $amount): float
    {
        return ($amount / 100) * ($transactionData->transactionSalesCount ?? 0);
    }
}
