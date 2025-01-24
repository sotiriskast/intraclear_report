<?php

namespace App\Classes\Fees\Calculators;

class OneTimeFeeCalculator extends AbstractFeeCalculator
{
    public function calculate($transactionData): float
    {
        return $this->feeConfiguration['amount'];
    }
}
