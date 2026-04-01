<?php
/**
 * Deep enrich: Fetch displayads-formats preview pages to discover app store URLs + YouTube videos.
 * Streams progress to browser so it doesn't time out.
 *
 * Run: /dashboard/api/enrich_store_urls.php?token=ads-intelligent-2024
 * Optional: &limit=500 (default 200, max 1000)
 * Optional: &advertiser_id=AR00744063166605950977  (target specific advertiser)
 */
header('Content-Type: text/plain; charset=utf-8');
header('X-Accel-Buffering: no');
header('Cache-Control: no-cache');
set_time_limit(600);

// Disable output buffering for live progress
if (ob_get_level()) ob_end_flush();

$basePath = dirname(dirname(__DIR__));
$config = require $basePath . '/config/config.php';

$authToken = isset($config['ingest_token']) ? $config['ingest_token'] : 'ads-intelligent-2024';
$token = isset($_GET['token']) ? $_GET['token'] : '';
if ($token !== $authToken) {
    echo "ERROR: Invalid token\n";
    exit;
}

require_once $basePath . '/src/Database.php';
require_once $basePath . '/src/AssetManager.php';
require_once $basePath . '/src/Processor.php';

$db = Database::getInstance($config['db']);
$assetManager = new AssetManager($config['storage']);
$processor = new Processor($db, $assetManager);

$limit = min(1000, max(50, (int)($_GET['limit'] ?? 200)));
$targetAdvertiser = isset($_GET['advertiser_id']) ? trim($_GET['advertiser_id']) : '';
$deepScan = isset($_GET['deep_scan']) && $_GET['deep_scan'] === '1';

function progress($msg) {
    echo $msg . "\n";
    flush();
}

