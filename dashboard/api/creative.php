<?php

/**
 * API: Creative Detail
 * Returns full details for a single ad creative with all related data.
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

    // Get ad record with advertiser name
    $ad = $db->fetchOne(
        "SELECT a.*, COALESCE(ma.name, a.advertiser_id) AS advertiser_name, ma.status AS advertiser_status
         FROM ads a
         LEFT JOIN managed_advertisers ma ON a.advertiser_id = ma.advertiser_id
         WHERE a.creative_id = ?",
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

    // Get products/apps linked to this ad
    $products = $db->fetchAll(
        "SELECT p.id AS product_id, p.product_name, p.product_type, p.store_platform, p.store_url,
                am.icon_url, am.rating, am.rating_count, am.developer_name, am.category, am.downloads, am.app_name
         FROM ad_product_map pm
         INNER JOIN ad_products p ON pm.product_id = p.id
         LEFT JOIN app_metadata am ON am.product_id = p.id
         WHERE pm.creative_id = ?",
        [$creativeId]
    );

    // Get YouTube video metadata if this is a video ad
    $youtube = null;
    $ytAsset = null;
    foreach ($assets as $asset) {
        if ($asset['type'] === 'video' && strpos($asset['original_url'], 'youtube.com') !== false) {
            $ytAsset = $asset;
            break;
        }
    }
    if ($ytAsset && preg_match('/[?&]v=([a-zA-Z0-9_-]{11})/', $ytAsset['original_url'], $m)) {
        $youtube = $db->fetchOne(
            "SELECT * FROM youtube_metadata WHERE video_id = ?",
            [$m[1]]
        );
    }

    // Distinct countries list
    $countries = $db->fetchAll(
        "SELECT DISTINCT country FROM ad_targeting WHERE creative_id = ? ORDER BY country",
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
        'success'       => true,
        'ad'            => $ad,
        'details'       => $details,
        'assets'        => $assets,
        'targeting'     => $targeting,
        'products'      => $products,
        'youtube'       => $youtube,
        'countries'     => array_column($countries, 'country'),
        'duration_days' => $duration,
        'version_count' => count($details),
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
