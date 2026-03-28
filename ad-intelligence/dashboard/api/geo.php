<?php

/**
 * API: Geo Distribution
 * Returns country-wise ad targeting data for the geo dashboard.
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

    $distribution = $analytics->getGeoDistribution($advertiserId);
    $expansion = $analytics->getGeoExpansionTimeline($advertiserId);
    $platforms = $analytics->getPlatformDistribution($advertiserId);

    echo json_encode([
        'success'      => true,
        'distribution' => $distribution,
        'expansion'    => $expansion,
        'platforms'    => $platforms,
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
