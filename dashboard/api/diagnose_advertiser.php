<?php
/**
 * Diagnose: Show all data we have for an advertiser to understand why apps aren't detected.
 * /dashboard/api/diagnose_advertiser.php?token=ads-intelligent-2024&advertiser_id=AR00744063166605950977
 */
header('Content-Type: application/json');
set_time_limit(120);

$basePath = dirname(dirname(__DIR__));
$config = require $basePath . '/config/config.php';

$authToken = isset($config['ingest_token']) ? $config['ingest_token'] : 'ads-intelligent-2024';
$token = isset($_GET['token']) ? $_GET['token'] : '';
if ($token !== $authToken) {
    http_response_code(403);
    echo json_encode(array('error' => 'Invalid token'));
    exit;
}

$advId = isset($_GET['advertiser_id']) ? trim($_GET['advertiser_id']) : '';
if (!$advId) {
    echo json_encode(array('error' => 'advertiser_id required'));
    exit;
}

require_once $basePath . '/src/Database.php';
$db = Database::getInstance($config['db']);
$results = array();

// 1. All products for this advertiser
$results['products'] = $db->fetchAll(
    "SELECT p.id, p.product_name, p.product_type, p.store_platform, p.store_url,
            am.app_name, am.icon_url, am.developer_name,
            (SELECT COUNT(*) FROM ad_product_map pm WHERE pm.product_id = p.id) as mapped_ads
     FROM ad_products p
     LEFT JOIN app_metadata am ON am.product_id = p.id
     WHERE p.advertiser_id = ?
     ORDER BY mapped_ads DESC",
    [$advId]
);

// 2. Total ads
$results['total_ads'] = (int) $db->fetchColumn(
    "SELECT COUNT(*) FROM ads WHERE advertiser_id = ?", [$advId]
);

// 3. Ads with product mappings
$results['ads_with_mapping'] = (int) $db->fetchColumn(
    "SELECT COUNT(DISTINCT pm.creative_id) FROM ad_product_map pm
     INNER JOIN ads a ON a.creative_id = pm.creative_id
     WHERE a.advertiser_id = ?", [$advId]
);

// 4. Ads WITHOUT any product mapping
$results['ads_without_mapping'] = (int) $db->fetchColumn(
    "SELECT COUNT(*) FROM ads a
     WHERE a.advertiser_id = ?
       AND NOT EXISTS (SELECT 1 FROM ad_product_map pm WHERE pm.creative_id = a.creative_id)",
    [$advId]
);

// 5. Sample unmapped ads (show their headlines, landing URLs, assets)
$unmapped = $db->fetchAll(
    "SELECT a.creative_id, a.ad_type, a.first_seen, a.last_seen,
            d.headline, d.description, d.landing_url, d.cta
     FROM ads a
     LEFT JOIN ad_details d ON a.creative_id = d.creative_id
         AND d.id = (SELECT MAX(id) FROM ad_details WHERE creative_id = a.creative_id)
     WHERE a.advertiser_id = ?
       AND NOT EXISTS (SELECT 1 FROM ad_product_map pm WHERE pm.creative_id = a.creative_id)
     ORDER BY a.last_seen DESC
     LIMIT 20",
    [$advId]
);
// Add assets for each unmapped ad
foreach ($unmapped as &$ad) {
    $ad['assets'] = $db->fetchAll(
        "SELECT type, original_url FROM ad_assets WHERE creative_id = ? LIMIT 10",
        [$ad['creative_id']]
    );
}
unset($ad);
$results['unmapped_ads_sample'] = $unmapped;

// 6. All unique landing URLs for this advertiser
$results['unique_landing_urls'] = $db->fetchAll(
    "SELECT d.landing_url, COUNT(*) as ad_count
     FROM ad_details d
     INNER JOIN ads a ON d.creative_id = a.creative_id
     WHERE a.advertiser_id = ? AND d.landing_url IS NOT NULL AND d.landing_url != ''
     GROUP BY d.landing_url
     ORDER BY ad_count DESC
     LIMIT 30",
    [$advId]
);

// 7. All unique asset URLs that contain store links
$results['store_urls_in_assets'] = $db->fetchAll(
    "SELECT ass.original_url, COUNT(*) as ad_count
     FROM ad_assets ass
     INNER JOIN ads a ON ass.creative_id = a.creative_id
     WHERE a.advertiser_id = ?
       AND (ass.original_url LIKE '%play.google.com%' OR ass.original_url LIKE '%apps.apple.com%')
     GROUP BY ass.original_url
     ORDER BY ad_count DESC
     LIMIT 30",
    [$advId]
);

// 8. Sample raw payload (first 500 chars) to see format
$rawSample = $db->fetchOne(
    "SELECT LEFT(raw_json, 2000) as sample, LENGTH(raw_json) as full_length
     FROM raw_payloads WHERE advertiser_id = ? ORDER BY id DESC LIMIT 1",
    [$advId]
);
$results['raw_payload_sample'] = $rawSample;

// 9. Count raw payloads
$results['total_raw_payloads'] = (int) $db->fetchColumn(
    "SELECT COUNT(*) FROM raw_payloads WHERE advertiser_id = ?", [$advId]
);

echo json_encode(array('success' => true, 'advertiser_id' => $advId, 'results' => $results), JSON_PRETTY_PRINT);
