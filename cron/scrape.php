<?php

/**
 * ╔══════════════════════════════════════════════════════════╗
 * ║  Server-Side Auto Scraper                                 ║
 * ║  Runs on Cloudways cron — clean server IP, no CAPTCHA     ║
 * ║  Zero cost. Zero browser. Just cURL.                      ║
 * ╚══════════════════════════════════════════════════════════╝
 *
 * How it works:
 *   - Reads managed_advertisers from the database
 *   - Hits Google Ads Transparency API via cURL (server IP)
 *   - Stores raw payloads, triggers processing
 *   - Smart rate limiting to avoid IP blocks
 *
 * Cron setup (Cloudways → Cron Job Management):
 *   Every 6 hours:  php /path/to/cron/scrape.php
 *   Or via HTTP:     https://your-server/cron/scrape.php?token=ads-intelligent-2024
 *
 * CLI usage:
 *   php cron/scrape.php                    # Scrape all advertisers
 *   php cron/scrape.php --test             # Test connection only
 *   php cron/scrape.php --limit=5          # Only scrape 5 advertisers
 *   php cron/scrape.php --id=AR0123...     # Scrape specific advertiser
 */

// ── Allow both CLI and HTTP (with token) ────────────────
$isCli = (php_sapi_name() === 'cli');

if (!$isCli) {
    header('Content-Type: application/json');
    $token = $_GET['token'] ?? '';
    if ($token !== 'ads-intelligent-2024') {
        echo json_encode(['error' => 'Unauthorized']);
        http_response_code(401);
        exit;
    }
}

set_time_limit(600); // 10 minutes max
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Catch fatal errors and return JSON
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        global $isCli;
        if (!$isCli) {
            echo json_encode(['error' => $error['message'], 'file' => $error['file'], 'line' => $error['line']]);
        }
    }
});

// ── Load config ─────────────────────────────────────────
$baseDir = dirname(__DIR__);
$configFile = $baseDir . '/config.php';
if (!file_exists($configFile)) {
    output("ERROR: config.php not found at {$configFile}", true);
    exit(1);
}

require_once $configFile;
require_once $baseDir . '/src/Database.php';
require_once $baseDir . '/src/Processor.php';

$db = new Database();
$processor = new Processor($db);

// ── Constants ───────────────────────────────────────────
$GOOGLE_BASE = 'https://adstransparency.google.com';
$COOKIE_FILE = sys_get_temp_dir() . '/gads_scrape_' . getmypid() . '.txt';

// Rate limiting: be gentle to avoid server IP getting flagged
$DELAY_BETWEEN_PAGES       = 2;    // seconds between pagination requests
$DELAY_BETWEEN_ADVERTISERS = 8;    // seconds between different advertisers
$ADS_PER_PAGE              = 100;  // max ads per API call
$MAX_PAGES_PER_ADVERTISER  = 50;   // safety limit (5000 ads max)

// ── Parse CLI args ──────────────────────────────────────
$testOnly = false;
$limit = 0;
$specificId = null;

if ($isCli) {
    foreach ($argv as $arg) {
        if ($arg === '--test') $testOnly = true;
        if (preg_match('/^--limit=(\d+)$/', $arg, $m)) $limit = (int)$m[1];
        if (preg_match('/^--id=(.+)$/', $arg, $m)) $specificId = $m[1];
    }
} else {
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 0;
    $specificId = $_GET['id'] ?? null;
}

// ── Main ────────────────────────────────────────────────
$startTime = microtime(true);
$results = ['success' => true, 'advertisers' => [], 'total_ads' => 0, 'errors' => []];

output("╔══════════════════════════════════════════════════════════╗");
output("║   Ads Intelligence — Server Scraper                      ║");
output("╚══════════════════════════════════════════════════════════╝");
output("Started: " . date('Y-m-d H:i:s'));
output("");

// Step 1: Init Google session (get cookies)
output("Initializing Google session...");
$sessionOk = initGoogleSession($GOOGLE_BASE, $COOKIE_FILE);
if (!$sessionOk) {
    output("ERROR: Cannot reach Google Ads Transparency Center");
    $results['success'] = false;
    $results['errors'][] = 'Session init failed';
    finish($results, $startTime, $COOKIE_FILE, $isCli);
    exit(1);
}
output("Session ready.\n");

