<?php

namespace App\Classes\Fees\Calculators;

use Carbon\Carbon;

class DailyFeeCalculator extends AbstractFeeCalculator
{
    public function calculate($transactionData): float
    {
        $days = Carbon::parse($this->dateRange['start'])
                ->diffInDays(Carbon::parse($this->dateRange['end'])) + 1;
        return $this->feeConfiguration['amount'] * $days;
    }
}
