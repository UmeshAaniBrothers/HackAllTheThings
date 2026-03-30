<?php

/**
 * API: App/Product Profile
 * Returns full profile data for an app/product by id.
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

    // 1. Get product from ad_products
    $product = $db->fetchOne("SELECT * FROM ad_products WHERE id = ?", [$id]);
    if (!$product) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Product not found']);
        exit;
    }

    // 2. Get/refresh metadata from app_metadata via MetadataFetcher
    $metadata = null;
    if (!empty($product['store_url']) && $product['store_url'] !== 'not_found') {
        $fetcher = new MetadataFetcher($db);
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

    // 4. Count total ads for this product
    $totalAds = (int) $db->fetchColumn(
        "SELECT COUNT(*) FROM ad_product_map WHERE product_id = ?",
        [$id]
    );

    // 5. Get paginated ads for this product
    $offset = ($page - 1) * $perPage;
    $totalPages = max(1, (int) ceil($totalAds / $perPage));

    $ads = $db->fetchAll(
        "SELECT a.creative_id, a.ad_type, a.first_seen, a.last_seen, a.status, a.view_count,
                d.headline,
                (SELECT original_url FROM ad_assets ass WHERE ass.creative_id = a.creative_id AND ass.type = 'video' AND ass.original_url LIKE '%youtube.com%' LIMIT 1) as youtube_url,
                (SELECT original_url FROM ad_assets ass WHERE ass.creative_id = a.creative_id AND ass.type = 'image' AND ass.original_url LIKE '%ytimg.com%' LIMIT 1) as preview_image
         FROM ads a
         INNER JOIN ad_product_map pm ON pm.creative_id = a.creative_id
         LEFT JOIN ad_details d ON a.creative_id = d.creative_id
             AND d.id = (SELECT MAX(id) FROM ad_details WHERE creative_id = a.creative_id)
         WHERE pm.product_id = ?
         ORDER BY a.last_seen DESC
         LIMIT ? OFFSET ?",
        [$id, $perPage, $offset]
    );

    // 6. Get all YouTube videos for this product's ads
    $videoRows = $db->fetchAll(
        "SELECT DISTINCT ass.original_url
         FROM ad_assets ass
         INNER JOIN ad_product_map pm ON pm.creative_id = ass.creative_id
         WHERE pm.product_id = ?
           AND ass.type = 'video'
           AND ass.original_url LIKE '%youtube.com%'",
        [$id]
    );

    $videos = [];
    $fetcher = isset($fetcher) ? $fetcher : new MetadataFetcher($db);
    foreach ($videoRows as $row) {
        // Extract video_id from YouTube URL
        $videoId = null;
        if (preg_match('/[?&]v=([a-zA-Z0-9_-]+)/', $row['original_url'], $m)) {
            $videoId = $m[1];
        } elseif (preg_match('/youtu\.be\/([a-zA-Z0-9_-]+)/', $row['original_url'], $m)) {
            $videoId = $m[1];
        } elseif (preg_match('/\/embed\/([a-zA-Z0-9_-]+)/', $row['original_url'], $m)) {
            $videoId = $m[1];
        }
        if ($videoId) {
            $ytMeta = $fetcher->getYouTubeMetadata($videoId);
            $videos[] = [
                'video_id'   => $videoId,
                'title'      => $ytMeta['title'] ?? null,
                'view_count' => $ytMeta['view_count'] ?? 0,
            ];
        }
    }

    // 7. Get countries from ad_targeting for this product's ads
    $countryRows = $db->fetchAll(
        "SELECT DISTINCT t.country
         FROM ad_targeting t
         INNER JOIN ad_product_map pm ON pm.creative_id = t.creative_id
         WHERE pm.product_id = ?
         ORDER BY t.country",
        [$id]
    );
    $countries = array_column($countryRows, 'country');

    // 8. Get activity timeline
    $timeline = $db->fetchAll(
        "SELECT DATE_FORMAT(a.first_seen, '%Y-%m') as month, COUNT(*) as count
         FROM ads a
         INNER JOIN ad_product_map pm ON pm.creative_id = a.creative_id
         WHERE pm.product_id = ?
         GROUP BY month
         ORDER BY month",
        [$id]
    );

    echo json_encode([
        'success'     => true,
        'product'     => $product,
        'metadata'    => $metadata ?: null,
        'advertiser'  => $advertiser ?: null,
        'total_ads'   => $totalAds,
        'ads'         => $ads,
        'videos'      => $videos,
        'countries'   => $countries,
        'timeline'    => $timeline,
        'page'        => $page,
        'total_pages' => $totalPages,
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
