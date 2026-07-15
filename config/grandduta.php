<?php

return [
    'notification' => [
        'enabled' => env('NOTIFICATION_ENABLED', false),
        'billing_day' => env('BILLING_GENERATION_DAY', 1),
        'reminder_day' => env('BILLING_REMINDER_DAY', 15),
        'penalty_day' => env('BILLING_PENALTY_DAY', 20),
    ],
    'fonnte_token' => env('FONNTE_TOKEN'),
    'receipt_prefix' => env('RECEIPT_PREFIX', 'GD'),
    'max_upload_size' => env('MAX_UPLOAD_SIZE', 5120),
    'app_version' => env('APP_VERSION', '1.0.0'),
    'support' => [
        'email' => env('SUPPORT_EMAIL'),
        'phone' => env('SUPPORT_PHONE'),
    ],
];
