<?php

/**
 * API: Overview Stats
 * Returns dashboard overview KPIs, charts, top entities, and summary data.
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
                SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive_ads,
                SUM(CASE WHEN ad_type = 'video' THEN 1 ELSE 0 END) as video_ads,
                SUM(CASE WHEN ad_type = 'image' THEN 1 ELSE 0 END) as image_ads,
                SUM(CASE WHEN ad_type = 'text' THEN 1 ELSE 0 END) as text_ads,
                COUNT(DISTINCT advertiser_id) as total_advertisers
         FROM ads WHERE {$where}",
        $params
    );

    // Pending payloads
    $pending = $db->fetchColumn("SELECT COUNT(*) FROM raw_payloads WHERE processed_flag = 0");
    $stats['pending_payloads'] = (int) $pending;

    // Total apps detected
    $stats['total_apps'] = (int) $db->fetchColumn(
        "SELECT COUNT(*) FROM ad_products WHERE store_platform IN ('ios', 'playstore')"
    );

    // Total YouTube videos
    $stats['total_videos'] = (int) $db->fetchColumn(
        "SELECT COUNT(DISTINCT original_url) FROM ad_assets WHERE type = 'video' AND original_url LIKE '%youtube.com%'"
    );

    // Total countries
    $stats['total_countries'] = (int) $db->fetchColumn(
        "SELECT COUNT(DISTINCT country) FROM ad_targeting"
    );

    // New ads in last 24h, 7d
    $stats['new_ads_24h'] = (int) $db->fetchColumn(
        "SELECT COUNT(*) FROM ads WHERE first_seen >= DATE_SUB(NOW(), INTERVAL 24 HOUR)" .
        ($advertiserId ? " AND advertiser_id = ?" : ""),
        $advertiserId ? [$advertiserId] : []
    );
    $stats['new_ads_7d'] = (int) $db->fetchColumn(
        "SELECT COUNT(*) FROM ads WHERE first_seen >= DATE_SUB(NOW(), INTERVAL 7 DAY)" .
        ($advertiserId ? " AND advertiser_id = ?" : ""),
        $advertiserId ? [$advertiserId] : []
    );

    // New apps in last 7d
    $stats['new_apps_7d'] = (int) $db->fetchColumn(
        "SELECT COUNT(*) FROM ad_products WHERE store_platform IN ('ios', 'playstore') AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
    );

    // Advertiser list with new ads count
    $advertisers = $db->fetchAll(
        "SELECT ma.advertiser_id, ma.name, ma.status, ma.total_ads, ma.active_ads, ma.last_fetched_at,
                (SELECT COUNT(*) FROM ads a WHERE a.advertiser_id = ma.advertiser_id AND a.first_seen >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) as new_ads_24h,
                (SELECT COUNT(*) FROM ads a WHERE a.advertiser_id = ma.advertiser_id AND a.first_seen >= DATE_SUB(NOW(), INTERVAL 7 DAY)) as new_ads_7d
         FROM managed_advertisers ma
         WHERE ma.status NOT IN ('deleted')
         ORDER BY ma.total_ads DESC"
    );

    // Recent activity (last 20 ads)
    $recentActivity = $db->fetchAll(
        "SELECT a.creative_id, a.advertiser_id, a.ad_type, a.status, a.last_seen,
                d.headline,
                COALESCE(adv.name, a.advertiser_id) as advertiser_name
         FROM ads a
         LEFT JOIN ad_details d ON a.creative_id = d.creative_id
             AND d.id = (SELECT MAX(id) FROM ad_details WHERE creative_id = a.creative_id)
         LEFT JOIN managed_advertisers adv ON a.advertiser_id = adv.advertiser_id
         " . ($advertiserId ? "WHERE a.advertiser_id = ? " : "") . "
         ORDER BY a.last_seen DESC
         LIMIT 20",
        $advertiserId ? [$advertiserId] : []
    );

    // Ad type breakdown (for pie chart)
    $adTypeBreakdown = $db->fetchAll(
        "SELECT ad_type, COUNT(*) as count FROM ads " .
        ($advertiserId ? "WHERE advertiser_id = ? " : "") .
        "GROUP BY ad_type ORDER BY count DESC",
        $advertiserId ? [$advertiserId] : []
    );

    // Status breakdown
    $statusBreakdown = $db->fetchAll(
        "SELECT status, COUNT(*) as count FROM ads " .
        ($advertiserId ? "WHERE advertiser_id = ? " : "") .
        "GROUP BY status ORDER BY count DESC",
        $advertiserId ? [$advertiserId] : []
    );

    // Activity timeline (last 12 months)
    $timeline = $db->fetchAll(
        "SELECT DATE_FORMAT(first_seen, '%Y-%m') as month, COUNT(*) as count
         FROM ads
         " . ($advertiserId ? "WHERE advertiser_id = ? " : "") . "
         GROUP BY month
         ORDER BY month DESC
         LIMIT 12",
        $advertiserId ? [$advertiserId] : []
    );
    $timeline = array_reverse($timeline);

    // Top 5 advertisers by total ads
    $topAdvertisers = $db->fetchAll(
        "SELECT a.advertiser_id, COALESCE(ma.name, a.advertiser_id) as name,
                COUNT(*) as total_ads,
                SUM(a.status = 'active') as active_ads
         FROM ads a
         LEFT JOIN managed_advertisers ma ON a.advertiser_id = ma.advertiser_id
         GROUP BY a.advertiser_id
         ORDER BY total_ads DESC
         LIMIT 5"
    );

    // Top 5 apps by ad count
    $topApps = $db->fetchAll(
        "SELECT p.id, p.product_name, p.store_platform, p.store_url,
                COUNT(pm.creative_id) as ad_count
         FROM ad_products p
         INNER JOIN ad_product_map pm ON p.id = pm.product_id
         WHERE p.store_platform IN ('ios', 'playstore')
           AND p.product_name != 'Unknown'
         GROUP BY p.id
         ORDER BY ad_count DESC
         LIMIT 5"
    );

    // Top countries
    $topCountries = $db->fetchAll(
        "SELECT country, COUNT(DISTINCT creative_id) as ad_count
         FROM ad_targeting
         GROUP BY country
         ORDER BY ad_count DESC
         LIMIT 10"
    );

    // Top YouTube videos by view count
    $topVideos = $db->fetchAll(
        "SELECT a.creative_id, a.view_count, d.headline,
                (SELECT original_url FROM ad_assets ass WHERE ass.creative_id = a.creative_id AND ass.type = 'video' AND ass.original_url LIKE '%youtube.com%' LIMIT 1) as youtube_url
         FROM ads a
         LEFT JOIN ad_details d ON a.creative_id = d.creative_id
             AND d.id = (SELECT MAX(id) FROM ad_details WHERE creative_id = a.creative_id)
         WHERE a.ad_type = 'video' AND a.view_count > 0
         ORDER BY a.view_count DESC
         LIMIT 5"
    );

    echo json_encode([
        'success'          => true,
        'stats'            => $stats,
        'advertisers'      => $advertisers,
        'recent_activity'  => $recentActivity,
        'ad_type_breakdown' => $adTypeBreakdown,
        'status_breakdown' => $statusBreakdown,
        'timeline'         => $timeline,
        'top_advertisers'  => $topAdvertisers,
        'top_apps'         => $topApps,
        'top_countries'    => $topCountries,
        'top_videos'       => $topVideos,
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
