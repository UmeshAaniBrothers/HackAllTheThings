<?php

/**
 * API: Advertiser Profile
 * Returns full profile data for an advertiser including stats, ads, apps, videos, etc.
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

    // 1. Get advertiser info
    $advertiser = $db->fetchOne(
        "SELECT * FROM managed_advertisers WHERE advertiser_id = ?",
        [$advertiserId]
    );

    if (!$advertiser) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Advertiser not found']);
        exit;
    }

    // 2. Count total/active/inactive ads
    $statsRow = $db->fetchOne(
        "SELECT COUNT(*) as total,
                COALESCE(SUM(status = 'active'), 0) as active,
                COALESCE(SUM(status = 'inactive'), 0) as inactive
         FROM ads
         WHERE advertiser_id = ?",
        [$advertiserId]
    );

    $stats = [
        'total'    => (int) $statsRow['total'],
        'active'   => (int) $statsRow['active'],
        'inactive' => (int) $statsRow['inactive'],
    ];

    // 3. Ad type breakdown
    $adTypes = $db->fetchAll(
        "SELECT ad_type, COUNT(*) as count
         FROM ads
         WHERE advertiser_id = ?
         GROUP BY ad_type",
        [$advertiserId]
    );

    // 4. Products/apps for this advertiser
    $apps = $db->fetchAll(
        "SELECT p.id as product_id, p.product_name, p.store_platform, p.store_url,
                COUNT(pm.creative_id) as ad_count
         FROM ad_products p
         LEFT JOIN ad_product_map pm ON p.id = pm.product_id
         WHERE p.advertiser_id = ?
           AND p.store_platform IN ('ios', 'playstore')
         GROUP BY p.id
         ORDER BY ad_count DESC",
        [$advertiserId]
    );

    // 5. Unique YouTube videos — extract video IDs in PHP for reliability
    $videoRows = $db->fetchAll(
        "SELECT DISTINCT ass.original_url, d.headline as title, a.view_count
         FROM ad_assets ass
         INNER JOIN ads a ON ass.creative_id = a.creative_id
         LEFT JOIN ad_details d ON a.creative_id = d.creative_id
             AND d.id = (SELECT MAX(id) FROM ad_details WHERE creative_id = a.creative_id)
         WHERE ass.type = 'video'
           AND ass.original_url LIKE '%youtube.com%'
           AND a.advertiser_id = ?
         ORDER BY a.view_count DESC",
        [$advertiserId]
    );

    $videos = [];
    $seenVideoIds = [];
    foreach ($videoRows as $row) {
        $videoId = null;
        if (preg_match('/[?&]v=([a-zA-Z0-9_-]{11})/', $row['original_url'], $m)) {
            $videoId = $m[1];
        } elseif (preg_match('/youtu\.be\/([a-zA-Z0-9_-]{11})/', $row['original_url'], $m)) {
            $videoId = $m[1];
        } elseif (preg_match('/\/embed\/([a-zA-Z0-9_-]{11})/', $row['original_url'], $m)) {
            $videoId = $m[1];
        }
        if ($videoId && !isset($seenVideoIds[$videoId])) {
            $seenVideoIds[$videoId] = true;
            $videos[] = [
                'video_id'   => $videoId,
                'title'      => $row['title'],
                'view_count' => (int) $row['view_count'],
            ];
        }
    }

    // 6. Countries
    $countryRows = $db->fetchAll(
        "SELECT DISTINCT t.country
         FROM ad_targeting t
         INNER JOIN ads a ON t.creative_id = a.creative_id
         WHERE a.advertiser_id = ?
         ORDER BY t.country",
        [$advertiserId]
    );
    $countries = array_column($countryRows, 'country');

    // 7. Activity timeline
    $timeline = $db->fetchAll(
        "SELECT DATE_FORMAT(first_seen, '%Y-%m') as month, COUNT(*) as count
         FROM ads
         WHERE advertiser_id = ?
         GROUP BY month
         ORDER BY month",
        [$advertiserId]
    );

    // 8. Top ads (paginated)
    $totalAds = (int) $stats['total'];
    $offset = ($page - 1) * $perPage;

    $ads = $db->fetchAll(
        "SELECT a.creative_id, a.advertiser_id, a.ad_type, a.first_seen, a.last_seen, a.status, a.view_count,
                d.headline, d.description, d.cta, d.landing_url,
                (SELECT GROUP_CONCAT(DISTINCT t.country) FROM ad_targeting t WHERE t.creative_id = a.creative_id) as countries,
                (SELECT GROUP_CONCAT(DISTINCT t.platform) FROM ad_targeting t WHERE t.creative_id = a.creative_id) as platforms,
                (SELECT original_url FROM ad_assets ass WHERE ass.creative_id = a.creative_id AND ass.type = 'image' AND ass.original_url LIKE '%ytimg.com%' LIMIT 1) as preview_image,
                (SELECT original_url FROM ad_assets ass WHERE ass.creative_id = a.creative_id AND ass.type = 'video' AND ass.original_url LIKE '%youtube.com%' LIMIT 1) as youtube_url,
                (SELECT original_url FROM ad_assets ass WHERE ass.creative_id = a.creative_id AND ass.original_url LIKE '%displayads-formats%' LIMIT 1) as preview_url,
                adv.name as advertiser_name,
                (SELECT GROUP_CONCAT(DISTINCT p.product_name SEPARATOR '||') FROM ad_product_map pm INNER JOIN ad_products p ON pm.product_id = p.id WHERE pm.creative_id = a.creative_id AND p.store_platform IN ('ios', 'playstore')) as product_names,
                (SELECT pm2.product_id FROM ad_product_map pm2 INNER JOIN ad_products p2x ON pm2.product_id = p2x.id WHERE pm2.creative_id = a.creative_id AND p2x.store_platform IN ('ios', 'playstore') LIMIT 1) as product_id,
                (SELECT p2.store_url FROM ad_product_map pm3 INNER JOIN ad_products p2 ON pm3.product_id = p2.id WHERE pm3.creative_id = a.creative_id AND p2.store_platform IN ('ios', 'playstore') AND p2.store_url IS NOT NULL AND p2.store_url != '' AND p2.store_url != 'not_found' LIMIT 1) as store_url,
                (SELECT p3.store_platform FROM ad_product_map pm4 INNER JOIN ad_products p3 ON pm4.product_id = p3.id WHERE pm4.creative_id = a.creative_id AND p3.store_platform IN ('ios', 'playstore') LIMIT 1) as store_platform
         FROM ads a
         LEFT JOIN ad_details d ON a.creative_id = d.creative_id
             AND d.id = (SELECT MAX(id) FROM ad_details WHERE creative_id = a.creative_id)
         LEFT JOIN managed_advertisers adv ON a.advertiser_id = adv.advertiser_id
         WHERE a.advertiser_id = ?
         ORDER BY a.last_seen DESC
         LIMIT ? OFFSET ?",
        [$advertiserId, $perPage, $offset]
    );

    echo json_encode([
        'success'     => true,
        'advertiser'  => $advertiser,
        'stats'       => $stats,
        'ad_types'    => $adTypes,
        'apps'        => $apps,
        'videos'      => $videos,
        'countries'   => $countries,
        'timeline'    => $timeline,
        'ads'         => $ads,
        'total_ads'   => $totalAds,
        'page'        => $page,
        'total_pages' => (int) ceil($totalAds / $perPage),
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
