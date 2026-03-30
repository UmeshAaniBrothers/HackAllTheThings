<?php

/**
 * CRON: Detect Changes
 *
 * Runs the change detection engine to identify new, updated,
 * removed, and revived ads.
 *
 * Schedule: Every 15 minutes
 * *​/15 * * * * php /path/to/ad-intelligence/cron/detect_changes.php
 */

set_time_limit(0);

$basePath = dirname(__DIR__);
$config = require $basePath . '/config/config.php';

require_once $basePath . '/src/Database.php';
require_once $basePath . '/src/ChangeDetector.php';

echo "[" . date('Y-m-d H:i:s') . "] === DETECT CHANGES START ===\n";

try {
    $db = Database::getInstance($config['db']);
    $detector = new ChangeDetector($db);

    $results = $detector->runAll();

    echo "\nSummary:\n";
    echo "  New ads:     {$results['new_ads']}\n";
    echo "  Updated ads: {$results['updated_ads']}\n";
    echo "  Removed ads: {$results['removed_ads']}\n";
    echo "  Revived ads: {$results['revived_ads']}\n";

    echo "\n[" . date('Y-m-d H:i:s') . "] === DETECT CHANGES COMPLETE ===\n";

} catch (Exception $e) {
    echo "FATAL ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
