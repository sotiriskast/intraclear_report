<?php

namespace App\Services\Logging;

use Illuminate\Support\Facades\Log;

class ErrorLogHandler implements LogHandlerInterface
{
    public function log(string $message, array $context = []): void
    {
        Log::error($message, $context);
    }
}