// ══════════════════════════════════════════════
// DEEP SCAN MODE: Rescan ALL preview pages for an advertiser (even already-mapped)
// Usage: &deep_scan=1&advertiser_id=AR...
// ══════════════════════════════════════════════
if ($deepScan && $targetAdvertiser) {
    $advName = $db->fetchColumn(
        "SELECT COALESCE(ma.name, ?) FROM managed_advertisers ma WHERE ma.advertiser_id = ?",
        [$targetAdvertiser, $targetAdvertiser]
    );
    if (!$advName) $advName = $targetAdvertiser;

    progress("=== DEEP APP SCAN: {$advName} ===");
    progress("Advertiser: {$targetAdvertiser}");
    progress("Time: " . date('Y-m-d H:i:s'));
    progress("");

    // Current products
    $currentProducts = $db->fetchAll(
        "SELECT p.id, p.product_name, p.store_platform, p.store_url,
                (SELECT COUNT(*) FROM ad_product_map pm WHERE pm.product_id = p.id) as mapped_ads
         FROM ad_products p WHERE p.advertiser_id = ? ORDER BY mapped_ads DESC",
        [$targetAdvertiser]
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

    // Scan landing URLs for app store links
    progress("=== SCANNING LANDING URLs ===");
    $landingUrls = $db->fetchAll(
        "SELECT DISTINCT d.landing_url, d.creative_id
         FROM ad_details d INNER JOIN ads a ON d.creative_id = a.creative_id
         WHERE a.advertiser_id = ? AND d.landing_url IS NOT NULL AND d.landing_url != ''",
        [$targetAdvertiser]
    );

    $landingApps = array();
    foreach ($landingUrls as $lu) {
        $url = $lu['landing_url'];
        if (preg_match('/(?:itunes\.apple\.com|apps\.apple\.com).*?(?:\/id|id=)(\d+)/', $url, $m)) {
            $su = 'https://apps.apple.com/app/id' . $m[1];
            if (!isset($landingApps[$su])) $landingApps[$su] = array('count' => 0, 'platform' => 'ios', 'creative_ids' => array());
            $landingApps[$su]['count']++;
            $landingApps[$su]['creative_ids'][] = $lu['creative_id'];
        }
        if (preg_match('/play\.google\.com.*?id=([a-zA-Z0-9._]+)/', $url, $m)) {
            $su = 'https://play.google.com/store/apps/details?id=' . $m[1];
            if (!isset($landingApps[$su])) $landingApps[$su] = array('count' => 0, 'platform' => 'playstore', 'creative_ids' => array());
            $landingApps[$su]['count']++;
            $landingApps[$su]['creative_ids'][] = $lu['creative_id'];
        }
    }
    foreach ($landingApps as $url => $info) {
        $status = isset($knownStoreUrls[$url]) ? 'KNOWN' : 'NEW!';
        progress("  [{$status}] [{$info['platform']}] {$url} ({$info['count']} ads)");
    }
    if (empty($landingApps)) progress("  (no app store links in landing URLs)");
    progress("");

    // Get ALL ads with preview URLs (even already-mapped)
    $allAds = $db->fetchAll(
        "SELECT a.creative_id, a.ad_type, ass.original_url as preview_url
         FROM ads a
         INNER JOIN ad_assets ass ON a.creative_id = ass.creative_id
            AND ass.original_url LIKE '%displayads-formats%'
         WHERE a.advertiser_id = ?
         GROUP BY a.creative_id ORDER BY a.last_seen DESC",
        [$targetAdvertiser]
    );

    $totalAds = (int)$db->fetchColumn("SELECT COUNT(*) FROM ads WHERE advertiser_id = ?", [$targetAdvertiser]);
    progress("Total ads: {$totalAds}, With preview URLs: " . count($allAds));
    progress("=== SCANNING ALL " . count($allAds) . " PREVIEW PAGES ===");
    progress("");

    $allDiscovered = array();
    $youtubeFound = 0;
    $errors = 0;
    $noData = 0;

    foreach ($allAds as $i => $ad) {
        $num = $i + 1;
        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL => $ad['preview_url'], CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
            CURLOPT_HTTPHEADER => array('Referer: https://adstransparency.google.com/', 'Accept: */*'),
            CURLOPT_ENCODING => 'gzip, deflate',
        ));
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!$response || $httpCode !== 200) { $errors++; usleep(200000); continue; }

        $decoded = $response;
        if (strpos($response, '\\u') !== false) {
            $decoded .= "\n" . (json_decode('"' . str_replace('"', '\\"', $response) . '"') ?: '');
        }
        if (strpos($response, '\\x') !== false) {
            $decoded .= "\n" . preg_replace_callback('/\\\\x([0-9a-fA-F]{2})/', function($m) { return chr(hexdec($m[1])); }, $response);
        }

        // Extract app
        $storeUrl = null; $storePlatform = null; $appName = null; $appId = null; $appStoreType = null;
        if (preg_match('/[\'"]appId[\'"]\s*:\s*[\'"]([a-zA-Z0-9._]+)[\'"]/', $decoded, $ai)) $appId = $ai[1];
        if (preg_match('/[\'"]appStore[\'"]\s*:\s*[\'"]?(\d+)[\'"]?/', $decoded, $as)) $appStoreType = $as[1];

        if ($appId && $appStoreType === '2') {
            $storeUrl = 'https://play.google.com/store/apps/details?id=' . $appId; $storePlatform = 'playstore';
        } elseif ($appId && $appStoreType === '1') {
            $storeUrl = preg_match('/^\d+$/', $appId) ? 'https://apps.apple.com/app/id' . $appId : 'https://apps.apple.com/app/' . $appId;
            $storePlatform = 'ios';
        }
        if (!$storeUrl && preg_match('/(?:itunes\.apple\.com|apps\.apple\.com)(?:%2F|\/)+(?:[a-z]{2}(?:%2F|\/)+)?app(?:%2F|\/)+(?:[^%"\'\\\\&\s]*(?:%2F|\/)+)?id(\d+)/', $decoded, $m)) {
            $storeUrl = 'https://apps.apple.com/app/id' . $m[1]; $storePlatform = 'ios';
        }
        if (!$storeUrl && preg_match('/play\.google\.com(?:%2F|\/)+store(?:%2F|\/)+apps(?:%2F|\/)+details(?:%3F|\?)id(?:%3D|=)([a-zA-Z0-9._]+)/', $decoded, $m)) {
            $storeUrl = 'https://play.google.com/store/apps/details?id=' . $m[1]; $storePlatform = 'playstore';
        }
        if (!$storeUrl && preg_match('/itunes\.apple\.com.*?(?:\/id|%2Fid)(\d+)/', $decoded, $m)) {
            $storeUrl = 'https://apps.apple.com/app/id' . $m[1]; $storePlatform = 'ios';
        }
        if (preg_match('/[\'"]appName[\'"]\s*:\s*[\'"]([^"\']{3,200})[\'"]/', $decoded, $an)) $appName = trim($an[1]);

        if ($storeUrl) {
            if (!isset($allDiscovered[$storeUrl])) $allDiscovered[$storeUrl] = array('name' => $appName ?: $appId ?: 'unknown', 'platform' => $storePlatform, 'count' => 0, 'creative_ids' => array());
            $allDiscovered[$storeUrl]['count']++;
            $allDiscovered[$storeUrl]['creative_ids'][] = $ad['creative_id'];
            if ($appName && $allDiscovered[$storeUrl]['name'] === 'unknown') $allDiscovered[$storeUrl]['name'] = $appName;
        } else {
            $noData++;
        }

        // YouTube
        $ytId = null;
        if (preg_match('/[\'"]video_videoId[\'"]\s*:\s*[\'"]([a-zA-Z0-9_-]{11})[\'"]/', $decoded, $m)) $ytId = $m[1];
        elseif (preg_match('/ytimg\.com\/vi\/([a-zA-Z0-9_-]{11})\//', $decoded, $m)) $ytId = $m[1];
        elseif (preg_match('/youtube\.com\/(?:embed\/|watch\?v=|v\/)([a-zA-Z0-9_-]{11})/', $decoded, $m)) $ytId = $m[1];
        if ($ytId && $ad['ad_type'] === 'video') { $processor->saveYouTubeAssets($ad['creative_id'], $ytId); $youtubeFound++; }

        if ($num % 50 === 0) progress("[{$num}/" . count($allAds) . "] Apps: " . count($allDiscovered) . " | YT: {$youtubeFound} | Errors: {$errors}");
        usleep(200000);
    }

    progress("");
    progress("=== SCAN COMPLETE ===");
    progress("Scanned: " . count($allAds) . " | Unique apps: " . count($allDiscovered) . " | YouTube: {$youtubeFound} | No data: {$noData} | Errors: {$errors}");
    progress("");

    // Show all discovered apps
    progress("=== ALL APPS IN PREVIEW PAGES ===");
    uasort($allDiscovered, function($a, $b) { return $b['count'] - $a['count']; });
    $newApps = array();
    foreach ($allDiscovered as $url => $info) {
        $isNew = !isset($knownStoreUrls[$url]);
        progress("  [" . ($isNew ? '** NEW **' : 'KNOWN') . "] [{$info['platform']}] {$info['name']} ({$info['count']} ads)");
        progress("    {$url}");
        if ($isNew) $newApps[$url] = $info;
    }

    // Add apps from landing URLs not found in preview pages
    foreach ($landingApps as $url => $info) {
        if (!isset($knownStoreUrls[$url]) && !isset($allDiscovered[$url])) {
            $newApps[$url] = array('name' => 'unknown', 'platform' => $info['platform'], 'count' => $info['count'], 'creative_ids' => $info['creative_ids']);
            progress("  [** NEW from landing **] [{$info['platform']}] {$url} ({$info['count']} ads)");
        }
    }
    progress("");

    // Create products for new apps
    if (!empty($newApps)) {
        progress("=== CREATING " . count($newApps) . " NEW PRODUCTS ===");
        $created = 0;
        foreach ($newApps as $storeUrl => $info) {
            $existing = $db->fetchOne("SELECT id FROM ad_products WHERE store_url = ? AND advertiser_id = ?", [$storeUrl, $targetAdvertiser]);
            $productId = null;
            if ($existing) {
                $productId = $existing['id'];
                progress("  Exists: {$info['name']} (#{$productId})");
            } else {
                try {
                    $productId = $db->insert('ad_products', array('advertiser_id' => $targetAdvertiser, 'product_name' => $info['name'], 'product_type' => 'app', 'store_platform' => $info['platform'], 'store_url' => $storeUrl));
                    $created++;
                    progress("  Created: {$info['name']} (#{$productId})");
                } catch (Exception $e) {
                    $existing = $db->fetchOne("SELECT id FROM ad_products WHERE store_url = ?", [$storeUrl]);
                    if ($existing) $productId = $existing['id'];
                }
            }
            if ($productId && !empty($info['creative_ids'])) {
                $mapped = 0;
                foreach ($info['creative_ids'] as $cid) {
                    $exists = $db->fetchOne("SELECT id FROM ad_product_map WHERE creative_id = ? AND product_id = ?", [$cid, $productId]);
                    if (!$exists) { try { $db->insert('ad_product_map', array('creative_id' => $cid, 'product_id' => $productId)); $mapped++; } catch (Exception $e) {} }
                }
                if ($mapped) progress("    Mapped {$mapped} ads");
            }
        }
        progress("New products created: {$created}");
    } else {
        progress("No new apps discovered in preview pages or landing URLs.");
    }

    // Enrich metadata
    progress("");
    progress("=== Enriching metadata ===");
    $enriched = $processor->enrichAppMetadata();
    progress("Apps enriched: {$enriched}");
    $stmt = $db->query("UPDATE IGNORE ad_products p INNER JOIN app_metadata am ON am.product_id = p.id SET p.product_name = am.app_name WHERE am.app_name IS NOT NULL AND am.app_name != '' AND BINARY p.product_name != BINARY am.app_name AND LENGTH(am.app_name) > 2");
    progress("Names updated: " . $stmt->rowCount());

    // Developer-based discovery: find ALL apps from same developers
    progress("");
    progress("=== DISCOVERING APPS FROM SAME DEVELOPERS ===");
    $devDiscovered = $processor->discoverDeveloperApps($targetAdvertiser);
    progress("New apps from developer accounts: {$devDiscovered}");

    // Final list
    progress("");
    progress("=== FINAL APP LIST ===");
    $final = $db->fetchAll(
        "SELECT p.product_name, p.store_platform, p.store_url, COALESCE(am.app_name, p.product_name) as display_name,
                (SELECT COUNT(*) FROM ad_product_map pm WHERE pm.product_id = p.id) as mapped_ads
         FROM ad_products p LEFT JOIN app_metadata am ON am.product_id = p.id
         WHERE p.advertiser_id = ? AND p.store_platform IN ('ios','playstore') ORDER BY mapped_ads DESC",
        [$targetAdvertiser]
    );
    progress("Total apps: " . count($final));
    foreach ($final as $p) {
        progress("  [{$p['store_platform']}] {$p['display_name']} ({$p['mapped_ads']} ads)");
        progress("    {$p['store_url']}");
    }
    progress("");
    progress("Done! " . date('Y-m-d H:i:s'));
    exit;
}

