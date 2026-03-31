<?php
/**
 * Receive YouTube metadata from Puppeteer scraper (Mac).
 * Accepts batch of video results and updates DB.
 *
 * POST /dashboard/api/youtube_update.php?token=...
 * Body: { "videos": [ { "creative_id", "video_id", "title", "author", "view_count", "thumbnail" }, ... ] }
 */
header('Content-Type: application/json');
set_time_limit(120);

$basePath = dirname(dirname(__DIR__));
$config = require $basePath . '/config/config.php';

$authToken = $config['ingest_token'] ?? 'ads-intelligent-2024';
if (($_GET['token'] ?? '') !== $authToken) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid token']);
    exit;
}

require_once $basePath . '/src/Database.php';

$input = json_decode(file_get_contents('php://input'), true);
$videos = $input['videos'] ?? [];

if (empty($videos)) {
    echo json_encode(['success' => false, 'error' => 'No videos provided']);
    exit;
}

try {
    $db = Database::getInstance($config['db']);

    $updated = 0;
    $failed = 0;

    foreach ($videos as $video) {
        $creativeId = $video['creative_id'] ?? null;
        $videoId = $video['video_id'] ?? null;
        $title = $video['title'] ?? null;
        $author = $video['author'] ?? null;
        $viewCount = $video['view_count'] ?? null;
        $thumbnail = $video['thumbnail'] ?? null;
        $status = $video['status'] ?? 'ok'; // 'ok', 'failed', 'private'

        if (!$creativeId || !$videoId) {
            $failed++;
            continue;
        }

        if ($status === 'failed') {
            // Mark as checked so we don't retry endlessly
            $db->update('ads', ['view_count' => 1], 'creative_id = ?', [$creativeId]);
            $failed++;
            continue;
        }

        // Update ads.view_count
        $db->update('ads', ['view_count' => $viewCount ?? 0], 'creative_id = ?', [$creativeId]);

        // Build description
        $description = '';
        if ($viewCount !== null && $viewCount > 0) {
            $description = number_format($viewCount) . ' views';
        }
        if ($author) {
            $description .= ($description ? ' | ' : '') . 'by ' . $author;
        }

        // Update ad_details
        $existingDetail = $db->fetchOne(
            "SELECT id, headline, headline_source FROM ad_details WHERE creative_id = ? ORDER BY id DESC LIMIT 1",
            [$creativeId]
        );

        if ($existingDetail) {
            $updateData = ['description' => $description ?: null];
            $hasRealHeadline = !empty($existingDetail['headline']) && $existingDetail['headline_source'] === 'ad';
            if (!$hasRealHeadline && $title) {
                $updateData['headline'] = $title;
                $updateData['headline_source'] = 'youtube';
            }
            $db->update('ad_details', $updateData, 'id = ?', [$existingDetail['id']]);
        } else if ($title || $description) {
            $db->insert('ad_details', [
                'creative_id'     => $creativeId,
                'headline'        => $title,
                'description'     => $description ?: null,
                'headline_source' => $title ? 'youtube' : null,
            ]);
        }

        // Save/update youtube_metadata
        $existing = $db->fetchOne("SELECT id FROM youtube_metadata WHERE video_id = ?", [$videoId]);
        $metaData = [
            'video_id'      => $videoId,
            'title'         => $title,
            'channel_name'  => $author,
            'view_count'    => $viewCount ?? 0,
            'thumbnail_url' => $thumbnail ?: 'https://i.ytimg.com/vi/' . $videoId . '/hqdefault.jpg',
            'fetched_at'    => date('Y-m-d H:i:s'),
        ];
        if ($existing) {
            unset($metaData['video_id']);
            $db->update('youtube_metadata', $metaData, 'id = ?', [$existing['id']]);
        } else {
            $db->insert('youtube_metadata', $metaData);
        }

        $updated++;
    }

    echo json_encode([
        'success' => true,
        'updated' => $updated,
        'failed'  => $failed,
        'total'   => count($videos),
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
