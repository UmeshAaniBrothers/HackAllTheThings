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

switch ($command) {
    case 'test':
        testConnection($GOOGLE_BASE, $SERVER_URL, $AUTH_TOKEN);
        break;
    case 'search':
        if (empty($arg)) die("Usage: php cli/scrape.php search \"Company Name\"\n");
        searchAdvertisers($arg, $GOOGLE_BASE);
        break;
    case 'fetch':
        if (empty($arg)) die("Usage: php cli/scrape.php fetch AR1234... [Name]\n");
        fetchAdvertiser($arg, $arg2 ?: $arg, $GOOGLE_BASE, $SERVER_URL, $AUTH_TOKEN);
        break;
    default:
        echo "Ads Intelligent - CLI Scraper\n";
        echo "==============================\n\n";
        echo "Scrapes Google locally, sends results to your server.\n\n";
        echo "Usage:\n";
        echo "  php cli/scrape.php test                          Test connections\n";
        echo "  php cli/scrape.php search \"Nike\"                 Search advertisers\n";
        echo "  php cli/scrape.php fetch AR1234... [Name]        Fetch all ads\n\n";
        echo "Server: {$SERVER_URL}\n";
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

function fetchAdvertiser($advertiserId, $advertiserName, $googleBase, $serverUrl, $token)
{
    echo "Fetching ads for: {$advertiserId} ({$advertiserName})\n\n";

    $cookieFile = initGoogleSession($googleBase);
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

    // Send to server
    echo "\nSending to server...\n";

    $ingestUrl = $serverUrl . '/dashboard/api/ingest.php?action=store_and_process&token=' . urlencode($token);
    $result = postJson($ingestUrl, [
        'advertiser_id'   => $advertiserId,
        'advertiser_name' => $advertiserName,
        'ads_count'       => $totalAds,
        'payloads'        => $allPayloads,
    ]);

    if ($result && isset($result['success']) && $result['success']) {
        echo "SUCCESS: " . $result['message'] . "\n";
        echo "Total ads in DB: " . ($result['ads_total'] ?? '?') . "\n";
        echo "Active ads: " . ($result['ads_active'] ?? '?') . "\n";
    } else {
        echo "FAILED to send to server: " . ($result['error'] ?? 'Unknown error') . "\n";
        // Save locally as backup
        $backupFile = __DIR__ . '/backup_' . $advertiserId . '_' . date('Ymd_His') . '.json';
        file_put_contents($backupFile, json_encode($allPayloads));
        echo "Payloads saved locally: {$backupFile}\n";
    }
}

// ── Google API helpers ───────────────────────────────────

function initGoogleSession($googleBase)
{
    $cookieFile = tempnam(sys_get_temp_dir(), 'gads_');
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $googleBase . '/?region=anywhere',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEJAR      => $cookieFile,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
        CURLOPT_ENCODING       => 'gzip, deflate, br',
    ]);
    curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

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
    curl_close($ch);

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

function postJson($url, $data)
{
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 120,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Accept: application/json',
        ],
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        return ['success' => false, 'error' => 'Curl error: ' . $error];
    }

    $decoded = json_decode($response, true);
    if ($decoded === null) {
        return ['success' => false, 'error' => 'Invalid response (HTTP ' . $httpCode . '): ' . substr($response, 0, 200)];
    }

    return $decoded;
}
