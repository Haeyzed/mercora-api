<?php

declare(strict_types=1);

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

        'paystack' => [
            'secret_key' => env('PAYSTACK_SECRET_KEY'),
            'public_key' => env('PAYSTACK_PUBLIC_KEY'),
            'base_url' => env('PAYSTACK_BASE_URL', 'https://api.paystack.co'),
            'supported_currencies' => ['NGN', 'GHS', 'ZAR', 'USD'],
        ],

        'stripe' => [
            'secret_key' => env('STRIPE_SECRET_KEY'),
            'public_key' => env('STRIPE_PUBLIC_KEY'),
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
            'webhook_tolerance' => (int) env('STRIPE_WEBHOOK_TOLERANCE', 300),
            'base_url' => env('STRIPE_BASE_URL', 'https://api.stripe.com/v1'),
            'supported_currencies' => ['USD', 'EUR', 'GBP', 'CAD', 'AUD', 'NZD', 'CHF', 'SGD'],
        ],
    ],

    'reference_prefix' => env('PAYMENT_REFERENCE_PREFIX', 'mercora'),

    'redirect_url' => env('PAYMENT_REDIRECT_URL'),

];
