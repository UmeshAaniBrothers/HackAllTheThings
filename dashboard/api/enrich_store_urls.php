<?php
/**
 * Deep enrich: Fetch displayads-formats preview pages to discover app store URLs.
 * Streams progress to browser so it doesn't time out.
 *
 * Run: /dashboard/api/enrich_store_urls.php?token=ads-intelligent-2024
 * Optional: &limit=500 (default 200, max 1000)
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

function progress($msg) {
    echo $msg . "\n";
    flush();
}

progress("=== Store URL Deep Enrichment ===");
progress("Time: " . date('Y-m-d H:i:s'));
progress("");

// Step 1: Count ads needing enrichment
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
     )"
);
progress("Ads needing store URL: {$needsEnrichment}");
progress("Processing limit: {$limit}");
progress("");

// Step 2: Get ads to process
$ads = $db->fetchAll(
    "SELECT a.creative_id, a.advertiser_id, ass.original_url as preview_url,
            COALESCE(ma.name, a.advertiser_id) as adv_name
     FROM ads a
     INNER JOIN ad_assets ass ON a.creative_id = ass.creative_id
        AND ass.original_url LIKE '%displayads-formats%'
     LEFT JOIN managed_advertisers ma ON a.advertiser_id = ma.advertiser_id
     WHERE NOT EXISTS (
         SELECT 1 FROM ad_product_map pm
         INNER JOIN ad_products p ON pm.product_id = p.id
         WHERE pm.creative_id = a.creative_id
           AND p.store_platform IN ('ios', 'playstore')
           AND p.store_url IS NOT NULL AND p.store_url != '' AND p.store_url != 'not_found'
     )
     GROUP BY a.creative_id
     ORDER BY a.last_seen DESC
     LIMIT " . (int)$limit
);

$total = count($ads);
progress("Fetching {$total} preview pages...");
progress("");

$found = 0;
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

    // Extract appId + appStore
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
            // Create product
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
                // Duplicate — find it
                $existing = $db->fetchOne("SELECT id FROM ad_products WHERE store_url = ?", [$storeUrl]);
                if ($existing) $productId = $existing['id'];
            }
        }

        if ($productId) {
            // Map ad to product
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

        $found++;
        $advName = $ad['adv_name'];
        if (!isset($appsByAdvertiser[$advName])) $appsByAdvertiser[$advName] = array();
        $displayName = $appName ?: $appId;
        $appsByAdvertiser[$advName][$storeUrl] = $displayName;

        if ($found % 10 === 0 || $num % 50 === 0) {
            progress("[{$num}/{$total}] Found {$found} apps so far...");
        }
    } else {
        $notFound++;
    }

    usleep(200000); // 200ms rate limit
}

progress("");
progress("=== RESULTS ===");
progress("Total processed: {$total}");
progress("Apps found: {$found}");
progress("No app data: {$notFound}");
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

// Remaining
$remaining = (int)$db->fetchColumn(
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
     )"
);
progress("");
progress("Still needing enrichment: {$remaining}");
if ($remaining > 0) {
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
