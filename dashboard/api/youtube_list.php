<?php
/**
 * Returns list of YouTube videos that need metadata fetching.
 * Used by cli/youtube.js (Puppeteer scraper on Mac).
 *
 * GET /dashboard/api/youtube_list.php?token=...&action=youtube_pending
 * GET /dashboard/api/youtube_list.php?token=...&action=youtube_pending&include_stale=1
 */
header('Content-Type: application/json');

$basePath = dirname(dirname(__DIR__));
$config = require $basePath . '/config/config.php';

$authToken = $config['ingest_token'] ?? 'ads-intelligent-2024';
if (($_GET['token'] ?? '') !== $authToken) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid token']);
    exit;
}

require_once $basePath . '/src/Database.php';

try {
    $db = Database::getInstance($config['db']);
    $includeStale = isset($_GET['include_stale']);
    $videos = [];

    // Part 1: Videos that have never been fetched (view_count IS NULL)
    $newVideos = $db->fetchAll(
        "SELECT a.creative_id, ass.original_url as youtube_url
         FROM ads a
         INNER JOIN ad_assets ass ON ass.creative_id = a.creative_id
            AND ass.type = 'video' AND ass.original_url LIKE '%youtube.com%'
         WHERE a.ad_type = 'video' AND a.view_count IS NULL
         GROUP BY a.creative_id
         ORDER BY a.last_seen DESC
         LIMIT 500"
    );

    foreach ($newVideos as $row) {
        $videoId = extractVideoId($row['youtube_url']);
        if ($videoId) {
            $videos[] = [
                'creative_id' => $row['creative_id'],
                'video_id'    => $videoId,
                'type'        => 'new',
            ];
        }
    }

    // Part 2: Stale videos (fetched_at > 15 days ago) — only if requested
    if ($includeStale) {
        $staleVideos = $db->fetchAll(
            "SELECT a.creative_id, ass.original_url as youtube_url
             FROM ads a
             INNER JOIN ad_assets ass ON ass.creative_id = a.creative_id
                AND ass.type = 'video' AND ass.original_url LIKE '%youtube.com%'
             INNER JOIN youtube_metadata ym ON ym.video_id = SUBSTRING_INDEX(SUBSTRING_INDEX(ass.original_url, 'v=', -1), '&', 1)
             WHERE a.ad_type = 'video'
                AND a.view_count > 0
                AND ym.fetched_at < DATE_SUB(NOW(), INTERVAL 1 DAY)
             GROUP BY a.creative_id
             ORDER BY ym.fetched_at ASC
             LIMIT 200"
        );

        // Deduplicate
        $existingIds = array_column($videos, 'creative_id');
        foreach ($staleVideos as $row) {
            if (in_array($row['creative_id'], $existingIds)) continue;
            $videoId = extractVideoId($row['youtube_url']);
            if ($videoId) {
                $videos[] = [
                    'creative_id' => $row['creative_id'],
                    'video_id'    => $videoId,
                    'type'        => 'refresh',
                ];
            }
        }
    }

    echo json_encode([
        'success' => true,
        'count'   => count($videos),
        'videos'  => $videos,
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

function extractVideoId($url) {
    if (preg_match('/[?&]v=([a-zA-Z0-9_-]{11})/', $url, $m)) {
        return $m[1];
    }
    return null;
}
