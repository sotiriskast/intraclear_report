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
        'private_key_path' => env('DECTA_SFTP_PRIVATE_KEY_PATH', storage_path('app/private/decta/decta_rsa')),
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
            env('DECTA_NOTIFICATION_EMAIL_1','skastanas@intraclear.com'),
            env('DECTA_NOTIFICATION_EMAIL_2','l.koniotis@yourcompany.com'),
            env('DECTA_NOTIFICATION_EMAIL_3','d.slobodchikov@intraclear.com'),
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
    ],
];
