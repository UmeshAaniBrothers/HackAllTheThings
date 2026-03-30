<?php
/**
 * Standalone test: Fetches a preview URL and shows what text can be extracted
 * with the NEW single-quote-aware patterns.
 */
header('Content-Type: text/plain; charset=utf-8');
set_time_limit(30);

$basePath = dirname(dirname(__DIR__));
$config = require $basePath . '/config/config.php';

$authToken = $config['ingest_token'] ?? 'ads-intelligent-2024';
$providedToken = $_GET['token'] ?? '';
if ($providedToken !== $authToken) { http_response_code(403); echo "Invalid token\n"; exit; }

require_once $basePath . '/src/Database.php';
$db = Database::getInstance($config['db']);

echo "=== PREVIEW TEXT EXTRACTION TEST (NEW PATTERNS) ===\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

// Get 3 ads with preview URLs
$ads = $db->fetchAll(
    "SELECT a.creative_id, a.ad_type, ass.original_url as preview_url,
            d.headline as current_headline
     FROM ads a
     INNER JOIN ad_assets ass ON a.creative_id = ass.creative_id
        AND ass.original_url LIKE '%displayads-formats%'
     LEFT JOIN ad_details d ON a.creative_id = d.creative_id
        AND d.id = (SELECT MAX(id) FROM ad_details WHERE creative_id = a.creative_id)
     GROUP BY a.creative_id
     ORDER BY a.last_seen DESC
     LIMIT 3"
);

echo "Testing " . count($ads) . " ads...\n\n";

