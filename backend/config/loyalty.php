<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Loyalty Engine Configuration
    |--------------------------------------------------------------------------
    |
    | Konfigurasi rasio perhitungan poin loyalitas Jarvis EV Charging.
    | Perhitungan poin bersifat deterministik dan dikelola murni oleh backend.
    |
    */

    // Rasio konversi energi pengisian daya: 1 kWh menghasilkan N poin (default: 10 poin/kWh)
    'kwh_to_points_ratio' => (float) env('LOYALTY_KWH_RATIO', 10),

    // Bonus poin saat pertama kali menyelesaikan registrasi akun
    'welcome_points_bonus' => (int) env('LOYALTY_WELCOME_BONUS', 0),

    // Ambang batas tier keanggotaan
    'tiers' => [
        'platinum' => (int) env('LOYALTY_TIER_PLATINUM', 1000),
        'gold' => (int) env('LOYALTY_TIER_GOLD', 500),
        'silver' => 0,
    ],
];