// Test mode
if ($testOnly) {
    output("Testing API...");
    $testResult = testApi($GOOGLE_BASE, $COOKIE_FILE);
    output($testResult ? "API works! Response received." : "API test FAILED.");
    finish($results, $startTime, $COOKIE_FILE, $isCli);
    exit($testResult ? 0 : 1);
}

// Step 2: Get advertisers from database
if ($specificId) {
    $advertisers = [['advertiser_id' => $specificId, 'advertiser_name' => $specificId]];
    // Try to get name from DB
    $row = $db->getConnection()->query(
        "SELECT advertiser_name FROM managed_advertisers WHERE advertiser_id = " .
        $db->getConnection()->quote($specificId)
    )->fetch(PDO::FETCH_ASSOC);
    if ($row) $advertisers[0]['advertiser_name'] = $row['advertiser_name'];
} else {
    $stmt = $db->getConnection()->query(
        "SELECT advertiser_id, advertiser_name FROM managed_advertisers WHERE status = 'active' ORDER BY last_scraped_at ASC"
    );
    $advertisers = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

if (empty($advertisers)) {
    output("No advertisers found in database.");
    finish($results, $startTime, $COOKIE_FILE, $isCli);
    exit(0);
}

if ($limit > 0) {
    $advertisers = array_slice($advertisers, 0, $limit);
}

output(count($advertisers) . " advertisers to scrape\n");

// Step 3: Scrape each advertiser
$totalSuccess = 0;
$totalFailed = 0;
$totalAds = 0;

foreach ($advertisers as $idx => $adv) {
    $num = $idx + 1;
    $id = $adv['advertiser_id'];
    $name = $adv['advertiser_name'];
    $elapsed = formatElapsed(microtime(true) - $startTime);

    output("--- [{$num}/" . count($advertisers) . "] {$name} ({$id}) [{$elapsed}] ---");

    try {
        $result = scrapeAdvertiser($id, $GOOGLE_BASE, $COOKIE_FILE, $ADS_PER_PAGE, $MAX_PAGES_PER_ADVERTISER, $DELAY_BETWEEN_PAGES);

        if ($result['total_ads'] > 0) {
            // Store payloads directly to database (we're ON the server!)
            $stored = storePayloads($db, $id, $name, $result['payloads']);
            output("  Stored: {$stored} pages, {$result['total_ads']} ads");

            // Update advertiser stats
            updateAdvertiserStats($db, $id, $result['total_ads']);

            $totalAds += $result['total_ads'];
            $totalSuccess++;
            $results['advertisers'][] = ['id' => $id, 'name' => $name, 'ads' => $result['total_ads']];
        } else {
            output("  No ads found");
            $totalFailed++;
        }
    } catch (Exception $e) {
        output("  ERROR: " . $e->getMessage());
        $totalFailed++;
        $results['errors'][] = "{$id}: {$e->getMessage()}";

        // If we get blocked, stop immediately
        if (strpos($e->getMessage(), 'BLOCKED') !== false || strpos($e->getMessage(), 'CAPTCHA') !== false) {
            output("\n⚠️ Server IP appears blocked. Stopping to protect the IP.");
            output("  Wait a few hours before running again.");
            break;
        }
    }

    // Delay between advertisers (skip for last one)
    if ($idx < count($advertisers) - 1) {
        $delay = $DELAY_BETWEEN_ADVERTISERS + rand(0, 4);
        output("  (waiting {$delay}s...)");
        sleep($delay);
    }
}

output("\n✅ Done: {$totalSuccess} success, {$totalFailed} failed, {$totalAds} total ads\n");

// Step 4: Trigger processing
output("━━━ Processing scraped data ━━━\n");
$steps = ['process', 'text', 'youtube', 'apps', 'countries', 'products'];
foreach ($steps as $step) {
    $batch = 0;
    $didWork = true;
    while ($didWork && $batch < 30) {
        $batch++;
        try {
            if ($step === 'process') {
                $processed = $processor->processAll(10);
                $didWork = $processed > 0;
                if ($didWork) output("  process #{$batch}: {$processed} ads");
            } elseif ($step === 'text') {
                $enriched = $processor->enrichTextAll(10);
                $didWork = $enriched > 0;
                if ($didWork) output("  text #{$batch}: {$enriched} enriched");
            } elseif ($step === 'youtube') {
                $enriched = $processor->enrichYouTubeAll(10);
                $didWork = $enriched > 0;
                if ($didWork) output("  youtube #{$batch}: {$enriched} enriched");
            } elseif ($step === 'apps') {
                $enriched = $processor->enrichAppsAll(10);
                $didWork = $enriched > 0;
                if ($didWork) output("  apps #{$batch}: {$enriched} enriched");
            } elseif ($step === 'countries') {
                $enriched = $processor->enrichCountriesAll(10);
                $didWork = $enriched > 0;
                if ($didWork) output("  countries #{$batch}: {$enriched} enriched");
            } elseif ($step === 'products') {
                $mapped = $processor->mapProductsAll(10);
                $didWork = $mapped > 0;
                if ($didWork) output("  products #{$batch}: {$mapped} mapped");
            } else {
                $didWork = false;
            }
        } catch (Exception $e) {
            output("  {$step} error: " . $e->getMessage());
            $didWork = false;
        }
    }
}

$results['total_ads'] = $totalAds;
finish($results, $startTime, $COOKIE_FILE, $isCli);


// ═══════════════════════════════════════════════════════
// Core Scraping Functions
// ═══════════════════════════════════════════════════════

function scrapeAdvertiser($advertiserId, $googleBase, $cookieFile, $perPage, $maxPages, $delayBetweenPages)
{
    $allPayloads = [];
    $totalAds = 0;
    $pageToken = null;
    $pageNum = 0;

    do {
        $pageNum++;
        if ($pageNum > $maxPages) break;

        $freqData = [
            '2' => $perPage,
            '3' => [
                '12' => ['1' => '', '2' => true],
                '13' => ['1' => [$advertiserId]],
            ],
            '7' => ['1' => 1],
        ];
        if ($pageToken !== null) {
            $freqData['4'] = $pageToken;
        }

        if ($pageNum > 1) {
            sleep($delayBetweenPages);
        }

        $url = $googleBase . '/anji/_/rpc/SearchService/SearchCreatives?authuser=0';
        $body = 'f.req=' . urlencode(json_encode($freqData));
        $resp = googleRequest($url, $body, $cookieFile);

        if ($resp === null) {
            if ($pageNum === 1) {
                throw new Exception('API request failed — possibly BLOCKED');
            }
            break;
        }

        $data = json_decode($resp, true);
        if (!is_array($data) || empty($data)) {
            break;
        }

        $ads = $data['1'] ?? [];
        $count = is_array($ads) ? count($ads) : 0;

        if ($count > 0) {
            $allPayloads[] = $resp;
            $totalAds += $count;
            output("  Page {$pageNum}: {$count} ads (total: {$totalAds})");
        }

        $pageToken = isset($data['2']) && is_string($data['2']) && $data['2'] !== '' ? $data['2'] : null;

    } while ($pageToken !== null);

    return ['payloads' => $allPayloads, 'total_ads' => $totalAds, 'pages' => $pageNum];
}


// ═══════════════════════════════════════════════════════
// Database Storage (direct — we're on the server!)
// ═══════════════════════════════════════════════════════

function storePayloads($db, $advertiserId, $advertiserName, $payloads)
{
    $pdo = $db->getConnection();
    $stored = 0;

    // Ensure advertiser exists
    $stmt = $pdo->prepare(
        "INSERT INTO managed_advertisers (advertiser_id, advertiser_name, status, created_at)
         VALUES (:id, :name, 'active', NOW())
         ON DUPLICATE KEY UPDATE advertiser_name = :name2"
    );
    $stmt->execute(['id' => $advertiserId, 'name' => $advertiserName, 'name2' => $advertiserName]);

    // Store each payload page
    $stmt = $pdo->prepare(
        "INSERT INTO raw_payloads (advertiser_id, payload, region, processed_flag, created_at)
         VALUES (:id, :payload, 'ANYWHERE', 0, NOW())"
    );

    foreach ($payloads as $payload) {
        try {
            $stmt->execute(['id' => $advertiserId, 'payload' => $payload]);
            $stored++;
        } catch (Exception $e) {
            // Skip duplicates or errors
        }
    }

    return $stored;
}

function updateAdvertiserStats($db, $advertiserId, $newAdsCount)
{
    $pdo = $db->getConnection();
    try {
        $pdo->prepare(
            "UPDATE managed_advertisers
             SET last_scraped_at = NOW(),
                 total_ads = COALESCE(total_ads, 0) + :count
             WHERE advertiser_id = :id"
        )->execute(['count' => $newAdsCount, 'id' => $advertiserId]);
    } catch (Exception $e) {
        // Non-critical
    }
}


// ═══════════════════════════════════════════════════════
// Google API Functions
// ═══════════════════════════════════════════════════════

function initGoogleSession($googleBase, $cookieFile)
{
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $googleBase . '/?region=anywhere',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEFILE     => $cookieFile,
        CURLOPT_COOKIEJAR      => $cookieFile,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_USERAGENT      => chromeUA(),
        CURLOPT_ENCODING       => 'gzip, deflate, br',
        CURLOPT_HTTPHEADER     => [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.9',
            'Cache-Control: no-cache',
            'Sec-Ch-Ua: "Chromium";v="134", "Google Chrome";v="134", "Not:A-Brand";v="24"',
            'Sec-Ch-Ua-Mobile: ?0',
            'Sec-Ch-Ua-Platform: "Linux"',
            'Sec-Fetch-Dest: document',
            'Sec-Fetch-Mode: navigate',
            'Sec-Fetch-Site: none',
            'Sec-Fetch-User: ?1',
            'Upgrade-Insecure-Requests: 1',
        ],
    ]);
    $html = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Check for CAPTCHA / block
    if ($html && (strpos($html, 'google.com/sorry') !== false || strpos($html, 'detected unusual traffic') !== false)) {
        output("WARNING: Server IP may be blocked by Google");
        return false;
    }

    return $code === 200;
}