foreach ($ads as $ad) {
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "Creative: {$ad['creative_id']} ({$ad['ad_type']})\n";
    echo "Current DB Headline: " . ($ad['current_headline'] ?: '(none)') . "\n";
    echo "Preview URL: " . substr($ad['preview_url'], 0, 80) . "...\n\n";

    // Fetch the preview content
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $ad['preview_url'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
        CURLOPT_HTTPHEADER => ['Referer: https://adstransparency.google.com/', 'Accept: */*'],
        CURLOPT_ENCODING => 'gzip, deflate',
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (!$response || $httpCode !== 200) {
        echo "  FAILED: HTTP $httpCode\n\n";
        continue;
    }

    echo "  Response size: " . strlen($response) . " bytes\n\n";

    $decoded = $response;

    // ── Extract with NEW patterns (single quotes) ──

    // App Name (headline)
    $appName = null;
    if (preg_match('/[\'"]appName[\'"]\s*:\s*[\'"]([^"\']{3,200})[\'"]/', $decoded, $m)) {
        $appName = $m[1];
    }
    echo "  'appName':           " . ($appName ?: '(not found)') . "\n";

    $shortAppName = null;
    if (preg_match('/[\'"]shortAppName[\'"]\s*:\s*[\'"]([^"\']{3,200})[\'"]/', $decoded, $m)) {
        $shortAppName = $m[1];
    }
    echo "  'shortAppName':      " . ($shortAppName ?: '(not found)') . "\n";

    // Description
    $shortDesc = null;
    if (preg_match('/[\'"]shortDescription[\'"]\s*:\s*[\'"]([^"\']{3,500})[\'"]/', $decoded, $m)) {
        $shortDesc = $m[1];
    }
    echo "  'shortDescription':  " . ($shortDesc ?: '(not found)') . "\n";

    // CTA
    $cta = null;
    if (preg_match('/[\'"](?:callToAction|callToActionInstall)[\'"]\s*:\s*[\'"]([^"\']{2,50})[\'"]/', $decoded, $m)) {
        $cta = $m[1];
    }
    echo "  'callToAction':      " . ($cta ?: '(not found)') . "\n";

    // YouTube Video ID
    $ytId = null;
    if (preg_match('/[\'"]video_videoId[\'"]\s*:\s*[\'"]([a-zA-Z0-9_-]{11})[\'"]/', $decoded, $m)) {
        $ytId = $m[1];
    }
    echo "  'video_videoId':     " . ($ytId ?: '(not found)') . "\n";

    // App ID / Package
    $appId = null;
    if (preg_match('/[\'"]appId[\'"]\s*:\s*[\'"]([a-zA-Z0-9._]+)[\'"]/', $decoded, $m)) {
        $appId = $m[1];
    }
    echo "  'appId':             " . ($appId ?: '(not found)') . "\n";

    // App Store type
    $appStore = null;
    if (preg_match('/[\'"]appStore[\'"]\s*:\s*[\'"]?(\d+)[\'"]?/', $decoded, $m)) {
        $appStore = $m[1];
        $storeLabel = $appStore === '2' ? 'Google Play' : ($appStore === '1' ? 'App Store' : 'Unknown');
    }
    echo "  'appStore':          " . ($appStore ? "$appStore ($storeLabel)" : '(not found)') . "\n";

    // Developer
    $dev = null;
    if (preg_match('/[\'"]developer[\'"]\s*:\s*[\'"]([^"\']{2,200})[\'"]/', $decoded, $m)) {
        $dev = $m[1];
    }
    echo "  'developer':         " . ($dev ?: '(not found)') . "\n";

    // Category
    $cat = null;
    if (preg_match('/[\'"]appCategory[\'"]\s*:\s*[\'"]([^"\']{2,100})[\'"]/', $decoded, $m)) {
        $cat = $m[1];
    }
    echo "  'appCategory':       " . ($cat ?: '(not found)') . "\n";

    // App Icon
    $icon = null;
    if (preg_match('/[\'"]appIconHighRes[\'"]\s*:\s*[\'"]([^"\']{10,500})[\'"]/', $decoded, $m)) {
        $icon = $m[1];
        if (strpos($icon, '//') === 0) $icon = 'https:' . $icon;
    }
    echo "  'appIconHighRes':    " . ($icon ? substr($icon, 0, 80) . '...' : '(not found)') . "\n";

    // Store name
    $storeName = null;
    if (preg_match('/[\'"]appStoreName[\'"]\s*:\s*[\'"]([^"\']{2,50})[\'"]/', $decoded, $m)) {
        $storeName = $m[1];
    }
    echo "  'appStoreName':      " . ($storeName ?: '(not found)') . "\n";

    // Old pattern check: would the old regex find "Cannot find global object"?
    $oldHeadline = null;
    if (preg_match('/"headlines?":\s*"([^"]{3,200})"/', $decoded, $m)) {
        $oldHeadline = $m[1];
    }
    echo "\n  [OLD PATTERN] \"headline\": " . ($oldHeadline ?: '(not found)') . "\n";

    // Check for "Cannot find global object"
    $errorCount = substr_count($decoded, 'Cannot find global object');
    echo "  [DEBUG] 'Cannot find global object' appears: {$errorCount} times\n";

    echo "\n";
    usleep(500000);
}

// DB Stats
echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "DATABASE STATS:\n";
$total = (int)$db->fetchColumn("SELECT COUNT(*) FROM ads");
$withHL = (int)$db->fetchColumn("SELECT COUNT(DISTINCT d.creative_id) FROM ad_details d WHERE d.headline IS NOT NULL AND d.headline != '' AND d.headline NOT LIKE '%Cannot find%'");
$badHL = (int)$db->fetchColumn("SELECT COUNT(DISTINCT d.creative_id) FROM ad_details d WHERE d.headline LIKE '%Cannot find%'");
$withCountry = (int)$db->fetchColumn("SELECT COUNT(DISTINCT creative_id) FROM ad_targeting");
echo "  Total ads:              $total\n";
echo "  Good headlines:         $withHL (" . round($withHL/$total*100) . "%)\n";
echo "  Bad 'Cannot find' HL:   $badHL\n";
echo "  With country targeting: $withCountry (" . round($withCountry/$total*100) . "%)\n";
echo "\n=== DONE ===\n";
