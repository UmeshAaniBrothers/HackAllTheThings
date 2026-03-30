<?php

/**
 * API: YouTube Video Profile
 * Returns full profile data for a YouTube video including metadata,
 * associated ads, products/apps, advertisers, and targeting countries.
 *
 * Parameter: id (11-char YouTube video ID)
 */

header('Content-Type: application/json');

$basePath = dirname(dirname(__DIR__));
$config = require $basePath . '/config/config.php';

require_once $basePath . '/src/Database.php';
require_once $basePath . '/src/MetadataFetcher.php';

try {
    $db = Database::getInstance($config['db']);

    // Validate video ID parameter
    $videoId = isset($_GET['id']) ? trim($_GET['id']) : null;
    if (!$videoId || !preg_match('/^[a-zA-Z0-9_-]{11}$/', $videoId)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid or missing video ID. Must be an 11-character YouTube video ID.']);
        exit;
    }

    // 1. Get/refresh YouTube metadata via MetadataFetcher
    $fetcher = new MetadataFetcher($db);
    $metadata = $fetcher->getYouTubeMetadata($videoId);

    $video = [
        'video_id'      => $videoId,
        'title'         => $metadata['title'] ?? null,
        'channel_name'  => $metadata['channel_name'] ?? null,
        'view_count'    => (int) ($metadata['view_count'] ?? 0),
        'like_count'    => (int) ($metadata['like_count'] ?? 0),
        'publish_date'  => $metadata['publish_date'] ?? null,
        'duration'      => $metadata['duration'] ?? null,
        'description'   => $metadata['description'] ?? null,
        'thumbnail_url' => $metadata['thumbnail_url'] ?? null,
        'channel_url'   => $metadata['channel_url'] ?? null,
    ];

    // 2. Find all ads using this video
    $ads = $db->fetchAll(
        "SELECT a.creative_id, a.advertiser_id, a.ad_type, a.first_seen, a.last_seen, a.status, a.view_count,
                d.headline, d.description
         FROM ads a
         INNER JOIN ad_assets ass ON a.creative_id = ass.creative_id
         LEFT JOIN ad_details d ON a.creative_id = d.creative_id
             AND d.id = (SELECT MAX(id) FROM ad_details WHERE creative_id = a.creative_id)
         WHERE ass.type = 'video' AND ass.original_url LIKE CONCAT('%', ?, '%')
         GROUP BY a.creative_id",
        [$videoId]
    );

    // Collect creative IDs for subsequent queries
    $creativeIds = array_column($ads, 'creative_id');

    // 3. Get product/app info for those ads
    $apps = [];
    if (!empty($creativeIds)) {
        $placeholders = implode(',', array_fill(0, count($creativeIds), '?'));
        $apps = $db->fetchAll(
            "SELECT DISTINCT p.*
             FROM ad_products p
             INNER JOIN ad_product_map pm ON p.id = pm.product_id
             WHERE pm.creative_id IN ({$placeholders})",
            $creativeIds
        );
    }

    // 4. Get advertiser info
    $advertisers = [];
    if (!empty($creativeIds)) {
        $placeholders = implode(',', array_fill(0, count($creativeIds), '?'));
        $advertisers = $db->fetchAll(
            "SELECT DISTINCT ma.*
             FROM managed_advertisers ma
             INNER JOIN ads a ON ma.advertiser_id = a.advertiser_id
             INNER JOIN ad_assets ass ON a.creative_id = ass.creative_id
             WHERE ass.type = 'video' AND ass.original_url LIKE CONCAT('%', ?, '%')
               AND a.creative_id IN ({$placeholders})",
            array_merge([$videoId], $creativeIds)
        );
    }

    // 5. Get countries from ad_targeting for those ads
    $countries = [];
    if (!empty($creativeIds)) {
        $placeholders = implode(',', array_fill(0, count($creativeIds), '?'));
        $rows = $db->fetchAll(
            "SELECT DISTINCT t.country
             FROM ad_targeting t
             WHERE t.creative_id IN ({$placeholders})
             ORDER BY t.country",
            $creativeIds
        );
        $countries = array_column($rows, 'country');
    }

    echo json_encode([
        'success'     => true,
        'video'       => $video,
        'ads'         => $ads,
        'apps'        => $apps,
        'advertisers' => $advertisers,
        'countries'   => $countries,
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
