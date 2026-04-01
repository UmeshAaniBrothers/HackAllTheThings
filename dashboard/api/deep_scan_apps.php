<?php
/**
 * Deep Scan: Re-fetch ALL preview pages for an advertiser to find every possible app.
 * Unlike enrich_store_urls.php which skips already-mapped ads, this rescans EVERYTHING.
 * Also checks landing URLs in ad_details for app store links.
 *
 * Run: /dashboard/api/deep_scan_apps.php?token=ads-intelligent-2024&advertiser_id=AR00744063166605950977
 */
header('Content-Type: text/plain; charset=utf-8');
header('X-Accel-Buffering: no');
header('Cache-Control: no-cache');
set_time_limit(600);

if (ob_get_level()) ob_end_flush();

$basePath = dirname(dirname(__DIR__));
$config = require $basePath . '/config/config.php';

$authToken = isset($config['ingest_token']) ? $config['ingest_token'] : 'ads-intelligent-2024';
$token = isset($_GET['token']) ? $_GET['token'] : '';
if ($token !== $authToken) {
    echo "ERROR: Invalid token\n";
    exit;
}

$advId = isset($_GET['advertiser_id']) ? trim($_GET['advertiser_id']) : '';
if (!$advId) {
    echo "ERROR: advertiser_id required\n";
    exit;
}

require_once $basePath . '/src/Database.php';
require_once $basePath . '/src/AssetManager.php';
require_once $basePath . '/src/Processor.php';

$db = Database::getInstance($config['db']);
$assetManager = new AssetManager($config['storage']);
$processor = new Processor($db, $assetManager);

function progress($msg) {
    echo $msg . "\n";
    flush();
}

$advName = $db->fetchColumn(
    "SELECT COALESCE(ma.name, ?) FROM managed_advertisers ma WHERE ma.advertiser_id = ?",
    [$advId, $advId]
);
if (!$advName) $advName = $advId;

progress("=== DEEP APP SCAN: {$advName} ===");
progress("Advertiser: {$advId}");
progress("Time: " . date('Y-m-d H:i:s'));
progress("");

// Step 1: Get ALL current products
$currentProducts = $db->fetchAll(
    "SELECT p.id, p.product_name, p.store_platform, p.store_url,
            (SELECT COUNT(*) FROM ad_product_map pm WHERE pm.product_id = p.id) as mapped_ads
     FROM ad_products p
     WHERE p.advertiser_id = ?
     ORDER BY mapped_ads DESC",
    [$advId]
);

progress("=== CURRENT PRODUCTS (" . count($currentProducts) . ") ===");
$knownStoreUrls = array();
foreach ($currentProducts as $p) {
    $platform = $p['store_platform'] ?: 'unknown';
    progress("  [{$platform}] {$p['product_name']} ({$p['mapped_ads']} ads)");
    if ($p['store_url']) {
        progress("    {$p['store_url']}");
        $knownStoreUrls[$p['store_url']] = $p['product_name'];
    }
}
progress("");

// Step 2: Get ALL ads with preview URLs (even already-mapped ones)
$allAds = $db->fetchAll(
    "SELECT a.creative_id, a.ad_type, ass.original_url as preview_url
     FROM ads a
     INNER JOIN ad_assets ass ON a.creative_id = ass.creative_id
        AND ass.original_url LIKE '%displayads-formats%'
     WHERE a.advertiser_id = ?
     GROUP BY a.creative_id
     ORDER BY a.last_seen DESC",
    [$advId]
);

$totalAds = (int)$db->fetchColumn("SELECT COUNT(*) FROM ads WHERE advertiser_id = ?", [$advId]);

progress("Total ads: {$totalAds}");
progress("Ads with preview URLs: " . count($allAds));
progress("");

// Step 3: Also check ALL landing URLs for store links
progress("=== SCANNING LANDING URLs ===");
$landingUrls = $db->fetchAll(
    "SELECT DISTINCT d.landing_url, d.creative_id
     FROM ad_details d
     INNER JOIN ads a ON d.creative_id = a.creative_id
     WHERE a.advertiser_id = ?
       AND d.landing_url IS NOT NULL AND d.landing_url != ''",
    [$advId]
);

