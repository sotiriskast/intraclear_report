<?php

namespace App\Services;

use App\Services\Logging\CustomLogHandler;
use App\Services\Logging\DebugLogHandler;
use App\Services\Logging\ErrorLogHandler;
use App\Services\Logging\InfoLogHandler;
use Illuminate\Support\Facades\Auth;

class DynamicLogger
{
    protected array $handlers = [];

    public function __construct()
    {
        // Predefined handlers
        $this->handlers = [
            'info' => new InfoLogHandler,
            'error' => new ErrorLogHandler,
            'debug' => new DebugLogHandler,
        ];
    }

    /**
     * Logs a message with a specified level and context.
     *
     * @param  string  $level  The log level (e.g., info, error, debug).
     * @param  string  $message  The log message.
     * @param  array  $context  Additional context for the log entry.
     */
    public function log(string $level, string $message, array $context = []): void
    {
        $handler = $this->handlers[$level] ?? new CustomLogHandler($level);
        $handler->log($message, $this->addAuthUserContext($context));
    }

    /**
     * Adds default context, including authenticated user details.
     *
     * @param  array  $context  The original log context.
     * @return array The updated context with default metadata.
     */
    protected function addAuthUserContext(array $context): array
    {
        $user = Auth::user();

        return array_merge($context, [
            'user_id' => $user?->id ?? 'guest',
            'user_name' => $user?->name ?? 'guest',
            'ip_address' => request()->ip(),
            'timestamp' => now()->toDateTimeString(),
        ]);
    }
}
