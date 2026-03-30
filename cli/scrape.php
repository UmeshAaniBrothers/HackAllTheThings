#!/usr/bin/env php
<?php

/**
 * CLI Scraper - Run from your local machine to bypass Google's server IP blocks.
 *
 * Scrapes Google Ads Transparency Center locally, then POSTs results
 * to your Cloudways server for storage and processing.
 *
 * Usage:
 *   php cli/scrape.php test                              # Test API + server connection
 *   php cli/scrape.php search "Nike"                     # Search for advertisers
 *   php cli/scrape.php fetch AR11784881014541647873       # Fetch all ads
 *   php cli/scrape.php fetch AR11784881014541647873 Google # Fetch with name
 */

if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.\n");
}

// ── Configuration ────────────────────────────────────────
// Change this to your server URL
$SERVER_URL = 'https://phpstack-1170423-6314737.cloudwaysapps.com';
$AUTH_TOKEN = 'ads-intelligent-2024';
$GOOGLE_BASE = 'https://adstransparency.google.com';

// ── Parse command ────────────────────────────────────────
$command = $argv[1] ?? 'help';
$arg = $argv[2] ?? '';
$arg2 = $argv[3] ?? '';

$ADVERTISERS_FILE = __DIR__ . '/advertisers.txt';

switch ($command) {
    case 'test':
        testConnection($GOOGLE_BASE, $SERVER_URL, $AUTH_TOKEN);
        break;
    case 'search':
        if (empty($arg)) die("Usage: php cli/scrape.php search \"Company Name\"\n");
        searchAdvertisers($arg, $GOOGLE_BASE);
        break;
    case 'fetch':
        if (empty($arg)) die("Usage: php cli/scrape.php fetch AR1234... [Name] [region]\n");
        $region = $argv[4] ?? 'anywhere';
        fetchAdvertiser($arg, $arg2 ?: $arg, $GOOGLE_BASE, $SERVER_URL, $AUTH_TOKEN, $region);
        break;
    case 'add':
        if (empty($arg)) die("Usage: php cli/scrape.php add AR1234... \"Company Name\" [region]\n");
        $region = $argv[4] ?? 'IN';
        addAdvertiser($arg, $arg2 ?: $arg, $region, $ADVERTISERS_FILE);
        break;
    case 'list':
        listAdvertisers($ADVERTISERS_FILE);
        break;
    case 'fetchall':
        fetchAllAdvertisers($ADVERTISERS_FILE, $GOOGLE_BASE, $SERVER_URL, $AUTH_TOKEN);
        break;
    case 'enrich':
        enrichYouTubeFromCli($SERVER_URL, $AUTH_TOKEN);
        break;
    default:
        echo "Ads Intelligent - CLI Scraper\n";
        echo "==============================\n\n";
        echo "Scrapes Google locally, sends results to your server.\n\n";
        echo "Usage:\n";
        echo "  php cli/scrape.php test                              Test connections\n";
        echo "  php cli/scrape.php search \"Nike\"                     Search advertisers\n";
        echo "  php cli/scrape.php fetch AR1234... \"Name\" [region]   Fetch ads (region: IN,US,GB...)\n";
        echo "  php cli/scrape.php add AR1234... \"Name\" [region]     Add to list (default: IN)\n";
        echo "  php cli/scrape.php list                              Show saved advertisers\n";
        echo "  php cli/scrape.php fetchall                          Fetch ALL saved advertisers\n";
        echo "  php cli/scrape.php enrich                            Extract YouTube URLs locally\n\n";
        echo "Regions: IN (India), US (USA), GB (UK), AU, CA, DE, FR, JP, BR, anywhere\n\n";
        echo "Server: {$SERVER_URL}\n";
        echo "Advertisers file: {$ADVERTISERS_FILE}\n";
        break;
}
echo "\n";
exit(0);

// ── Functions ────────────────────────────────────────────

