<?php

namespace App\Classes\Fees;

use App\Classes\Fees\Calculators\{
    AbstractFeeCalculator,
};

class FeeCalculatorFactory
{
    private const CALCULATOR_MAP = [
        'transaction' => Calculators\TransactionFeeCalculator::class,
        'daily' => Calculators\DailyFeeCalculator::class,
        'weekly' => Calculators\WeeklyFeeCalculator::class,
        'monthly' => Calculators\MonthlyFeeCalculator::class,
        'yearly' => Calculators\YearlyFeeCalculator::class,
        'one_time' => Calculators\OneTimeFeeCalculator::class
    ];

    public function create(string $frequencyType, array $config, array $dateRange): AbstractFeeCalculator
    {
        if (!isset(self::CALCULATOR_MAP[$frequencyType])) {
            throw new \InvalidArgumentException("Unknown fee type: {$frequencyType}");
        }

        $calculatorClass = self::CALCULATOR_MAP[$frequencyType];
        return new $calculatorClass($config, $dateRange);
    }
}
