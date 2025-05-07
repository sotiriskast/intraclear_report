<?php

return [
    /*
    |--------------------------------------------------------------------------
    | CESOP Reporting Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains configuration settings for CESOP (Central Electronic
    | System of Payment Information) reporting required for payment service
    | providers operating in the European Union.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | PSP (Payment Service Provider) Information
    |--------------------------------------------------------------------------
    |
    | Details about your payment service provider that will be included in
    | all CESOP reports.
    |
    */
    'psp' => [
        'bic' => env('CESOP_PSP_BIC', 'ITRACY2L'), // Business Identifier Code
        'name' => env('CESOP_PSP_NAME', 'INTRACLEAR LIMITED'), // PSP legal name
        'country' => env('CESOP_PSP_COUNTRY', 'CY'), // ISO country code
        'tax_id' => env('CESOP_PSP_TAX_ID', 'CY10389967P'), // PSP tax ID
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Merchant Information
    |--------------------------------------------------------------------------
    |
    | Default values for merchant information when data is missing from the database.
    | These values will be used as fallbacks.
    |
    */
    'merchant' => [
        'country' => env('CESOP_MERCHANT_COUNTRY', 'CY'),
        'street' => env('CESOP_MERCHANT_STREET', ''),
        'city' => env('CESOP_MERCHANT_CITY', ''),
        'postcode' => env('CESOP_MERCHANT_POSTCODE', ''),
        'vat' => env('CESOP_MERCHANT_VAT', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | EU Countries List
    |--------------------------------------------------------------------------
    |
    | List of EU member states ISO country codes used to determine eligible
    | transactions for reporting.
    |
    */
    'eu_countries' => [
        'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR',
        'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL',
        'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE'
    ],

    /*
    |--------------------------------------------------------------------------
    | XML Schema and Output Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for XML validation and output.
    |
    */
    'schema_path' => base_path('Modules/Cesop/resources/xsd/PaymentData.xsd'),
    'output_path' => storage_path('app/cesop'),

    /*
    |--------------------------------------------------------------------------
    | Reporting Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for the reporting process.
    |
    */
    'transaction_threshold' => env('CESOP_TRANSACTION_THRESHOLD', 25),
    'max_transactions_per_file' => env('CESOP_MAX_TRANSACTIONS', 100000),

    /*
    |--------------------------------------------------------------------------
    | Database Connection
    |--------------------------------------------------------------------------
    |
    | The database connection to use for CESOP reporting queries.
    |
    */
    'db_connection' => env('CESOP_DB_CONNECTION', 'payment_gateway_mysql'),
];