// ══════════════════════════════════════════════
// NORMAL MODE: Process only unmapped ads
// ══════════════════════════════════════════════
progress("=== Store URL + Video Deep Enrichment ===");
progress("Time: " . date('Y-m-d H:i:s'));
if ($targetAdvertiser) {
    progress("Target advertiser: {$targetAdvertiser}");
}
progress("");

// Build advertiser filter
$advFilter = '';
$advParams = array();
if ($targetAdvertiser) {
    $advFilter = ' AND a.advertiser_id = ?';
    $advParams = array($targetAdvertiser);
}

// Step 1: Count ads needing store URL enrichment
$needsEnrichment = (int)$db->fetchColumn(
    "SELECT COUNT(DISTINCT a.creative_id)
     FROM ads a
     INNER JOIN ad_assets ass ON a.creative_id = ass.creative_id
        AND ass.original_url LIKE '%displayads-formats%'
     WHERE NOT EXISTS (
         SELECT 1 FROM ad_product_map pm
         INNER JOIN ad_products p ON pm.product_id = p.id
         WHERE pm.creative_id = a.creative_id
           AND p.store_platform IN ('ios', 'playstore')
           AND p.store_url IS NOT NULL AND p.store_url != '' AND p.store_url != 'not_found'
     )" . $advFilter,
    $advParams
);

