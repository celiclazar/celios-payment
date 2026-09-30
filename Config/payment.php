<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Payment Gateway
    |--------------------------------------------------------------------------
    |
    | This option controls the default gateway driver that will be used by the
    | payment service when no specific gateway is specified.
    |
    */

    'default' => env('PAYMENT_DEFAULT_GATEWAY', 'mock'),

    /*
    |--------------------------------------------------------------------------
    | Default Currency
    |--------------------------------------------------------------------------
    |
    | The default ISO 4217 3-letter currency code used when no currency is
    | explicitly provided in the charge request.
    |
    */

    'currency' => env('PAYMENT_DEFAULT_CURRENCY', 'EUR'),

    /*
    |--------------------------------------------------------------------------
    | Payment Gateways Configuration
    |--------------------------------------------------------------------------
    |
    | Configure credentials and options for each supported gateway driver.
    | Database settings in the `payment_gateways` table can override these
    | values at runtime via the CMS admin panel.
    |
    */

    'gateways' => [

        'mock' => [
            'enabled' => env('PAYMENT_MOCK_ENABLED', true),
            'test_mode' => true,
            'simulate' => 'success', // 'success', 'failure', 'action_required'
            'supported_currencies' => ['EUR', 'USD', 'RSD', 'GBP'],
        ],

        'bank_transfer' => [
            'enabled' => env('PAYMENT_BANK_TRANSFER_ENABLED', true),
            'test_mode' => false,
            'recipient_name' => env('PAYMENT_BT_RECIPIENT', env('APP_NAME', 'Celios CMS')),
            'account_number' => env('PAYMENT_BT_ACCOUNT', '160-0000000000000-00'),
            'iban' => env('PAYMENT_BT_IBAN', 'RS35160000000000000000'),
            'swift_bic' => env('PAYMENT_BT_SWIFT', 'DBDBRSBG'),
            'note' => 'Please include your payment reference in the payment order.',
            'supported_currencies' => ['EUR', 'RSD', 'USD'],
        ],

        'stripe' => [
            'enabled' => env('STRIPE_ENABLED', false),
            'test_mode' => env('STRIPE_TEST_MODE', true),
            'key' => env('STRIPE_KEY'),
            'secret_key' => env('STRIPE_SECRET_KEY'),
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
            'supported_currencies' => ['USD', 'EUR', 'GBP', 'CAD', 'AUD', 'CHF'],
        ],

    ],

];
