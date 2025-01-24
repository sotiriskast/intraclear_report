<?php

namespace App\Classes\Fees\Calculators;

use App\Services\Settlement\Fee\FeeService;
use Carbon\Carbon;

class MonthlyFeeCalculator extends FeeService
{
    public function calculate($transactionData): float
    {
        $months = Carbon::parse($this->dateRange['start'])
                ->diffInMonths(Carbon::parse($this->dateRange['end'])) + 1;
        return $this->feeConfiguration['amount'] * $months;
    }
}
