<?php

namespace App\Service\Bot\Implements;

use App\Service\Bot\Contracts\WifiTokenService;
use App\Traits\ServiceHelper;
use Carbon\Carbon;
use Illuminate\Support\Str;

class WifiTokenServiceImpl implements WifiTokenService
{
    use ServiceHelper;

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
    public function generateToken(?string $customerName = null, int $durationMinutes = 30): array
    {
        $m = __METHOD__;
        $ssid = (string) config('services.wifi.ssid', 'Jarvis-Guest-SunsetRoad');

        $slugName = $customerName ? Str::slug(Str::words($customerName, 2, '')) : '';
        $usernamePrefix = $slugName !== '' ? 'jarvis-'.$slugName : 'jarvis-guest';
        $username = $usernamePrefix.'-'.mt_rand(100, 999);
        $password = 'WFI'.mt_rand(100, 999);

        $now = now();
        $expiresAt = $now->copy()->addMinutes($durationMinutes);

        $result = [
            'ssid' => $ssid,
            'username' => $username,
            'password' => $password,
            'duration_minutes' => $durationMinutes,
            'expires_at' => $expiresAt,
            'expires_at_formatted' => $expiresAt->copy()->setTimezone('Asia/Jakarta')->format('H:i').' WIB',
        ];

        $this->logInfo($m, 'Generated guest Wi-Fi token for customer', [
            'username' => $username,
            'duration_minutes' => $durationMinutes,
            'expires_at' => $result['expires_at_formatted'],
        ]);

        return $result;
    }
}