function testConnection($googleBase, $serverUrl, $token)
{
    echo "Testing Google Ads Transparency API...\n";

    // Test Google
    $cookieFile = initGoogleSession($googleBase);
    $url = $googleBase . '/anji/_/rpc/SearchService/SearchSuggestions?authuser=0';
    $body = 'f.req=' . urlencode(json_encode(['1' => 'Google', '2' => 3, '3' => 3]));
    $resp = googleRequest($url, $body, $cookieFile);
    cleanupCookies($cookieFile);

    if ($resp === null) {
        echo "FAILED: Cannot reach Google API\n";
    } else {
        $data = json_decode($resp, true);
        $count = is_array($data) ? count($data['1'] ?? []) : 0;
        echo "OK: Google API reachable ({$count} suggestions returned)\n";
    }

    // Test server
    echo "\nTesting server connection...\n";
    $testUrl = $serverUrl . '/dashboard/api/ingest.php?action=update_advertiser&token=' . urlencode($token);
    $testResp = postJson($testUrl, ['advertiser_id' => 'TEST_CONNECTION', 'status' => 'deleted', 'name' => 'Test']);
    if ($testResp && isset($testResp['success']) && $testResp['success']) {
        echo "OK: Server connection works\n";
    } else {
        echo "FAILED: " . ($testResp['error'] ?? 'Cannot reach server') . "\n";
        echo "Server URL: {$serverUrl}\n";
    }
}

function searchAdvertisers($keyword, $googleBase)
{
    echo "Searching for: {$keyword}\n\n";

    $cookieFile = initGoogleSession($googleBase);
    $url = $googleBase . '/anji/_/rpc/SearchService/SearchSuggestions?authuser=0';
    $body = 'f.req=' . urlencode(json_encode(['1' => $keyword, '2' => 10, '3' => 10]));

    usleep(500000);
    $resp = googleRequest($url, $body, $cookieFile);
    cleanupCookies($cookieFile);

    if ($resp === null) {
        echo "Failed to search. Google may be rate-limiting.\n";
        return;
    }

    $data = json_decode($resp, true);
    if (!is_array($data) || empty($data['1'])) {
        echo "No results found.\n";
        return;
    }

    echo str_pad("Advertiser ID", 28) . "Name\n";
    echo str_repeat("-", 70) . "\n";

    foreach ($data['1'] as $item) {
        // Suggestions can be advertiser type (key "1") or domain type (key "2")
        $adv = $item['1'] ?? null;
        $dom = $item['2'] ?? null;

        if ($adv) {
            $id = $adv['2'] ?? $adv['1'] ?? '?';
            $name = $adv['1'] ?? 'Unknown';
            echo str_pad($id, 28) . $name . "\n";
        } elseif ($dom) {
            echo str_pad('[domain]', 28) . ($dom['1'] ?? '?') . "\n";
        }
    }

    echo "\nTo fetch ads: php cli/scrape.php fetch <ADVERTISER_ID> \"Name\"\n";
}

