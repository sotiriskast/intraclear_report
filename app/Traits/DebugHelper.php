<?php

namespace App\Traits;

use Illuminate\Support\Facades\Log;
/**
 * Trait providing debugging and logging functionality
 *
 * This trait provides:
 * - Structured logging with context
 * - Memory usage tracking
 * - Execution time tracking
 * - Debug variable dumping in development
 */
trait DebugHelper
{
    /**
     * Log a debug message with context
     *
     * @param string $message Log message
     * @param array $context Additional context data
     * @param string $level Log level (debug, info, warning, error)
     */
    private function debugLog(string $message, array $context = [], string $level = 'debug'): void
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1];
        $caller = [
            'file' => str_replace(base_path(), '', $trace['file']),
            'line' => $trace['line'],
            'function' => $trace['function'],
            'class' => $trace['class']
        ];

        $context = array_merge($context, [
            'caller' => $caller,
            'memory_usage' => $this->getMemoryUsage(),
            'execution_time' => $this->getExecutionTime()
        ]);

        Log::$level($message, $context);
    }
    /**
     * Get formatted memory usage
     *
     * @return string Formatted memory usage with units
     */
    private function getMemoryUsage(): string
    {
        $size = memory_get_usage(true);
        $unit = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        return @round($size/pow(1024,($i=floor(log($size,1024)))),2).' '.$unit[$i];
    }
    /**
     * Get execution time since application start
     *
     * @return string Formatted execution time in milliseconds
     */
    private function getExecutionTime(): string
    {
        return number_format((microtime(true) - LARAVEL_START) * 1000, 2) . 'ms';
    }
    /**
     * Dump variable for debugging in development environments
     *
     * @param mixed $var Variable to dump
     */
    private function dumpDebug($var): void
    {
        if (app()->environment('local', 'development')) {
            dump($var);
        }
    }
}
