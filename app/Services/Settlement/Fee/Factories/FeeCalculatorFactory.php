<?php

namespace App\Services\Settlement\Fee\Factories;

use App\Services\Settlement\Fee\Strategies\interfaces\FeeCalculationStrategy;
use App\Services\Settlement\Fee\Strategies\PercentageFeeStrategy;
use App\Services\Settlement\Fee\Strategies\TransactionBasedFeeStrategy;
use App\Services\Settlement\Fee\Strategies\RefundFeeStrategy;
use App\Services\Settlement\Fee\Strategies\ChargebackFeeStrategy;
use App\Services\Settlement\Fee\Strategies\DeclinedFeeStrategy;
use App\Services\Settlement\Fee\Strategies\FixedFeeStrategy;
class FeeCalculatorFactory
{
    public function createCalculator(string $feeType, bool $isPercentage, string $key = ''): FeeCalculationStrategy
    {
        if ($isPercentage) {
            return new PercentageFeeStrategy();
        }

        return match ($key) {
            'transaction_fee' => new TransactionBasedFeeStrategy(),
            'refund_fee' => new RefundFeeStrategy(),
            'chargeback_fee' => new ChargebackFeeStrategy(),
            'declined_fee' => new DeclinedFeeStrategy(),
            default => new FixedFeeStrategy(),
        };
    }
}
