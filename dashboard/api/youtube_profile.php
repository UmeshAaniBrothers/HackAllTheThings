<?php

/**
 * API: YouTube Video Profile
 * Returns full profile with deep cross-linking to apps, advertisers,
 * other videos from same advertiser, and target countries.
 */

header('Content-Type: application/json');

$basePath = dirname(dirname(__DIR__));
$config = require $basePath . '/config/config.php';

require_once $basePath . '/src/Database.php';
require_once $basePath . '/src/MetadataFetcher.php';

try {
    $db = Database::getInstance($config['db']);

    $videoId = isset($_GET['id']) ? trim($_GET['id']) : null;
    if (!$videoId || !preg_match('/^[a-zA-Z0-9_-]{11}$/', $videoId)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid or missing video ID.']);
        exit;
    }

    // 1. Get/refresh YouTube metadata
    $fetcher = new MetadataFetcher($db);
    $metadata = $fetcher->getYouTubeMetadata($videoId);

    $video = [
        'video_id'      => $videoId,
        'title'         => $metadata['title'] ?? null,
        'channel_name'  => $metadata['channel_name'] ?? null,
        'channel_id'    => $metadata['channel_id'] ?? null,
        'channel_url'   => $metadata['channel_url'] ?? null,
        'view_count'    => (int) ($metadata['view_count'] ?? 0),
        'like_count'    => (int) ($metadata['like_count'] ?? 0),
        'comment_count' => (int) ($metadata['comment_count'] ?? 0),
        'publish_date'  => $metadata['publish_date'] ?? null,
        'duration'      => $metadata['duration'] ?? null,
        'description'   => $metadata['description'] ?? null,
        'thumbnail_url' => $metadata['thumbnail_url'] ?? null,
    ];

    // 2. Find all ads using this video
    $ads = $db->fetchAll(
        "SELECT a.creative_id, a.advertiser_id, a.ad_type, a.first_seen, a.last_seen, a.status, a.view_count,
                d.headline, d.description as ad_description, d.cta,
                COALESCE(adv.name, a.advertiser_id) as advertiser_name,
                (SELECT GROUP_CONCAT(DISTINCT t.country) FROM ad_targeting t WHERE t.creative_id = a.creative_id) as countries,
                (SELECT GROUP_CONCAT(DISTINCT p.product_name SEPARATOR '||') FROM ad_product_map pm INNER JOIN ad_products p ON pm.product_id = p.id WHERE pm.creative_id = a.creative_id AND p.store_platform IN ('ios', 'playstore')) as product_names,
                (SELECT pm2.product_id FROM ad_product_map pm2 INNER JOIN ad_products p2 ON pm2.product_id = p2.id WHERE pm2.creative_id = a.creative_id AND p2.store_platform IN ('ios', 'playstore') LIMIT 1) as product_id
         FROM ads a
         INNER JOIN ad_assets ass ON a.creative_id = ass.creative_id
         LEFT JOIN ad_details d ON a.creative_id = d.creative_id
             AND d.id = (SELECT MAX(id) FROM ad_details WHERE creative_id = a.creative_id)
         LEFT JOIN managed_advertisers adv ON a.advertiser_id = adv.advertiser_id
         WHERE ass.type = 'video' AND ass.original_url LIKE CONCAT('%', ?, '%')
         GROUP BY a.creative_id
         ORDER BY a.view_count DESC",
        [$videoId]
    );

    $creativeIds = array_column($ads, 'creative_id');

    // 3. Apps promoted via this video
    $apps = [];
    if (!empty($creativeIds)) {
        $ph = implode(',', array_fill(0, count($creativeIds), '?'));
        $apps = $db->fetchAll(
            "SELECT DISTINCT p.id as product_id, p.product_name, p.store_platform, p.store_url,
                    (SELECT am.icon_url FROM app_metadata am WHERE am.product_id = p.id LIMIT 1) as icon_url,
                    (SELECT am.rating FROM app_metadata am WHERE am.product_id = p.id LIMIT 1) as rating,
                    COUNT(DISTINCT pm.creative_id) as ad_count
             FROM ad_products p
             INNER JOIN ad_product_map pm ON p.id = pm.product_id
             WHERE pm.creative_id IN ({$ph}) AND p.store_platform IN ('ios', 'playstore')
             GROUP BY p.id ORDER BY ad_count DESC",
            $creativeIds
        );
    }

    // 4. Advertisers using this video
    $advertisers = [];
    if (!empty($creativeIds)) {
        $ph = implode(',', array_fill(0, count($creativeIds), '?'));
        $advertisers = $db->fetchAll(
            "SELECT DISTINCT a.advertiser_id, COALESCE(ma.name, a.advertiser_id) as name,
                    COUNT(DISTINCT a.creative_id) as ad_count,
                    SUM(a.status = 'active') as active_count
             FROM ads a
             LEFT JOIN managed_advertisers ma ON a.advertiser_id = ma.advertiser_id
             WHERE a.creative_id IN ({$ph})
             GROUP BY a.advertiser_id ORDER BY ad_count DESC",
            $creativeIds
        );
    }

    // 5. Countries
    $countries = [];
    if (!empty($creativeIds)) {
        $ph = implode(',', array_fill(0, count($creativeIds), '?'));
        $countries = $db->fetchAll(
            "SELECT t.country, COUNT(DISTINCT t.creative_id) as ad_count
             FROM ad_targeting t WHERE t.creative_id IN ({$ph})
             GROUP BY t.country ORDER BY ad_count DESC",
            $creativeIds
        );
    }

    // 6. Other videos from same advertisers (cross-link)
    $relatedVideos = [];
    $advertiserIds = array_column($advertisers, 'advertiser_id');
    if (!empty($advertiserIds)) {
        $ph = implode(',', array_fill(0, count($advertiserIds), '?'));
        $relatedRows = $db->fetchAll(
            "SELECT DISTINCT ass.original_url, a.view_count
             FROM ad_assets ass
             INNER JOIN ads a ON ass.creative_id = a.creative_id
             WHERE ass.type = 'video' AND ass.original_url LIKE '%youtube.com%'
               AND a.advertiser_id IN ({$ph})
               AND ass.original_url NOT LIKE ?
             ORDER BY a.view_count DESC LIMIT 10",
            array_merge($advertiserIds, ['%' . $videoId . '%'])
        );

        $seenIds = [];
        foreach ($relatedRows as $row) {
            $relId = null;
            if (preg_match('/[?&]v=([a-zA-Z0-9_-]{11})/', $row['original_url'], $m)) $relId = $m[1];
            elseif (preg_match('/youtu\.be\/([a-zA-Z0-9_-]{11})/', $row['original_url'], $m)) $relId = $m[1];
            elseif (preg_match('/\/embed\/([a-zA-Z0-9_-]{11})/', $row['original_url'], $m)) $relId = $m[1];
            if ($relId && !isset($seenIds[$relId])) {
                $seenIds[$relId] = true;
                $relMeta = $db->fetchOne("SELECT title, view_count, duration FROM youtube_metadata WHERE video_id = ?", [$relId]);
                $relatedVideos[] = [
                    'video_id'   => $relId,
                    'title'      => $relMeta['title'] ?? null,
                    'view_count' => (int) ($relMeta['view_count'] ?? $row['view_count'] ?? 0),
                    'duration'   => $relMeta['duration'] ?? null,
                ];
                if (count($relatedVideos) >= 6) break;
            }
        }
    }

    // 7. Ad timeline for this video
    $timeline = [];
    if (!empty($creativeIds)) {
        $ph = implode(',', array_fill(0, count($creativeIds), '?'));
        $timeline = $db->fetchAll(
            "SELECT DATE_FORMAT(first_seen, '%Y-%m') as month, COUNT(*) as count
             FROM ads WHERE creative_id IN ({$ph})
             GROUP BY month ORDER BY month",
            $creativeIds
        );
    }

    echo json_encode([
        'success'        => true,
        'video'          => $video,
        'ads'            => $ads,
        'apps'           => $apps,
        'advertisers'    => $advertisers,
        'countries'      => $countries,
        'related_videos' => $relatedVideos,
        'timeline'       => $timeline,
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
