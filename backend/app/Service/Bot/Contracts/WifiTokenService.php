<?php

namespace App\Service\Bot\Contracts;

use Illuminate\Support\Carbon;

interface WifiTokenService
{
    /**
     * Generate temporary guest Wi-Fi credentials for customers.
     *
     * @param  string|null  $customerName  Name of the customer for personalized username
     * @param  int  $durationMinutes  Duration of validity in minutes (default 30)
     * @return array{
     *     ssid: string,
     *     username: string,
     *     password: string,
     *     duration_minutes: int,
     *     expires_at: Carbon,
     *     expires_at_formatted: string
     * }
     */
    public function generateToken(?string $customerName = null, int $durationMinutes = 30): array;
}
