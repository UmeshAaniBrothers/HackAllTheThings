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

define('SCAN_REGIONS', [
    'IN','US','GB','CA','AU','DE','FR','JP','BR','MX',
    'IT','ES','NL','SE','PL','AT','CH','BE','NZ','SG',
    'HK','KR','TH','MY','PH','ID','ZA','SA','AE','TR',
]);

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
        $region = $argv[4] ?? 'anywhere';
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
    case 'countries':
        if (empty($arg)) die("Usage: php cli/scrape.php countries AR1234...\n");
        scanCountries($arg, $GOOGLE_BASE, $SERVER_URL, $AUTH_TOKEN);
        break;
    case 'countriesall':
        scanCountriesAll($ADVERTISERS_FILE, $GOOGLE_BASE, $SERVER_URL, $AUTH_TOKEN);
        break;
    case 'text':
        enrichTextFromCli($SERVER_URL, $AUTH_TOKEN);
        break;
    default:
        echo "Ads Intelligent - CLI Scraper\n";
        echo "==============================\n\n";
        echo "Scrapes Google locally, sends results to your server.\n\n";
        echo "Usage:\n";
        echo "  php cli/scrape.php test                              Test connections\n";
        echo "  php cli/scrape.php search \"Nike\"                     Search advertisers\n";
        echo "  php cli/scrape.php fetch AR1234... \"Name\" [region]   Fetch ads (region: IN,US,GB,anywhere)\n";
        echo "  php cli/scrape.php add AR1234... \"Name\" [region]     Add to list (default: anywhere = all regions)\n";
        echo "  php cli/scrape.php list                              Show saved advertisers\n";
        echo "  php cli/scrape.php fetchall                          Fetch ALL saved advertisers\n";
        echo "  php cli/scrape.php enrich                            Extract YouTube URLs locally\n";
        echo "  php cli/scrape.php text                              Extract ad text from preview URLs locally\n";
        echo "  php cli/scrape.php countries AR1234...               Deep scan countries for one advertiser\n";
        echo "  php cli/scrape.php countriesall                      Deep scan countries for ALL advertisers\n\n";
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

    // Step 1: Ensure advertiser exists (include region for auto-country assignment)
    $advResult = postJson($updateUrl, [
        'advertiser_id' => $advertiserId,
        'name'          => $advertiserName,
        'status'        => 'active',
        'region'        => $region,
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
        $region = isset($parts[2]) ? trim($parts[2]) : 'anywhere';
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
        $region = isset($parts[2]) ? trim($parts[2]) : 'anywhere';
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

// ── Multi-region country scanning ──────────────────────────────────

// Regions to scan - major Google Ads markets
define('SCAN_REGIONS', [
    'IN', 'US', 'GB', 'CA', 'AU', 'DE', 'FR', 'JP', 'BR', 'MX',
    'ES', 'IT', 'NL', 'SE', 'NO', 'DK', 'FI', 'PL', 'RU', 'TR',
    'SA', 'AE', 'EG', 'ZA', 'NG', 'KE', 'PK', 'BD', 'ID', 'PH',
    'MY', 'SG', 'TH', 'VN', 'KR', 'TW', 'HK', 'NZ', 'AR', 'CL',
    'CO', 'PE', 'IL', 'IE', 'PT', 'CH', 'AT', 'BE', 'CZ', 'RO',
]);

/**
 * Scan which countries an advertiser's ads appear in by querying multiple regions.
 * For each region, we just fetch the first page of creative IDs (fast scan).
 */
function scanCountries($advertiserId, $googleBase, $serverUrl, $token)
{
    echo "=== Country Scan for {$advertiserId} ===\n";
    echo "Scanning " . count(SCAN_REGIONS) . " regions (3s delay each)...\n\n";

    // Map: creative_id => [region1, region2, ...]
    $adCountries = [];
    $scanned = 0;
    $regionsWithAds = 0;

    foreach (SCAN_REGIONS as $region) {
        $scanned++;
        echo "  [{$scanned}/" . count(SCAN_REGIONS) . "] Region {$region}...";

        // Fresh session per region (Google uses session cookie for region)
        $cookieFile = initGoogleSession($googleBase, $region);

        if ($scanned > 1) {
            sleep(3); // 3 seconds between regions to avoid 429
        }

        $freqData = [
            '2' => 100,
            '3' => [
                '12' => ['1' => '', '2' => true],
                '13' => ['1' => [$advertiserId]],
            ],
            '7' => ['1' => 1],
        ];

        $url = $googleBase . '/anji/_/rpc/SearchService/SearchCreatives?authuser=0';
        $body = 'f.req=' . urlencode(json_encode($freqData));

        $resp = googleRequest($url, $body, $cookieFile);
        cleanupCookies($cookieFile);

        if ($resp === null) {
            echo " FAILED\n";
            continue;
        }

        $data = json_decode($resp, true);
        $ads = $data['1'] ?? $data[1] ?? [];

        if (!is_array($ads) || empty($ads)) {
            echo " 0 ads\n";
            continue;
        }

        $count = count($ads);
        $regionsWithAds++;
        echo " {$count} ads";

        // Check for more pages
        $hasMore = !empty($data['2'] ?? $data[2] ?? null);

        foreach ($ads as $creative) {
            $creativeId = $creative['2'] ?? $creative[2] ?? null;
            if (!$creativeId) continue;

            if (!isset($adCountries[$creativeId])) {
                $adCountries[$creativeId] = [];
            }
            $adCountries[$creativeId][] = $region;
        }

        if ($hasMore) {
            // Fetch remaining pages for this region
            $pageToken = $data['2'] ?? $data[2] ?? null;
            $extraPages = 0;
            while ($pageToken && $extraPages < 20) {
                $extraPages++;
                $freqData['4'] = $pageToken;
                $body = 'f.req=' . urlencode(json_encode($freqData));
                usleep(1000000);

                $resp = googleRequest($url, $body, $cookieFile);
                if (!$resp) break;

                $data = json_decode($resp, true);
                $moreAds = $data['1'] ?? $data[1] ?? [];
                if (empty($moreAds)) break;

                foreach ($moreAds as $creative) {
                    $creativeId = $creative['2'] ?? $creative[2] ?? null;
                    if (!$creativeId) continue;
                    if (!isset($adCountries[$creativeId])) {
                        $adCountries[$creativeId] = [];
                    }
                    $adCountries[$creativeId][] = $region;
                }
                $count += count($moreAds);

                $pageToken = $data['2'] ?? $data[2] ?? null;
            }
            echo " ({$count} total across " . ($extraPages + 1) . " pages)";
        }

        echo "\n";
    }

    echo "\n=== Results ===\n";
    echo "Regions scanned: " . count(SCAN_REGIONS) . "\n";
    echo "Regions with ads: {$regionsWithAds}\n";
    echo "Unique ads found: " . count($adCountries) . "\n\n";

    if (empty($adCountries)) {
        echo "No ads found in any region.\n";
        return;
    }

    // Show summary
    $countryCounts = [];
    foreach ($adCountries as $creativeId => $regions) {
        $regionCount = count($regions);
        echo "  {$creativeId}: " . implode(', ', $regions) . " ({$regionCount} countries)\n";
        foreach ($regions as $r) {
            $countryCounts[$r] = ($countryCounts[$r] ?? 0) + 1;
        }
    }

    echo "\nCountry distribution:\n";
    arsort($countryCounts);
    foreach ($countryCounts as $country => $count) {
        echo "  {$country}: {$count} ads\n";
    }

    // Send to server
    echo "\nSending country data to server...\n";
    $countryUrl = $serverUrl . '/dashboard/api/ingest.php?action=set_ad_countries&token=' . urlencode($token);
    $result = postJson($countryUrl, [
        'advertiser_id' => $advertiserId,
        'ad_countries'  => $adCountries,
    ], 60);

    if ($result && !empty($result['success'])) {
        echo "OK: {$result['message']}\n";
    } else {
        echo "Failed: " . ($result['error'] ?? 'Unknown error') . "\n";
    }
}

/**
 * Scan countries for ALL saved advertisers.
 */
function scanCountriesAll($filePath, $googleBase, $serverUrl, $token)
{
    if (!file_exists($filePath)) {
        die("Advertisers file not found: {$filePath}\n");
    }

    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $advertisers = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || $line[0] === '#') continue;
        $parts = explode('|', $line);
        $id = trim($parts[0]);
        if (!empty($id)) {
            $advertisers[] = $id;
        }
    }

    echo "=== Scanning countries for " . count($advertisers) . " advertisers ===\n\n";

    foreach ($advertisers as $i => $advId) {
        $num = $i + 1;
        echo "\n--- [{$num}/" . count($advertisers) . "] {$advId} ---\n";
        scanCountries($advId, $googleBase, $serverUrl, $token);

        if ($i < count($advertisers) - 1) {
            echo "\nWaiting 10 seconds before next advertiser...\n";
            sleep(10);
        }
    }

    echo "\n=== All country scans complete ===\n";
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

function enrichTextFromCli($serverUrl, $token)
{
    echo "Fetching ads that need text extraction...\n";

    // Get ads with preview URLs but missing/bad headlines from server
    $apiUrl = $serverUrl . '/dashboard/api/ads.php?per_page=200&sort=newest';
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
        echo "No ads found.\n";
        return;
    }

    // Find ads missing headlines (empty or null)
    $needsText = [];
    foreach ($data['ads'] as $ad) {
        $headline = $ad['headline'] ?? '';
        $hasBadHeadline = empty($headline)
            || stripos($headline, 'Cannot find') !== false
            || stripos($headline, 'global object') !== false;

        if ($hasBadHeadline) {
            $needsText[] = $ad;
        }
    }

    if (empty($needsText)) {
        echo "All ads already have text. Nothing to do.\n";
        return;
    }

    echo "Found " . count($needsText) . " ads needing text extraction.\n";

    $texts = [];

    foreach ($needsText as $i => $ad) {
        $num = $i + 1;
        echo "  [{$num}/" . count($needsText) . "] {$ad['creative_id']}...";

        // Get creative detail to find preview URL
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

        // Fetch preview locally and extract text
        $extracted = extractTextFromPreview($previewUrl);
        if ($extracted && (!empty($extracted['headline']) || !empty($extracted['description']))) {
            $extracted['creative_id'] = $ad['creative_id'];
            $texts[] = $extracted;
            echo " headline: " . substr($extracted['headline'] ?? '(none)', 0, 50) . "\n";
        } else {
            echo " no text found\n";
        }

        usleep(400000); // 400ms between requests
    }

    if (empty($texts)) {
        echo "\nNo text extracted from any ads.\n";
        return;
    }

    // Send to server in batches of 50
    echo "\nSending " . count($texts) . " text records to server...\n";
    $batches = array_chunk($texts, 50);
    $totalSaved = 0;

    foreach ($batches as $bi => $batch) {
        $textUrl = $serverUrl . '/dashboard/api/ingest.php?action=set_ad_text&token=' . urlencode($token);
        $result = postJson($textUrl, ['texts' => $batch]);

        if ($result && !empty($result['success'])) {
            $saved = ($result['updated'] ?? 0) + ($result['inserted'] ?? 0);
            $totalSaved += $saved;
            echo "  Batch " . ($bi + 1) . ": saved {$saved}\n";
        } else {
            echo "  Batch " . ($bi + 1) . " failed: " . ($result['error'] ?? 'Unknown error') . "\n";
        }
    }

    echo "\nDone: extracted text for {$totalSaved} ads.\n";
}

