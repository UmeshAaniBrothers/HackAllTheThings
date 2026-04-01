<?php
/**
 * Re-detect products: scan ad_assets + ad_details for store URLs, find ALL apps per advertiser.
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

// ── Step 1: Find ALL store URLs from ad_assets (Play Store & App Store links in asset URLs) ──
$advFilter = $specificAdvertiser ? ' AND a.advertiser_id = ?' : '';
$advParams = $specificAdvertiser ? [$specificAdvertiser] : [];

$assetStoreUrls = $db->fetchAll(
    "SELECT DISTINCT a.advertiser_id, ass.original_url
     FROM ad_assets ass
     INNER JOIN ads a ON ass.creative_id = a.creative_id
     WHERE (ass.original_url LIKE '%play.google.com/store/apps/details%'
         OR ass.original_url LIKE '%apps.apple.com%/app/%')" . $advFilter,
    $advParams
);
$results['store_urls_in_assets'] = count($assetStoreUrls);

// ── Step 2: Find ALL store URLs from ad_details landing_url ──
$landingStoreUrls = $db->fetchAll(
    "SELECT DISTINCT a.advertiser_id, d.landing_url
     FROM ad_details d
     INNER JOIN ads a ON d.creative_id = a.creative_id
     WHERE (d.landing_url LIKE '%play.google.com/store/apps/details%'
         OR d.landing_url LIKE '%apps.apple.com%/app/%')" . $advFilter,
    $advParams
);
$results['store_urls_in_landing'] = count($landingStoreUrls);

// ── Step 3: Also check existing ad_products for store URLs we already know ──
$existingProducts = $db->fetchAll(
    "SELECT DISTINCT advertiser_id, store_url, store_platform
     FROM ad_products
     WHERE store_platform IN ('ios', 'playstore')
       AND store_url IS NOT NULL AND store_url != '' AND store_url != 'not_found'"
       . ($specificAdvertiser ? ' AND advertiser_id = ?' : ''),
    $specificAdvertiser ? [$specificAdvertiser] : []
);
$results['existing_app_products'] = count($existingProducts);

// ── Step 4: Consolidate ALL store URLs per advertiser ──
$advertiserApps = array(); // [advId => [url => [platform, package]]]

// From assets
foreach ($assetStoreUrls as $row) {
    $url = $row['original_url'];
    $advId = $row['advertiser_id'];
    $parsed = parseStoreUrl($url);
    if ($parsed) {
        if (!isset($advertiserApps[$advId])) $advertiserApps[$advId] = array();
        $advertiserApps[$advId][$parsed['url']] = $parsed;
    }
}

// From landing URLs
foreach ($landingStoreUrls as $row) {
    $url = $row['landing_url'];
    $advId = $row['advertiser_id'];
    $parsed = parseStoreUrl($url);
    if ($parsed) {
        if (!isset($advertiserApps[$advId])) $advertiserApps[$advId] = array();
        $advertiserApps[$advId][$parsed['url']] = $parsed;
    }
}

// From existing products
foreach ($existingProducts as $row) {
    $advId = $row['advertiser_id'];
    if (!isset($advertiserApps[$advId])) $advertiserApps[$advId] = array();
    $advertiserApps[$advId][$row['store_url']] = array(
        'platform' => $row['store_platform'],
        'url' => $row['store_url'],
        'package' => '',
    );
}

$totalApps = 0;
foreach ($advertiserApps as $urls) $totalApps += count($urls);
$results['total_unique_store_urls_found'] = $totalApps;
$results['advertisers_with_apps'] = count($advertiserApps);

// ── Step 5: Create missing ad_products entries ──
$productsCreated = 0;
$productsExisted = 0;
$platformFixed = 0;
foreach ($advertiserApps as $advId => $urls) {
    foreach ($urls as $urlInfo) {
        $storeUrl = $urlInfo['url'];
        $platform = $urlInfo['platform'];

        // Check if product already exists
        $existing = $db->fetchOne(
            "SELECT id, store_platform FROM ad_products WHERE store_url = ? AND advertiser_id = ?",
            [$storeUrl, $advId]
        );

        if ($existing) {
            $productsExisted++;
            if ($existing['store_platform'] === 'web') {
                $db->query(
                    "UPDATE ad_products SET store_platform = ?, product_type = 'app' WHERE id = ?",
                    [$platform, $existing['id']]
                );
                $platformFixed++;
            }
            continue;
        }

        // Also check without advertiser (might be from a different advertiser mapping)
        $existingAny = $db->fetchOne(
            "SELECT id FROM ad_products WHERE store_url = ?",
            [$storeUrl]
        );
        if ($existingAny) {
            $productsExisted++;
            continue;
        }

        // Create — use package name as temp name
        $tempName = 'Unknown';
        if ($platform === 'playstore' && preg_match('/id=([^&]+)/', $storeUrl, $m)) {
            $parts = explode('.', $m[1]);
            $tempName = ucwords(str_replace(array('_', '-'), ' ', end($parts)));
        } elseif ($platform === 'ios' && preg_match('/id(\d+)/', $storeUrl, $m)) {
            $tempName = 'iOS App ' . $m[1];
        }

        try {
            $db->insert('ad_products', array(
                'advertiser_id' => $advId,
                'product_name' => $tempName,
                'product_type' => 'app',
                'store_platform' => $platform,
                'store_url' => $storeUrl,
            ));
            $productsCreated++;
        } catch (Exception $e) {
            // Duplicate — skip
        }
    }
}
$results['products_already_existed'] = $productsExisted;
$results['new_products_created'] = $productsCreated;
$results['platforms_fixed_to_app'] = $platformFixed;

// ── Step 6: Map ads to products by matching asset URLs / landing URLs to store URLs ──
// Find ads that have store URLs in their assets but are NOT mapped to the right product
$adsWithStoreAssets = $db->fetchAll(
    "SELECT a.creative_id, a.advertiser_id, ass.original_url
     FROM ads a
     INNER JOIN ad_assets ass ON ass.creative_id = a.creative_id
     WHERE (ass.original_url LIKE '%play.google.com/store/apps/details%'
         OR ass.original_url LIKE '%apps.apple.com%/app/%')" . $advFilter . "
     LIMIT 5000",
    $advParams
);

$adsMapped = 0;
foreach ($adsWithStoreAssets as $row) {
    $parsed = parseStoreUrl($row['original_url']);
    if (!$parsed) continue;

    $product = $db->fetchOne(
        "SELECT id FROM ad_products WHERE store_url = ? AND advertiser_id = ?",
        [$parsed['url'], $row['advertiser_id']]
    );
    if (!$product) continue;

    $exists = $db->fetchOne(
        "SELECT id FROM ad_product_map WHERE creative_id = ? AND product_id = ?",
        [$row['creative_id'], $product['id']]
    );
    if (!$exists) {
        try {
            $db->insert('ad_product_map', array(
                'creative_id' => $row['creative_id'],
                'product_id' => $product['id'],
            ));
            $adsMapped++;
        } catch (Exception $e) {}
    }
}

// Also map by landing_url
$adsWithStoreLanding = $db->fetchAll(
    "SELECT a.creative_id, a.advertiser_id, d.landing_url
     FROM ads a
     INNER JOIN ad_details d ON d.creative_id = a.creative_id
     WHERE (d.landing_url LIKE '%play.google.com/store/apps/details%'
         OR d.landing_url LIKE '%apps.apple.com%/app/%')" . $advFilter . "
     LIMIT 5000",
    $advParams
);

foreach ($adsWithStoreLanding as $row) {
    $parsed = parseStoreUrl($row['landing_url']);
    if (!$parsed) continue;

    $product = $db->fetchOne(
        "SELECT id FROM ad_products WHERE store_url = ? AND advertiser_id = ?",
        [$parsed['url'], $row['advertiser_id']]
    );
    if (!$product) continue;

    $exists = $db->fetchOne(
        "SELECT id FROM ad_product_map WHERE creative_id = ? AND product_id = ?",
        [$row['creative_id'], $product['id']]
    );
    if (!$exists) {
        try {
            $db->insert('ad_product_map', array(
                'creative_id' => $row['creative_id'],
                'product_id' => $product['id'],
            ));
            $adsMapped++;
        } catch (Exception $e) {}
    }
}
$results['ads_mapped_by_store_url'] = $adsMapped;

// ── Step 7: Run enrichment for new products ──
$results['products_needing_enrichment'] = (int) $db->fetchColumn(
    "SELECT COUNT(*)
     FROM ad_products p
     LEFT JOIN app_metadata am ON am.product_id = p.id
     WHERE p.store_platform IN ('ios', 'playstore')
       AND p.store_url IS NOT NULL AND p.store_url != '' AND p.store_url != 'not_found'
       AND am.id IS NULL"
);

require_once $basePath . '/src/AssetManager.php';
require_once $basePath . '/src/Processor.php';
$assetManager = new AssetManager($config['storage']);
$processor = new Processor($db, $assetManager);
$enriched = $processor->enrichAppMetadata();
$results['apps_enriched'] = $enriched;

// Update names from enriched metadata
$stmt = $db->query(
    "UPDATE IGNORE ad_products p
     INNER JOIN app_metadata am ON am.product_id = p.id
     SET p.product_name = am.app_name
     WHERE am.app_name IS NOT NULL AND am.app_name != ''
       AND BINARY p.product_name != BINARY am.app_name
       AND LENGTH(am.app_name) > 2"
);
$results['names_updated'] = $stmt->rowCount();

// ── Step 8: Stats ──
$topAdv = $db->fetchAll(
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
$results['advertiser_product_stats'] = $topAdv;

// Specific advertiser detail if requested
if ($specificAdvertiser) {
    $advProducts = $db->fetchAll(
        "SELECT p.id, p.product_name, p.store_platform, p.store_url,
                am.app_name, am.icon_url,
                (SELECT COUNT(*) FROM ad_product_map pm WHERE pm.product_id = p.id) as mapped_ads
         FROM ad_products p
         LEFT JOIN app_metadata am ON am.product_id = p.id
         WHERE p.advertiser_id = ?
         ORDER BY mapped_ads DESC",
        [$specificAdvertiser]
    );
    $results['advertiser_products'] = $advProducts;
}

echo json_encode(array('success' => true, 'results' => $results), JSON_PRETTY_PRINT);

/**
 * Parse a store URL into normalized form.
 */
function parseStoreUrl($url) {
    if (!$url) return null;

    // Play Store
    if (preg_match('/play\.google\.com\/store\/apps\/details\?id=([^&\s"\']+)/', $url, $m)) {
        return array(
            'platform' => 'playstore',
            'package' => $m[1],
            'url' => 'https://play.google.com/store/apps/details?id=' . $m[1],
        );
    }

    // App Store
    if (preg_match('/apps\.apple\.com\/(?:[a-z]{2}\/)?app\/(?:[^\/]+\/)?id(\d+)/', $url, $m)) {
        return array(
            'platform' => 'ios',
            'package' => 'id' . $m[1],
            'url' => 'https://apps.apple.com/app/id' . $m[1],
        );
    }

    return null;
}
