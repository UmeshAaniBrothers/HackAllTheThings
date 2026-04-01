<?php

/**
 * API: Advertiser Profile
 * Returns full profile with deep cross-linking to apps, videos, countries, and ads.
 */

header('Content-Type: application/json');

$basePath = dirname(dirname(__DIR__));
$config = require $basePath . '/config/config.php';

require_once $basePath . '/src/Database.php';

try {
    $db = Database::getInstance($config['db']);

    $advertiserId = isset($_GET['id']) ? trim($_GET['id']) : null;
    if (!$advertiserId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing required parameter: id']);
        exit;
    }

    $page = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = 20;

    // 1. Advertiser info
    $advertiser = $db->fetchOne(
        "SELECT * FROM managed_advertisers WHERE advertiser_id = ?",
        [$advertiserId]
    );
    if (!$advertiser) {
        // Fallback: create minimal advertiser from ads table
        $adRow = $db->fetchOne("SELECT advertiser_id FROM ads WHERE advertiser_id = ? LIMIT 1", [$advertiserId]);
        if (!$adRow) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Advertiser not found']);
            exit;
        }
        $advertiser = ['advertiser_id' => $advertiserId, 'name' => $advertiserId, 'status' => 'unknown'];
    }

    // 2. Comprehensive stats
    $stats = $db->fetchOne(
        "SELECT COUNT(*) as total,
                SUM(status = 'active') as active,
                SUM(status = 'inactive') as inactive,
                SUM(ad_type = 'video') as video_count,
                SUM(ad_type = 'image') as image_count,
                SUM(ad_type = 'text') as text_count,
                MIN(first_seen) as earliest_seen,
                MAX(last_seen) as latest_seen,
                SUM(COALESCE(view_count, 0)) as total_views,
                AVG(CASE WHEN view_count > 0 THEN view_count END) as avg_views
         FROM ads WHERE advertiser_id = ?",
        [$advertiserId]
    );

    // 3. Ad type breakdown
    $adTypes = $db->fetchAll(
        "SELECT ad_type, COUNT(*) as count, SUM(status='active') as active_count
         FROM ads WHERE advertiser_id = ? GROUP BY ad_type",
        [$advertiserId]
    );

    // 4. Apps with metadata hints
    $apps = $db->fetchAll(
        "SELECT p.id as product_id, p.product_name, p.store_platform, p.store_url,
                COUNT(pm.creative_id) as ad_count,
                (SELECT am.icon_url FROM app_metadata am WHERE am.product_id = p.id LIMIT 1) as icon_url,
                (SELECT am.rating FROM app_metadata am WHERE am.product_id = p.id LIMIT 1) as rating
         FROM ad_products p
         LEFT JOIN ad_product_map pm ON p.id = pm.product_id
         WHERE p.advertiser_id = ? AND p.store_platform IN ('ios', 'playstore')
         GROUP BY p.id ORDER BY ad_count DESC",
        [$advertiserId]
    );

    // 5. YouTube videos with metadata
    $videoRows = $db->fetchAll(
        "SELECT DISTINCT ass.original_url, a.view_count
         FROM ad_assets ass
         INNER JOIN ads a ON ass.creative_id = a.creative_id
         WHERE ass.type = 'video' AND ass.original_url LIKE '%youtube.com%' AND a.advertiser_id = ?
         ORDER BY a.view_count DESC",
        [$advertiserId]
    );

    $videos = [];
    $seenVideoIds = [];
    foreach ($videoRows as $row) {
        $videoId = null;
        if (preg_match('/[?&]v=([a-zA-Z0-9_-]{11})/', $row['original_url'], $m)) $videoId = $m[1];
        elseif (preg_match('/youtu\.be\/([a-zA-Z0-9_-]{11})/', $row['original_url'], $m)) $videoId = $m[1];
        elseif (preg_match('/\/embed\/([a-zA-Z0-9_-]{11})/', $row['original_url'], $m)) $videoId = $m[1];
        if ($videoId && !isset($seenVideoIds[$videoId])) {
            $seenVideoIds[$videoId] = true;
            // Try cached metadata first
            $ytMeta = $db->fetchOne("SELECT title, view_count, like_count, duration, thumbnail_url, channel_name FROM youtube_metadata WHERE video_id = ?", [$videoId]);
            $videos[] = [
                'video_id'   => $videoId,
                'title'      => $ytMeta['title'] ?? null,
                'view_count' => (int) ($ytMeta['view_count'] ?? $row['view_count'] ?? 0),
                'like_count' => (int) ($ytMeta['like_count'] ?? 0),
                'duration'   => $ytMeta['duration'] ?? null,
                'channel_name' => $ytMeta['channel_name'] ?? null,
            ];
        }
    }

    // 6. Countries with ad counts
    $countries = $db->fetchAll(
        "SELECT t.country, COUNT(DISTINCT t.creative_id) as ad_count
         FROM ad_targeting t
         INNER JOIN ads a ON t.creative_id = a.creative_id
         WHERE a.advertiser_id = ?
         GROUP BY t.country ORDER BY ad_count DESC",
        [$advertiserId]
    );

    // 7. Timeline with type breakdown
    $timeline = $db->fetchAll(
        "SELECT DATE_FORMAT(first_seen, '%Y-%m') as month,
                COUNT(*) as count,
                SUM(ad_type='video') as videos,
                SUM(ad_type='image') as images,
                SUM(ad_type='text') as texts
         FROM ads WHERE advertiser_id = ?
         GROUP BY month ORDER BY month",
        [$advertiserId]
    );

    // 8. Paginated ads
    $totalAds = (int) ($stats['total'] ?? 0);
    $offset = ($page - 1) * $perPage;

    $ads = $db->fetchAll(
        "SELECT a.creative_id, a.advertiser_id, a.ad_type, a.first_seen, a.last_seen, a.status, a.view_count,
                d.headline, d.description, d.cta, d.landing_url, d.headline_source,
                (SELECT GROUP_CONCAT(DISTINCT t.country) FROM ad_targeting t WHERE t.creative_id = a.creative_id) as countries,
                (SELECT original_url FROM ad_assets ass WHERE ass.creative_id = a.creative_id AND ass.type = 'image' AND ass.original_url LIKE '%ytimg.com%' LIMIT 1) as preview_image,
                (SELECT original_url FROM ad_assets ass WHERE ass.creative_id = a.creative_id AND ass.type = 'video' AND ass.original_url LIKE '%youtube.com%' LIMIT 1) as youtube_url,
                (SELECT original_url FROM ad_assets ass WHERE ass.creative_id = a.creative_id AND ass.original_url LIKE '%displayads-formats%' LIMIT 1) as preview_url,
                (SELECT GROUP_CONCAT(DISTINCT p.product_name SEPARATOR '||') FROM ad_product_map pm INNER JOIN ad_products p ON pm.product_id = p.id WHERE pm.creative_id = a.creative_id AND p.store_platform IN ('ios', 'playstore')) as product_names,
                (SELECT pm2.product_id FROM ad_product_map pm2 INNER JOIN ad_products p2x ON pm2.product_id = p2x.id WHERE pm2.creative_id = a.creative_id AND p2x.store_platform IN ('ios', 'playstore') LIMIT 1) as product_id,
                (SELECT p3.store_platform FROM ad_product_map pm4 INNER JOIN ad_products p3 ON pm4.product_id = p3.id WHERE pm4.creative_id = a.creative_id AND p3.store_platform IN ('ios', 'playstore') LIMIT 1) as store_platform
         FROM ads a
         LEFT JOIN ad_details d ON a.creative_id = d.creative_id
             AND d.id = (SELECT MAX(id) FROM ad_details WHERE creative_id = a.creative_id)
         WHERE a.advertiser_id = ?
         ORDER BY a.last_seen DESC
         LIMIT ? OFFSET ?",
        [$advertiserId, $perPage, $offset]
    );

    // 8b. Developer ecosystem: group apps by developer account
    $developers = $db->fetchAll(
        "SELECT am.developer_name, am.developer_url,
                COUNT(DISTINCT p.id) as app_count,
                GROUP_CONCAT(DISTINCT p.id ORDER BY p.product_name SEPARATOR ',') as product_ids,
                GROUP_CONCAT(DISTINCT COALESCE(NULLIF(am.app_name, ''), p.product_name) ORDER BY p.product_name SEPARATOR '||') as app_names,
                GROUP_CONCAT(DISTINCT am.icon_url ORDER BY p.product_name SEPARATOR '||') as icon_urls
         FROM ad_products p
         INNER JOIN app_metadata am ON am.product_id = p.id
         WHERE p.advertiser_id = ? AND p.store_platform IN ('ios', 'playstore')
           AND am.developer_name IS NOT NULL AND am.developer_name != ''
         GROUP BY am.developer_name, am.developer_url
         ORDER BY app_count DESC",
        [$advertiserId]
    );

    // 9. Competitors: other advertisers promoting same apps
    $competitors = [];
    if (!empty($apps)) {
        $appIds = array_column($apps, 'product_id');
        $storeUrls = array_filter(array_column($apps, 'store_url'));
        if (!empty($storeUrls)) {
            $placeholders = implode(',', array_fill(0, count($storeUrls), '?'));
            $competitors = $db->fetchAll(
                "SELECT DISTINCT p.advertiser_id, COALESCE(ma.name, p.advertiser_id) as name,
                        COUNT(DISTINCT pm.creative_id) as ad_count
                 FROM ad_products p
                 INNER JOIN ad_product_map pm ON p.id = pm.product_id
                 LEFT JOIN managed_advertisers ma ON p.advertiser_id = ma.advertiser_id
                 WHERE p.store_url IN ({$placeholders}) AND p.advertiser_id != ?
                 GROUP BY p.advertiser_id ORDER BY ad_count DESC LIMIT 10",
                array_merge($storeUrls, [$advertiserId])
            );
        }
    }

    // 10. Top performing ads
    $topAds = $db->fetchAll(
        "SELECT a.creative_id, a.view_count, a.ad_type, a.status, d.headline, d.headline_source,
                (SELECT original_url FROM ad_assets ass WHERE ass.creative_id = a.creative_id AND ass.type = 'video' AND ass.original_url LIKE '%youtube.com%' LIMIT 1) as youtube_url,
                (SELECT original_url FROM ad_assets ass WHERE ass.creative_id = a.creative_id AND ass.type = 'image' AND ass.original_url LIKE '%ytimg.com%' LIMIT 1) as preview_image
         FROM ads a
         LEFT JOIN ad_details d ON a.creative_id = d.creative_id
             AND d.id = (SELECT MAX(id) FROM ad_details WHERE creative_id = a.creative_id)
         WHERE a.advertiser_id = ? AND a.view_count > 0
         ORDER BY a.view_count DESC LIMIT 5",
        [$advertiserId]
    );

    // 11. Platform distribution
    $platforms = $db->fetchAll(
        "SELECT t.platform, COUNT(DISTINCT t.creative_id) as ad_count
         FROM ad_targeting t
         INNER JOIN ads a ON t.creative_id = a.creative_id
         WHERE a.advertiser_id = ? AND t.platform IS NOT NULL AND t.platform != 'unknown'
         GROUP BY t.platform ORDER BY ad_count DESC",
        [$advertiserId]
    );

    echo json_encode([
        'success'          => true,
        'advertiser'       => $advertiser,
        'stats'            => $stats,
        'ad_types'         => $adTypes,
        'apps'             => $apps,
        'developers'       => $developers,
        'videos'           => $videos,
        'countries'        => $countries,
        'timeline'         => $timeline,
        'ads'              => $ads,
        'total_ads'        => $totalAds,
        'page'             => $page,
        'total_pages'      => (int) ceil($totalAds / $perPage),
        'competitors'      => $competitors,
        'top_ads'          => $topAds,
        'platforms'        => $platforms,
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
