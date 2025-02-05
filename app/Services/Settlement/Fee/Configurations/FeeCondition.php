<?php

namespace App\Services\Settlement\Fee\Configurations;

/**
 * Class representing a conditional rule for fee application
 * Encapsulates the logic for determining whether a fee should be applied
 * based on provided settings or context
 */
class FeeCondition
{
    /**
     * Initializes a new fee condition with a callback function
     *
     * @param callable $callback Function that evaluates the condition
     *                          Must return bool and accept settings parameter
     * @throws \InvalidArgumentException If the provided callback is not callable
     */
    public function __construct(private $callback)
    {
        if (!is_callable($callback)) {
            throw new \InvalidArgumentException('Callback must be callable');
        }
    }

    /**
     * Evaluates the condition using the provided settings
     *
     * @param mixed $settings Settings or context data to evaluate against
     * @return bool True if the condition is met, false otherwise
     */
    public function evaluate($settings): bool
    {
        return ($this->callback)($settings);
    }
}
