<?php
header('Content-Type: application/json');
$basePath = dirname(dirname(__DIR__));
$config = require $basePath . '/config/config.php';
require_once $basePath . '/src/Database.php';

try {
    $db = Database::getInstance($config['db']);
    $q = isset($_GET['q']) ? trim($_GET['q']) : 'test';
    $like = '%' . $q . '%';

    // Simple test: search youtube_metadata
    $t1 = microtime(true);
    $videos = $db->fetchAll(
        "SELECT video_id, title, channel_name FROM youtube_metadata WHERE title LIKE ? OR channel_name LIKE ? LIMIT 5",
        [$like, $like]
    );
    $t2 = microtime(true);

    // Simple test: search managed_advertisers
    $advs = $db->fetchAll(
        "SELECT advertiser_id, name FROM managed_advertisers WHERE name LIKE ? OR advertiser_id LIKE ? LIMIT 5",
        [$like, $like]
    );
    $t3 = microtime(true);

    // Simple test: search ad_products + app_metadata
    $apps = $db->fetchAll(
        "SELECT p.id, p.product_name, am.developer_name
         FROM ad_products p LEFT JOIN app_metadata am ON am.product_id = p.id
         WHERE p.product_name LIKE ? OR am.developer_name LIKE ?
         LIMIT 5",
        [$like, $like]
    );
    $t4 = microtime(true);

    echo json_encode([
        'success' => true,
        'version' => 'search_test_v1',
        'videos'  => $videos,
        'videos_ms' => round(($t2 - $t1) * 1000),
        'advertisers' => $advs,
        'advs_ms' => round(($t3 - $t2) * 1000),
        'apps' => $apps,
        'apps_ms' => round(($t4 - $t3) * 1000),
        'total_ms' => round(($t4 - $t1) * 1000),
    ], JSON_PRETTY_PRINT);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
