<?php

return [
    'name' => 'Cesop',
    'psp' => [
        'name' => env('CESOP_PSP_NAME', 'Your Payment Service Provider'),
        'bic' => env('CESOP_PSP_BIC', 'ABCDEF12XXX'),
        'country' => env('CESOP_PSP_COUNTRY', 'CY'),
        'tax_id' => env('CESOP_PSP_TAX_ID', 'CY12345678X'),
    ],

    // Default merchant information
    'merchant' => [
        'name' => env('CESOP_MERCHANT_NAME', 'Your Merchant Name Ltd'),
        'street' => env('CESOP_MERCHANT_STREET', 'Main Street 123'),
        'city' => env('CESOP_MERCHANT_CITY', 'London'),
        'postcode' => env('CESOP_MERCHANT_POSTCODE', 'W1A 1AA'),
        'country' => env('CESOP_MERCHANT_COUNTRY', 'GB'),
        'vat' => env('CESOP_MERCHANT_VAT', 'GB123456789'),
        'mcc' => env('CESOP_MERCHANT_MCC', '5732'),
    ],

    // EU member states for filtering transactions
    'eu_countries' => [
        'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR',
        'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL',
        'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE'
    ],

    // Threshold for reporting transactions
    'transaction_threshold' => env('CESOP_TRANSACTION_THRESHOLD', 25),

    // Output settings
    'output' => [
        'default_format' => env('CESOP_OUTPUT_FORMAT', 'xml'),
        'default_path' => env('CESOP_OUTPUT_PATH', storage_path('app/cesop/')),
    ],
];