// Count video ads needing YouTube extraction
$needsYouTube = (int)$db->fetchColumn(
    "SELECT COUNT(DISTINCT a.creative_id)
     FROM ads a
     INNER JOIN ad_assets ass ON a.creative_id = ass.creative_id
        AND ass.original_url LIKE '%displayads-formats%'
     WHERE a.ad_type = 'video'
       AND NOT EXISTS (
           SELECT 1 FROM ad_assets v
           WHERE v.creative_id = a.creative_id
             AND v.type = 'video'
             AND v.original_url LIKE '%youtube.com%'
       )" . $advFilter,
    $advParams
);

progress("Ads needing store URL: {$needsEnrichment}");
progress("Video ads needing YouTube: {$needsYouTube}");
progress("Processing limit: {$limit}");
progress("");

// Step 2: Get ads to process (UNION of store-needing + youtube-needing)
// We fetch ALL ads that need EITHER store URL or YouTube, then process once per preview page
$sql = "SELECT a.creative_id, a.advertiser_id, a.ad_type, ass.original_url as preview_url,
            COALESCE(ma.name, a.advertiser_id) as adv_name,
            CASE WHEN EXISTS (
                SELECT 1 FROM ad_product_map pm
                INNER JOIN ad_products p ON pm.product_id = p.id
                WHERE pm.creative_id = a.creative_id
                  AND p.store_platform IN ('ios', 'playstore')
                  AND p.store_url IS NOT NULL AND p.store_url != '' AND p.store_url != 'not_found'
            ) THEN 1 ELSE 0 END as has_store,
            CASE WHEN a.ad_type = 'video' AND NOT EXISTS (
                SELECT 1 FROM ad_assets v
                WHERE v.creative_id = a.creative_id
                  AND v.type = 'video'
                  AND v.original_url LIKE '%youtube.com%'
            ) THEN 1 ELSE 0 END as needs_youtube
        FROM ads a
        INNER JOIN ad_assets ass ON a.creative_id = ass.creative_id
            AND ass.original_url LIKE '%displayads-formats%'
        LEFT JOIN managed_advertisers ma ON a.advertiser_id = ma.advertiser_id
        WHERE (
            NOT EXISTS (
                SELECT 1 FROM ad_product_map pm
                INNER JOIN ad_products p ON pm.product_id = p.id
                WHERE pm.creative_id = a.creative_id
                  AND p.store_platform IN ('ios', 'playstore')
                  AND p.store_url IS NOT NULL AND p.store_url != '' AND p.store_url != 'not_found'
            )
            OR (
                a.ad_type = 'video' AND NOT EXISTS (
                    SELECT 1 FROM ad_assets v
                    WHERE v.creative_id = a.creative_id
                      AND v.type = 'video'
                      AND v.original_url LIKE '%youtube.com%'
                )
            )
        )" . $advFilter . "
        GROUP BY a.creative_id
        ORDER BY a.last_seen DESC
        LIMIT " . (int)$limit;

