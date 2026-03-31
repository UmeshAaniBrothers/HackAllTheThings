<?php
/**
 * Debug: Test YouTube view count scraping for specific videos
 */
header('Content-Type: application/json');

$basePath = dirname(dirname(__DIR__));
$config = require $basePath . '/config/config.php';

if (($_GET['token'] ?? '') !== ($config['ingest_token'] ?? 'ads-intelligent-2024')) {
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

require_once $basePath . '/src/Database.php';
$db = Database::getInstance($config['db']);

// Get a few video ads with view_count = 0
$videoId = $_GET['v'] ?? '';
$results = [];

if ($videoId) {
    $testIds = [$videoId];
} else {
    // Get 5 videos with 0 views
    $rows = $db->fetchAll(
        "SELECT ass.original_url FROM ads a
         INNER JOIN ad_assets ass ON ass.creative_id = a.creative_id AND ass.type = 'video' AND ass.original_url LIKE '%youtube.com%'
         WHERE a.ad_type = 'video' AND a.view_count = 0
         LIMIT 5"
    );
    $testIds = [];
    foreach ($rows as $r) {
        if (preg_match('/[?&]v=([a-zA-Z0-9_-]{11})/', $r['original_url'], $m)) {
            $testIds[] = $m[1];
        }
    }
}

foreach ($testIds as $vid) {
    $watchUrl = 'https://www.youtube.com/watch?v=' . $vid;

    $ch = curl_init($watchUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    ]);
    $resp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    $viewCount = null;
    $matchCount = 0;
    if ($resp && preg_match_all('/"viewCount"\s*:\s*"(\d+)"/', $resp, $matches)) {
        $viewCount = (int) $matches[1][0];
        $matchCount = count($matches[1]);
    }

    // Also check for consent/redirect page
    $isConsent = (strpos($resp, 'consent.youtube.com') !== false || strpos($resp, 'CONSENT') !== false);
    $bodyLen = strlen($resp ?: '');

    // Try alternate patterns
    $altView = null;
    if ($resp && preg_match('/viewCount.*?(\d{3,})/', $resp, $am)) {
        $altView = (int) $am[1];
    }

    $results[] = [
        'video_id' => $vid,
        'http_code' => $httpCode,
        'curl_error' => $curlError ?: null,
        'body_length' => $bodyLen,
        'is_consent_page' => $isConsent,
        'view_count' => $viewCount,
        'match_count' => $matchCount,
        'alt_view_count' => $altView,
    ];
}

echo json_encode(['results' => $results], JSON_PRETTY_PRINT);
