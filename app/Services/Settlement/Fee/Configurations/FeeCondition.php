<?php

namespace App\Services\Settlement\Fee\Configurations;

class FeeCondition
{
    public function __construct(private $callback)
    {
        if (!is_callable($callback)) {
            throw new \InvalidArgumentException('Callback must be callable');
        }
    }

    public function evaluate($settings): bool
    {
        return ($this->callback)($settings);
    }
}
