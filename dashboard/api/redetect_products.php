<?php
/**
 * Re-detect products for all ads by scanning ALL raw payloads.
 * Finds store URLs that were missed due to previous LIMIT 5 constraint.
 * Run via: /dashboard/api/redetect_products.php?token=ads-intelligent-2024
 *
 * Optional: ?advertiser_id=XXXXX to re-detect for a specific advertiser
 */
header('Content-Type: application/json');
set_time_limit(600);

$basePath = dirname(dirname(__DIR__));
$config = require $basePath . '/config/config.php';

$authToken = isset($config['ingest_token']) ? $config['ingest_token'] : 'ads-intelligent-2024';
$token = isset($_GET['token']) ? $_GET['token'] : '';
if ($token !== $authToken) {
    http_response_code(403);
    echo json_encode(array('error' => 'Invalid token'));
    exit;
}

require_once $basePath . '/src/Database.php';
$db = Database::getInstance($config['db']);
$results = array();

$specificAdvertiser = isset($_GET['advertiser_id']) ? trim($_GET['advertiser_id']) : null;

// Step 1: Scan ALL raw payloads for store URLs per advertiser
$advWhere = '';
$advParams = [];
if ($specificAdvertiser) {
    $advWhere = ' WHERE advertiser_id = ?';
    $advParams = [$specificAdvertiser];
}

$payloads = $db->fetchAll(
    "SELECT advertiser_id, raw_json FROM raw_payloads{$advWhere} ORDER BY id DESC",
    $advParams
);
$results['payloads_scanned'] = count($payloads);

// Extract ALL store URLs per advertiser
$advertiserStoreUrls = array();
foreach ($payloads as $row) {
    $advId = $row['advertiser_id'];
    if (!isset($advertiserStoreUrls[$advId])) {
        $advertiserStoreUrls[$advId] = array();
    }

    $json = $row['raw_json'];

    // Play Store URLs
    if (preg_match_all('/play\.google\.com(?:\\\\\/|\/)+store(?:\\\\\/|\/)+apps(?:\\\\\/|\/)+details\?id=([^&\s"\'\\\\]+)/', $json, $matches)) {
        foreach ($matches[1] as $packageName) {
            $url = 'https://play.google.com/store/apps/details?id=' . $packageName;
            $advertiserStoreUrls[$advId][$url] = array(
                'platform' => 'playstore',
                'package' => $packageName,
                'url' => $url,
            );
        }
    }

    // App Store URLs
    if (preg_match_all('/apps\.apple\.com(?:\\\\\/|\/)+(?:[a-z]{2}(?:\\\\\/|\/)+)?app(?:\\\\\/|\/)+(?:[^\/\s"\'\\\\]+(?:\\\\\/|\/)+)?id(\d+)/', $json, $matches)) {
        foreach ($matches[1] as $appId) {
            $url = 'https://apps.apple.com/app/id' . $appId;
            $advertiserStoreUrls[$advId][$url] = array(
                'platform' => 'ios',
                'package' => 'id' . $appId,
                'url' => $url,
            );
        }
    }
}

// Step 2: Count unique store URLs found per advertiser
$totalStoreUrls = 0;
$advertiserStats = array();
foreach ($advertiserStoreUrls as $advId => $urls) {
    $count = count($urls);
    $totalStoreUrls += $count;
    $advertiserStats[$advId] = $count;
}
arsort($advertiserStats);
$results['advertisers_with_store_urls'] = count($advertiserStats);
$results['total_unique_store_urls'] = $totalStoreUrls;
$results['top_advertisers'] = array_slice($advertiserStats, 0, 10, true);

