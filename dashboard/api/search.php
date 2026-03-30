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

    // ─────────────────────────────────────────────────
    // 1. Search ADS (headline, description, CTA, landing URL, creative_id)
    // ─────────────────────────────────────────────────
    if ($type === 'all' || $type === 'ads') {
        // Count
        $countRow = $db->fetchOne(
            "SELECT COUNT(DISTINCT a.creative_id) as cnt
             FROM ads a
             LEFT JOIN ad_details d ON a.creative_id = d.creative_id
                 AND d.id = (SELECT MAX(id) FROM ad_details WHERE creative_id = a.creative_id)
             WHERE a.creative_id LIKE ?
                OR d.headline LIKE ?
                OR d.description LIKE ?
                OR d.cta LIKE ?
                OR d.landing_url LIKE ?",
            [$like, $like, $like, $like, $like]
        );
        $counts['ads'] = (int) ($countRow['cnt'] ?? 0);

        // Fetch results (paginated only when type=ads, else top 5)
        $limit  = ($type === 'ads') ? $perPage : 5;
        $offset = ($type === 'ads') ? ($page - 1) * $perPage : 0;

        $adRows = $db->fetchAll(
            "SELECT a.creative_id, a.advertiser_id, a.ad_type, a.first_seen, a.last_seen,
                    a.status, a.view_count,
                    d.headline, d.description AS ad_description, d.cta, d.landing_url,
                    COALESCE(ma.name, a.advertiser_id) AS advertiser_name,
                    (SELECT GROUP_CONCAT(DISTINCT t.country) FROM ad_targeting t WHERE t.creative_id = a.creative_id) AS countries,
                    (SELECT GROUP_CONCAT(DISTINCT p.product_name SEPARATOR '||')
                     FROM ad_product_map pm INNER JOIN ad_products p ON pm.product_id = p.id
                     WHERE pm.creative_id = a.creative_id AND p.store_platform IN ('ios','playstore')) AS product_names,
                    (SELECT pm2.product_id FROM ad_product_map pm2 INNER JOIN ad_products p2 ON pm2.product_id = p2.id
                     WHERE pm2.creative_id = a.creative_id AND p2.store_platform IN ('ios','playstore') LIMIT 1) AS product_id,
                    (SELECT original_url FROM ad_assets ass WHERE ass.creative_id = a.creative_id AND ass.type = 'image' AND ass.original_url NOT LIKE '%displayads-formats%' ORDER BY (ass.original_url LIKE '%ytimg.com%') DESC, ass.id DESC LIMIT 1) AS preview_image,
                    (SELECT original_url FROM ad_assets ass WHERE ass.creative_id = a.creative_id AND ass.type = 'video' AND ass.original_url LIKE '%youtube.com%' LIMIT 1) AS youtube_url,
                    (SELECT original_url FROM ad_assets ass WHERE ass.creative_id = a.creative_id AND ass.original_url LIKE '%displayads-formats%' LIMIT 1) AS preview_url
             FROM ads a
             LEFT JOIN ad_details d ON a.creative_id = d.creative_id
                 AND d.id = (SELECT MAX(id) FROM ad_details WHERE creative_id = a.creative_id)
             LEFT JOIN managed_advertisers ma ON a.advertiser_id = ma.advertiser_id
             WHERE a.creative_id LIKE ?
                OR d.headline LIKE ?
                OR d.description LIKE ?
                OR d.cta LIKE ?
                OR d.landing_url LIKE ?
             ORDER BY a.view_count DESC, a.last_seen DESC
             LIMIT ? OFFSET ?",
            [$like, $like, $like, $like, $like, $limit, $offset]
        );

        $results['ads'] = $adRows;
    }

    // ─────────────────────────────────────────────────
    // 2. Search ADVERTISERS (name, advertiser_id)
    // ─────────────────────────────────────────────────
    if ($type === 'all' || $type === 'advertisers') {
        $countRow = $db->fetchOne(
            "SELECT COUNT(DISTINCT adv_id) as cnt FROM (
                SELECT advertiser_id AS adv_id FROM managed_advertisers WHERE name LIKE ? OR advertiser_id LIKE ?
                UNION
                SELECT advertiser_id AS adv_id FROM ads WHERE advertiser_id LIKE ?
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
                    (SELECT COUNT(*) FROM ads a WHERE a.advertiser_id = sub.advertiser_id) AS total_ads,
                    (SELECT SUM(a.status = 'active') FROM ads a WHERE a.advertiser_id = sub.advertiser_id) AS active_ads,
                    (SELECT SUM(COALESCE(a.view_count, 0)) FROM ads a WHERE a.advertiser_id = sub.advertiser_id) AS total_views,
                    (SELECT COUNT(DISTINCT t.country) FROM ad_targeting t INNER JOIN ads a ON t.creative_id = a.creative_id WHERE a.advertiser_id = sub.advertiser_id) AS country_count,
                    (SELECT COUNT(DISTINCT p.id) FROM ad_products p WHERE p.advertiser_id = sub.advertiser_id AND p.store_platform IN ('ios','playstore')) AS app_count,
                    (SELECT GROUP_CONCAT(DISTINCT p.product_name SEPARATOR '||')
                     FROM ad_products p WHERE p.advertiser_id = sub.advertiser_id AND p.store_platform IN ('ios','playstore')
                     LIMIT 5) AS app_names
             FROM (
                SELECT DISTINCT advertiser_id FROM managed_advertisers WHERE name LIKE ? OR advertiser_id LIKE ?
                UNION
                SELECT DISTINCT advertiser_id FROM ads WHERE advertiser_id LIKE ?
             ) sub
             LEFT JOIN managed_advertisers ma ON sub.advertiser_id = ma.advertiser_id
             ORDER BY (SELECT SUM(COALESCE(a2.view_count, 0)) FROM ads a2 WHERE a2.advertiser_id = sub.advertiser_id) DESC
             LIMIT ? OFFSET ?",
            [$like, $like, $like, $limit, $offset]
        );

        $results['advertisers'] = $advRows;
    }

    // ─────────────────────────────────────────────────
    // 3. Search APPS (product_name, bundle_id, developer, category, description)
    // ─────────────────────────────────────────────────
    if ($type === 'all' || $type === 'apps') {
        $countRow = $db->fetchOne(
            "SELECT COUNT(DISTINCT p.id) as cnt
             FROM ad_products p
             LEFT JOIN app_metadata am ON am.product_id = p.id
             WHERE p.store_platform IN ('ios','playstore')
               AND (p.product_name LIKE ?
                    OR am.bundle_id LIKE ?
                    OR am.developer_name LIKE ?
                    OR am.category LIKE ?
                    OR am.app_name LIKE ?
                    OR am.description LIKE ?)",
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
                    (SELECT SUM(a.status = 'active') FROM ads a INNER JOIN ad_product_map pm ON pm.creative_id = a.creative_id WHERE pm.product_id = p.id) AS active_ads,
                    (SELECT SUM(COALESCE(a.view_count, 0)) FROM ads a INNER JOIN ad_product_map pm ON pm.creative_id = a.creative_id WHERE pm.product_id = p.id) AS total_views,
                    (SELECT COUNT(DISTINCT t.country) FROM ad_targeting t INNER JOIN ad_product_map pm ON pm.creative_id = t.creative_id WHERE pm.product_id = p.id) AS country_count
             FROM ad_products p
             LEFT JOIN app_metadata am ON am.product_id = p.id
             LEFT JOIN managed_advertisers ma ON p.advertiser_id = ma.advertiser_id
             WHERE p.store_platform IN ('ios','playstore')
               AND (p.product_name LIKE ?
                    OR am.bundle_id LIKE ?
                    OR am.developer_name LIKE ?
                    OR am.category LIKE ?
                    OR am.app_name LIKE ?
                    OR am.description LIKE ?)
             ORDER BY (SELECT COUNT(DISTINCT pm2.creative_id) FROM ad_product_map pm2 WHERE pm2.product_id = p.id) DESC
             LIMIT ? OFFSET ?",
            [$like, $like, $like, $like, $like, $like, $limit, $offset]
        );

        $results['apps'] = $appRows;
    }

    // ─────────────────────────────────────────────────
    // 4. Search YOUTUBE VIDEOS (title, channel, description)
    // ─────────────────────────────────────────────────
    if ($type === 'all' || $type === 'videos') {
        $countRow = $db->fetchOne(
            "SELECT COUNT(*) as cnt FROM youtube_metadata
             WHERE title LIKE ? OR channel_name LIKE ? OR description LIKE ? OR video_id LIKE ?",
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
                     FROM ad_assets ass INNER JOIN ads a ON ass.creative_id = a.creative_id
                     WHERE ass.type = 'video' AND ass.original_url LIKE CONCAT('%', ym.video_id, '%')) AS ad_count,
                    (SELECT COUNT(DISTINCT a.advertiser_id)
                     FROM ad_assets ass INNER JOIN ads a ON ass.creative_id = a.creative_id
                     WHERE ass.type = 'video' AND ass.original_url LIKE CONCAT('%', ym.video_id, '%')) AS advertiser_count
             FROM youtube_metadata ym
             WHERE ym.title LIKE ? OR ym.channel_name LIKE ? OR ym.description LIKE ? OR ym.video_id LIKE ?
             ORDER BY ym.view_count DESC
             LIMIT ? OFFSET ?",
            [$like, $like, $like, $like, $limit, $offset]
        );

        $results['videos'] = $videoRows;
    }

    // ─────────────────────────────────────────────────
    // 5. Suggestions: recent / popular searches from actual data
    // ─────────────────────────────────────────────────
    $suggestions = [];
    if ($type === 'all') {
        // Top advertisers matching
        $sugAdv = $db->fetchAll(
            "SELECT DISTINCT COALESCE(ma.name, a.advertiser_id) AS label, 'advertiser' AS entity_type, a.advertiser_id AS entity_id
             FROM ads a LEFT JOIN managed_advertisers ma ON a.advertiser_id = ma.advertiser_id
             WHERE ma.name LIKE ? OR a.advertiser_id LIKE ?
             ORDER BY (SELECT COUNT(*) FROM ads a2 WHERE a2.advertiser_id = a.advertiser_id) DESC LIMIT 3",
            [$like, $like]
        );
        // Top apps matching
        $sugApp = $db->fetchAll(
            "SELECT DISTINCT p.product_name AS label, 'app' AS entity_type, p.id AS entity_id
             FROM ad_products p WHERE p.product_name LIKE ? AND p.store_platform IN ('ios','playstore')
             ORDER BY (SELECT COUNT(*) FROM ad_product_map pm WHERE pm.product_id = p.id) DESC LIMIT 3",
            [$like]
        );
        // Top videos matching
        $sugVid = $db->fetchAll(
            "SELECT ym.title AS label, 'video' AS entity_type, ym.video_id AS entity_id
             FROM youtube_metadata ym WHERE ym.title LIKE ? OR ym.channel_name LIKE ?
             ORDER BY ym.view_count DESC LIMIT 3",
            [$like, $like]
        );
        $suggestions = array_merge($sugAdv, $sugApp, $sugVid);
    }

    // ─────────────────────────────────────────────────
    // 6. Analytics: aggregate insights for the query
    // ─────────────────────────────────────────────────
    $analytics = [];
    if ($type === 'all' && ($counts['ads'] > 0 || $counts['apps'] > 0)) {
        // Top countries for matching ads
        $analytics['top_countries'] = $db->fetchAll(
            "SELECT t.country, COUNT(DISTINCT t.creative_id) AS ad_count
             FROM ad_targeting t
             INNER JOIN ads a ON t.creative_id = a.creative_id
             LEFT JOIN ad_details d ON a.creative_id = d.creative_id
                 AND d.id = (SELECT MAX(id) FROM ad_details WHERE creative_id = a.creative_id)
             WHERE d.headline LIKE ? OR d.description LIKE ? OR a.creative_id LIKE ?
             GROUP BY t.country ORDER BY ad_count DESC LIMIT 10",
            [$like, $like, $like]
        );

        // Ad type distribution
        $analytics['ad_types'] = $db->fetchAll(
            "SELECT a.ad_type, COUNT(*) AS count
             FROM ads a
             LEFT JOIN ad_details d ON a.creative_id = d.creative_id
                 AND d.id = (SELECT MAX(id) FROM ad_details WHERE creative_id = a.creative_id)
             WHERE d.headline LIKE ? OR d.description LIKE ? OR a.creative_id LIKE ?
             GROUP BY a.ad_type ORDER BY count DESC",
            [$like, $like, $like]
        );

        // Timeline
        $analytics['timeline'] = $db->fetchAll(
            "SELECT DATE_FORMAT(a.first_seen, '%Y-%m') AS month, COUNT(*) AS count
             FROM ads a
             LEFT JOIN ad_details d ON a.creative_id = d.creative_id
                 AND d.id = (SELECT MAX(id) FROM ad_details WHERE creative_id = a.creative_id)
             WHERE d.headline LIKE ? OR d.description LIKE ? OR a.creative_id LIKE ?
             GROUP BY month ORDER BY month DESC LIMIT 12",
            [$like, $like, $like]
        );
    }

    // Pagination meta (only meaningful for single-type views)
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
