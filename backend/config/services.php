<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'gowa' => [
        'base_url' => env('GOWA_BASE_URL', 'http://gowa:3000'),
        'username' => env('GOWA_USERNAME', 'user1'),
        'password' => env('GOWA_PASSWORD', 'pass1'),
        'webhook_secret' => env('GOWA_WEBHOOK_SECRET'),
        'target_webhook_url' => env('GOWA_TARGET_WEBHOOK_URL', 'http://laravel.test/api/v1/webhooks/whatsapp'),
        'default_device_id' => env('GOWA_DEFAULT_DEVICE_ID', 'default'),
    ],

];
