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
        'private_key_path' => env('DECTA_SFTP_PRIVATE_KEY_PATH', storage_path('app/decta/decta_rsa')),
        'remote_path' => env('DECTA_SFTP_REMOTE_PATH', 'in_file/reports'),
        'local_path' => env('DECTA_SFTP_LOCAL_PATH', 'decta/files'),
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
];