$landingApps = array();
foreach ($landingUrls as $lu) {
    $url = $lu['landing_url'];
    // Check for App Store
    if (preg_match('/(?:itunes\.apple\.com|apps\.apple\.com).*?(?:\/id|id=)(\d+)/', $url, $m)) {
        $storeUrl = 'https://apps.apple.com/app/id' . $m[1];
        if (!isset($landingApps[$storeUrl])) $landingApps[$storeUrl] = array('count' => 0, 'platform' => 'ios');
        $landingApps[$storeUrl]['count']++;
    }
    // Check for Play Store
    if (preg_match('/play\.google\.com.*?id=([a-zA-Z0-9._]+)/', $url, $m)) {
        $storeUrl = 'https://play.google.com/store/apps/details?id=' . $m[1];
        if (!isset($landingApps[$storeUrl])) $landingApps[$storeUrl] = array('count' => 0, 'platform' => 'playstore');
        $landingApps[$storeUrl]['count']++;
    }
}

foreach ($landingApps as $url => $info) {
    $status = isset($knownStoreUrls[$url]) ? 'KNOWN' : 'NEW!';
    progress("  [{$status}] [{$info['platform']}] {$url} ({$info['count']} ads)");
}
if (empty($landingApps)) {
    progress("  (no app store links found in landing URLs)");
}
progress("");

// Step 4: Re-fetch ALL preview pages and extract app data
progress("=== SCANNING ALL " . count($allAds) . " PREVIEW PAGES ===");
progress("(This re-scans even already-mapped ads to find misclassified ones)");
progress("");

$allDiscovered = array(); // storeUrl => ['name'=>..., 'platform'=>..., 'count'=>0, 'creative_ids'=>[]]
$youtubeFound = 0;
$errors = 0;
$noData = 0;

