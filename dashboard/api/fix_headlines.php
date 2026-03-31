<?php
/**
 * Fix: Cleans bad data from ad_details and ad_products.
 * - Removes "Cannot find global object" JS error text from headlines
 * - Removes displayads-formats preview URLs saved as landing URLs
 * - Removes bad product names (JS error text)
 * - Safe to run multiple times
 */
header('Content-Type: application/json');

$basePath = dirname(dirname(__DIR__));
$config = require $basePath . '/config/config.php';

$authToken = $config['ingest_token'] ?? 'ads-intelligent-2024';
$providedToken = $_GET['token'] ?? '';
if ($providedToken !== $authToken) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid token']);
    exit;
}

require_once $basePath . '/src/Database.php';

try {
    $db = Database::getInstance($config['db']);
    $results = [];

    // 1. Clear bad headlines (JS error text captured as headline)
    $badHeadlines = (int) $db->fetchColumn(
        "SELECT COUNT(*) FROM ad_details WHERE headline LIKE '%Cannot find%' OR headline LIKE '%global object%' OR headline LIKE '%Error(%'"
    );
    if ($badHeadlines > 0) {
        $db->query(
            "UPDATE ad_details SET headline = NULL, description = NULL
             WHERE headline LIKE '%Cannot find%' OR headline LIKE '%global object%' OR headline LIKE '%Error(%'"
        );
    }
    $results['bad_headlines_cleared'] = $badHeadlines;

    // 2. Clear landing_urls that are actually preview URLs
    $badUrls = (int) $db->fetchColumn(
        "SELECT COUNT(*) FROM ad_details WHERE landing_url LIKE '%displayads-formats%'"
    );
    if ($badUrls > 0) {
        $db->query("UPDATE ad_details SET landing_url = NULL WHERE landing_url LIKE '%displayads-formats%'");
    }
    $results['bad_landing_urls_cleared'] = $badUrls;

    // 3. Clean bad product names (JS error text as product name)
    $badProducts = (int) $db->fetchColumn(
        "SELECT COUNT(*) FROM ad_products WHERE product_name LIKE '%Cannot find%' OR product_name LIKE '%global object%' OR product_name LIKE '%Error(%'"
    );
    if ($badProducts > 0) {
        // Get IDs first, then delete mappings, then delete products
        $badProductIds = $db->fetchAll(
            "SELECT id FROM ad_products WHERE product_name LIKE '%Cannot find%' OR product_name LIKE '%global object%' OR product_name LIKE '%Error(%'"
        );
        $ids = array_column($badProductIds, 'id');
        if (!empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $db->query("DELETE FROM ad_product_map WHERE product_id IN ({$placeholders})", $ids);
            $db->query("DELETE FROM ad_products WHERE id IN ({$placeholders})", $ids);
        }
    }
    $results['bad_products_deleted'] = $badProducts;

    // 4. Remove fake app products (marked as playstore/ios but no real store URL)
    $fakeApps = (int) $db->fetchColumn(
        "SELECT COUNT(*) FROM ad_products
         WHERE store_platform IN ('playstore', 'ios')
           AND (store_url IS NULL OR store_url = '' OR store_url = 'not_found')"
    );
    if ($fakeApps > 0) {
        $fakeAppIds = $db->fetchAll(
            "SELECT id FROM ad_products
             WHERE store_platform IN ('playstore', 'ios')
               AND (store_url IS NULL OR store_url = '' OR store_url = 'not_found')"
        );
        $ids = array_column($fakeAppIds, 'id');
        if (!empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $db->query("DELETE FROM ad_product_map WHERE product_id IN ({$placeholders})", $ids);
            $db->query("DELETE FROM ad_products WHERE id IN ({$placeholders})", $ids);
        }
    }
    $results['fake_apps_deleted'] = $fakeApps;

    // 5. Remove products with video/file names (e.g. GGL_PCOL_250417_OT_0_EN_PT_4.mp4)
    $videoProducts = (int) $db->fetchColumn(
        "SELECT COUNT(*) FROM ad_products
         WHERE product_name REGEXP '\\.(mp4|mov|avi|webm|mkv)$'
            OR product_name REGEXP '^GGL_'
            OR product_name REGEXP '^[A-Z0-9_]{10,}$'"
    );
    if ($videoProducts > 0) {
        $vpIds = $db->fetchAll(
            "SELECT id FROM ad_products
             WHERE product_name REGEXP '\\.(mp4|mov|avi|webm|mkv)$'
                OR product_name REGEXP '^GGL_'
                OR product_name REGEXP '^[A-Z0-9_]{10,}$'"
        );
        $ids = array_column($vpIds, 'id');
        if (!empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $db->query("DELETE FROM ad_product_map WHERE product_id IN ({$placeholders})", $ids);
            $db->query("DELETE FROM ad_products WHERE id IN ({$placeholders})", $ids);
        }
    }
    $results['video_name_products_deleted'] = $videoProducts;

    // 6. Remove products named "Unknown"
    $unknownProducts = (int) $db->fetchColumn(
        "SELECT COUNT(*) FROM ad_products WHERE product_name = 'Unknown'"
    );
    if ($unknownProducts > 0) {
        $ukIds = $db->fetchAll("SELECT id FROM ad_products WHERE product_name = 'Unknown'");
        $ids = array_column($ukIds, 'id');
        if (!empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $db->query("DELETE FROM ad_product_map WHERE product_id IN ({$placeholders})", $ids);
            $db->query("DELETE FROM ad_products WHERE id IN ({$placeholders})", $ids);
        }
    }
    $results['unknown_products_deleted'] = $unknownProducts;

    // 7. Remove duplicate products: keep only one per store_url (the one with best name)
    $dupeStoreUrls = $db->fetchAll(
        "SELECT store_url, COUNT(*) as cnt FROM ad_products
         WHERE store_url IS NOT NULL AND store_url != '' AND store_url != 'not_found'
         GROUP BY store_url HAVING cnt > 1"
    );
    $dupesDeleted = 0;
    foreach ($dupeStoreUrls as $dupe) {
        // Keep the product with the longest non-Unknown name
        $products = $db->fetchAll(
            "SELECT id, product_name FROM ad_products WHERE store_url = ? ORDER BY
             CASE WHEN product_name = 'Unknown' THEN 1 ELSE 0 END,
             LENGTH(product_name) DESC",
            [$dupe['store_url']]
        );
        $keepId = $products[0]['id'];
        $removeIds = [];
        for ($i = 1; $i < count($products); $i++) {
            $removeIds[] = $products[$i]['id'];
        }
        if (!empty($removeIds)) {
            $placeholders = implode(',', array_fill(0, count($removeIds), '?'));
            // Re-map ads from duplicates to the keeper
            $db->query("UPDATE ad_product_map SET product_id = ? WHERE product_id IN ({$placeholders})",
                array_merge([$keepId], $removeIds));
            $db->query("DELETE FROM ad_products WHERE id IN ({$placeholders})", $removeIds);
            $dupesDeleted += count($removeIds);
        }
    }
    $results['duplicate_products_merged'] = $dupesDeleted;

    // 8. Clean bad descriptions in ad_details (JS error text)
    $badDescs = (int) $db->fetchColumn(
        "SELECT COUNT(*) FROM ad_details WHERE description LIKE '%Cannot find%' OR description LIKE '%global object%'"
    );
    if ($badDescs > 0) {
        $db->query(
            "UPDATE ad_details SET description = NULL
             WHERE description LIKE '%Cannot find%' OR description LIKE '%global object%'"
        );
    }
    $results['bad_descriptions_cleared'] = $badDescs;

    echo json_encode([
        'success' => true,
        'results' => $results,
        'message' => "Cleared {$badHeadlines} headlines, {$badUrls} URLs, {$badProducts} products, {$badDescs} descriptions."
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
