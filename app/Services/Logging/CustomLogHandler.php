<?php

namespace App\Services\Logging;

use Illuminate\Support\Facades\Log;

class CustomLogHandler implements LogHandlerInterface
{
    protected string $level;

    public function __construct(string $level)
    {
        $this->level = $level;
    }

    public function log(string $message, array $context = []): void
    {
        Log::log($this->level, $message, $context);
    }
}
