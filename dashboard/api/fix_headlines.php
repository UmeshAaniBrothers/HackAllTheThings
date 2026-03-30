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

    // 4. Clean bad descriptions in ad_details (JS error text)
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
