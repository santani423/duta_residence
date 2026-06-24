<?php

return [
    'gateway' => env('PAYMENT_GATEWAY', 'manual'),
    'currency' => env('PAYMENT_CURRENCY', 'IDR'),
    'xendit' => [
        'secret_key' => env('XENDIT_SECRET_KEY'),
        'public_key' => env('XENDIT_PUBLIC_KEY'),
        'callback_token' => env('XENDIT_CALLBACK_TOKEN'),
        'webhook_url' => env('XENDIT_WEBHOOK_URL'),
        'is_production' => env('XENDIT_IS_PRODUCTION', false),
    ],
    'midtrans' => [
        'server_key' => env('MIDTRANS_SERVER_KEY'),
        'client_key' => env('MIDTRANS_CLIENT_KEY'),
        'merchant_id' => env('MIDTRANS_MERCHANT_ID'),
        'is_production' => env('MIDTRANS_IS_PRODUCTION', false),
        'is_sanitized' => env('MIDTRANS_IS_SANITIZED', true),
        'is_3ds' => env('MIDTRANS_IS_3DS', true),
        'webhook_url' => env('MIDTRANS_WEBHOOK_URL'),
    ],
    'manual' => [
        'enabled' => env('MANUAL_PAYMENT_ENABLED', true),
        'bank_name' => env('MANUAL_PAYMENT_BANK_NAME'),
        'account_number' => env('MANUAL_PAYMENT_ACCOUNT_NUMBER'),
        'account_name' => env('MANUAL_PAYMENT_ACCOUNT_NAME'),
    ],
];