/**
 * Fetch a Google Ads preview URL locally and extract headline, description, CTA, landing URL.
 * Mirrors the extraction patterns from Processor::fetchPreviewData().
 */
function extractTextFromPreview($previewUrl)
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

    // Prepare decoded content
    $decoded = $response;
    if (strpos($response, '%20') !== false || strpos($response, '%3A') !== false) {
        $decoded .= "\n" . urldecode($response);
    }
    if (strpos($response, '\\u') !== false) {
        $decoded .= "\n" . (json_decode('"' . str_replace('"', '\\"', $response) . '"') ?: '');
    }

    $result = [];

    // ── Headlines ──
    $headlines = [];

    // appName
    if (preg_match('/[\'"]appName[\'"]\s*:\s*[\'"]([^"\']{3,200})[\'"]/', $decoded, $m)) {
        $headlines[] = $m[1];
    }
    // shortAppName
    if (preg_match('/[\'"]shortAppName[\'"]\s*:\s*[\'"]([^"\']{3,200})[\'"]/', $decoded, $m)) {
        $headlines[] = $m[1];
    }
    // headline(s) field
    if (preg_match_all('/[\'"]headlines?[\'"]\s*:\s*[\'"]([^"\']{3,200})[\'"]/', $decoded, $m)) {
        $headlines = array_merge($headlines, $m[1]);
    }
    // headlines array
    if (preg_match('/[\'"]headlines?[\'"]\s*:\s*\[([^\]]+)\]/', $decoded, $m)) {
        if (preg_match_all('/[\'"]([^"\']{3,200})[\'"]/', $m[1], $items)) {
            $headlines = array_merge($headlines, $items[1]);
        }
    }
    // title field
    if (preg_match_all('/[\'"]title[\'"]\s*:\s*[\'"]([^"\']{3,200})[\'"]/', $decoded, $m)) {
        $headlines = array_merge($headlines, $m[1]);
    }
    // HTML headings
    if (preg_match_all('/<h[1-3][^>]*>([^<]{3,200})<\/h[1-3]>/i', $decoded, $m)) {
        $headlines = array_merge($headlines, array_map('trim', $m[1]));
    }
    // Headline-class divs
    if (preg_match_all('/<(?:div|span)[^>]*class="[^"]*(?:headline|title|header)[^"]*"[^>]*>([^<]{3,200})/i', $decoded, $m)) {
        $headlines = array_merge($headlines, array_map('trim', $m[1]));
    }
    // Bold text
    if (preg_match_all('/<(?:b|strong|em)[^>]*>([^<]{5,150})<\/(?:b|strong|em)>/i', $decoded, $m)) {
        $headlines = array_merge($headlines, array_map('trim', $m[1]));
    }

    // Clean headlines
    $headlines = array_values(array_unique(array_filter($headlines, function($h) {
        $h = trim($h);
        return strlen($h) >= 3 && strlen($h) <= 200
            && !preg_match('/^https?:/', $h)
            && !preg_match('/^\{|\}$/', $h)
            && !preg_match('/Cannot find|Error|undefined|function|var |const |let |null|true|false|^\d+$/i', $h)
            && preg_match('/[a-zA-Z]{2,}/', $h);
    })));

    if (!empty($headlines)) {
        $result['headline'] = $headlines[0];
        $result['headlines_json'] = json_encode(array_slice($headlines, 0, 10));
    }

    // ── Descriptions ──
    $descriptions = [];

    if (preg_match('/[\'"]shortDescription[\'"]\s*:\s*[\'"]([^"\']{5,500})[\'"]/', $decoded, $m)) {
        $descriptions[] = $m[1];
    }
    if (preg_match('/[\'"]longDescription[\'"]\s*:\s*[\'"]([^"\']{5,2000})[\'"]/', $decoded, $m)) {
        $descriptions[] = $m[1];
    }
    if (preg_match_all('/[\'"]descriptions?[\'"]\s*:\s*[\'"]([^"\']{8,500})[\'"]/', $decoded, $m)) {
        $descriptions = array_merge($descriptions, $m[1]);
    }
    if (preg_match('/[\'"]descriptions?[\'"]\s*:\s*\[([^\]]+)\]/', $decoded, $m)) {
        if (preg_match_all('/[\'"]([^"\']{8,500})[\'"]/', $m[1], $items)) {
            $descriptions = array_merge($descriptions, $items[1]);
        }
    }
    // body/bodyText fields
    if (preg_match_all('/[\'"](?:body|bodyText|body_text)[\'"]\s*:\s*[\'"]([^"\']{8,500})[\'"]/', $decoded, $m)) {
        $descriptions = array_merge($descriptions, $m[1]);
    }
    // HTML paragraphs
    if (preg_match_all('/<p[^>]*>([^<]{10,500})<\/p>/i', $decoded, $m)) {
        $descriptions = array_merge($descriptions, array_map('trim', $m[1]));
    }

    // Clean descriptions
    $descriptions = array_values(array_unique(array_filter($descriptions, function($d) {
        $d = trim($d);
        return strlen($d) >= 8
            && !preg_match('/^https?:/', $d)
            && !preg_match('/Cannot find|Error|undefined|function|var |const |let |null|true|false/i', $d)
            && preg_match('/[a-zA-Z]{2,}/', $d);
    })));

    if (!empty($descriptions)) {
        $result['description'] = $descriptions[0];
        $result['descriptions_json'] = json_encode(array_slice($descriptions, 0, 5));
    }

    // ── CTA ──
    if (preg_match('/[\'"](?:callToAction|call_to_action|cta|ctaText|cta_text|buttonText|button_text)[\'"]\s*:\s*[\'"]([^"\']{2,50})[\'"]/', $decoded, $m)) {
        $result['cta'] = $m[1];
    }
    if (empty($result['cta']) && preg_match('/<(?:button|a)[^>]*class="[^"]*(?:cta|button|action)[^"]*"[^>]*>([^<]{2,50})/i', $decoded, $m)) {
        $result['cta'] = trim($m[1]);
    }

    // ── Landing URL ──
    if (preg_match('/(?:adurl|clickurl|click_url|redirect|landing_?url|finalUrl|final_url|destinationUrl|destination_url)["\'\s:=]+["\']?(https?[^"\'\\\\&\s]{10,500})/', $decoded, $m)) {
        $landingUrl = urldecode($m[1]);
        if (strpos($landingUrl, 'displayads-formats') === false) {
            $result['landing_url'] = $landingUrl;
        }
    }
    if (empty($result['landing_url']) && preg_match('/googleadservices\.com.*?(?:adurl|url)=(https?(?:%3A|:)[^&\s"\']{10,500})/', $decoded, $m)) {
        $landingUrl = urldecode($m[1]);
        if (strpos($landingUrl, 'displayads-formats') === false) {
            $result['landing_url'] = $landingUrl;
        }
    }

    // ── Display URL ──
    if (preg_match('/[\'"](?:displayUrl|display_url|visible_url)[\'"]\s*:\s*[\'"]([^"\']{5,100})[\'"]/', $decoded, $m)) {
        $result['display_url'] = $m[1];
    }

    return !empty($result) ? $result : null;
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
