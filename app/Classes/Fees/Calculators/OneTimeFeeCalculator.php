<?php

namespace App\Classes\Fees\Calculators;

class OneTimeFeeCalculator extends AbstractFeeCalculator
{
    //@todo Need To make test for checking if is only one time are applied and not multiple
    public function calculate($transactionData): float
    {
        return $this->feeConfiguration['amount'];
    }
}
