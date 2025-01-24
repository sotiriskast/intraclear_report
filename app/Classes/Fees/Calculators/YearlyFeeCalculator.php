<?php

namespace App\Classes\Fees\Calculators;
class YearlyFeeCalculator extends AbstractFeeCalculator
{
    public function calculate($transactionData): float
    {
        return $this->feeConfiguration['amount'];
    }
}
