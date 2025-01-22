<?php

namespace App\Services\Logging;

use Illuminate\Support\Facades\Log;

class DebugLogHandler implements LogHandlerInterface
{
    public function log(string $message, array $context = []): void
    {
        Log::debug($message, $context);
    }
}
