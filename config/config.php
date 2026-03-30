<?php

/**
 * Ad Intelligence Dashboard - Configuration
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
            // Advertisers are now managed via the database (tracked_advertisers table)
            // and the Manage page UI. This array is kept for backward compatibility
            // with existing cron jobs.
        ],
    ],
    'storage' => [
        'media_path'   => __DIR__ . '/../storage/media/',
        'export_path'  => __DIR__ . '/../storage/exports/',
        'driver'       => 'local',
    ],
    'cron' => [
        'fetch_interval'   => 21600,
        'process_interval' => 600,
        'detect_interval'  => 900,
    ],
    'alerts' => [
        'email_from' => 'umesh@aanibrothers.in',
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