function googleRequest($url, $body, $cookieFile)
{
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_COOKIEFILE     => $cookieFile,
        CURLOPT_COOKIEJAR      => $cookieFile,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT      => chromeUA(),
        CURLOPT_ENCODING       => 'gzip, deflate, br',
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: */*',
            'Accept-Language: en-US,en;q=0.9',
            'Origin: https://adstransparency.google.com',
            'Referer: https://adstransparency.google.com/',
            'Sec-Ch-Ua: "Chromium";v="134", "Google Chrome";v="134", "Not:A-Brand";v="24"',
            'Sec-Ch-Ua-Mobile: ?0',
            'Sec-Ch-Ua-Platform: "Linux"',
            'Sec-Fetch-Dest: empty',
            'Sec-Fetch-Mode: cors',
            'Sec-Fetch-Site: same-origin',
        ],
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $httpCode !== 200) {
        return null;
    }

    // Check for HTML (CAPTCHA page instead of JSON)
    if (strpos($response, '<!DOCTYPE') !== false || strpos($response, 'google.com/sorry') !== false) {
        return null;
    }

    return $response;
}

function testApi($googleBase, $cookieFile)
{
    $url = $googleBase . '/anji/_/rpc/SearchService/SearchSuggestions?authuser=0';
    $body = 'f.req=' . urlencode(json_encode(['1' => 'Google', '2' => 3, '3' => 3]));
    $resp = googleRequest($url, $body, $cookieFile);
    return $resp !== null;
}

function chromeUA()
{
    return 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36';
}


// ═══════════════════════════════════════════════════════
// Utilities
// ═══════════════════════════════════════════════════════

$_logBuffer = [];

function output($msg, $isError = false)
{
    global $isCli, $_logBuffer;
    $_logBuffer[] = $msg;
    if ($isCli) {
        echo $msg . "\n";
    }
}

function formatElapsed($seconds)
{
    $s = (int)$seconds;
    if ($s < 60) return "{$s}s";
    $m = floor($s / 60);
    if ($m < 60) return "{$m}m " . ($s % 60) . "s";
    return floor($m / 60) . "h " . ($m % 60) . "m";
}

function finish($results, $startTime, $cookieFile, $isCli)
{
    // Cleanup
    if (file_exists($cookieFile)) {
        @unlink($cookieFile);
    }

    $elapsed = round(microtime(true) - $startTime, 1);
    $results['elapsed_seconds'] = $elapsed;

    output("\n⏱  Time: {$elapsed}s");
    output("Done.\n");

    if (!$isCli) {
        global $_logBuffer;
        $results['log'] = $_logBuffer;
        echo json_encode($results, JSON_PRETTY_PRINT);
    }
}
