<?php

namespace App\Traits;

use Illuminate\Support\Facades\Log;

trait ServiceHelper
{
    /**
     * Log an informational message.
     */
    protected function logInfo(string $method, string $message, array $context = []): void
    {
        Log::info(sprintf('[%s] %s', $method, $message), $context);
    }

    /**
     * Log an error message.
     */
    protected function logError(string $method, string $message, array $context = []): void
    {
        Log::error(sprintf('[%s] %s', $method, $message), $context);
    }

    /**
     * Log a warning message.
     */
    protected function logWarning(string $method, string $message, array $context = []): void
    {
        Log::warning(sprintf('[%s] %s', $method, $message), $context);
    }
}
