<?php

/**
 * API: Trends & Pattern Detection
 * Returns velocity trends, detected patterns, and burst data.
 */

header('Content-Type: application/json');

$basePath = dirname(dirname(__DIR__));
$config = require $basePath . '/config/config.php';

require_once $basePath . '/src/Database.php';
require_once $basePath . '/src/TrendAnalyzer.php';

try {
    $db = Database::getInstance($config['db']);
    $trends = new TrendAnalyzer($db);

    $advertiserId = isset($_GET['advertiser_id']) ? trim($_GET['advertiser_id']) : null;
    $days = (int) ($_GET['days'] ?? 30);

    $velocity = $advertiserId ? $trends->getVelocityTrend($advertiserId, $days) : [];
    $patterns = $trends->getPatterns($advertiserId);

    // Seasonality (only if advertiser specified)
    $seasonality = $advertiserId ? $trends->detectSeasonality($advertiserId) : [];

    // Get bursts for all advertisers
    $bursts = $db->fetchAll(
        "SELECT * FROM trend_snapshots WHERE is_burst = 1 ORDER BY snapshot_date DESC LIMIT 20"
    );

    echo json_encode([
        'success'     => true,
        'velocity'    => $velocity,
        'patterns'    => $patterns,
        'seasonality' => $seasonality,
        'bursts'      => $bursts,
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