$ads = $db->fetchAll($sql, $advParams);

$total = count($ads);
progress("Fetching {$total} preview pages...");
progress("");

$foundApps = 0;
$foundYouTube = 0;
$notFound = 0;
$errors = 0;
$appsByAdvertiser = array();

foreach ($ads as $i => $ad) {
    $num = $i + 1;
    $previewUrl = $ad['preview_url'];

    // Fetch the preview page
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
        if ($num % 50 === 0) progress("[{$num}/{$total}] ... (errors: {$errors})");
        usleep(200000);
        continue;
    }

    // Decode content
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

    $foundSomething = false;

    // ── Extract Store URL (if this ad needs it) ──
    if (!$ad['has_store']) {
        $storeUrl = null;
        $storePlatform = null;
        $appName = null;

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

        // Fallback: direct URL patterns
        if (!$storeUrl && preg_match('/(?:itunes\.apple\.com|apps\.apple\.com)(?:%2F|\/)+(?:[a-z]{2}(?:%2F|\/)+)?app(?:%2F|\/)+(?:[^%"\'\\\\&\s]*(?:%2F|\/)+)?id(\d+)/', $decoded, $m)) {
            $storeUrl = 'https://apps.apple.com/app/id' . $m[1];
            $storePlatform = 'ios';
        }
        if (!$storeUrl && preg_match('/play\.google\.com(?:%2F|\/)+store(?:%2F|\/)+apps(?:%2F|\/)+details(?:%3F|\?)id(?:%3D|=)([a-zA-Z0-9._]+)/', $decoded, $m)) {
            $storeUrl = 'https://play.google.com/store/apps/details?id=' . $m[1];
            $storePlatform = 'playstore';
        }

        // Extract app name
        if (preg_match('/[\'"]appName[\'"]\s*:\s*[\'"]([^"\']{3,200})[\'"]/', $decoded, $an)) {
            $appName = trim($an[1]);
        }

        if ($storeUrl) {
            // Find or create product
            $existing = $db->fetchOne(
                "SELECT id FROM ad_products WHERE store_url = ? AND advertiser_id = ?",
                [$storeUrl, $ad['advertiser_id']]
            );

            $productId = null;
            if ($existing) {
                $productId = $existing['id'];
            } else {
                $pName = $appName ?: ($appId ?: 'Unknown');
                try {
                    $productId = $db->insert('ad_products', array(
                        'advertiser_id' => $ad['advertiser_id'],
                        'product_name' => $pName,
                        'product_type' => 'app',
                        'store_platform' => $storePlatform,
                        'store_url' => $storeUrl,
                    ));
                } catch (Exception $e) {
                    $existing = $db->fetchOne("SELECT id FROM ad_products WHERE store_url = ?", [$storeUrl]);
                    if ($existing) $productId = $existing['id'];
                }
            }

            if ($productId) {
                $mapExists = $db->fetchOne(
                    "SELECT id FROM ad_product_map WHERE creative_id = ? AND product_id = ?",
                    [$ad['creative_id'], $productId]
                );
                if (!$mapExists) {
                    try {
                        $db->insert('ad_product_map', array(
                            'creative_id' => $ad['creative_id'],
                            'product_id' => $productId,
                        ));
                    } catch (Exception $e) {}
                }
            }

            $foundApps++;
            $foundSomething = true;
            $advName = $ad['adv_name'];
            if (!isset($appsByAdvertiser[$advName])) $appsByAdvertiser[$advName] = array();
            $displayName = $appName ?: $appId;
            $appsByAdvertiser[$advName][$storeUrl] = $displayName;
        }
    }

    // ── Extract YouTube ID (if this is a video ad needing it) ──
    if ($ad['needs_youtube']) {
        $ytId = null;
        if (preg_match('/[\'"]video_videoId[\'"]\s*:\s*[\'"]([a-zA-Z0-9_-]{11})[\'"]/', $decoded, $m)) {
            $ytId = $m[1];
        } elseif (preg_match('/ytimg\.com\/vi\/([a-zA-Z0-9_-]{11})\//', $decoded, $m)) {
            $ytId = $m[1];
        } elseif (preg_match('/youtube\.com\/(?:embed\/|watch\?v=|v\/)([a-zA-Z0-9_-]{11})/', $decoded, $m)) {
            $ytId = $m[1];
        } elseif (preg_match('/youtu\.be\/([a-zA-Z0-9_-]{11})/', $decoded, $m)) {
            $ytId = $m[1];
        }

        if ($ytId) {
            $processor->saveYouTubeAssets($ad['creative_id'], $ytId);
            $foundYouTube++;
            $foundSomething = true;
        }
    }

    if (!$foundSomething) {
        $notFound++;
    }

    if ($num % 50 === 0 || ($foundApps + $foundYouTube) % 20 === 0) {
        progress("[{$num}/{$total}] Apps: {$foundApps} | YouTube: {$foundYouTube} | Errors: {$errors}");
    }

    usleep(200000); // 200ms rate limit
}

