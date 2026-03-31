<?php
/**
 * One-time batch: Fetch YouTube view counts for ALL video ads.
 * Run multiple times — each call processes a batch of 100.
 * Once all videos have view_count, the regular 15-day refresh takes over.
 *
 * Usage: /dashboard/api/fetch_all_youtube.php?token=ads-intelligent-2024
 */
$autoMode = isset($_GET['auto']);
if (!$autoMode) {
    header('Content-Type: application/json');
}
set_time_limit(300);

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

    // Count total and remaining
    $totalVideos = (int) $db->fetchColumn(
        "SELECT COUNT(DISTINCT a.creative_id) FROM ads a
         INNER JOIN ad_assets ass ON ass.creative_id = a.creative_id AND ass.type = 'video' AND ass.original_url LIKE '%youtube.com%'
         WHERE a.ad_type = 'video'"
    );
    $remaining = (int) $db->fetchColumn(
        "SELECT COUNT(DISTINCT a.creative_id) FROM ads a
         INNER JOIN ad_assets ass ON ass.creative_id = a.creative_id AND ass.type = 'video' AND ass.original_url LIKE '%youtube.com%'
         WHERE a.ad_type = 'video' AND (a.view_count = 0 OR a.view_count IS NULL)"
    );

    if ($remaining === 0) {
        echo json_encode([
            'success' => true,
            'message' => 'All done! Every video already has view count.',
            'total_videos' => $totalVideos,
            'remaining' => 0,
        ], JSON_PRETTY_PRINT);
        exit;
    }

    // Get batch of videos without view count
    $ads = $db->fetchAll(
        "SELECT a.creative_id, ass.original_url as youtube_url
         FROM ads a
         INNER JOIN ad_assets ass ON ass.creative_id = a.creative_id AND ass.type = 'video' AND ass.original_url LIKE '%youtube.com%'
         WHERE a.ad_type = 'video' AND (a.view_count = 0 OR a.view_count IS NULL)
         GROUP BY a.creative_id
         ORDER BY a.last_seen DESC
         LIMIT 100"
    );

    $enriched = 0;
    $failed = 0;
    $errors = [];

    foreach ($ads as $ad) {
        $videoId = null;
        if (preg_match('/[?&]v=([a-zA-Z0-9_-]{11})/', $ad['youtube_url'], $m)) {
            $videoId = $m[1];
        }
        if (!$videoId) continue;

        // Fetch via oEmbed for title
        $oembedUrl = 'https://www.youtube.com/oembed?url=https://www.youtube.com/watch?v=' . $videoId . '&format=json';
        $ch = curl_init($oembedUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; AdsIntelligent/1.0)',
        ]);
        $oembedResp = curl_exec($ch);
        $oembedCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $title = null;
        $author = null;
        if ($oembedCode === 200) {
            $oData = json_decode($oembedResp, true);
            $title = $oData['title'] ?? null;
            $author = $oData['author_name'] ?? null;
        }

        // Fetch watch page for view count
        $watchUrl = 'https://www.youtube.com/watch?v=' . $videoId;
        $ch = curl_init($watchUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        ]);
        $watchResp = curl_exec($ch);
        $watchCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $viewCount = null;
        if ($watchCode === 200 && $watchResp) {
            if (preg_match('/"viewCount"\s*:\s*"(\d+)"/', $watchResp, $vm)) {
                $viewCount = (int) $vm[1];
            }
        }

        if ($viewCount === null && $oembedCode !== 200) {
            $failed++;
            if ($failed >= 5) {
                $errors[] = "Stopped early: {$failed} consecutive failures (rate limited?)";
                break;
            }
            continue;
        }
        $failed = 0;

        // Update ads.view_count
        if ($viewCount !== null) {
            $db->update('ads', ['view_count' => $viewCount], 'creative_id = ?', [$ad['creative_id']]);
        }

        // Update ad_details
        $description = '';
        if ($viewCount !== null) $description = number_format($viewCount) . ' views';
        if ($author) $description .= ($description ? ' | ' : '') . 'by ' . $author;

        $existingDetail = $db->fetchOne(
            "SELECT id, headline, headline_source FROM ad_details WHERE creative_id = ? ORDER BY id DESC LIMIT 1",
            [$ad['creative_id']]
        );

        if ($existingDetail) {
            $updateData = ['description' => $description ?: null];
            $hasRealHeadline = !empty($existingDetail['headline']) && $existingDetail['headline_source'] === 'ad';
            if (!$hasRealHeadline && $title) {
                $updateData['headline'] = $title;
                $updateData['headline_source'] = 'youtube';
            }
            $db->update('ad_details', $updateData, 'id = ?', [$existingDetail['id']]);
        } else {
            $db->insert('ad_details', [
                'creative_id'     => $ad['creative_id'],
                'headline'        => $title,
                'description'     => $description ?: null,
                'headline_source' => 'youtube',
            ]);
        }

        // Save/update youtube_metadata
        $existing = $db->fetchOne("SELECT id FROM youtube_metadata WHERE video_id = ?", [$videoId]);
        $metaData = [
            'video_id'      => $videoId,
            'title'         => $title,
            'channel_name'  => $author,
            'view_count'    => $viewCount ?? 0,
            'thumbnail_url' => 'https://i.ytimg.com/vi/' . $videoId . '/hqdefault.jpg',
            'fetched_at'    => date('Y-m-d H:i:s'),
        ];
        if ($existing) {
            unset($metaData['video_id']);
            $db->update('youtube_metadata', $metaData, 'id = ?', [$existing['id']]);
        } else {
            $db->insert('youtube_metadata', $metaData);
        }

        $enriched++;
        usleep(300000); // 300ms delay
    }

    $newRemaining = $remaining - $enriched;

    if ($autoMode) {
        // Auto mode: show progress page and auto-continue
        $done = $newRemaining <= 0;
        $pct = $totalVideos > 0 ? round(($totalVideos - $newRemaining) / $totalVideos * 100) : 100;
        echo '<!DOCTYPE html><html><head><title>YouTube Fetch Progress</title>
        <meta charset="utf-8"><style>
        body{font-family:system-ui,sans-serif;max-width:500px;margin:80px auto;text-align:center;background:#f8f9fa}
        .bar{background:#e9ecef;border-radius:8px;height:32px;overflow:hidden;margin:20px 0}
        .fill{background:#0d6efd;height:100%;transition:width .3s;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:600;font-size:.85rem}
        .stat{color:#6c757d;font-size:.9rem;margin:8px 0}
        .done{color:#198754;font-size:1.3rem;font-weight:700}
        </style>';
        if (!$done) {
            echo '<meta http-equiv="refresh" content="2;url=?token=' . urlencode($_GET['token']) . '&auto">';
        }
        echo '</head><body>';
        echo '<h2>📺 Fetching YouTube View Counts</h2>';
        echo '<div class="bar"><div class="fill" style="width:' . $pct . '%">' . $pct . '%</div></div>';
        echo '<div class="stat">Batch: ' . $enriched . ' fetched | Remaining: ' . $newRemaining . ' / ' . $totalVideos . '</div>';
        if (!empty($errors)) {
            echo '<div style="color:#dc3545;font-size:.85rem">' . implode('<br>', array_map('htmlspecialchars', $errors)) . '</div>';
        }
        if ($done) {
            echo '<div class="done">✅ All done! All ' . $totalVideos . ' videos have view counts.</div>';
            echo '<p class="stat">Regular 15-day refresh will handle updates from now on.</p>';
        } else {
            echo '<div class="stat">⏳ Auto-continuing in 2 seconds...</div>';
        }
        echo '</body></html>';
    } else {
        echo json_encode([
            'success'       => true,
            'total_videos'  => $totalVideos,
            'fetched_now'   => $enriched,
            'remaining'     => $newRemaining,
            'errors'        => $errors,
            'message'       => $newRemaining > 0
                ? "Fetched {$enriched} videos. Run again for remaining {$newRemaining}."
                : "All done! Fetched {$enriched} videos. All videos now have view counts.",
        ], JSON_PRETTY_PRINT);
    }

} catch (Exception $e) {
    if ($autoMode) {
        echo '<div style="color:red;text-align:center;margin:80px auto;font-family:sans-serif"><h3>Error</h3><p>' . htmlspecialchars($e->getMessage()) . '</p></div>';
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
