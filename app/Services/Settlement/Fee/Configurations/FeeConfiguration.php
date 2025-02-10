<?php

namespace App\Services\Settlement\Fee\Configurations;

/**
 * Value object representing a fee configuration
 * Contains all necessary information to define a fee and its application rules
 */
readonly class FeeConfiguration
{
    /**
     * Initializes a new fee configuration
     *
     * @param  string  $key  Unique identifier for the fee configuration
     * @param  string  $name  Human-readable name of the fee
     * @param  int  $amount  Fee amount (in cents if fixed, basis points if percentage)
     * @param  bool  $isPercentage  Whether the fee is percentage-based (true) or fixed amount (false)
     * @param  string  $frequency  How often the fee should be applied (e.g., 'transaction', 'monthly', 'yearly')
     * @param  FeeCondition|null  $condition  Optional condition that must be met for fee application
     */
    public function __construct(
        public string $key,
        public string $name,
        public int $amount,
        public bool $isPercentage,
        public string $frequency,
        public ?FeeCondition $condition = null
    ) {}

    /**
     * Evaluates whether the fee configuration meets its condition for application
     * Always returns true if no condition is set
     *
     * @param  mixed  $settings  Settings or context data to evaluate the condition against
     * @return bool True if the condition is met or no condition exists, false otherwise
     */
    public function meetsCondition($settings): bool
    {
        if ($this->condition === null) {
            return true;
        }

        return $this->condition->evaluate($settings);
    }
}
