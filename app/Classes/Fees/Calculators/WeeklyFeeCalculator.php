<?php

namespace App\Classes\Fees\Calculators;

use Carbon\Carbon;

class WeeklyFeeCalculator extends AbstractFeeCalculator
{
    public function calculate($transactionData): float
    {
        $weeks = Carbon::parse($this->dateRange['start'])
                ->diffInWeeks(Carbon::parse($this->dateRange['end'])) + 1;
        return $this->feeConfiguration['amount'] * $weeks;
    }
}
