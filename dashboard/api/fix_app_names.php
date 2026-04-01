<?php
/**
 * One-time fix: Update app names from store metadata + re-enrich incomplete records.
 * Run via: /dashboard/api/fix_app_names.php?token=ads-intelligent-2024
 */
header('Content-Type: application/json');
set_time_limit(300);

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
$C = 'utf8mb4_general_ci'; // force all comparisons to this collation

// Step 1: Show bad names
$badNames = $db->fetchAll(
    "SELECT p.id, p.product_name, am.app_name, p.store_url
     FROM ad_products p
     INNER JOIN app_metadata am ON am.product_id = p.id
     WHERE am.app_name IS NOT NULL AND am.app_name != ''
       AND BINARY p.product_name != BINARY am.app_name
     LIMIT 50"
);
$results['bad_names_found'] = count($badNames);
$results['bad_names_sample'] = array_slice($badNames, 0, 20);

// Step 2: Update product_name from app_metadata
$stmt = $db->query(
    "UPDATE ad_products p
     INNER JOIN app_metadata am ON am.product_id = p.id
     SET p.product_name = am.app_name
     WHERE am.app_name IS NOT NULL
       AND am.app_name != ''
       AND BINARY p.product_name != BINARY am.app_name
       AND LENGTH(am.app_name) > 2"
);
$results['names_updated_from_metadata'] = $stmt->rowCount();

// Step 3: Find incomplete app_metadata
$incomplete = $db->fetchAll(
    "SELECT p.id, p.product_name, p.store_url, am.id as am_id
     FROM ad_products p
     INNER JOIN app_metadata am ON am.product_id = p.id
     WHERE am.icon_url IS NULL AND am.rating IS NULL AND am.developer_name IS NULL AND am.downloads IS NULL
       AND p.store_platform IN ('ios', 'playstore')
       AND p.store_url IS NOT NULL AND p.store_url != '' AND p.store_url != 'not_found'
     LIMIT 100"
);
$results['incomplete_metadata_found'] = count($incomplete);
$results['incomplete_sample'] = array_slice($incomplete, 0, 10);

// Step 4: Delete incomplete metadata
$stmt = $db->query(
    "DELETE am FROM app_metadata am
     WHERE am.icon_url IS NULL AND am.rating IS NULL AND am.developer_name IS NULL AND am.downloads IS NULL"
);
$results['incomplete_metadata_deleted'] = $stmt->rowCount();

// Step 5: Count products needing enrichment
$noMeta = $db->fetchColumn(
    "SELECT COUNT(*)
     FROM ad_products p
     LEFT JOIN app_metadata am ON am.product_id = p.id
     WHERE p.store_platform IN ('ios', 'playstore')
       AND p.store_url IS NOT NULL AND p.store_url != '' AND p.store_url != 'not_found'
       AND am.id IS NULL"
);
$results['products_needing_enrichment'] = (int) $noMeta;

// Step 6: Enrich (fetch from stores)
require_once $basePath . '/src/AssetManager.php';
require_once $basePath . '/src/Processor.php';
$assetManager = new AssetManager($config['storage']);
$processor = new Processor($db, $assetManager);
$enriched = $processor->enrichAppMetadata();
$results['apps_enriched_now'] = $enriched;

// Step 7: Update names again after enrichment
$stmt = $db->query(
    "UPDATE ad_products p
     INNER JOIN app_metadata am ON am.product_id = p.id
     SET p.product_name = am.app_name
     WHERE am.app_name IS NOT NULL
       AND am.app_name != ''
       AND BINARY p.product_name != BINARY am.app_name
       AND LENGTH(am.app_name) > 2"
);
$results['names_updated_after_enrichment'] = $stmt->rowCount();

// Step 8: Normalize duplicate store_urls
$stmt = $db->query(
    "UPDATE ad_products p1
     INNER JOIN ad_products p2 ON BINARY p1.store_url = BINARY p2.store_url
       AND p1.id != p2.id
       AND p1.store_url IS NOT NULL AND p1.store_url != '' AND p1.store_url != 'not_found'
     INNER JOIN app_metadata am ON am.product_id = p2.id
       AND am.app_name IS NOT NULL AND am.app_name != ''
     SET p1.product_name = am.app_name
     WHERE BINARY p1.product_name != BINARY am.app_name"
);
$results['duplicate_names_normalized'] = $stmt->rowCount();

// Step 9: Check specific app
$specificApp = $db->fetchAll(
    "SELECT p.id, p.product_name, p.store_url, p.store_platform, am.app_name, am.icon_url, am.developer_name
     FROM ad_products p
     LEFT JOIN app_metadata am ON am.product_id = p.id
     WHERE p.store_url LIKE '%com.lark.remote.control.smart.appliance%'
     LIMIT 5"
);
$results['specific_app_check'] = $specificApp;

// Step 10: Final stats
$results['total_store_products'] = (int) $db->fetchColumn(
    "SELECT COUNT(*) FROM ad_products WHERE store_platform IN ('ios','playstore')"
);
$results['products_with_metadata'] = (int) $db->fetchColumn(
    "SELECT COUNT(*) FROM ad_products p INNER JOIN app_metadata am ON am.product_id = p.id WHERE p.store_platform IN ('ios','playstore')"
);
$results['still_needing_enrichment'] = (int) $db->fetchColumn(
    "SELECT COUNT(*) FROM ad_products p LEFT JOIN app_metadata am ON am.product_id = p.id WHERE p.store_platform IN ('ios','playstore') AND p.store_url IS NOT NULL AND p.store_url != '' AND p.store_url != 'not_found' AND am.id IS NULL"
);

// Step 11: Clean up JS code in headlines/descriptions
$stmt = $db->query(
    "UPDATE ad_details SET headline = NULL
     WHERE headline REGEXP 'function\\\\(|var [a-z]|Object\\\\.create|typeof |prototype|globalThis|querySelector|document\\\\.|window\\\\.|createElement|appendChild|innerHTML'"
);
$results['js_headlines_cleaned'] = $stmt->rowCount();

$stmt = $db->query(
    "UPDATE ad_details SET description = NULL
     WHERE description REGEXP 'function\\\\(|var [a-z]|Object\\\\.create|typeof |prototype|globalThis|querySelector|document\\\\.|window\\\\.|createElement|appendChild|innerHTML'"
);
$results['js_descriptions_cleaned'] = $stmt->rowCount();

echo json_encode(array('success' => true, 'results' => $results), JSON_PRETTY_PRINT);
