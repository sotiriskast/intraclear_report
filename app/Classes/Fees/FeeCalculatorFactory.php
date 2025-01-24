<?php

namespace App\Classes\Fees;

abstract class FeeCalculatorFactory
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
