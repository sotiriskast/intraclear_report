<?php

namespace App\Services\Logging;

use Illuminate\Support\Facades\Log;

class WarningLogHandler implements LogHandlerInterface
{
    public function log(string $message, array $context = []): void
    {
        Log::warning($message, $context);
    }
}
