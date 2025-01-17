<?php
namespace App\Services\Logging;

interface LogHandlerInterface
{
    /**
     * Logs a message with the provided context.
     *
     * @param string $message The log message.
     * @param array $context Additional context for the log entry.
     */
    public function log(string $message, array $context = []): void;
}