function fetchAdvertiser($advertiserId, $advertiserName, $googleBase, $serverUrl, $token, $region = 'anywhere')
{
    $region = strtoupper(trim($region));
    echo "Fetching ads for: {$advertiserId} ({$advertiserName}) [Region: {$region}]\n\n";

    $cookieFile = initGoogleSession($googleBase, $region);
    $allPayloads = [];
    $totalAds = 0;
    $pageToken = null;
    $pageCount = 0;

    do {
        $pageCount++;
        echo "Fetching page {$pageCount}...";

        $freqData = [
            '2' => 100,
            '3' => [
                '12' => ['1' => '', '2' => true],
                '13' => ['1' => [$advertiserId]],
            ],
            '7' => ['1' => 1],
        ];
        if ($pageToken !== null) {
            $freqData['4'] = $pageToken;
        }

        $url = $googleBase . '/anji/_/rpc/SearchService/SearchCreatives?authuser=0';
        $body = 'f.req=' . urlencode(json_encode($freqData));

        if ($pageCount > 1) {
            usleep(1500000); // 1.5s between pages
        } else {
            usleep(500000);
        }

        $resp = googleRequest($url, $body, $cookieFile);

        if ($resp === null) {
            echo " FAILED (request error)\n";
            break;
        }

        $data = json_decode($resp, true);
        if (!is_array($data) || empty($data)) {
            echo " empty response (advertiser may not exist)\n";
            break;
        }

        $ads = $data['1'] ?? [];
        $adsOnPage = is_array($ads) ? count($ads) : 0;
        $totalAds += $adsOnPage;
        echo " {$adsOnPage} ads\n";

        if ($adsOnPage > 0) {
            $allPayloads[] = $resp;
        }

        // Next page
        $pageToken = isset($data['2']) && is_string($data['2']) && $data['2'] !== '' ? $data['2'] : null;

    } while ($pageToken !== null);

    cleanupCookies($cookieFile);

    echo "\nTotal: {$totalAds} ads across {$pageCount} page(s)\n";

    if ($totalAds === 0) {
        echo "No ads found. The advertiser ID may be invalid.\n";
        return;
    }

    // Send to server - store only (fast)
    echo "\nSending to server...\n";

    $storeUrl = $serverUrl . '/dashboard/api/ingest.php?action=store_payload&token=' . urlencode($token);
    $updateUrl = $serverUrl . '/dashboard/api/ingest.php?action=update_advertiser&token=' . urlencode($token);
    $cronUrl = $serverUrl . '/cron/process.php?token=' . urlencode($token);

    // Step 1: Ensure advertiser exists
    $advResult = postJson($updateUrl, [
        'advertiser_id' => $advertiserId,
        'name'          => $advertiserName,
        'status'        => 'active',
    ]);
    if (!$advResult || empty($advResult['success'])) {
        echo "WARNING: Could not update advertiser record\n";
    }

    // Step 2: Send each page (store only, no processing yet)
    $sentPages = 0;
    foreach ($allPayloads as $i => $payload) {
        $pageNum = $i + 1;
        echo "  Page {$pageNum}/" . count($allPayloads) . "...";

        $result = postJson($storeUrl, [
            'advertiser_id' => $advertiserId,
            'payload'       => $payload,
        ]);

        if ($result && !empty($result['success'])) {
            echo " OK\n";
            $sentPages++;
        } else {
            echo " FAILED: " . ($result['error'] ?? 'Unknown') . "\n";
        }
    }

    echo "\nStored {$sentPages}/" . count($allPayloads) . " pages.\n";

    if ($sentPages === 0) {
        $backupFile = __DIR__ . '/backup_' . $advertiserId . '_' . date('Ymd_His') . '.json';
        file_put_contents($backupFile, json_encode($allPayloads));
        echo "Payloads saved locally: {$backupFile}\n";
        return;
    }

    // Step 3: Trigger background processing on server
    echo "Triggering server processing...";
    $cronResult = postJson($cronUrl, [], 300);
    if ($cronResult && !empty($cronResult['success'])) {
        $msg = "processed {$cronResult['processed']} payloads";
        if (!empty($cronResult['youtube'])) {
            $msg .= ", {$cronResult['youtube']} YouTube URLs";
        }
        echo " OK ({$msg})\n";
    } else {
        echo " triggered (will complete in background)\n";
    }

    // Step 4: Set country for all ads of this advertiser
    if ($region !== 'ANYWHERE' && $region !== '') {
        echo "Setting country '{$region}' for all ads of this advertiser...";
        $countryUrl = $serverUrl . '/dashboard/api/ingest.php?action=set_country&token=' . urlencode($token);
        $countryResult = postJson($countryUrl, [
            'advertiser_id' => $advertiserId,
            'country'       => $region,
        ]);
        if ($countryResult && !empty($countryResult['success'])) {
            echo " OK ({$countryResult['updated']} ads)\n";
        } else {
            echo " " . ($countryResult['error'] ?? 'failed') . "\n";
        }
    }

    echo "\nDone! Ads stored and processing triggered.\n";
}

