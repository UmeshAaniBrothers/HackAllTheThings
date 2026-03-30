<?php

/**
 * CRON: Fetch Ads
 *
 * Scrapes advertiser data from Google Ads Transparency Center
 * and stores raw JSON payloads for processing.
 *
 * Schedule: Every 6 hours
 * 0 *​/6 * * * php /path/to/ad-intelligence/cron/fetch_ads.php
 */

set_time_limit(0);

$basePath = dirname(__DIR__);
$config = require $basePath . '/config/config.php';

require_once $basePath . '/src/Database.php';
require_once $basePath . '/src/Scraper.php';

echo "[" . date('Y-m-d H:i:s') . "] === FETCH ADS START ===\n";

try {
    $db = Database::getInstance($config['db']);
    $scraper = new Scraper($db, $config['scraper']);

    $advertisers = $config['scraper']['advertisers'] ?? [];

    if (empty($advertisers)) {
        echo "No advertisers configured. Add advertiser IDs to config.php\n";
        exit(0);
    }

    $totalAds = 0;

    foreach ($advertisers as $advertiserId => $advertiserName) {
        echo "\nFetching: {$advertiserName} ({$advertiserId})\n";

        try {
            $ads = $scraper->fetchAdvertiser($advertiserId);
            $count = count($ads);
            $totalAds += $count;
            echo "  Result: {$count} ads fetched\n";
        } catch (Exception $e) {
            echo "  ERROR: " . $e->getMessage() . "\n";

            // Log failure
            $db->insert('scrape_logs', [
                'advertiser_id' => $advertiserId,
                'ads_found'     => 0,
                'new_ads'       => 0,
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
            ]);
        }
    }

    echo "\n[" . date('Y-m-d H:i:s') . "] === FETCH ADS COMPLETE: {$totalAds} total ads ===\n";

} catch (Exception $e) {
    echo "FATAL ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
