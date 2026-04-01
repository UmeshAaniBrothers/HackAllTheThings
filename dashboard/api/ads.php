<?php

/**
 * API: Ad Listing
 * Returns filtered, paginated ad listings with sorting.
 */

header('Content-Type: application/json');

$basePath = dirname(dirname(__DIR__));
$config = require $basePath . '/config/config.php';

require_once $basePath . '/src/Database.php';

try {
    $db = Database::getInstance($config['db']);

    // Parse filters
    $advertiserId = isset($_GET['advertiser_id']) ? trim($_GET['advertiser_id']) : null;
    $country = isset($_GET['country']) ? trim($_GET['country']) : null;
    $platform = isset($_GET['platform']) ? trim($_GET['platform']) : null;
    $adType = isset($_GET['ad_type']) ? trim($_GET['ad_type']) : null;
    $status = isset($_GET['status']) ? trim($_GET['status']) : null;
    $dateFrom = isset($_GET['date_from']) ? trim($_GET['date_from']) : null;
    $dateTo = isset($_GET['date_to']) ? trim($_GET['date_to']) : null;
    $search = isset($_GET['search']) ? trim($_GET['search']) : null;
    $productId = isset($_GET['product_id']) ? trim($_GET['product_id']) : null;
    $sort = isset($_GET['sort']) ? trim($_GET['sort']) : 'newest';
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = min(100, max(10, (int) ($_GET['per_page'] ?? 20)));

    $where = ['1=1'];
    $params = [];

    if ($advertiserId) {
        $where[] = 'a.advertiser_id = ?';
        $params[] = $advertiserId;
    }
    if ($adType && in_array($adType, ['text', 'image', 'video'])) {
        $where[] = 'a.ad_type = ?';
        $params[] = $adType;
    }
    if ($status && in_array($status, ['active', 'inactive'])) {
        $where[] = 'a.status = ?';
        $params[] = $status;
    }
    if ($dateFrom) {
        $where[] = 'a.first_seen >= ?';
        $params[] = $dateFrom;
    }
    if ($dateTo) {
        $where[] = 'a.last_seen <= ?';
        $params[] = $dateTo;
    }

    // Country filter via ad_targeting subquery
    if ($country) {
        $where[] = 'EXISTS (SELECT 1 FROM ad_targeting t WHERE t.creative_id = a.creative_id AND t.country = ?)';
        $params[] = $country;
    }
    // Platform filter via ad_products store_platform (ios/playstore/web)
    if ($platform && in_array($platform, ['ios', 'playstore', 'web'])) {
        $where[] = 'EXISTS (SELECT 1 FROM ad_product_map pm INNER JOIN ad_products p ON pm.product_id = p.id WHERE pm.creative_id = a.creative_id AND p.store_platform = ?)';
        $params[] = $platform;
    }

    // Product filter via subquery
    if ($productId) {
        $where[] = 'EXISTS (SELECT 1 FROM ad_product_map pm WHERE pm.creative_id = a.creative_id AND pm.product_id = ?)';
        $params[] = (int) $productId;
    }

    // Search in headline/description
    if ($search) {
        $where[] = 'EXISTS (SELECT 1 FROM ad_details d WHERE d.creative_id = a.creative_id AND (d.headline LIKE ? OR d.description LIKE ?))';
        $searchParam = '%' . $search . '%';
        $params[] = $searchParam;
        $params[] = $searchParam;
    }

    // Advanced filters
    $domain = isset($_GET['domain']) ? trim($_GET['domain']) : null;
    $cta = isset($_GET['cta']) ? trim($_GET['cta']) : null;
    $appGroup = isset($_GET['app_group']) ? (int)$_GET['app_group'] : null;
    $videoGroup = isset($_GET['video_group']) ? (int)$_GET['video_group'] : null;
    $timeFilter = isset($_GET['time_filter']) ? trim($_GET['time_filter']) : null;

    if ($domain) {
        $where[] = 'EXISTS (SELECT 1 FROM ad_details dd WHERE dd.creative_id = a.creative_id AND dd.landing_url LIKE ?)';
        $params[] = '%' . $domain . '%';
    }
    if ($cta) {
        $where[] = 'EXISTS (SELECT 1 FROM ad_details dd WHERE dd.creative_id = a.creative_id AND dd.cta LIKE ?)';
        $params[] = '%' . $cta . '%';
    }

    // App Group filter
    if ($appGroup) {
        $where[] = 'EXISTS (SELECT 1 FROM ad_product_map pm5 JOIN app_group_members agm ON agm.product_id = pm5.product_id WHERE pm5.creative_id = a.creative_id AND agm.group_id = ?)';
        $params[] = $appGroup;
    }

    // Video Group filter
    if ($videoGroup) {
        $where[] = "EXISTS (SELECT 1 FROM ad_assets va JOIN video_group_members vgm ON vgm.video_id = SUBSTRING_INDEX(SUBSTRING_INDEX(va.original_url, 'v=', -1), '&', 1) WHERE va.creative_id = a.creative_id AND va.type = 'video' AND vgm.group_id = ?)";
        $params[] = $videoGroup;
    }

    // Time-based filter
    $timeIntervals = [
        '48h'  => 'INTERVAL 48 HOUR',
        '7d'   => 'INTERVAL 7 DAY',
        '30d'  => 'INTERVAL 30 DAY',
        '90d'  => 'INTERVAL 90 DAY',
    ];
    if ($timeFilter && isset($timeIntervals[$timeFilter])) {
        $where[] = 'a.first_seen >= DATE_SUB(NOW(), ' . $timeIntervals[$timeFilter] . ')';
    }
    // "oldest" sort override — show oldest ads first
    if ($timeFilter === 'oldest') {
        $sort = 'oldest';
    }

    $whereClause = implode(' AND ', $where);

    // Sort order
    $sortMap = array(
        'newest'     => 'a.last_seen DESC',
        'oldest'     => 'a.first_seen ASC',
        'last_seen'  => 'a.last_seen DESC',
        'views_desc' => 'a.view_count DESC, a.last_seen DESC',
        'views_asc'  => 'CASE WHEN a.view_count = 0 OR a.view_count IS NULL THEN 1 ELSE 0 END, a.view_count ASC',
    );
    $orderBy = isset($sortMap[$sort]) ? $sortMap[$sort] : 'a.last_seen DESC';

    // Count total
    $total = (int) $db->fetchColumn(
        "SELECT COUNT(*) FROM ads a WHERE {$whereClause}",
        $params
    );

    // Fetch page
    $offset = ($page - 1) * $perPage;
    $fetchParams = array_merge($params, [$perPage, $offset]);

    // Step 1: Get paginated ads with basic 1:1 JOINs (no correlated subqueries)
    $ads = $db->fetchAll(
        "SELECT a.creative_id, a.advertiser_id, a.ad_type, a.first_seen, a.last_seen, a.status, a.view_count,
                d.headline, d.description, d.cta, d.landing_url, d.display_url, d.ad_width, d.ad_height, d.headlines_json, d.descriptions_json, d.tracking_ids_json, d.headline_source,
                adv.name as advertiser_name
         FROM ads a
         LEFT JOIN ad_details d ON a.creative_id = d.creative_id
             AND d.id = (SELECT MAX(id) FROM ad_details WHERE creative_id = a.creative_id)
         LEFT JOIN managed_advertisers adv ON a.advertiser_id = adv.advertiser_id
         WHERE {$whereClause}
         ORDER BY {$orderBy}
         LIMIT ? OFFSET ?",
        $fetchParams
    );

    // Step 2: Batch-fetch supplemental data for just the returned page of ads
    if (!empty($ads)) {
        $creativeIds = array_column($ads, 'creative_id');
        $placeholders = implode(',', array_fill(0, count($creativeIds), '?'));

        // Batch fetch targeting (countries, platforms)
        $targeting = $db->fetchAll(
            "SELECT creative_id, GROUP_CONCAT(DISTINCT country) as countries, GROUP_CONCAT(DISTINCT platform) as platforms
             FROM ad_targeting WHERE creative_id IN ($placeholders) GROUP BY creative_id",
            $creativeIds
        );
        $targetingMap = [];
        foreach ($targeting as $t) $targetingMap[$t['creative_id']] = $t;

        // Batch fetch assets (images, videos, preview URLs)
        $assets = $db->fetchAll(
            "SELECT creative_id, type, original_url FROM ad_assets WHERE creative_id IN ($placeholders)",
            $creativeIds
        );
        $assetMap = [];
        foreach ($assets as $asset) {
            $cid = $asset['creative_id'];
            if (!isset($assetMap[$cid])) $assetMap[$cid] = ['images' => [], 'videos' => [], 'previews' => []];
            if ($asset['type'] === 'image' && strpos($asset['original_url'], 'displayads-formats') === false) {
                $assetMap[$cid]['images'][] = $asset['original_url'];
            } elseif ($asset['type'] === 'video' && strpos($asset['original_url'], 'youtube.com') !== false) {
                $assetMap[$cid]['videos'][] = $asset['original_url'];
            }
            if (strpos($asset['original_url'], 'displayads-formats') !== false) {
                $assetMap[$cid]['previews'][] = $asset['original_url'];
            }
        }

        // Batch fetch products
        $products = $db->fetchAll(
            "SELECT pm.creative_id, p.id as product_id, p.product_name, p.store_url, p.store_platform
             FROM ad_product_map pm
             INNER JOIN ad_products p ON pm.product_id = p.id
             WHERE pm.creative_id IN ($placeholders) AND p.store_platform IN ('ios', 'playstore')",
            $creativeIds
        );
        $productMap = [];
        foreach ($products as $p) {
            $cid = $p['creative_id'];
            if (!isset($productMap[$cid])) {
                $productMap[$cid] = [
                    'product_names' => [],
                    'product_id' => $p['product_id'],
                    'store_url' => null,
                    'store_platform' => $p['store_platform'],
                ];
            }
            $productMap[$cid]['product_names'][] = $p['product_name'];
            if (!$productMap[$cid]['store_url'] && $p['store_url'] && $p['store_url'] !== '' && $p['store_url'] !== 'not_found') {
                $productMap[$cid]['store_url'] = $p['store_url'];
            }
        }

        // Merge supplemental data into ads
        foreach ($ads as &$ad) {
            $cid = $ad['creative_id'];

            // Targeting
            $t = $targetingMap[$cid] ?? null;
            $ad['countries'] = $t['countries'] ?? '';
            $ad['platforms'] = $t['platforms'] ?? '';

            // Assets — prefer ytimg thumbnails for preview_image
            $a = $assetMap[$cid] ?? null;
            $images = $a['images'] ?? [];
            usort($images, function($x, $y) {
                return (strpos($y, 'ytimg.com') !== false) - (strpos($x, 'ytimg.com') !== false);
            });
            $ad['preview_image'] = $images[0] ?? null;
            $ad['youtube_url'] = ($a['videos'] ?? [])[0] ?? null;
            $ad['preview_url'] = ($a['previews'] ?? [])[0] ?? null;

            // Products
            $pm = $productMap[$cid] ?? null;
            $ad['product_names'] = $pm ? implode('||', array_unique($pm['product_names'])) : null;
            $ad['product_id'] = $pm['product_id'] ?? null;
            $ad['store_url'] = $pm['store_url'] ?? null;
            $ad['store_platform'] = $pm['store_platform'] ?? null;
        }
        unset($ad);
    }

    // Add is_new flag (first_seen within 48 hours)
    $sevenDaysAgo = date('Y-m-d H:i:s', strtotime('-48 hours'));
    foreach ($ads as &$ad) {
        $ad['is_new'] = ($ad['first_seen'] >= $sevenDaysAgo) ? 1 : 0;
    }
    unset($ad);

    // Get available filter values
    $filterOptions = [
        'advertisers' => $db->fetchAll("SELECT DISTINCT a.advertiser_id, COALESCE(ma.name, a.advertiser_id) as name FROM ads a LEFT JOIN managed_advertisers ma ON a.advertiser_id = ma.advertiser_id ORDER BY ma.name, a.advertiser_id"),
        'countries'   => $db->fetchAll("SELECT DISTINCT country FROM ad_targeting ORDER BY country"),
        'platforms'   => $db->fetchAll("SELECT DISTINCT store_platform as platform FROM ad_products WHERE store_platform IS NOT NULL ORDER BY FIELD(store_platform, 'ios', 'playstore', 'web')"),
        'products'    => $db->fetchAll("SELECT p.id as product_id, p.product_name, p.product_type, p.store_platform, p.store_url, p.advertiser_id, COUNT(pm.creative_id) as ad_count FROM ad_products p LEFT JOIN ad_product_map pm ON p.id = pm.product_id WHERE p.store_platform IN ('ios', 'playstore') GROUP BY p.id ORDER BY ad_count DESC"),
        'app_groups'  => [],
        'video_groups' => [],
    ];

    // Load app groups (graceful if table doesn't exist yet)
    try {
        $filterOptions['app_groups'] = $db->fetchAll("SELECT id, name, color FROM app_groups ORDER BY name");
    } catch (Exception $e) {}

    // Load video groups (graceful if table doesn't exist yet)
    try {
        $filterOptions['video_groups'] = $db->fetchAll("SELECT id, name, color FROM video_groups ORDER BY name");
    } catch (Exception $e) {}

    echo json_encode([
        'success'        => true,
        'ads'            => $ads,
        'total'          => $total,
        'page'           => $page,
        'per_page'       => $perPage,
        'total_pages'    => ceil($total / $perPage),
        'filter_options' => $filterOptions,
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
