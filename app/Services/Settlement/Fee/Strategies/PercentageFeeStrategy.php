<?php

namespace App\Services\Settlement\Fee\Strategies;

use App\Services\Settlement\Fee\Strategies\interfaces\FeeCalculationStrategy;
use App\DTO\TransactionData;

class PercentageFeeStrategy implements FeeCalculationStrategy
{

    public function calculate(TransactionData $transactionData, int $amount): float
    {
        return ($transactionData->total_sales_eur ?? 0) * ($amount / 10000);
    }
}
