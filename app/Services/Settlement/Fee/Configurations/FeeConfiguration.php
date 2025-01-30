<?php

namespace App\Services\Settlement\Fee\Configurations;

class FeeConfiguration
{
    public function __construct(
        public readonly string $key,
        public readonly string $name,
        public readonly int $amount,
        public readonly bool $isPercentage,
        public readonly string $frequency,
        public readonly ?FeeCondition $condition = null
    ) {}

    public function meetsCondition($settings): bool
    {
        if ($this->condition === null) {
            return true;
        }
        return $this->condition->evaluate($settings);
    }
}
