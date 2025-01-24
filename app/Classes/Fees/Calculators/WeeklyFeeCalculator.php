<?php

namespace App\Classes\Fees\Calculators;

use App\Services\Settlement\Fee\FeeService;
use Carbon\Carbon;

class WeeklyFeeCalculator extends FeeService
{
    public function calculate($transactionData): float
    {
        $weeks = Carbon::parse($this->dateRange['start'])
                ->diffInWeeks(Carbon::parse($this->dateRange['end'])) + 1;
        return $this->feeConfiguration['amount'] * $weeks;
    }
}
