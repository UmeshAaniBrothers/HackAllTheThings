<?php

/**
 * API: Overview Stats
 * Returns dashboard overview KPIs and summary data.
 */

header('Content-Type: application/json');

$basePath = dirname(dirname(__DIR__));
$config = require $basePath . '/config/config.php';

require_once $basePath . '/src/Database.php';

try {
    $db = Database::getInstance($config['db']);

    $advertiserId = isset($_GET['advertiser_id']) ? trim($_GET['advertiser_id']) : null;

    // Core stats
    $where = '1=1';
    $params = [];
    if ($advertiserId) {
        $where = 'advertiser_id = ?';
        $params = [$advertiserId];
    }

    $stats = $db->fetchOne(
        "SELECT COUNT(*) as total_ads,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_ads,
                SUM(CASE WHEN ad_type = 'video' THEN 1 ELSE 0 END) as video_ads,
                SUM(CASE WHEN ad_type = 'image' THEN 1 ELSE 0 END) as image_ads,
                SUM(CASE WHEN ad_type = 'text' THEN 1 ELSE 0 END) as text_ads,
                COUNT(DISTINCT advertiser_id) as total_advertisers
         FROM ads WHERE {$where}",
        $params
    );

    // Pending payloads
    $pending = $db->fetchColumn(
        "SELECT COUNT(*) FROM raw_payloads WHERE processed_flag = 0"
    );
    $stats['pending_payloads'] = (int) $pending;

    // Advertiser list
    $advertisers = $db->fetchAll(
        "SELECT ma.advertiser_id, ma.name, ma.status, ma.total_ads, ma.active_ads, ma.last_fetched_at
         FROM managed_advertisers ma
         WHERE ma.status NOT IN ('deleted')
         ORDER BY ma.updated_at DESC"
    );

    // Recent activity (last 20 ads seen)
    $recentActivity = $db->fetchAll(
        "SELECT a.creative_id, a.advertiser_id, a.ad_type, a.status, a.last_seen,
                d.headline
         FROM ads a
         LEFT JOIN ad_details d ON a.creative_id = d.creative_id
             AND d.id = (SELECT MAX(id) FROM ad_details WHERE creative_id = a.creative_id)
         ORDER BY a.last_seen DESC
         LIMIT 20"
    );

    echo json_encode([
        'success'         => true,
        'stats'           => $stats,
        'advertisers'     => $advertisers,
        'recent_activity' => $recentActivity,
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