// ── Advertiser list management ───────────────────────────

function addAdvertiser($advertiserId, $advertiserName, $region, $filePath)
{
    // Check if already exists
    $lines = file_exists($filePath) ? file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
    foreach ($lines as $line) {
        if (strpos($line, '#') === 0) continue;
        $parts = explode('|', $line, 3);
        if (trim($parts[0]) === $advertiserId) {
            echo "Already in list: {$advertiserId}\n";
            return;
        }
    }

    $region = strtoupper(trim($region));
    file_put_contents($filePath, $advertiserId . '|' . $advertiserName . '|' . $region . "\n", FILE_APPEND);
    echo "Added: {$advertiserId} ({$advertiserName}) [Region: {$region}]\n";
    echo "File: {$filePath}\n";
    echo "Run 'php cli/scrape.php fetchall' to fetch all advertisers.\n";
}

function listAdvertisers($filePath)
{
    if (!file_exists($filePath)) {
        echo "No advertisers saved yet.\n";
        echo "Add with: php cli/scrape.php add AR1234... \"Company Name\"\n";
        return;
    }

    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (empty($lines)) {
        echo "Advertisers list is empty.\n";
        return;
    }

    echo str_pad("Advertiser ID", 28) . str_pad("Region", 10) . "Name\n";
    echo str_repeat("-", 80) . "\n";

    $count = 0;
    foreach ($lines as $line) {
        if (strpos($line, '#') === 0) continue;
        $parts = explode('|', $line, 3);
        $id = trim($parts[0]);
        $name = isset($parts[1]) ? trim($parts[1]) : $id;
        $region = isset($parts[2]) ? trim($parts[2]) : 'IN';
        echo str_pad($id, 28) . str_pad($region, 10) . $name . "\n";
        $count++;
    }

    echo "\nTotal: {$count} advertisers\n";
    echo "Run 'php cli/scrape.php fetchall' to fetch all.\n";
}

function fetchAllAdvertisers($filePath, $googleBase, $serverUrl, $token)
{
    if (!file_exists($filePath)) {
        echo "No advertisers file found: {$filePath}\n";
        echo "Add advertisers with: php cli/scrape.php add AR1234... \"Name\"\n";
        return;
    }

    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $advertisers = [];
    foreach ($lines as $line) {
        if (strpos($line, '#') === 0) continue;
        $parts = explode('|', $line, 3);
        $id = trim($parts[0]);
        $name = isset($parts[1]) ? trim($parts[1]) : $id;
        $region = isset($parts[2]) ? trim($parts[2]) : 'IN';
        if ($id !== '') {
            $advertisers[] = ['id' => $id, 'name' => $name, 'region' => $region];
        }
    }

    if (empty($advertisers)) {
        echo "Advertisers list is empty.\n";
        return;
    }

    echo "=== Fetching " . count($advertisers) . " advertisers ===\n";
    echo "Started: " . date('Y-m-d H:i:s') . "\n\n";

    $success = 0;
    $failed = 0;

    foreach ($advertisers as $i => $adv) {
        $num = $i + 1;
        echo "--- [{$num}/" . count($advertisers) . "] {$adv['name']} ({$adv['id']}) [Region: {$adv['region']}] ---\n";

        try {
            fetchAdvertiser($adv['id'], $adv['name'], $googleBase, $serverUrl, $token, $adv['region']);
            $success++;
        } catch (Exception $e) {
            echo "ERROR: " . $e->getMessage() . "\n";
            $failed++;
        }

        // Wait between advertisers to avoid rate limiting
        if ($i < count($advertisers) - 1) {
            echo "Waiting 5 seconds before next advertiser...\n\n";
            sleep(5);
        }
    }

    echo "\n=== Batch Complete ===\n";
    echo "Success: {$success}, Failed: {$failed}\n";
    echo "Finished: " . date('Y-m-d H:i:s') . "\n";

    // Auto-enrich YouTube URLs
    echo "\n=== Enriching YouTube URLs ===\n";
    enrichYouTubeFromCli($serverUrl, $token);
}

