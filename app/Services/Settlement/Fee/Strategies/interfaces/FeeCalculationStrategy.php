<?php

namespace App\Services\Settlement\Fee\Strategies\interfaces;
use App\DTO\TransactionData;
interface FeeCalculationStrategy
{
    public function calculate(TransactionData $transactionData, int $amount): float;
}
