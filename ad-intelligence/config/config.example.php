<?php

/**
 * Ad Intelligence Dashboard - Configuration Template
 *
 * Copy this file to config.php and update with your actual credentials.
 * NEVER commit config.php to version control.
 */

return [
    'db' => [
        'host'    => 'localhost',
        'name'    => 'ad_intelligence',
        'user'    => 'your_db_user',
        'pass'    => 'your_db_password',
        'charset' => 'utf8mb4',
    ],
    'scraper' => [
        'max_retries' => 3,
        'advertisers' => [
            // 'AR00000000000000000' => 'Advertiser Name',
        ],
    ],
    'storage' => [
        'media_path' => __DIR__ . '/../storage/media/',
        'driver'     => 'local', // 'local' or 's3'
    ],
    'cron' => [
        'fetch_interval'   => 21600, // 6 hours
        'process_interval' => 600,   // 10 minutes
        'detect_interval'  => 900,   // 15 minutes
    ],
];
