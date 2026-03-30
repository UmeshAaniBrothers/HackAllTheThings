<?php

/**
 * CRON: Trend Analysis
 *
 * Runs velocity, burst, and seasonality detection.
 * Also updates advertiser profiles and creative fingerprints.
 *
 * Schedule: Every 30 minutes
 * *​/30 * * * * php /path/to/ad-intelligence/cron/run_trends.php
 */

set_time_limit(0);

$basePath = dirname(__DIR__);
$config = require $basePath . '/config/config.php';

require_once $basePath . '/src/Database.php';
require_once $basePath . '/src/TrendAnalyzer.php';
require_once $basePath . '/src/CreativeFingerprint.php';
require_once $basePath . '/src/AdvertiserProfile.php';
require_once $basePath . '/src/AIIntelligence.php';
require_once $basePath . '/src/TaggingSystem.php';

echo "[" . date('Y-m-d H:i:s') . "] === RUN TRENDS START ===\n";

try {
    $db = Database::getInstance($config['db']);

    // 1. Trend analysis
    $trends = new TrendAnalyzer($db);
    $trendResults = $trends->analyzeAll();
    echo "Trends: " . json_encode($trendResults) . "\n";

    // 2. Creative fingerprinting
    $fingerprints = new CreativeFingerprint($db);
    $fpCount = $fingerprints->processAll();
    echo "Fingerprinted: {$fpCount} ads\n";

    // 3. Advertiser profiles
    $profiles = new AdvertiserProfile($db);
    $profCount = $profiles->updateAll();
    echo "Profiles updated: {$profCount}\n";

    // 4. AI analysis
    $ai = new AIIntelligence($db);
    $aiCount = $ai->analyzeAll();
    echo "AI analyzed: {$aiCount} ads\n";

    // 5. Auto-tagging
    $tags = new TaggingSystem($db);
    $tagCount = $tags->autoTagAll();
    echo "Auto-tagged: {$tagCount}\n";

    echo "\n[" . date('Y-m-d H:i:s') . "] === RUN TRENDS COMPLETE ===\n";

} catch (Exception $e) {
    echo "FATAL ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
