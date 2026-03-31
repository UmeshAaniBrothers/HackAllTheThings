<?php

/**
 * API: App/Product Profile
 * Returns full profile data for an app/product with deep cross-linking
 * to advertisers, YouTube videos, other apps from same advertiser, and ads.
 */

header('Content-Type: application/json');

$basePath = dirname(dirname(__DIR__));
$config = require $basePath . '/config/config.php';

require_once $basePath . '/src/Database.php';
require_once $basePath . '/src/MetadataFetcher.php';

try {
    $db = Database::getInstance($config['db']);

    $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = 20;

    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing required parameter: id']);
        exit;
    }

    // 1. Get product
    $product = $db->fetchOne("SELECT * FROM ad_products WHERE id = ?", [$id]);
    if (!$product) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Product not found']);
        exit;
    }

    // 2. Get/refresh metadata
    $fetcher = new MetadataFetcher($db);
    $metadata = null;
    if (!empty($product['store_url']) && $product['store_url'] !== 'not_found') {
        $metadata = $fetcher->getAppMetadata($id, $product['store_url'], $product['store_platform']);
    }
    if (!$metadata) {
        $metadata = $db->fetchOne("SELECT * FROM app_metadata WHERE product_id = ?", [$id]);
    }

    // 3. Get advertiser info
    $advertiser = $db->fetchOne(
        "SELECT * FROM managed_advertisers WHERE advertiser_id = ?",
        [$product['advertiser_id']]
    );

    // 4. Stats: total ads, active, inactive, video/image/text
    $adStats = $db->fetchOne(
        "SELECT COUNT(*) as total,
                SUM(a.status = 'active') as active,
                SUM(a.status = 'inactive') as inactive,
                SUM(a.ad_type = 'video') as video_count,
                SUM(a.ad_type = 'image') as image_count,
                SUM(a.ad_type = 'text') as text_count,
                MIN(a.first_seen) as earliest_seen,
                MAX(a.last_seen) as latest_seen,
                SUM(COALESCE(a.view_count, 0)) as total_views
         FROM ads a
         INNER JOIN ad_product_map pm ON pm.creative_id = a.creative_id
         WHERE pm.product_id = ?",
        [$id]
    );

    // 5. Paginated ads
    $totalAds = (int) ($adStats['total'] ?? 0);
    $offset = ($page - 1) * $perPage;
    $totalPages = max(1, (int) ceil($totalAds / $perPage));

    $ads = $db->fetchAll(
        "SELECT a.creative_id, a.advertiser_id, a.ad_type, a.first_seen, a.last_seen, a.status, a.view_count,
                d.headline, d.description, d.cta, d.headline_source,
                (SELECT original_url FROM ad_assets ass WHERE ass.creative_id = a.creative_id AND ass.type = 'video' AND ass.original_url LIKE '%youtube.com%' LIMIT 1) as youtube_url,
                (SELECT original_url FROM ad_assets ass WHERE ass.creative_id = a.creative_id AND ass.type = 'image' AND ass.original_url LIKE '%ytimg.com%' LIMIT 1) as preview_image,
                (SELECT original_url FROM ad_assets ass WHERE ass.creative_id = a.creative_id AND ass.original_url LIKE '%displayads-formats%' LIMIT 1) as preview_url,
                (SELECT GROUP_CONCAT(DISTINCT t.country) FROM ad_targeting t WHERE t.creative_id = a.creative_id) as countries
         FROM ads a
         INNER JOIN ad_product_map pm ON pm.creative_id = a.creative_id
         LEFT JOIN ad_details d ON a.creative_id = d.creative_id
             AND d.id = (SELECT MAX(id) FROM ad_details WHERE creative_id = a.creative_id)
         WHERE pm.product_id = ?
         ORDER BY a.last_seen DESC
         LIMIT ? OFFSET ?",
        [$id, $perPage, $offset]
    );

    // 6. YouTube videos with metadata
    $videoRows = $db->fetchAll(
        "SELECT DISTINCT ass.original_url
         FROM ad_assets ass
         INNER JOIN ad_product_map pm ON pm.creative_id = ass.creative_id
         WHERE pm.product_id = ? AND ass.type = 'video' AND ass.original_url LIKE '%youtube.com%'",
        [$id]
    );

    $videos = [];
    foreach ($videoRows as $row) {
        $videoId = null;
        if (preg_match('/[?&]v=([a-zA-Z0-9_-]{11})/', $row['original_url'], $m)) $videoId = $m[1];
        elseif (preg_match('/youtu\.be\/([a-zA-Z0-9_-]{11})/', $row['original_url'], $m)) $videoId = $m[1];
        elseif (preg_match('/\/embed\/([a-zA-Z0-9_-]{11})/', $row['original_url'], $m)) $videoId = $m[1];
        if ($videoId) {
            $ytMeta = $fetcher->getYouTubeMetadata($videoId);
            $videos[] = [
                'video_id'      => $videoId,
                'title'         => $ytMeta['title'] ?? null,
                'channel_name'  => $ytMeta['channel_name'] ?? null,
                'view_count'    => (int) ($ytMeta['view_count'] ?? 0),
                'like_count'    => (int) ($ytMeta['like_count'] ?? 0),
                'duration'      => $ytMeta['duration'] ?? null,
                'thumbnail_url' => $ytMeta['thumbnail_url'] ?? null,
            ];
        }
    }

    // 7. Countries
    $countryRows = $db->fetchAll(
        "SELECT DISTINCT t.country, COUNT(DISTINCT t.creative_id) as ad_count
         FROM ad_targeting t
         INNER JOIN ad_product_map pm ON pm.creative_id = t.creative_id
         WHERE pm.product_id = ?
         GROUP BY t.country
         ORDER BY ad_count DESC",
        [$id]
    );

    // 8. Activity timeline
    $timeline = $db->fetchAll(
        "SELECT DATE_FORMAT(a.first_seen, '%Y-%m') as month,
                COUNT(*) as count,
                SUM(a.ad_type = 'video') as videos,
                SUM(a.ad_type = 'image') as images,
                SUM(a.ad_type = 'text') as texts
         FROM ads a
         INNER JOIN ad_product_map pm ON pm.creative_id = a.creative_id
         WHERE pm.product_id = ?
         GROUP BY month ORDER BY month",
        [$id]
    );

    // 9. Other apps from same advertiser (cross-link)
    $relatedApps = $db->fetchAll(
        "SELECT p.id as product_id, p.product_name, p.store_platform, p.store_url,
                COUNT(pm.creative_id) as ad_count
         FROM ad_products p
         LEFT JOIN ad_product_map pm ON p.id = pm.product_id
         WHERE p.advertiser_id = ? AND p.id != ? AND p.store_platform IN ('ios', 'playstore')
         GROUP BY p.id ORDER BY ad_count DESC LIMIT 10",
        [$product['advertiser_id'], $id]
    );

    // 10. Other advertisers also promoting this same app (by store_url match)
    $otherAdvertisers = [];
    if (!empty($product['store_url']) && $product['store_url'] !== 'not_found') {
        $otherAdvertisers = $db->fetchAll(
            "SELECT DISTINCT p2.advertiser_id, COALESCE(ma.name, p2.advertiser_id) as name,
                    COUNT(DISTINCT pm.creative_id) as ad_count
             FROM ad_products p2
             INNER JOIN ad_product_map pm ON p2.id = pm.product_id
             LEFT JOIN managed_advertisers ma ON p2.advertiser_id = ma.advertiser_id
             WHERE p2.store_url = ? AND p2.advertiser_id != ?
             GROUP BY p2.advertiser_id
             ORDER BY ad_count DESC LIMIT 10",
            [$product['store_url'], $product['advertiser_id']]
        );
    }

    // 11. Ad type breakdown for this app
    $adTypeBreakdown = $db->fetchAll(
        "SELECT a.ad_type, COUNT(*) as count
         FROM ads a INNER JOIN ad_product_map pm ON pm.creative_id = a.creative_id
         WHERE pm.product_id = ? GROUP BY a.ad_type ORDER BY count DESC",
        [$id]
    );

    // 12. Top performing ads (by view count)
    $topAds = $db->fetchAll(
        "SELECT a.creative_id, a.view_count, a.ad_type, d.headline, d.headline_source,
                (SELECT original_url FROM ad_assets ass WHERE ass.creative_id = a.creative_id AND ass.type = 'video' AND ass.original_url LIKE '%youtube.com%' LIMIT 1) as youtube_url
         FROM ads a
         INNER JOIN ad_product_map pm ON pm.creative_id = a.creative_id
         LEFT JOIN ad_details d ON a.creative_id = d.creative_id
             AND d.id = (SELECT MAX(id) FROM ad_details WHERE creative_id = a.creative_id)
         WHERE pm.product_id = ? AND a.view_count > 0
         ORDER BY a.view_count DESC LIMIT 5",
        [$id]
    );

    echo json_encode([
        'success'          => true,
        'product'          => $product,
        'metadata'         => $metadata ?: null,
        'advertiser'       => $advertiser ?: null,
        'ad_stats'         => $adStats,
        'total_ads'        => $totalAds,
        'ads'              => $ads,
        'videos'           => $videos,
        'countries'        => $countryRows,
        'timeline'         => $timeline,
        'related_apps'     => $relatedApps,
        'other_advertisers' => $otherAdvertisers,
        'ad_type_breakdown' => $adTypeBreakdown,
        'top_ads'          => $topAds,
        'page'             => $page,
        'total_pages'      => $totalPages,
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
