<?php
/**
 * Lightweight API: Advertiser list for dropdowns
 * Much faster than overview.php — no stats/charts/subqueries
 */
header('Content-Type: application/json');

$basePath = dirname(dirname(__DIR__));
$config = require $basePath . '/config/config.php';
require_once $basePath . '/src/Database.php';

try {
    $db = Database::getInstance($config['db']);

    $advertisers = $db->fetchAll(
        "SELECT ma.advertiser_id, ma.name, ma.total_ads, ma.active_ads
         FROM managed_advertisers ma
         WHERE ma.status NOT IN ('deleted')
         ORDER BY ma.total_ads DESC"
    );

    echo json_encode(['success' => true, 'advertisers' => $advertisers]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
