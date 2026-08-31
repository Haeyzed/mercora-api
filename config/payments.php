<?php

return [

    'default' => env('PAYMENT_DRIVER', 'flutterwave'),

    'drivers' => [
        'flutterwave' => [
            'secret_key' => env('FLW_SECRET_KEY'),
            'public_key' => env('FLW_PUBLIC_KEY'),
            'secret_hash' => env('FLW_SECRET_HASH'),
            'base_url' => env('FLW_BASE_URL', 'https://api.flutterwave.com/v3'),
            'supported_currencies' => ['NGN', 'USD', 'GBP', 'EUR', 'GHS', 'KES', 'ZAR', 'UGX', 'TZS', 'RWF'],
        ],
    ],

    'reference_prefix' => env('PAYMENT_REFERENCE_PREFIX', 'mercora'),

    'redirect_url' => env('PAYMENT_REDIRECT_URL'),

];
