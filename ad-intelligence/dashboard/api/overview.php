<?php

/**
 * API: Overview Stats
 * Returns dashboard overview KPIs and summary data.
 */

header('Content-Type: application/json');

$basePath = dirname(dirname(__DIR__));
$config = require $basePath . '/config/config.php';

require_once $basePath . '/src/Database.php';
require_once $basePath . '/src/Analytics.php';

try {
    $db = Database::getInstance($config['db']);
    $analytics = new Analytics($db);

    $advertiserId = isset($_GET['advertiser_id']) ? trim($_GET['advertiser_id']) : null;

    $stats = $analytics->getOverviewStats($advertiserId);
    $velocity = $analytics->getAdVelocity($advertiserId, 30);
    $advertisers = $analytics->getAdvertiserList();
    $recentActivity = $analytics->getRecentActivity(20);

    echo json_encode([
        'success'         => true,
        'stats'           => $stats,
        'velocity'        => $velocity,
        'advertisers'     => $advertisers,
        'recent_activity' => $recentActivity,
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
