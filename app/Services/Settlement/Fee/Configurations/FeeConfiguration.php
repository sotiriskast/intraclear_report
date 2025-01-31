<?php

namespace App\Services\Settlement\Fee\Configurations;

readonly class FeeConfiguration
{
    public function __construct(
        public string        $key,
        public string        $name,
        public int           $amount,
        public bool          $isPercentage,
        public string        $frequency,
        public ?FeeCondition $condition = null
    ) {}

    public function meetsCondition($settings): bool
    {
        if ($this->condition === null) {
            return true;
        }
        return $this->condition->evaluate($settings);
    }
}