// Step 3: Create missing ad_products entries for store URLs not yet in the database
$productsCreated = 0;
$productsExisted = 0;
foreach ($advertiserStoreUrls as $advId => $urls) {
    foreach ($urls as $urlInfo) {
        // Check if product already exists for this store URL
        $existing = $db->fetchOne(
            "SELECT id FROM ad_products WHERE store_url = ? AND advertiser_id = ?",
            [$urlInfo['url'], $advId]
        );

        if ($existing) {
            $productsExisted++;
            // Ensure platform is correct
            $db->query(
                "UPDATE ad_products SET store_platform = ? WHERE id = ? AND store_platform = 'web'",
                [$urlInfo['platform'], $existing['id']]
            );
            continue;
        }

        // Create new product — use package name as temp name (enrichment will fix it)
        $packageName = $urlInfo['package'];
        // Convert package to readable name: com.example.myapp -> My App
        $nameParts = explode('.', $packageName);
        $lastPart = end($nameParts);
        $tempName = ucwords(str_replace(array('_', '-'), ' ', $lastPart));

        try {
            $db->insert('ad_products', array(
                'advertiser_id' => $advId,
                'product_name' => $tempName,
                'product_type' => 'app',
                'store_platform' => $urlInfo['platform'],
                'store_url' => $urlInfo['url'],
            ));
            $productsCreated++;
        } catch (Exception $e) {
            // Duplicate — skip
        }
    }
}
$results['products_already_existed'] = $productsExisted;
$results['new_products_created'] = $productsCreated;

// Step 4: Now re-map ads to products using store URLs found in their asset URLs
// Get ads that are mapped to 'web' products or 'Unknown' products
$adsToRemap = $db->fetchAll(
    "SELECT a.creative_id, a.advertiser_id, d.headline, d.landing_url,
            (SELECT GROUP_CONCAT(ass.original_url SEPARATOR '||')
             FROM ad_assets ass WHERE ass.creative_id = a.creative_id) as all_asset_urls,
            pm.id as map_id, pm.product_id, p.store_platform as current_platform, p.product_name as current_product
     FROM ads a
     LEFT JOIN ad_details d ON a.creative_id = d.creative_id
         AND d.id = (SELECT MAX(id) FROM ad_details WHERE creative_id = a.creative_id)
     LEFT JOIN ad_product_map pm ON a.creative_id = pm.creative_id
     LEFT JOIN ad_products p ON pm.product_id = p.id
     WHERE " . ($specificAdvertiser ? "a.advertiser_id = ?" : "1=1") . "
       AND (p.store_platform = 'web' OR p.product_name = 'Unknown' OR pm.id IS NULL)
     ORDER BY a.advertiser_id
     LIMIT 2000",
    $specificAdvertiser ? [$specificAdvertiser] : []
);
$results['ads_to_remap'] = count($adsToRemap);

