<?php

namespace App\Classes\Fees\Calculators;

use App\Services\Settlement\Fee\FeeService;
use Carbon\Carbon;

class DailyFeeCalculator extends FeeService
{
    public function calculate($transactionData): float
    {
        $days = Carbon::parse($this->dateRange['start'])
                ->diffInDays(Carbon::parse($this->dateRange['end'])) + 1;
        return $this->feeConfiguration['amount'] * $days;
    }
}