foreach ($allAds as $i => $ad) {
    $num = $i + 1;
    $previewUrl = $ad['preview_url'];

    $ch = curl_init();
    curl_setopt_array($ch, array(
        CURLOPT_URL            => $previewUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
        CURLOPT_HTTPHEADER     => array(
            'Referer: https://adstransparency.google.com/',
            'Accept: */*',
        ),
        CURLOPT_ENCODING       => 'gzip, deflate',
    ));
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (!$response || $httpCode !== 200) {
        $errors++;
        if ($num % 50 === 0) progress("[{$num}/" . count($allAds) . "] errors: {$errors}");
        usleep(200000);
        continue;
    }

    // Decode
    $decoded = $response;
    if (strpos($response, '\\u') !== false) {
        $decoded .= "\n" . (json_decode('"' . str_replace('"', '\\"', $response) . '"') ?: '');
    }
    if (strpos($response, '\\x') !== false) {
        $hexDecoded = preg_replace_callback('/\\\\x([0-9a-fA-F]{2})/', function($m) {
            return chr(hexdec($m[1]));
        }, $response);
        $decoded .= "\n" . $hexDecoded;
    }

    // Extract app data
    $storeUrl = null;
    $storePlatform = null;
    $appName = null;

    // Method 1: appId + appStore fields
    $appId = null;
    $appStoreType = null;
    if (preg_match('/[\'"]appId[\'"]\s*:\s*[\'"]([a-zA-Z0-9._]+)[\'"]/', $decoded, $ai)) {
        $appId = $ai[1];
    }
    if (preg_match('/[\'"]appStore[\'"]\s*:\s*[\'"]?(\d+)[\'"]?/', $decoded, $as)) {
        $appStoreType = $as[1];
    }

    if ($appId && $appStoreType === '2') {
        $storeUrl = 'https://play.google.com/store/apps/details?id=' . $appId;
        $storePlatform = 'playstore';
    } elseif ($appId && $appStoreType === '1') {
        if (preg_match('/^\d+$/', $appId)) {
            $storeUrl = 'https://apps.apple.com/app/id' . $appId;
        } else {
            $storeUrl = 'https://apps.apple.com/app/' . $appId;
        }
        $storePlatform = 'ios';
    }

    // Method 2: Direct URL patterns in preview content
    if (!$storeUrl && preg_match('/(?:itunes\.apple\.com|apps\.apple\.com)(?:%2F|\/)+(?:[a-z]{2}(?:%2F|\/)+)?app(?:%2F|\/)+(?:[^%"\'\\\\&\s]*(?:%2F|\/)+)?id(\d+)/', $decoded, $m)) {
        $storeUrl = 'https://apps.apple.com/app/id' . $m[1];
        $storePlatform = 'ios';
    }
    if (!$storeUrl && preg_match('/play\.google\.com(?:%2F|\/)+store(?:%2F|\/)+apps(?:%2F|\/)+details(?:%3F|\?)id(?:%3D|=)([a-zA-Z0-9._]+)/', $decoded, $m)) {
        $storeUrl = 'https://play.google.com/store/apps/details?id=' . $m[1];
        $storePlatform = 'playstore';
    }

    // Method 3: itunes.apple.com in ANY form (including googleadservices redirects)
    if (!$storeUrl && preg_match('/itunes\.apple\.com.*?(?:\/id|%2Fid)(\d+)/', $decoded, $m)) {
        $storeUrl = 'https://apps.apple.com/app/id' . $m[1];
        $storePlatform = 'ios';
    }

    // Extract app name
    if (preg_match('/[\'"]appName[\'"]\s*:\s*[\'"]([^"\']{3,200})[\'"]/', $decoded, $an)) {
        $appName = trim($an[1]);
    }

    // Extract YouTube
    $ytId = null;
    if (preg_match('/[\'"]video_videoId[\'"]\s*:\s*[\'"]([a-zA-Z0-9_-]{11})[\'"]/', $decoded, $m)) {
        $ytId = $m[1];
    } elseif (preg_match('/ytimg\.com\/vi\/([a-zA-Z0-9_-]{11})\//', $decoded, $m)) {
        $ytId = $m[1];
    } elseif (preg_match('/youtube\.com\/(?:embed\/|watch\?v=|v\/)([a-zA-Z0-9_-]{11})/', $decoded, $m)) {
        $ytId = $m[1];
    }

    if ($storeUrl) {
        if (!isset($allDiscovered[$storeUrl])) {
            $allDiscovered[$storeUrl] = array(
                'name' => $appName ?: $appId ?: 'unknown',
                'platform' => $storePlatform,
                'count' => 0,
                'creative_ids' => array(),
            );
        }
        $allDiscovered[$storeUrl]['count']++;
        $allDiscovered[$storeUrl]['creative_ids'][] = $ad['creative_id'];
        if ($appName && $allDiscovered[$storeUrl]['name'] === 'unknown') {
            $allDiscovered[$storeUrl]['name'] = $appName;
        }
    } else {
        $noData++;
    }

    if ($ytId && $ad['ad_type'] === 'video') {
        $processor->saveYouTubeAssets($ad['creative_id'], $ytId);
        $youtubeFound++;
    }

    if ($num % 50 === 0) {
        progress("[{$num}/" . count($allAds) . "] Apps: " . count($allDiscovered) . " | YouTube: {$youtubeFound} | No data: {$noData} | Errors: {$errors}");
    }

    usleep(200000); // 200ms rate limit
}

progress("");
progress("=== SCAN COMPLETE ===");
progress("Preview pages scanned: " . count($allAds));
progress("Unique apps found: " . count($allDiscovered));
progress("YouTube videos found: {$youtubeFound}");
progress("No app data: {$noData}");
progress("Errors: {$errors}");
progress("");

// Step 5: Show ALL discovered apps and identify new ones
progress("=== ALL APPS FOUND IN PREVIEW PAGES ===");
// Sort by count desc
uasort($allDiscovered, function($a, $b) { return $b['count'] - $a['count']; });

$newApps = array();
foreach ($allDiscovered as $url => $info) {
    $isNew = !isset($knownStoreUrls[$url]);
    $status = $isNew ? '** NEW **' : 'KNOWN';
    progress("  [{$status}] [{$info['platform']}] {$info['name']} ({$info['count']} ads)");
    progress("    {$url}");
    if ($isNew) {
        $newApps[$url] = $info;
    }
}
progress("");

