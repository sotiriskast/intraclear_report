<?php


return [
    'name' => 'MerchantPortal',

    'pagination' => [
        'transactions_per_page' => 25,
        'reserves_per_page' => 20,
        'shops_per_page' => 15,
    ],

    'cache' => [
        'dashboard_ttl' => 1800, // 30 minutes
        'merchant_data_ttl' => 1800, // 30 minutes
    ],

    'features' => [
        'export_enabled' => true,
        'advanced_filtering' => true,
        'real_time_notifications' => false,
    ],

    'status_mapping' => [
        'pending' => 'Pending',
        'processing' => 'Processing',
        'matched' => 'Completed',
        'failed' => 'Failed',
    ],

    'currency_symbols' => [
        'EUR' => '€',
        'USD' => '$',
        'GBP' => '£',
        'JPY' => '¥',
    ],
];
