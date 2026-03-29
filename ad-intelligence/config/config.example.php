<?php

/**
 * Ad Intelligence Dashboard - Configuration Template
 *
 * Copy this file to config.php and update with your actual credentials.
 * NEVER commit config.php to version control.
 */

return [
    'db' => [
        'host'    => '1170423.cloudwaysapps.com',
        'name'    => 'okgtyacqrv',
        'user'    => 'okgtyacqrv',
        'pass'    => 'z8ruvbj9dX',
        'charset' => 'utf8mb4',
    ],
    'scraper' => [
        'max_retries' => 3,
        'advertisers' => [
            'AR00744063166605950977' => 'NIRAJKUMAR KANUBHAI MANGUKIYA',
        ],
    ],
    'storage' => [
        'media_path'   => __DIR__ . '/../storage/media/',
        'export_path'  => __DIR__ . '/../storage/exports/',
        'driver'       => 'local', // 'local' or 's3'
    ],
    'cron' => [
        'fetch_interval'   => 21600, // 6 hours
        'process_interval' => 600,   // 10 minutes
        'detect_interval'  => 900,   // 15 minutes
    ],
    'alerts' => [
        'email_from' => 'umesh@aanibrothers.in
        ',
        'email_to'   => '',
        'telegram'   => [
            'bot_token' => '',
            'chat_id'   => '',
        ],
        'slack' => [
            'webhook_url' => '',
        ],
    ],
    'proxy' => [
        'enabled' => false,
    ],
];