// Step 6: Also add apps found in landing URLs but not in preview pages
foreach ($landingApps as $url => $info) {
    if (!isset($knownStoreUrls[$url]) && !isset($allDiscovered[$url])) {
        $newApps[$url] = array(
            'name' => 'unknown',
            'platform' => $info['platform'],
            'count' => $info['count'],
            'creative_ids' => array(),
            'source' => 'landing_url',
        );
        progress("  [** NEW from landing URL **] [{$info['platform']}] {$url} ({$info['count']} ads)");
    }
}

// Step 7: Create products and mappings for NEW apps
if (!empty($newApps)) {
    progress("=== CREATING " . count($newApps) . " NEW APP PRODUCTS ===");
    $created = 0;

    foreach ($newApps as $storeUrl => $info) {
        // Find or create the product
        $existing = $db->fetchOne(
            "SELECT id FROM ad_products WHERE store_url = ? AND advertiser_id = ?",
            [$storeUrl, $advId]
        );

        $productId = null;
        if ($existing) {
            $productId = $existing['id'];
            progress("  Already exists: {$info['name']} (product #{$productId})");
        } else {
            try {
                $productId = $db->insert('ad_products', array(
                    'advertiser_id' => $advId,
                    'product_name' => $info['name'],
                    'product_type' => 'app',
                    'store_platform' => $info['platform'],
                    'store_url' => $storeUrl,
                ));
                $created++;
                progress("  Created: {$info['name']} (product #{$productId})");
            } catch (Exception $e) {
                $existing = $db->fetchOne("SELECT id FROM ad_products WHERE store_url = ?", [$storeUrl]);
                if ($existing) $productId = $existing['id'];
                progress("  Duplicate handled: {$info['name']}");
            }
        }

        // Map all creative IDs to this product
        if ($productId && !empty($info['creative_ids'])) {
            $mapped = 0;
            foreach ($info['creative_ids'] as $cid) {
                $mapExists = $db->fetchOne(
                    "SELECT id FROM ad_product_map WHERE creative_id = ? AND product_id = ?",
                    [$cid, $productId]
                );
                if (!$mapExists) {
                    try {
                        $db->insert('ad_product_map', array(
                            'creative_id' => $cid,
                            'product_id' => $productId,
                        ));
                        $mapped++;
                    } catch (Exception $e) {}
                }
            }
            if ($mapped > 0) {
                progress("    Mapped {$mapped} ads to this product");
            }
        }
    }
    progress("");
    progress("New products created: {$created}");
} else {
    progress("No new apps discovered.");
}

// Step 8: Enrich metadata for new products
progress("");
progress("=== Enriching app metadata ===");
$enriched = $processor->enrichAppMetadata();
progress("Apps enriched: {$enriched}");

$stmt = $db->query(
    "UPDATE IGNORE ad_products p
     INNER JOIN app_metadata am ON am.product_id = p.id
     SET p.product_name = am.app_name
     WHERE am.app_name IS NOT NULL AND am.app_name != ''
       AND BINARY p.product_name != BINARY am.app_name
       AND LENGTH(am.app_name) > 2"
);
progress("Names updated: " . $stmt->rowCount());

// Final summary
progress("");
progress("=== FINAL APP LIST ===");
$finalProducts = $db->fetchAll(
    "SELECT p.id, p.product_name, p.store_platform, p.store_url,
            COALESCE(am.app_name, p.product_name) as display_name,
            am.icon_url,
            (SELECT COUNT(*) FROM ad_product_map pm WHERE pm.product_id = p.id) as mapped_ads
     FROM ad_products p
     LEFT JOIN app_metadata am ON am.product_id = p.id
     WHERE p.advertiser_id = ? AND p.store_platform IN ('ios', 'playstore')
     ORDER BY mapped_ads DESC",
    [$advId]
);

progress("Total apps: " . count($finalProducts));
foreach ($finalProducts as $p) {
    progress("  [{$p['store_platform']}] {$p['display_name']} ({$p['mapped_ads']} ads)");
    progress("    {$p['store_url']}");
}

progress("");
progress("Done! " . date('Y-m-d H:i:s'));