progress("");
progress("=== RESULTS ===");
progress("Total processed: {$total}");
progress("Apps found: {$foundApps}");
progress("YouTube videos found: {$foundYouTube}");
progress("No data: {$notFound}");
progress("Errors: {$errors}");
progress("");

// Show discovered apps per advertiser
if (!empty($appsByAdvertiser)) {
    progress("=== NEW APPS DISCOVERED ===");
    foreach ($appsByAdvertiser as $advName => $apps) {
        progress("");
        progress("{$advName} (" . count($apps) . " apps):");
        foreach ($apps as $url => $name) {
            progress("  - {$name}");
            progress("    {$url}");
        }
    }
}

// Step 3: Enrich new products
progress("");
progress("=== Enriching new app metadata ===");
$appEnriched = $processor->enrichAppMetadata();
progress("Apps enriched: {$appEnriched}");

// Update names
$stmt = $db->query(
    "UPDATE IGNORE ad_products p
     INNER JOIN app_metadata am ON am.product_id = p.id
     SET p.product_name = am.app_name
     WHERE am.app_name IS NOT NULL AND am.app_name != ''
       AND BINARY p.product_name != BINARY am.app_name
       AND LENGTH(am.app_name) > 2"
);
progress("Names updated: " . $stmt->rowCount());

