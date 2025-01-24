<?php

namespace App\Classes\Fees\Calculators;

use App\Services\Settlement\Fee\FeeService;

class YearlyFeeCalculator extends FeeService
{
    public function calculate($transactionData): float
    {
        return $this->feeConfiguration['amount'];
    }
}
