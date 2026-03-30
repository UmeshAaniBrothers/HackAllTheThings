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

    // ── Extract YouTube video IDs from preview URLs ──────
    echo "\nExtracting YouTube video IDs from preview URLs...\n";
    $videoDetails = extractVideoDetails($allPayloads);
    echo "Found " . count($videoDetails) . " YouTube video(s)\n";

    // Send to server - one page at a time to avoid timeout
    echo "\nSending to server (page by page)...\n";

    $storeUrl = $serverUrl . '/dashboard/api/ingest.php?action=store_payload&token=' . urlencode($token);
    $updateUrl = $serverUrl . '/dashboard/api/ingest.php?action=update_advertiser&token=' . urlencode($token);
    $processUrl = $serverUrl . '/dashboard/api/manage.php?action=process';
    $enrichUrl = $serverUrl . '/dashboard/api/ingest.php?action=enrich_ads&token=' . urlencode($token);

    // Step 1: Ensure advertiser exists
    $advResult = postJson($updateUrl, [
        'advertiser_id' => $advertiserId,
        'name'          => $advertiserName,
        'status'        => 'active',
    ]);
    if (!$advResult || empty($advResult['success'])) {
        echo "WARNING: Could not update advertiser record\n";
    }

    // Step 2: Send each page separately
    $sentPages = 0;
    foreach ($allPayloads as $i => $payload) {
        $pageNum = $i + 1;
        echo "  Sending page {$pageNum}/" . count($allPayloads) . "...";

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

    echo "\nSent {$sentPages}/" . count($allPayloads) . " pages to server.\n";

    if ($sentPages === 0) {
        $backupFile = __DIR__ . '/backup_' . $advertiserId . '_' . date('Ymd_His') . '.json';
        file_put_contents($backupFile, json_encode($allPayloads));
        echo "Payloads saved locally: {$backupFile}\n";
        return;
    }

    // Step 3: Trigger processing on server
    echo "Processing payloads on server...";
    $procResult = postJson($processUrl, []);
    if ($procResult && !empty($procResult['success'])) {
        echo " OK: " . ($procResult['message'] ?? 'done') . "\n";
    } else {
        echo " Note: " . ($procResult['error'] ?? 'Processing may still be running') . "\n";
    }

    // Step 4: Send enrichment data (YouTube URLs, images, text)
    if (!empty($videoDetails)) {
        echo "\nSending enrichment data (YouTube URLs, thumbnails)...\n";

        // Send in batches of 50
        $batches = array_chunk($videoDetails, 50);
        foreach ($batches as $bi => $batch) {
            $batchNum = $bi + 1;
            echo "  Batch {$batchNum}/" . count($batches) . " (" . count($batch) . " ads)...";

            $result = postJson($enrichUrl, [
                'advertiser_id' => $advertiserId,
                'enrichments'   => $batch,
            ]);

            if ($result && !empty($result['success'])) {
                echo " OK\n";
            } else {
                echo " FAILED: " . ($result['error'] ?? 'Unknown') . "\n";
            }
        }
    }

    echo "\nDone! Refresh your Manage page to see the ads.\n";
}

/**
 * Extract YouTube video IDs by fetching each video ad's preview URL.
 * The preview content.js contains YouTube thumbnail URLs like:
 * https://i1.ytimg.com/vi/VIDEO_ID/hqdefault.jpg
 */
function extractVideoDetails($allPayloads)
{
    $results = [];
    $seen = [];
    $totalVideo = 0;

    foreach ($allPayloads as $payloadStr) {
        $data = json_decode($payloadStr, true);
        if (!is_array($data) || empty($data['1'])) continue;

        foreach ($data['1'] as $ad) {
            $creativeId = $ad['2'] ?? null;
            $format = $ad['4'] ?? null;
            if (!$creativeId || isset($seen[$creativeId])) continue;
            $seen[$creativeId] = true;

            // Get preview URL from field 3.1.4
            $content = $ad['3'] ?? [];
            $previewUrl = null;
            if (is_array($content)) {
                $f31 = $content['1'] ?? $content[1] ?? null;
                if (is_array($f31)) {
                    $previewUrl = $f31['4'] ?? $f31[4] ?? null;
                }
            }

            // Only fetch preview for video ads (format=3) to get YouTube ID
            if ($format == 3 && $previewUrl && is_string($previewUrl)) {
                $totalVideo++;
                // Rate limit: don't hammer Google
                if ($totalVideo > 1) {
                    usleep(300000); // 300ms between requests
                }

                $ytId = fetchYouTubeIdFromPreview($previewUrl);
                if ($ytId) {
                    echo ".";
                    $results[] = [
                        'creative_id' => $creativeId,
                        'youtube_id'  => $ytId,
                        'youtube_url' => 'https://www.youtube.com/watch?v=' . $ytId,
                        'thumbnail'   => 'https://i.ytimg.com/vi/' . $ytId . '/hqdefault.jpg',
                    ];
                } else {
                    echo "x";
                }

                // Progress every 50
                if ($totalVideo % 50 === 0) {
                    echo " [{$totalVideo}]\n";
                }
            }
        }
    }
    echo "\n";

    return $results;
}

/**
 * Fetch a preview content.js URL and extract YouTube video ID from it.
 * Looks for ytimg.com/vi/VIDEO_ID/ pattern in the JavaScript response.
 */
function fetchYouTubeIdFromPreview($previewUrl)
{
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $previewUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
        CURLOPT_HTTPHEADER     => [
            'Referer: https://adstransparency.google.com/',
            'Accept: */*',
        ],
        CURLOPT_ENCODING       => 'gzip, deflate, br',
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $httpCode !== 200) {
        return null;
    }

    // Look for YouTube thumbnail URL: ytimg.com/vi/VIDEO_ID/
    if (preg_match('/ytimg\.com\/vi\/([a-zA-Z0-9_-]{11})\//', $response, $matches)) {
        return $matches[1];
    }

    // Also try youtube.com/embed/VIDEO_ID or youtube.com/watch?v=VIDEO_ID
    if (preg_match('/youtube\.com\/(?:embed\/|watch\?v=)([a-zA-Z0-9_-]{11})/', $response, $matches)) {
        return $matches[1];
    }

    return null;
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
