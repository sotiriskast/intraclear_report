<?php

namespace App\Services\Settlement\Fee\Factories;

use App\Services\Settlement\Fee\Strategies\ChargebackFeeStrategy;
use App\Services\Settlement\Fee\Strategies\DeclinedFeeStrategy;
use App\Services\Settlement\Fee\Strategies\FixedFeeStrategy;
use App\Services\Settlement\Fee\Strategies\interfaces\FeeCalculationStrategy;
use App\Services\Settlement\Fee\Strategies\PayoutFeeStrategy;
use App\Services\Settlement\Fee\Strategies\PercentageFeeStrategy;
use App\Services\Settlement\Fee\Strategies\RefundFeeStrategy;
use App\Services\Settlement\Fee\Strategies\TransactionBasedFeeStrategy;

/**
 * Factory class for creating fee calculation strategy instances
 * Responsible for instantiating the appropriate fee calculation strategy
 * based on fee type and configuration
 */
class FeeCalculatorFactory
{
    /**
     * Creates and returns the appropriate fee calculation strategy
     *
     * @param  string  $feeType  Type of fee being calculated
     * @param  bool  $isPercentage  Whether the fee is percentage-based or fixed
     * @param  string  $key  Specific fee type key for determining strategy. Valid values:
     *                       - 'transaction_fee': For transaction-based calculations
     *                       - 'refund_fee': For refund-related fees
     *                       - 'chargeback_fee': For chargeback-related fees
     *                       - 'declined_fee': For declined transaction fees
     *                       - '': (empty) Defaults to fixed fee strategy
     * @return FeeCalculationStrategy Returns an instance of the appropriate fee calculation strategy
     *                                PercentageFeeStrategy if $isPercentage is true,
     *                                otherwise returns strategy based on $key
     */
    public function createCalculator(string $feeType, bool $isPercentage, string $key = ''): FeeCalculationStrategy
    {
        if ($isPercentage) {
            return new PercentageFeeStrategy;
        }

        return match ($key) {
            'transaction_fee' => new TransactionBasedFeeStrategy,
            'payout_fee' => new PayoutFeeStrategy,
            'refund_fee' => new RefundFeeStrategy,
            'chargeback_fee' => new ChargebackFeeStrategy,
            'declined_fee' => new DeclinedFeeStrategy,
            default => new FixedFeeStrategy,
        };
    }
}
