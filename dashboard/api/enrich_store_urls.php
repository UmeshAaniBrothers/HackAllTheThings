<?php
/**
 * Deep enrich: Fetch displayads-formats preview pages to discover app store URLs.
 * This is the KEY to finding more apps — the preview pages contain appId + appStore data.
 *
 * Run: /dashboard/api/enrich_store_urls.php?token=ads-intelligent-2024
 * Optional: &limit=500 (default 300, max 1000)
 * Optional: &advertiser_id=AR... (only enrich for a specific advertiser)
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
require_once $basePath . '/src/AssetManager.php';
require_once $basePath . '/src/Processor.php';

$db = Database::getInstance($config['db']);
$assetManager = new AssetManager($config['storage']);
$processor = new Processor($db, $assetManager);

$limit = min(1000, max(50, (int)($_GET['limit'] ?? 300)));
$specificAdvertiser = isset($_GET['advertiser_id']) ? trim($_GET['advertiser_id']) : null;
$results = array();

// Step 1: Count ads needing store URL enrichment
$advFilter = $specificAdvertiser ? " AND a.advertiser_id = '" . addslashes($specificAdvertiser) . "'" : '';
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
     )" . $advFilter
);
$results['ads_needing_store_url'] = $needsEnrichment;
$results['processing_limit'] = $limit;

// Step 2: Run enrichment
$enriched = $processor->enrichStoreUrlsFromPreview($limit);
$results['store_urls_found'] = $enriched;

// Step 3: Run product detection for newly mapped ads
$detected = $processor->detectProducts();
$results['products_detected'] = $detected;

// Step 4: Run app metadata enrichment
$appEnriched = $processor->enrichAppMetadata();
$results['apps_enriched'] = $appEnriched;

// Step 5: Update names
$stmt = $db->query(
    "UPDATE IGNORE ad_products p
     INNER JOIN app_metadata am ON am.product_id = p.id
     SET p.product_name = am.app_name
     WHERE am.app_name IS NOT NULL AND am.app_name != ''
       AND BINARY p.product_name != BINARY am.app_name
       AND LENGTH(am.app_name) > 2"
);
$results['names_updated'] = $stmt->rowCount();

// Step 6: Remaining count
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
     )" . $advFilter
);
$results['ads_still_needing_store_url'] = $remaining;

// Step 7: Stats
$topAdv = $db->fetchAll(
    "SELECT p.advertiser_id, COALESCE(ma.name, p.advertiser_id) as adv_name,
            COUNT(DISTINCT CASE WHEN p.store_platform IN ('ios','playstore') THEN p.id END) as app_count,
            (SELECT COUNT(*) FROM ads a WHERE a.advertiser_id = p.advertiser_id) as total_ads
     FROM ad_products p
     LEFT JOIN managed_advertisers ma ON p.advertiser_id = ma.advertiser_id
     GROUP BY p.advertiser_id
     ORDER BY app_count DESC
     LIMIT 15"
);
$results['advertiser_app_counts'] = $topAdv;

echo json_encode(array('success' => true, 'results' => $results), JSON_PRETTY_PRINT);
