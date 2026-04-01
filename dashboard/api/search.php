<?php

/**
 * API: Unified Search
 * Searches across all entities: ads, advertisers, apps, YouTube videos.
 * Returns categorised results with cross-linked data and analytics.
 *
 * Performance: Queries are simplified and bounded to avoid timeouts on 180k+ ads.
 */

header('Content-Type: application/json');

$basePath = dirname(dirname(__DIR__));
$config = require $basePath . '/config/config.php';
require_once $basePath . '/src/Database.php';

try {
    $db = Database::getInstance($config['db']);

    $q       = isset($_GET['q']) ? trim($_GET['q']) : '';
    $type    = isset($_GET['type']) ? $_GET['type'] : 'all';
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

    // ─────────────────────────────────────────────────
    // 1. Search ADS — simplified: search headline + creative_id only for speed
    // ─────────────────────────────────────────────────
    if ($type === 'all' || $type === 'ads') {
        try {
            // Fast count: only search indexed/fast columns
            $countRow = $db->fetchOne(
                "SELECT COUNT(*) as cnt FROM (
                    SELECT a.creative_id FROM ads a
                    INNER JOIN ad_details d ON a.creative_id = d.creative_id
                        AND d.id = (SELECT MAX(id) FROM ad_details d2 WHERE d2.creative_id = a.creative_id)
                    WHERE d.headline LIKE ? OR d.landing_url LIKE ? OR a.creative_id LIKE ?
                    LIMIT 10000
                ) bounded",
                [$like, $like, $like]
            );
            $counts['ads'] = (int) ($countRow['cnt'] ?? 0);

            $limit  = ($type === 'ads') ? $perPage : 5;
            $offset = ($type === 'ads') ? ($page - 1) * $perPage : 0;

            $adRows = $db->fetchAll(
                "SELECT a.creative_id, a.advertiser_id, a.ad_type, a.first_seen, a.last_seen,
                        a.status, a.view_count,
                        d.headline, d.description AS ad_description, d.cta, d.landing_url, d.headline_source,
                        COALESCE(ma.name, a.advertiser_id) AS advertiser_name,
                        (SELECT original_url FROM ad_assets ass WHERE ass.creative_id = a.creative_id AND ass.type = 'image' ORDER BY ass.id DESC LIMIT 1) AS preview_image,
                        (SELECT original_url FROM ad_assets ass WHERE ass.creative_id = a.creative_id AND ass.type = 'video' AND ass.original_url LIKE '%youtube.com%' LIMIT 1) AS youtube_url
                 FROM ads a
                 INNER JOIN ad_details d ON a.creative_id = d.creative_id
                     AND d.id = (SELECT MAX(id) FROM ad_details d2 WHERE d2.creative_id = a.creative_id)
                 LEFT JOIN managed_advertisers ma ON a.advertiser_id = ma.advertiser_id
                 WHERE d.headline LIKE ? OR d.landing_url LIKE ? OR a.creative_id LIKE ?
                 ORDER BY a.view_count DESC, a.last_seen DESC
                 LIMIT ? OFFSET ?",
                [$like, $like, $like, $limit, $offset]
            );
            $results['ads'] = $adRows;
        } catch (Exception $e) {
            $results['ads'] = [];
            $counts['ads'] = 0;
        }
    }

    // ─────────────────────────────────────────────────
    // 2. Search ADVERTISERS — simplified subqueries
    // ─────────────────────────────────────────────────
    if ($type === 'all' || $type === 'advertisers') {
        try {
            // Search managed_advertisers first (small table, fast)
            $advRows = $db->fetchAll(
                "SELECT ma.advertiser_id,
                        ma.name,
                        ma.status AS mgmt_status,
                        ma.last_fetched_at,
                        (SELECT COUNT(*) FROM ads a WHERE a.advertiser_id = ma.advertiser_id) AS total_ads,
                        (SELECT SUM(a.status = 'active') FROM ads a WHERE a.advertiser_id = ma.advertiser_id) AS active_ads,
                        (SELECT COUNT(DISTINCT p.id) FROM ad_products p WHERE p.advertiser_id = ma.advertiser_id AND p.store_platform IN ('ios','playstore')) AS app_count
                 FROM managed_advertisers ma
                 WHERE ma.name LIKE ? OR ma.advertiser_id LIKE ?
                 ORDER BY (SELECT COUNT(*) FROM ads a2 WHERE a2.advertiser_id = ma.advertiser_id) DESC
                 LIMIT ?",
                [$like, $like, ($type === 'advertisers') ? $perPage : 5]
            );
            $counts['advertisers'] = count($advRows);
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
            $limit  = ($type === 'apps') ? $perPage : 5;
            $offset = ($type === 'apps') ? ($page - 1) * $perPage : 0;

            $appRows = $db->fetchAll(
                "SELECT p.id AS product_id, p.product_name, p.store_platform, p.store_url,
                        p.advertiser_id,
                        COALESCE(ma.name, p.advertiser_id) AS advertiser_name,
                        am.icon_url, am.rating, am.rating_count, am.developer_name, am.category,
                        am.downloads, am.price,
                        (SELECT COUNT(DISTINCT pm.creative_id) FROM ad_product_map pm WHERE pm.product_id = p.id) AS ad_count
                 FROM ad_products p
                 LEFT JOIN app_metadata am ON am.product_id = p.id
                 LEFT JOIN managed_advertisers ma ON p.advertiser_id = ma.advertiser_id
                 WHERE p.store_platform IN ('ios','playstore')
                   AND (p.product_name LIKE ?
                        OR am.developer_name LIKE ?
                        OR am.app_name LIKE ?
                        OR am.category LIKE ?)
                 ORDER BY (SELECT COUNT(*) FROM ad_product_map pm2 WHERE pm2.product_id = p.id) DESC
                 LIMIT ? OFFSET ?",
                [$like, $like, $like, $like, $limit, $offset]
            );

            // Get count separately (fast)
            $countRow = $db->fetchOne(
                "SELECT COUNT(DISTINCT p.id) as cnt
                 FROM ad_products p
                 LEFT JOIN app_metadata am ON am.product_id = p.id
                 WHERE p.store_platform IN ('ios','playstore')
                   AND (p.product_name LIKE ?
                        OR am.developer_name LIKE ?
                        OR am.app_name LIKE ?
                        OR am.category LIKE ?)",
                [$like, $like, $like, $like]
            );
            $counts['apps'] = (int) ($countRow['cnt'] ?? 0);
            $results['apps'] = $appRows;
        } catch (Exception $e) {
            $results['apps'] = [];
        }
    }

    // ─────────────────────────────────────────────────
    // 4. Search YOUTUBE VIDEOS — simplified
    // ─────────────────────────────────────────────────
    if ($type === 'all' || $type === 'videos') {
        try {
            $limit  = ($type === 'videos') ? $perPage : 5;
            $offset = ($type === 'videos') ? ($page - 1) * $perPage : 0;

            $videoRows = $db->fetchAll(
                "SELECT ym.video_id, ym.title, ym.channel_name, ym.channel_id,
                        ym.view_count, ym.like_count, ym.comment_count, ym.duration,
                        ym.publish_date, ym.thumbnail_url
                 FROM youtube_metadata ym
                 WHERE ym.title LIKE ? OR ym.channel_name LIKE ? OR ym.video_id LIKE ?
                 ORDER BY ym.view_count DESC
                 LIMIT ? OFFSET ?",
                [$like, $like, $like, $limit, $offset]
            );

            $countRow = $db->fetchOne(
                "SELECT COUNT(*) as cnt FROM youtube_metadata
                 WHERE title LIKE ? OR channel_name LIKE ? OR video_id LIKE ?",
                [$like, $like, $like]
            );
            $counts['videos'] = (int) ($countRow['cnt'] ?? 0);
            $results['videos'] = $videoRows;
        } catch (Exception $e) {
            $results['videos'] = [];
        }
    }

    // ─────────────────────────────────────────────────
    // 5. Suggestions (lightweight)
    // ─────────────────────────────────────────────────
    $suggestions = [];
    if ($type === 'all') {
        try {
            $sugAdv = $db->fetchAll(
                "SELECT ma.name AS label, 'advertiser' AS entity_type, ma.advertiser_id AS entity_id
                 FROM managed_advertisers ma
                 WHERE ma.name LIKE ? OR ma.advertiser_id LIKE ?
                 LIMIT 3",
                [$like, $like]
            );
            $sugApp = $db->fetchAll(
                "SELECT p.product_name AS label, 'app' AS entity_type, p.id AS entity_id
                 FROM ad_products p WHERE p.product_name LIKE ? AND p.store_platform IN ('ios','playstore')
                 LIMIT 3",
                [$like]
            );
            $sugVid = $db->fetchAll(
                "SELECT ym.title AS label, 'video' AS entity_type, ym.video_id AS entity_id
                 FROM youtube_metadata ym WHERE ym.title LIKE ? OR ym.channel_name LIKE ?
                 ORDER BY ym.view_count DESC LIMIT 3",
                [$like, $like]
            );
            $suggestions = array_merge($sugAdv, $sugApp, $sugVid);
        } catch (Exception $e) {
            $suggestions = [];
        }
    }

    // ─────────────────────────────────────────────────
    // 6. Analytics — skip for type=all to avoid timeout, only for single-type deep dives
    // ─────────────────────────────────────────────────
    $analytics = [];
    if ($type === 'ads' && $counts['ads'] > 0) {
        try {
            $analytics['ad_types'] = $db->fetchAll(
                "SELECT a.ad_type, COUNT(*) AS count
                 FROM ads a
                 INNER JOIN ad_details d ON a.creative_id = d.creative_id
                     AND d.id = (SELECT MAX(id) FROM ad_details d2 WHERE d2.creative_id = a.creative_id)
                 WHERE d.headline LIKE ? OR a.creative_id LIKE ?
                 GROUP BY a.ad_type ORDER BY count DESC",
                [$like, $like]
            );
        } catch (Exception $e) { $analytics['ad_types'] = []; }
    }

    // Pagination meta
    $totalForType = $counts[$type] ?? 0;
    $totalPages   = ($type !== 'all' && $totalForType > 0) ? (int) ceil($totalForType / $perPage) : 1;

    echo json_encode([
        'success'     => true,
        '_v'          => 4,
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
