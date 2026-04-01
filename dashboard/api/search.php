<?php

/**
 * API: Unified Search
 * Searches across all entities: ads, advertisers, apps, YouTube videos.
 * Returns categorised results with cross-linked data and analytics.
 */

header('Content-Type: application/json');

$basePath = dirname(dirname(__DIR__));
$config = require $basePath . '/config/config.php';
require_once $basePath . '/src/Database.php';

// Collation constant — used to resolve utf8mb4_unicode_ci vs utf8mb4_general_ci mismatches
define('COL', 'COLLATE utf8mb4_general_ci');

try {
    $db = Database::getInstance($config['db']);

    $q       = isset($_GET['q']) ? trim($_GET['q']) : '';
    $type    = isset($_GET['type']) ? $_GET['type'] : 'all'; // all, ads, advertisers, apps, videos
    $page    = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = min(50, max(5, (int) ($_GET['per_page'] ?? 20)));

    if (strlen($q) < 2) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Search query must be at least 2 characters.']);
        exit;
    }

    $like = '%' . $q . '%';
    $results = [];
    $counts  = ['ads' => 0, 'advertisers' => 0, 'apps' => 0, 'videos' => 0];
    $C = COL; // shorthand

    // ─────────────────────────────────────────────────
    // 1. Search ADS
    // ─────────────────────────────────────────────────
    if ($type === 'all' || $type === 'ads') {
        try {
            $countRow = $db->fetchOne(
                "SELECT COUNT(DISTINCT a.creative_id) as cnt
                 FROM ads a
                 LEFT JOIN ad_details d ON a.creative_id $C = d.creative_id $C
                     AND d.id = (SELECT MAX(id) FROM ad_details WHERE creative_id $C = a.creative_id $C)
                 WHERE a.creative_id $C LIKE ?
                    OR d.headline $C LIKE ?
                    OR d.description $C LIKE ?
                    OR d.cta $C LIKE ?
                    OR d.landing_url $C LIKE ?",
                [$like, $like, $like, $like, $like]
            );
            $counts['ads'] = (int) ($countRow['cnt'] ?? 0);

            $limit  = ($type === 'ads') ? $perPage : 5;
            $offset = ($type === 'ads') ? ($page - 1) * $perPage : 0;

            $adRows = $db->fetchAll(
                "SELECT a.creative_id, a.advertiser_id, a.ad_type, a.first_seen, a.last_seen,
                        a.status, a.view_count,
                        d.headline, d.description AS ad_description, d.cta, d.landing_url, d.headline_source,
                        d.display_url, d.ad_width, d.ad_height,
                        COALESCE(ma.name, a.advertiser_id) AS advertiser_name,
                        (SELECT GROUP_CONCAT(DISTINCT t.country) FROM ad_targeting t WHERE t.creative_id $C = a.creative_id $C) AS countries,
                        (SELECT GROUP_CONCAT(DISTINCT p.product_name SEPARATOR '||')
                         FROM ad_product_map pm INNER JOIN ad_products p ON pm.product_id = p.id
                         WHERE pm.creative_id $C = a.creative_id $C AND p.store_platform IN ('ios','playstore')) AS product_names,
                        (SELECT pm2.product_id FROM ad_product_map pm2 INNER JOIN ad_products p2 ON pm2.product_id = p2.id
                         WHERE pm2.creative_id $C = a.creative_id $C AND p2.store_platform IN ('ios','playstore') LIMIT 1) AS product_id,
                        (SELECT original_url FROM ad_assets ass WHERE ass.creative_id $C = a.creative_id $C AND ass.type = 'image' AND ass.original_url NOT LIKE '%displayads-formats%' ORDER BY (ass.original_url LIKE '%ytimg.com%') DESC, ass.id DESC LIMIT 1) AS preview_image,
                        (SELECT original_url FROM ad_assets ass WHERE ass.creative_id $C = a.creative_id $C AND ass.type = 'video' AND ass.original_url LIKE '%youtube.com%' LIMIT 1) AS youtube_url,
                        (SELECT original_url FROM ad_assets ass WHERE ass.creative_id $C = a.creative_id $C AND ass.original_url LIKE '%displayads-formats%' LIMIT 1) AS preview_url
                 FROM ads a
                 LEFT JOIN ad_details d ON a.creative_id $C = d.creative_id $C
                     AND d.id = (SELECT MAX(id) FROM ad_details WHERE creative_id $C = a.creative_id $C)
                 LEFT JOIN managed_advertisers ma ON a.advertiser_id $C = ma.advertiser_id $C
                 WHERE a.creative_id $C LIKE ?
                    OR d.headline $C LIKE ?
                    OR d.description $C LIKE ?
                    OR d.cta $C LIKE ?
                    OR d.landing_url $C LIKE ?
                 ORDER BY a.view_count DESC, a.last_seen DESC
                 LIMIT ? OFFSET ?",
                [$like, $like, $like, $like, $like, $limit, $offset]
            );
            $results['ads'] = $adRows;
        } catch (Exception $e) {
            $results['ads'] = [];
        }
    }

    // ─────────────────────────────────────────────────
    // 2. Search ADVERTISERS
    // ─────────────────────────────────────────────────
    if ($type === 'all' || $type === 'advertisers') {
        try {
            $countRow = $db->fetchOne(
                "SELECT COUNT(DISTINCT adv_id) as cnt FROM (
                    SELECT advertiser_id AS adv_id FROM managed_advertisers WHERE name $C LIKE ? OR advertiser_id $C LIKE ?
                    UNION
                    SELECT advertiser_id AS adv_id FROM ads WHERE advertiser_id $C LIKE ?
                 ) AS u",
                [$like, $like, $like]
            );
            $counts['advertisers'] = (int) ($countRow['cnt'] ?? 0);

            $limit  = ($type === 'advertisers') ? $perPage : 5;
            $offset = ($type === 'advertisers') ? ($page - 1) * $perPage : 0;

            $advRows = $db->fetchAll(
                "SELECT sub.advertiser_id,
                        COALESCE(ma.name, sub.advertiser_id) AS name,
                        ma.status AS mgmt_status,
                        ma.last_fetched_at,
                        (SELECT COUNT(*) FROM ads a WHERE a.advertiser_id $C = sub.advertiser_id $C) AS total_ads,
                        (SELECT SUM(a.status = 'active') FROM ads a WHERE a.advertiser_id $C = sub.advertiser_id $C) AS active_ads,
                        (SELECT SUM(COALESCE(a.view_count, 0)) FROM ads a WHERE a.advertiser_id $C = sub.advertiser_id $C) AS total_views,
                        (SELECT COUNT(DISTINCT t.country) FROM ad_targeting t INNER JOIN ads a ON t.creative_id $C = a.creative_id $C WHERE a.advertiser_id $C = sub.advertiser_id $C) AS country_count,
                        (SELECT COUNT(DISTINCT p.id) FROM ad_products p WHERE p.advertiser_id $C = sub.advertiser_id $C AND p.store_platform IN ('ios','playstore')) AS app_count,
                        (SELECT GROUP_CONCAT(DISTINCT p.product_name SEPARATOR '||')
                         FROM ad_products p WHERE p.advertiser_id $C = sub.advertiser_id $C AND p.store_platform IN ('ios','playstore')
                         LIMIT 5) AS app_names
                 FROM (
                    SELECT DISTINCT advertiser_id FROM managed_advertisers WHERE name $C LIKE ? OR advertiser_id $C LIKE ?
                    UNION
                    SELECT DISTINCT advertiser_id FROM ads WHERE advertiser_id $C LIKE ?
                 ) sub
                 LEFT JOIN managed_advertisers ma ON sub.advertiser_id $C = ma.advertiser_id $C
                 ORDER BY (SELECT SUM(COALESCE(a2.view_count, 0)) FROM ads a2 WHERE a2.advertiser_id $C = sub.advertiser_id $C) DESC
                 LIMIT ? OFFSET ?",
                [$like, $like, $like, $limit, $offset]
            );
            $results['advertisers'] = $advRows;
        } catch (Exception $e) {
            $results['advertisers'] = [];
        }
    }

    // ─────────────────────────────────────────────────
    // 3. Search APPS
    // ─────────────────────────────────────────────────
    if ($type === 'all' || $type === 'apps') {
        try {
            $countRow = $db->fetchOne(
                "SELECT COUNT(DISTINCT p.id) as cnt
                 FROM ad_products p
                 LEFT JOIN app_metadata am ON am.product_id = p.id
                 WHERE p.store_platform IN ('ios','playstore')
                   AND (p.product_name $C LIKE ?
                        OR am.bundle_id $C LIKE ?
                        OR am.developer_name $C LIKE ?
                        OR am.category $C LIKE ?
                        OR am.app_name $C LIKE ?
                        OR am.description $C LIKE ?)",
                [$like, $like, $like, $like, $like, $like]
            );
            $counts['apps'] = (int) ($countRow['cnt'] ?? 0);

            $limit  = ($type === 'apps') ? $perPage : 5;
            $offset = ($type === 'apps') ? ($page - 1) * $perPage : 0;

            $appRows = $db->fetchAll(
                "SELECT p.id AS product_id, p.product_name, p.store_platform, p.store_url,
                        p.advertiser_id,
                        COALESCE(ma.name, p.advertiser_id) AS advertiser_name,
                        am.icon_url, am.rating, am.rating_count, am.developer_name, am.category,
                        am.downloads, am.description AS app_description, am.price,
                        (SELECT COUNT(DISTINCT pm.creative_id) FROM ad_product_map pm WHERE pm.product_id = p.id) AS ad_count,
                        (SELECT SUM(a.status = 'active') FROM ads a INNER JOIN ad_product_map pm ON pm.creative_id $C = a.creative_id $C WHERE pm.product_id = p.id) AS active_ads,
                        (SELECT SUM(COALESCE(a.view_count, 0)) FROM ads a INNER JOIN ad_product_map pm ON pm.creative_id $C = a.creative_id $C WHERE pm.product_id = p.id) AS total_views,
                        (SELECT COUNT(DISTINCT t.country) FROM ad_targeting t INNER JOIN ad_product_map pm ON pm.creative_id $C = t.creative_id $C WHERE pm.product_id = p.id) AS country_count
                 FROM ad_products p
                 LEFT JOIN app_metadata am ON am.product_id = p.id
                 LEFT JOIN managed_advertisers ma ON p.advertiser_id $C = ma.advertiser_id $C
                 WHERE p.store_platform IN ('ios','playstore')
                   AND (p.product_name $C LIKE ?
                        OR am.bundle_id $C LIKE ?
                        OR am.developer_name $C LIKE ?
                        OR am.category $C LIKE ?
                        OR am.app_name $C LIKE ?
                        OR am.description $C LIKE ?)
                 ORDER BY (SELECT COUNT(DISTINCT pm2.creative_id) FROM ad_product_map pm2 WHERE pm2.product_id = p.id) DESC
                 LIMIT ? OFFSET ?",
                [$like, $like, $like, $like, $like, $like, $limit, $offset]
            );
            $results['apps'] = $appRows;
        } catch (Exception $e) {
            $results['apps'] = [];
        }
    }

    // ─────────────────────────────────────────────────
    // 4. Search YOUTUBE VIDEOS
    // ─────────────────────────────────────────────────
    if ($type === 'all' || $type === 'videos') {
        try {
            $countRow = $db->fetchOne(
                "SELECT COUNT(*) as cnt FROM youtube_metadata
                 WHERE title $C LIKE ? OR channel_name $C LIKE ? OR description $C LIKE ? OR video_id $C LIKE ?",
                [$like, $like, $like, $like]
            );
            $counts['videos'] = (int) ($countRow['cnt'] ?? 0);

            $limit  = ($type === 'videos') ? $perPage : 5;
            $offset = ($type === 'videos') ? ($page - 1) * $perPage : 0;

            $videoRows = $db->fetchAll(
                "SELECT ym.video_id, ym.title, ym.channel_name, ym.channel_id,
                        ym.view_count, ym.like_count, ym.comment_count, ym.duration,
                        ym.publish_date, ym.thumbnail_url, ym.description AS video_description,
                        (SELECT COUNT(DISTINCT a.creative_id)
                         FROM ad_assets ass INNER JOIN ads a ON ass.creative_id $C = a.creative_id $C
                         WHERE ass.type = 'video' AND ass.original_url LIKE CONCAT('%', ym.video_id, '%')) AS ad_count,
                        (SELECT COUNT(DISTINCT a.advertiser_id)
                         FROM ad_assets ass INNER JOIN ads a ON ass.creative_id $C = a.creative_id $C
                         WHERE ass.type = 'video' AND ass.original_url LIKE CONCAT('%', ym.video_id, '%')) AS advertiser_count
                 FROM youtube_metadata ym
                 WHERE ym.title $C LIKE ? OR ym.channel_name $C LIKE ? OR ym.description $C LIKE ? OR ym.video_id $C LIKE ?
                 ORDER BY ym.view_count DESC
                 LIMIT ? OFFSET ?",
                [$like, $like, $like, $like, $limit, $offset]
            );
            $results['videos'] = $videoRows;
        } catch (Exception $e) {
            $results['videos'] = [];
        }
    }

    // ─────────────────────────────────────────────────
    // 5. Suggestions
    // ─────────────────────────────────────────────────
    $suggestions = [];
    if ($type === 'all') {
        try {
            $sugAdv = $db->fetchAll(
                "SELECT DISTINCT COALESCE(ma.name, a.advertiser_id) AS label, 'advertiser' AS entity_type, a.advertiser_id AS entity_id
                 FROM ads a LEFT JOIN managed_advertisers ma ON a.advertiser_id $C = ma.advertiser_id $C
                 WHERE ma.name $C LIKE ? OR a.advertiser_id $C LIKE ?
                 ORDER BY (SELECT COUNT(*) FROM ads a2 WHERE a2.advertiser_id $C = a.advertiser_id $C) DESC LIMIT 3",
                [$like, $like]
            );
            $sugApp = $db->fetchAll(
                "SELECT DISTINCT p.product_name AS label, 'app' AS entity_type, p.id AS entity_id
                 FROM ad_products p WHERE p.product_name $C LIKE ? AND p.store_platform IN ('ios','playstore')
                 ORDER BY (SELECT COUNT(*) FROM ad_product_map pm WHERE pm.product_id = p.id) DESC LIMIT 3",
                [$like]
            );
            $sugVid = $db->fetchAll(
                "SELECT ym.title AS label, 'video' AS entity_type, ym.video_id AS entity_id
                 FROM youtube_metadata ym WHERE ym.title $C LIKE ? OR ym.channel_name $C LIKE ?
                 ORDER BY ym.view_count DESC LIMIT 3",
                [$like, $like]
            );
            $suggestions = array_merge($sugAdv, $sugApp, $sugVid);
        } catch (Exception $e) {
            $suggestions = [];
        }
    }

    // ─────────────────────────────────────────────────
    // 6. Analytics
    // ─────────────────────────────────────────────────
    $analytics = [];
    if ($type === 'all' && ($counts['ads'] > 0 || $counts['apps'] > 0)) {
        try {
            $analytics['top_countries'] = $db->fetchAll(
                "SELECT t.country, COUNT(DISTINCT t.creative_id) AS ad_count
                 FROM ad_targeting t
                 INNER JOIN ads a ON t.creative_id $C = a.creative_id $C
                 LEFT JOIN ad_details d ON a.creative_id $C = d.creative_id $C
                     AND d.id = (SELECT MAX(id) FROM ad_details WHERE creative_id $C = a.creative_id $C)
                 WHERE d.headline $C LIKE ? OR d.description $C LIKE ? OR a.creative_id $C LIKE ?
                 GROUP BY t.country ORDER BY ad_count DESC LIMIT 10",
                [$like, $like, $like]
            );
        } catch (Exception $e) { $analytics['top_countries'] = []; }

        try {
            $analytics['ad_types'] = $db->fetchAll(
                "SELECT a.ad_type, COUNT(*) AS count
                 FROM ads a
                 LEFT JOIN ad_details d ON a.creative_id $C = d.creative_id $C
                     AND d.id = (SELECT MAX(id) FROM ad_details WHERE creative_id $C = a.creative_id $C)
                 WHERE d.headline $C LIKE ? OR d.description $C LIKE ? OR a.creative_id $C LIKE ?
                 GROUP BY a.ad_type ORDER BY count DESC",
                [$like, $like, $like]
            );
        } catch (Exception $e) { $analytics['ad_types'] = []; }

        try {
            $analytics['timeline'] = $db->fetchAll(
                "SELECT DATE_FORMAT(a.first_seen, '%Y-%m') AS month, COUNT(*) AS count
                 FROM ads a
                 LEFT JOIN ad_details d ON a.creative_id $C = d.creative_id $C
                     AND d.id = (SELECT MAX(id) FROM ad_details WHERE creative_id $C = a.creative_id $C)
                 WHERE d.headline $C LIKE ? OR d.description $C LIKE ? OR a.creative_id $C LIKE ?
                 GROUP BY month ORDER BY month DESC LIMIT 12",
                [$like, $like, $like]
            );
        } catch (Exception $e) { $analytics['timeline'] = []; }
    }

    // Pagination meta
    $totalForType = $counts[$type] ?? 0;
    $totalPages   = ($type !== 'all' && $totalForType > 0) ? (int) ceil($totalForType / $perPage) : 1;

    echo json_encode([
        'success'     => true,
        'query'       => $q,
        'type'        => $type,
        'counts'      => $counts,
        'results'     => $results,
        'suggestions' => $suggestions,
        'analytics'   => $analytics,
        'page'        => $page,
        'per_page'    => $perPage,
        'total_pages' => $totalPages,
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
