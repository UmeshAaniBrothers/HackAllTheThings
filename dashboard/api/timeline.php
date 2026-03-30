<?php

/**
 * API: Campaign Timeline
 * Returns timeline data for ad lifecycle visualization.
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
    $from = isset($_GET['from']) ? trim($_GET['from']) : null;
    $to = isset($_GET['to']) ? trim($_GET['to']) : null;

    $timeline = $analytics->getCampaignTimeline($advertiserId, $from, $to);
    $velocity = $analytics->getAdVelocity($advertiserId, 90);

    // Calculate activity spikes (days with above-average new ads)
    $avgPerDay = count($velocity) > 0
        ? array_sum(array_column($velocity, 'count')) / count($velocity)
        : 0;

    $spikes = array_filter($velocity, function($v) use ($avgPerDay) { return $v['count'] > ($avgPerDay * 1.5); });

    echo json_encode([
        'success'  => true,
        'timeline' => $timeline,
        'velocity' => $velocity,
        'spikes'   => array_values($spikes),
        'avg_per_day' => round($avgPerDay, 1),
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