// Remaining counts
$remainingApps = (int)$db->fetchColumn(
    "SELECT COUNT(DISTINCT a.creative_id)
     FROM ads a
     INNER JOIN ad_assets ass ON a.creative_id = ass.creative_id
        AND ass.original_url LIKE '%displayads-formats%'
     WHERE NOT EXISTS (
         SELECT 1 FROM ad_product_map pm
         INNER JOIN ad_products p ON pm.product_id = p.id
         WHERE pm.creative_id = a.creative_id
           AND p.store_platform IN ('ios', 'playstore')
           AND p.store_url IS NOT NULL AND p.store_url != '' AND p.store_url != 'not_found'
     )" . $advFilter,
    $advParams
);

$remainingYT = (int)$db->fetchColumn(
    "SELECT COUNT(DISTINCT a.creative_id)
     FROM ads a
     INNER JOIN ad_assets ass ON a.creative_id = ass.creative_id
        AND ass.original_url LIKE '%displayads-formats%'
     WHERE a.ad_type = 'video'
       AND NOT EXISTS (
           SELECT 1 FROM ad_assets v
           WHERE v.creative_id = a.creative_id
             AND v.type = 'video'
             AND v.original_url LIKE '%youtube.com%'
       )" . $advFilter,
    $advParams
);

progress("");
progress("Still needing store URL: {$remainingApps}");
progress("Still needing YouTube: {$remainingYT}");
if ($remainingApps > 0 || $remainingYT > 0) {
    progress("Run this URL again to process more.");
}

// Final stats
progress("");
progress("=== APP COUNTS PER ADVERTISER ===");
$topAdv = $db->fetchAll(
    "SELECT p.advertiser_id, COALESCE(ma.name, p.advertiser_id) as adv_name,
            COUNT(DISTINCT CASE WHEN p.store_platform IN ('ios','playstore') THEN p.id END) as app_count,
            (SELECT COUNT(*) FROM ads a WHERE a.advertiser_id = p.advertiser_id) as total_ads
     FROM ad_products p
     LEFT JOIN managed_advertisers ma ON p.advertiser_id = ma.advertiser_id
     GROUP BY p.advertiser_id
     ORDER BY app_count DESC
     LIMIT 20"
);
foreach ($topAdv as $a) {
    progress(sprintf("  %-45s %3d apps / %6d ads", $a['adv_name'], $a['app_count'], $a['total_ads']));
}

progress("");
progress("Done! " . date('Y-m-d H:i:s'));
