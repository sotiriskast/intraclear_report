<?php

namespace App\Services\Settlement\Fee\Strategies;

use App\Services\Settlement\Fee\Strategies\interfaces\FeeCalculationStrategy;
use App\DTO\TransactionData;

class ChargebackFeeStrategy implements FeeCalculationStrategy
{

    public function calculate(TransactionData $transactionData, int $amount): float
    {
        return ($amount / 100) * $transactionData->chargebackCount ?? 0;
    }
}