$remapped = 0;
$newMapped = 0;
foreach ($adsToRemap as $ad) {
    $advId = $ad['advertiser_id'];
    $allUrls = ($ad['landing_url'] ? $ad['landing_url'] : '') . '||' . ($ad['all_asset_urls'] ? $ad['all_asset_urls'] : '');

    $matchedProductId = null;

    // Check 1: Does this ad's URLs contain a store URL?
    if ($allUrls) {
        // Play Store
        if (preg_match('/play\.google\.com(?:\\\\\/|\/)+store(?:\\\\\/|\/)+apps(?:\\\\\/|\/)+details\?id=([^&\s|"\'\\\\]+)/', $allUrls, $m)) {
            $storeUrl = 'https://play.google.com/store/apps/details?id=' . $m[1];
            $product = $db->fetchOne(
                "SELECT id FROM ad_products WHERE store_url = ? AND advertiser_id = ?",
                [$storeUrl, $advId]
            );
            if ($product) $matchedProductId = $product['id'];
        }
        // App Store
        if (!$matchedProductId && preg_match('/apps\.apple\.com(?:\\\\\/|\/)+(?:[a-z]{2}(?:\\\\\/|\/)+)?app(?:\\\\\/|\/)+(?:[^\/\s|"\'\\\\]+(?:\\\\\/|\/)+)?id(\d+)/', $allUrls, $m)) {
            $storeUrl = 'https://apps.apple.com/app/id' . $m[1];
            $product = $db->fetchOne(
                "SELECT id FROM ad_products WHERE store_url = ?",
                [$storeUrl]
            );
            if ($product) $matchedProductId = $product['id'];
        }
    }

    // Check 2: Does this ad's headline match a known app name for this advertiser?
    if (!$matchedProductId && $ad['headline']) {
        $headline = strtolower(trim($ad['headline']));
        if (strlen($headline) >= 4 && isset($advertiserStoreUrls[$advId])) {
            // Get all app products for this advertiser
            $appProducts = $db->fetchAll(
                "SELECT p.id, LOWER(COALESCE(NULLIF(am.app_name, ''), p.product_name)) as app_name
                 FROM ad_products p
                 LEFT JOIN app_metadata am ON am.product_id = p.id
                 WHERE p.advertiser_id = ? AND p.store_platform IN ('ios', 'playstore') AND p.product_name != 'Unknown'",
                [$advId]
            );
            foreach ($appProducts as $ap) {
                if (strlen($ap['app_name']) >= 4 && strpos($headline, $ap['app_name']) !== false) {
                    $matchedProductId = $ap['id'];
                    break;
                }
            }
        }
    }

    if ($matchedProductId) {
        if ($ad['map_id']) {
            // Update existing mapping
            $db->query(
                "UPDATE ad_product_map SET product_id = ? WHERE id = ?",
                [$matchedProductId, $ad['map_id']]
            );
            $remapped++;
        } else {
            // Create new mapping
            $exists = $db->fetchOne(
                "SELECT id FROM ad_product_map WHERE creative_id = ? AND product_id = ?",
                [$ad['creative_id'], $matchedProductId]
            );
            if (!$exists) {
                $db->insert('ad_product_map', array(
                    'creative_id' => $ad['creative_id'],
                    'product_id' => $matchedProductId,
                ));
                $newMapped++;
            }
        }
    }
}
$results['ads_remapped_to_apps'] = $remapped;
$results['ads_newly_mapped'] = $newMapped;

// Step 5: Count products now needing enrichment
$results['products_needing_enrichment'] = (int) $db->fetchColumn(
    "SELECT COUNT(*)
     FROM ad_products p
     LEFT JOIN app_metadata am ON am.product_id = p.id
     WHERE p.store_platform IN ('ios', 'playstore')
       AND p.store_url IS NOT NULL AND p.store_url != '' AND p.store_url != 'not_found'
       AND am.id IS NULL"
);

// Step 6: Run enrichment for newly created products
require_once $basePath . '/src/AssetManager.php';
require_once $basePath . '/src/Processor.php';
$assetManager = new AssetManager($config['storage']);
$processor = new Processor($db, $assetManager);
$enriched = $processor->enrichAppMetadata();
$results['apps_enriched'] = $enriched;

// Step 7: Update product names from new metadata
$stmt = $db->query(
    "UPDATE IGNORE ad_products p
     INNER JOIN app_metadata am ON am.product_id = p.id
     SET p.product_name = am.app_name
     WHERE am.app_name IS NOT NULL AND am.app_name != ''
       AND BINARY p.product_name != BINARY am.app_name
       AND LENGTH(am.app_name) > 2"
);
$results['names_updated'] = $stmt->rowCount();

// Step 8: Final stats per advertiser (top 10)
$topAdvProducts = $db->fetchAll(
    "SELECT p.advertiser_id, COALESCE(ma.name, p.advertiser_id) as adv_name,
            COUNT(DISTINCT CASE WHEN p.store_platform IN ('ios','playstore') THEN p.id END) as app_count,
            COUNT(DISTINCT CASE WHEN p.store_platform = 'web' THEN p.id END) as web_count,
            (SELECT COUNT(*) FROM ads a WHERE a.advertiser_id = p.advertiser_id) as total_ads
     FROM ad_products p
     LEFT JOIN managed_advertisers ma ON p.advertiser_id = ma.advertiser_id
     GROUP BY p.advertiser_id
     ORDER BY app_count DESC
     LIMIT 15"
);
$results['advertiser_product_stats'] = $topAdvProducts;

echo json_encode(array('success' => true, 'results' => $results), JSON_PRETTY_PRINT);
