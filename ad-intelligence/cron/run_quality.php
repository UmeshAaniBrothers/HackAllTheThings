<?php

/**
 * CRON: Data Quality Checks
 *
 * Runs validation, anomaly detection, and data health checks.
 *
 * Schedule: Every hour
 * 0 * * * * php /path/to/ad-intelligence/cron/run_quality.php
 */

set_time_limit(0);

$basePath = dirname(__DIR__);
$config = require $basePath . '/config/config.php';

require_once $basePath . '/src/Database.php';
require_once $basePath . '/src/DataQuality.php';

echo "[" . date('Y-m-d H:i:s') . "] === DATA QUALITY CHECK START ===\n";

try {
    $db = Database::getInstance($config['db']);
    $quality = new DataQuality($db);

    $results = $quality->runAll();

    echo "Results:\n";
    foreach ($results as $type => $count) {
        echo "  {$type}: {$count} issues\n";
    }

    echo "\n[" . date('Y-m-d H:i:s') . "] === DATA QUALITY CHECK COMPLETE ===\n";

} catch (Exception $e) {
    echo "FATAL ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
