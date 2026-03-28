<?php

/**
 * API: Creative Detail
 * Returns full details for a single ad creative.
 */

header('Content-Type: application/json');

$basePath = dirname(dirname(__DIR__));
$config = require $basePath . '/config/config.php';

require_once $basePath . '/src/Database.php';

try {
    $db = Database::getInstance($config['db']);

    $creativeId = isset($_GET['id']) ? trim($_GET['id']) : null;

    if (!$creativeId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing creative ID']);
        exit;
    }

    // Get ad record
    $ad = $db->fetchOne(
        "SELECT * FROM ads WHERE creative_id = ?",
        [$creativeId]
    );

    if (!$ad) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Creative not found']);
        exit;
    }

    // Get all detail versions (history)
    $details = $db->fetchAll(
        "SELECT * FROM ad_details WHERE creative_id = ? ORDER BY snapshot_date DESC",
        [$creativeId]
    );

    // Get assets
    $assets = $db->fetchAll(
        "SELECT * FROM ad_assets WHERE creative_id = ? ORDER BY created_at DESC",
        [$creativeId]
    );

    // Get targeting
    $targeting = $db->fetchAll(
        "SELECT * FROM ad_targeting WHERE creative_id = ? ORDER BY detected_at DESC",
        [$creativeId]
    );

    // Campaign duration
    $duration = null;
    if ($ad['first_seen'] && $ad['last_seen']) {
        $first = new DateTime($ad['first_seen']);
        $last = new DateTime($ad['last_seen']);
        $diff = $first->diff($last);
        $duration = $diff->days;
    }

    echo json_encode([
        'success'   => true,
        'ad'        => $ad,
        'details'   => $details,
        'assets'    => $assets,
        'targeting' => $targeting,
        'duration_days' => $duration,
        'version_count' => count($details),
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
