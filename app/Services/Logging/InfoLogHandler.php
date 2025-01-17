<?php
namespace App\Services\Logging;

use Illuminate\Support\Facades\Log;

class InfoLogHandler implements LogHandlerInterface
{
    public function log(string $message, array $context = []): void
    {
        Log::info($message, $context);
    }
}
