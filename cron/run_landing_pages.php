<?php

/**
 * CRON: Landing Page Analysis
 *
 * Scrapes and analyzes landing pages from ad details.
 * Detects funnel types, technologies, and page changes.
 *
 * Schedule: Every 2 hours
 * 0 *​/2 * * * php /path/to/ad-intelligence/cron/run_landing_pages.php
 */

set_time_limit(0);

$basePath = dirname(__DIR__);
$config = require $basePath . '/config/config.php';

require_once $basePath . '/src/Database.php';
require_once $basePath . '/src/LandingPageAnalyzer.php';

echo "[" . date('Y-m-d H:i:s') . "] === LANDING PAGE ANALYSIS START ===\n";

try {
    $db = Database::getInstance($config['db']);
    $analyzer = new LandingPageAnalyzer($db);

    $analyzed = $analyzer->analyzeAll();
    echo "Analyzed {$analyzed} landing pages\n";

    echo "[" . date('Y-m-d H:i:s') . "] === LANDING PAGE ANALYSIS COMPLETE ===\n";

} catch (Exception $e) {
    echo "FATAL ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
