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

    // Also pull advertisers from the managed_advertisers table (managed via UI)
    try {
        $tracked = $db->fetchAll("SELECT advertiser_id, name FROM managed_advertisers WHERE status IN ('active', 'new')");
        foreach ($tracked as $t) {
            if (!isset($advertisers[$t['advertiser_id']])) {
                $advertisers[$t['advertiser_id']] = $t['name'];
            }
        }
    } catch (Exception $e) {
        // Table may not exist yet — that's fine, use config only
    }

    if (empty($advertisers)) {
        echo "No advertisers configured. Add them via config.php or the Manage page.\n";
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
