<?php

/**
 * CRON: Process Ads
 *
 * Processes raw JSON payloads into structured data,
 * extracts ad details, assets, and targeting information.
 *
 * Schedule: Every 10 minutes
 * *​/10 * * * * php /path/to/ad-intelligence/cron/process_ads.php
 */

set_time_limit(0);

$basePath = dirname(__DIR__);
$config = require $basePath . '/config/config.php';

require_once $basePath . '/src/Database.php';
require_once $basePath . '/src/AssetManager.php';
require_once $basePath . '/src/Processor.php';

echo "[" . date('Y-m-d H:i:s') . "] === PROCESS ADS START ===\n";

try {
    $db = Database::getInstance($config['db']);
    $assetManager = new AssetManager($config['storage']);
    $processor = new Processor($db, $assetManager);

    $processed = $processor->processAll();

    echo "[" . date('Y-m-d H:i:s') . "] === PROCESS ADS COMPLETE: {$processed} payloads processed ===\n";

} catch (Exception $e) {
    echo "FATAL ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
