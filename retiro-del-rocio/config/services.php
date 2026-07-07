<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
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

    'paystack' => [
        'public_key' => env('PAYSTACK_PUBLIC_KEY'),
        'secret_key' => env('PAYSTACK_SECRET_KEY'),
        'payment_url' => env('PAYSTACK_PAYMENT_URL', 'https://api.paystack.co'),
    ],

    'ttlock' => [
        // All TTLock credentials/tokens are configured via .env (single source
        // of truth). Refreshed tokens are held in the cache at runtime.
        'base_url' => env('TTLOCK_BASE_URL', 'https://euapi.ttlock.com'),
        'client_id' => env('TTLOCK_CLIENT_ID'),
        'client_secret' => env('TTLOCK_CLIENT_SECRET'),
        'access_token' => env('TTLOCK_ACCESS_TOKEN'),
        'refresh_token' => env('TTLOCK_REFRESH_TOKEN'),
        // The single Access Gate lock every guest passcode/QR is issued against.
        'lock_id' => env('TTLOCK_LOCK_ID'),
        'uid' => env('TTLOCK_UID'),
        'openid' => env('TTLOCK_OPENID'),
        'scope' => env('TTLOCK_SCOPE'),
        'token_type' => env('TTLOCK_TOKEN_TYPE', 'Bearer'),
        'expires_in' => env('TTLOCK_EXPIRES_IN'),
        // Default guest access window — bookings store dates only, so passcodes
        // start at the check-in time and expire at the check-out time.
        'checkin_time' => env('TTLOCK_CHECKIN_TIME', '14:00'),
        'checkout_time' => env('TTLOCK_CHECKOUT_TIME', '12:00'),
        // How passcodes reach the lock: 1=bluetooth, 2=gateway, 3=NB-IoT.
        'add_type' => (int) env('TTLOCK_ADD_TYPE', 2),
        // Request timeout (seconds) and retry attempts for the HTTP client.
        'timeout' => (int) env('TTLOCK_TIMEOUT', 15),
        'retries' => (int) env('TTLOCK_RETRIES', 2),
    ],

];
