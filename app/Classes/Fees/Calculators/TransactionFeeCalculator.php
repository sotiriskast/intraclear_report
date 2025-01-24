<?php

namespace App\Classes\Fees\Calculators;

use App\Services\Settlement\Fee\FeeService;

class TransactionFeeCalculator extends FeeService
{
    public function calculate($transactionData): float
    {
        $feeAmount = 0;
        if ($this->feeConfiguration['is_percentage']) {
            $feeAmount = $transactionData['total_sales_eur'] * ($this->feeConfiguration['amount'] / 100);
        } else {
            $feeAmount = $this->feeConfiguration['amount'] * $transactionData['transaction_count'];
        }
        return $feeAmount;
    }
}