// ── YouTube enrichment (runs locally since server can't reach Google) ──

function enrichYouTubeFromCli($serverUrl, $token)
{
    echo "Fetching ads that need YouTube extraction...\n";

    // Get list of video ads missing YouTube URLs from server
    $apiUrl = $serverUrl . '/dashboard/api/ads.php?ad_type=video&per_page=100&sort=newest';
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $apiUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
    ]);
    $resp = curl_exec($ch);
    unset($ch);

    $data = json_decode($resp, true);
    if (!$data || empty($data['ads'])) {
        echo "No video ads found.\n";
        return;
    }

    // Find ads that have preview URLs but no YouTube URL
    $needsExtraction = [];
    foreach ($data['ads'] as $ad) {
        if (empty($ad['youtube_url']) && !empty($ad['preview_image'])) {
            $needsExtraction[] = $ad;
        }
    }

    if (empty($needsExtraction)) {
        echo "All video ads already have YouTube URLs.\n";
        // Trigger server enrichment for view counts
        echo "\nTriggering server-side enrichment (view counts + products)...\n";
        $cronUrl = $serverUrl . '/cron/process.php?token=' . urlencode($token);
        $cronResp = postJson($cronUrl, [], 300);
        if ($cronResp && !empty($cronResp['success'])) {
            echo "Enriched: {$cronResp['enriched']}, Products: {$cronResp['products']}\n";
            if (!empty($cronResp['remaining_enrich'])) {
                echo "Remaining: {$cronResp['remaining_enrich']} still need view counts. Run again.\n";
            }
        } else {
            echo "Server processing triggered (may still be running).\n";
        }
        return;
    }

    echo "Found " . count($needsExtraction) . " ads needing YouTube extraction.\n";

    // We need to get the preview/displayads URLs from the server
    // These are stored in ad_assets but not returned by ads.php
    // So we'll get them via the creative detail endpoint
    $enrichments = [];

    foreach ($needsExtraction as $i => $ad) {
        $num = $i + 1;
        echo "  [{$num}/" . count($needsExtraction) . "] {$ad['creative_id']}...";

        // Fetch creative detail to get preview URL
        $detailUrl = $serverUrl . '/dashboard/api/creative.php?id=' . urlencode($ad['creative_id']);
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $detailUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
        ]);
        $detailResp = curl_exec($ch);
        unset($ch);

        $detail = json_decode($detailResp, true);
        if (!$detail || empty($detail['assets'])) {
            echo " no assets\n";
            continue;
        }

        // Find displayads-formats preview URL
        $previewUrl = null;
        foreach ($detail['assets'] as $asset) {
            if (isset($asset['original_url']) && strpos($asset['original_url'], 'displayads-formats') !== false) {
                $previewUrl = $asset['original_url'];
                break;
            }
        }

        if (!$previewUrl) {
            echo " no preview URL\n";
            continue;
        }

        // Fetch the preview page and extract YouTube ID
        $ytId = extractYouTubeIdFromPreview($previewUrl);
        if ($ytId) {
            $youtubeUrl = 'https://www.youtube.com/watch?v=' . $ytId;
            $thumbnail = 'https://i.ytimg.com/vi/' . $ytId . '/hqdefault.jpg';
            $enrichments[] = [
                'creative_id' => $ad['creative_id'],
                'youtube_url' => $youtubeUrl,
                'thumbnail'   => $thumbnail,
            ];
            echo " YouTube: {$ytId}\n";
        } else {
            echo " no YouTube found\n";
        }

        usleep(300000); // 300ms between requests
    }

    if (empty($enrichments)) {
        echo "\nNo YouTube URLs found in preview pages.\n";
        return;
    }

    // Send enrichments to server
    echo "\nSending " . count($enrichments) . " YouTube URLs to server...\n";
    $enrichUrl = $serverUrl . '/dashboard/api/ingest.php?action=enrich_ads&token=' . urlencode($token);
    $result = postJson($enrichUrl, ['enrichments' => $enrichments]);

    if ($result && !empty($result['success'])) {
        echo "OK: {$result['message']}\n";
    } else {
        echo "Failed: " . ($result['error'] ?? 'Unknown error') . "\n";
    }

    // Trigger server-side enrichment for view counts + products
    echo "\nTriggering server-side enrichment (view counts + products)...\n";
    $cronUrl = $serverUrl . '/cron/process.php?token=' . urlencode($token);
    $cronResp = postJson($cronUrl, [], 300);
    if ($cronResp && !empty($cronResp['success'])) {
        echo "Enriched: {$cronResp['enriched']}, Products: {$cronResp['products']}\n";
        if (!empty($cronResp['remaining_enrich'])) {
            echo "Remaining: {$cronResp['remaining_enrich']} still need view counts. Run 'enrich' again.\n";
        }
    } else {
        echo "Server processing triggered (may still be running).\n";
    }
}

