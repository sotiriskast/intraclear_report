<?php

return [
    'name' => 'Decta',

    /*
    |--------------------------------------------------------------------------
    | SFTP Connection Settings
    |--------------------------------------------------------------------------
    |
    | These are the settings used for connecting to Decta's SFTP server
    |
    */
    'sftp' => [
        'host' => env('DECTA_SFTP_HOST', 'files.decta.com'),
        'port' => env('DECTA_SFTP_PORT', 822),
        'username' => env('DECTA_SFTP_USERNAME', 'INTCL'),
        'private_key_path' => env('DECTA_SFTP_PRIVATE_KEY_PATH') ?
            (str_starts_with(env('DECTA_SFTP_PRIVATE_KEY_PATH'), '/') ?
                env('DECTA_SFTP_PRIVATE_KEY_PATH') :
                storage_path('app/private/' . basename(env('DECTA_SFTP_PRIVATE_KEY_PATH')))
            ) : storage_path('app/private/decta_rsa'),
        'remote_path' => env('DECTA_SFTP_REMOTE_PATH', 'in_file/reports'),
        'local_path' => env('DECTA_SFTP_LOCAL_PATH', 'files'),
        'timeout' => env('DECTA_SFTP_TIMEOUT', 30),
        'identities_only' => env('DECTA_SFTP_IDENTITIES_ONLY', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | File Processing Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for how downloaded files should be processed
    |
    */
    'files' => [
        'extensions' => ['.csv', '.xml', '.txt'], // File extensions to download
        'processed_dir' => 'processed', // Subdirectory for processed files
        'failed_dir' => 'failed', // Subdirectory for failed files
        'delete_remote_after_download' => env('DECTA_DELETE_REMOTE_FILES', false),
    ],
    /*
|--------------------------------------------------------------------------
| Email Notification Settings
|--------------------------------------------------------------------------
|
| Configure email notifications for Decta operations
|
*/
    'notifications' => [
        // Enable/disable email notifications
        'enabled' => env('DECTA_NOTIFICATIONS_ENABLED', true),

        // Email addresses to receive notifications
        'recipients' => array_filter([
            env('DECTA_NOTIFICATION_EMAIL_1', 's.kastanas@intraclear.com'),
            env('DECTA_NOTIFICATION_EMAIL_2', 'l.koniotis@yourcompany.com'),
            env('DECTA_NOTIFICATION_EMAIL_3', 'd.slobodchikov@intraclear.com'),
            // Add more as needed
        ]),

        // Notification settings for different operations
        'send_on_success' => env('DECTA_NOTIFY_SUCCESS', true),
        'send_on_failure' => env('DECTA_NOTIFY_FAILURE', true),

        // Specific operation notifications
        'download' => [
            'enabled' => env('DECTA_NOTIFY_DOWNLOAD', true),
            'send_on_success' => env('DECTA_NOTIFY_DOWNLOAD_SUCCESS', true),
            'send_on_failure' => env('DECTA_NOTIFY_DOWNLOAD_FAILURE', true),
        ],

        'processing' => [
            'enabled' => env('DECTA_NOTIFY_PROCESSING', true),
            'send_on_success' => env('DECTA_NOTIFY_PROCESSING_SUCCESS', true),
            'send_on_failure' => env('DECTA_NOTIFY_PROCESSING_FAILURE', true),
        ],

        'matching' => [
            'enabled' => env('DECTA_NOTIFY_MATCHING', true),
            'send_on_success' => env('DECTA_NOTIFY_MATCHING_SUCCESS', true),
            'send_on_failure' => env('DECTA_NOTIFY_MATCHING_FAILURE', true),
        ],

        'health_check' => [
            'enabled' => env('DECTA_NOTIFY_HEALTH_CHECK', true),
        ],
        'allowed_environments' => env('DECTA_NOTIFICATION_ENVIRONMENTS', 'staging,production,prod'),

    ],

    /*
|--------------------------------------------------------------------------
| Large Export Configuration
|--------------------------------------------------------------------------
|
| Configuration settings for handling large dataset exports
|
*/

    'memory_limit' => env('EXPORT_MEMORY_LIMIT', '1G'),
    'time_limit' => env('EXPORT_TIME_LIMIT', 3600), // 1 hour
    'chunk_size' => env('EXPORT_CHUNK_SIZE', 1000),
    'max_records' => env('EXPORT_MAX_RECORDS', 5000000), // 5 million max

    /*
    |--------------------------------------------------------------------------
    | Performance Thresholds
    |--------------------------------------------------------------------------
    |
    | Define when to show warnings and recommendations to users
    |
    */

    'thresholds' => [
        'large_dataset' => 300000,      // 300k records
        'very_large_dataset' => 800000, // 800k records
        'huge_dataset' => 1500000,      // 1.5M records
    ],

    /*
    |--------------------------------------------------------------------------
    | Format Recommendations
    |--------------------------------------------------------------------------
    |
    | Which formats work best for different dataset sizes
    |
    */

    'format_limits' => [
        'excel' => [
            'max_rows' => 1048576,      // Excel row limit
            'recommended_max' => 500000, // Recommended max for performance
        ],
        'csv' => [
            'max_rows' => PHP_INT_MAX,   // No practical limit
            'recommended_max' => 10000000, // 10M records
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Progress Tracking
    |--------------------------------------------------------------------------
    |
    | Settings for progress reporting during exports
    |
    */

    'progress' => [
        'log_interval' => 10000,    // Log progress every N records
        'memory_check_interval' => 5000, // Check memory every N records
    ],

    /*
    |--------------------------------------------------------------------------
    | Visa SMS Configuration (Daily Reports)
    |--------------------------------------------------------------------------
    |
    | Configuration for daily Visa SMS transaction detail reports
    |
    */
    'visa_sms' => [

        /*
        |--------------------------------------------------------------------------
        | SFTP Settings
        |--------------------------------------------------------------------------
        |
        | SFTP connection and file location settings for Visa SMS reports
        |
        */
        'sftp' => [
            'remote_path' => env('VISA_SMS_SFTP_REMOTE_PATH', '/in_file/reports'),
            'local_path' => env('VISA_SMS_SFTP_LOCAL_PATH', 'visa_sms'),
            'file_prefix' => env('VISA_SMS_FILE_PREFIX', 'INTCL_visa_sms_tr_det_'),
            'file_extension' => env('VISA_SMS_FILE_EXTENSION', '.csv'),
        ],

        /*
        |--------------------------------------------------------------------------
        | File Processing Settings
        |--------------------------------------------------------------------------
        |
        | Settings for processing Visa SMS CSV files
        |
        */
        'processing' => [
            // CSV parsing settings
            'csv_delimiter' => ';',
            'csv_enclosure' => '"',
            'csv_escape' => '\\',

            // Field mappings
            'field_mappings' => [
                'payment_id' => 'PAYMENT_ID',
                'interchange' => 'INTERCHANGE',
                'card' => 'CARD',
                'merchant_name' => 'MERCHANT_NAME',
                'merchant_id' => 'MERCHANT_ID',
                'amount' => 'TR_AMOUNT',
                'currency' => 'TR_CCY',
                'date_time' => 'TR_DATE_TIME',
                'approval_id' => 'TR_APPROVAL_ID',
            ],

            // Transaction matching settings
            'matching' => [
                'target_field' => 'user_define_field2', // The field to update with interchange value
                'require_payment_id' => true,
                'log_not_found' => false, // Set to true to log when transactions aren't found
                'batch_size' => 1000, // Process in batches for large files
            ],
        ],

        /*
        |--------------------------------------------------------------------------
        | Scheduling Settings
        |--------------------------------------------------------------------------
        |
        | Automated scheduling configuration
        |
        */
        'scheduling' => [
            'auto_download' => env('VISA_SMS_AUTO_DOWNLOAD', true),
            'auto_process' => env('VISA_SMS_AUTO_PROCESS', true),
            'download_schedule' => '0 3 * * *', // Daily at 3 AM
            'days_back_check' => 7, // How many days back to check for files
            'retention_days' => 365, // How long to keep processed files
        ],

        /*
        |--------------------------------------------------------------------------
        | Notification Settings
        |--------------------------------------------------------------------------
        |
        | Email and logging settings for Visa SMS processing
        |
        */
        'notifications' => [
            'enabled' => env('VISA_SMS_NOTIFICATIONS_ENABLED', true),
            'email_recipients' => env('VISA_SMS_EMAIL_RECIPIENTS', ''),
            'notify_on_success' => env('VISA_SMS_NOTIFY_SUCCESS', false),
            'notify_on_failure' => env('VISA_SMS_NOTIFY_FAILURE', true),
            'notify_on_no_files' => env('VISA_SMS_NOTIFY_NO_FILES', false),
        ],

        /*
        |--------------------------------------------------------------------------
        | Validation Rules
        |--------------------------------------------------------------------------
        |
        | Data validation rules for processing
        |
        */
        'validation' => [
            // Required CSV columns
            'required_columns' => [
                'PAYMENT_ID',
                'INTERCHANGE',
            ],

            // Optional columns that will be logged if missing
            'optional_columns' => [
                'CARD',
                'MERCHANT_NAME',
                'TR_AMOUNT',
                'TR_CCY',
            ],

            // Data validation rules
            'rules' => [
                'payment_id' => 'required|string|max:255',
                'interchange' => 'required|numeric',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Visa Issues Configuration (Manual Reports)
    |--------------------------------------------------------------------------
    |
    | Configuration for manual Visa Issues reports processing
    |
    */
    'visa_issues' => [

        /*
        |--------------------------------------------------------------------------
        | SFTP Settings
        |--------------------------------------------------------------------------
        |
        | SFTP connection and file location settings for Visa Issues reports
        |
        */
        'sftp' => [
            'remote_path' => env('VISA_ISSUES_SFTP_REMOTE_PATH', '/in_file/Different issues'),
            'local_path' => env('VISA_ISSUES_SFTP_LOCAL_PATH', 'visa_issues'),
            'file_pattern' => env('VISA_ISSUES_FILE_PATTERN', 'INTCL_visa_sms_tr_det_'),
            'file_extension' => env('VISA_ISSUES_FILE_EXTENSION', '.csv'),
        ],

        /*
        |--------------------------------------------------------------------------
        | File Processing Settings
        |--------------------------------------------------------------------------
        |
        | Settings for processing Visa Issues CSV files
        |
        */
        'processing' => [
            // CSV parsing settings
            'csv_delimiter' => ';',
            'csv_enclosure' => '"',
            'csv_escape' => '\\',

            // Field mappings
            'field_mappings' => [
                'payment_id' => 'PAYMENT_ID',
                'interchange' => 'INTERCHANGE',
                'card' => 'CARD',
                'merchant_name' => 'MERCHANT_NAME',
                'merchant_id' => 'MERCHANT_ID',
                'amount' => 'TR_AMOUNT',
                'currency' => 'TR_CCY',
                'date_time' => 'TR_DATE_TIME',
                'approval_id' => 'TR_APPROVAL_ID',
            ],

            // Transaction matching settings
            'matching' => [
                'target_field' => 'user_define_field2', // The field to update with interchange value
                'require_payment_id' => true,
                'log_not_found' => false, // Set to true to log when transactions aren't found
                'batch_size' => 1000, // Process in batches for large files
            ],
        ],

        /*
        |--------------------------------------------------------------------------
        | Notification Settings
        |--------------------------------------------------------------------------
        |
        | Email and logging settings for Visa Issues processing
        |
        */
        'notifications' => [
            'enabled' => env('VISA_ISSUES_NOTIFICATIONS_ENABLED', false),
            'email_recipients' => env('VISA_ISSUES_EMAIL_RECIPIENTS', ''),
            'notify_on_success' => env('VISA_ISSUES_NOTIFY_SUCCESS', false),
            'notify_on_failure' => env('VISA_ISSUES_NOTIFY_FAILURE', true),
        ],
        'scheduling' => [
            'auto_download' => env('VISA_SMS_AUTO_DOWNLOAD', true),
            'auto_process' => env('VISA_SMS_AUTO_PROCESS', true),
            'download_schedule' => '0 3 * * *', // Daily at 3 AM
            'days_back_check' => 7, // How many days back to check for files
            'retention_days' => 365, // How long to keep processed files
        ],

        /*
        |--------------------------------------------------------------------------
        | Validation Rules
        |--------------------------------------------------------------------------
        |
        | Data validation rules for processing
        |
        */
        'validation' => [
            // Required CSV columns
            'required_columns' => [
                'PAYMENT_ID',
                'INTERCHANGE',
            ],

            // Optional columns that will be logged if missing
            'optional_columns' => [
                'CARD',
                'MERCHANT_NAME',
                'TR_AMOUNT',
                'TR_CCY',
            ],

            // Data validation rules
            'rules' => [
                'payment_id' => 'required|string|max:255',
                'interchange' => 'required|numeric',
            ],
        ],
    ],
];
