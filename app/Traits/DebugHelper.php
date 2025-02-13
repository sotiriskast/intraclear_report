<?php

namespace App\Traits;

use Illuminate\Support\Facades\Log;

trait DebugHelper
{
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

    private function getMemoryUsage(): string
    {
        $size = memory_get_usage(true);
        $unit = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        return @round($size/pow(1024,($i=floor(log($size,1024)))),2).' '.$unit[$i];
    }

    private function getExecutionTime(): string
    {
        return number_format((microtime(true) - LARAVEL_START) * 1000, 2) . 'ms';
    }

    private function dumpDebug($var): void
    {
        if (app()->environment('local', 'development')) {
            dump($var);
        }
    }
}