function extractYouTubeIdFromPreview($previewUrl)
{
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $previewUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
        CURLOPT_HTTPHEADER     => [
            'Referer: https://adstransparency.google.com/',
            'Accept: */*',
        ],
        CURLOPT_ENCODING       => 'gzip, deflate',
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    unset($ch);

    if ($response === false || $httpCode !== 200 || strlen($response) < 100) {
        return null;
    }

    if (preg_match('/ytimg\.com\/vi\/([a-zA-Z0-9_-]{11})\//', $response, $matches)) {
        return $matches[1];
    }

    if (preg_match('/youtube\.com\/(?:embed\/|watch\?v=)([a-zA-Z0-9_-]{11})/', $response, $matches)) {
        return $matches[1];
    }

    return null;
}

// ── Google API helpers ───────────────────────────────────

function initGoogleSession($googleBase, $region = 'anywhere')
{
    $regionParam = strtolower($region);
    if ($regionParam === 'anywhere' || $regionParam === '') {
        $regionParam = 'anywhere';
    }
    $cookieFile = tempnam(sys_get_temp_dir(), 'gads_');
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $googleBase . '/?region=' . $regionParam,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEJAR      => $cookieFile,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
        CURLOPT_ENCODING       => 'gzip, deflate, br',
    ]);
    curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    unset($ch);

    if ($code !== 200) {
        echo "[warn] Session init got HTTP {$code}, continuing...\n";
    }

    return $cookieFile;
}

function googleRequest($url, $body, $cookieFile)
{
    $ch = curl_init();
    $opts = [
        CURLOPT_URL            => $url,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: */*',
            'Origin: https://adstransparency.google.com',
            'Referer: https://adstransparency.google.com/',
        ],
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
        CURLOPT_ENCODING       => 'gzip, deflate, br',
        CURLOPT_FOLLOWLOCATION => true,
    ];

    if ($cookieFile && file_exists($cookieFile)) {
        $opts[CURLOPT_COOKIEFILE] = $cookieFile;
        $opts[CURLOPT_COOKIEJAR]  = $cookieFile;
    }

    curl_setopt_array($ch, $opts);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    unset($ch);

    if ($response === false || $httpCode !== 200) {
        return null;
    }
    // Check for CAPTCHA
    if (strpos($response, '<!DOCTYPE') !== false) {
        return null;
    }

    return $response;
}

function cleanupCookies($cookieFile)
{
    if ($cookieFile && file_exists($cookieFile)) {
        @unlink($cookieFile);
    }
}

// ── Server API helper ────────────────────────────────────

function postJson($url, $data, $timeout = 120)
{
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Accept: application/json',
        ],
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    unset($ch);

    if ($response === false) {
        return ['success' => false, 'error' => 'Curl error: ' . $error];
    }

    $decoded = json_decode($response, true);
    if ($decoded === null) {
        return ['success' => false, 'error' => 'Invalid response (HTTP ' . $httpCode . '): ' . substr($response, 0, 200)];
    }

    return $decoded;
}
