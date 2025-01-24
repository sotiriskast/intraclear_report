<?php

namespace App\Classes\Fees\Calculators;

abstract class AbstractFeeCalculator
{
    protected $feeConfiguration;
    protected $dateRange;

    public function __construct(array $feeConfiguration, array $dateRange)
    {
        $this->feeConfiguration = $feeConfiguration;
        $this->dateRange = $dateRange;
    }

    abstract public function calculate($transactionData): float;
}
