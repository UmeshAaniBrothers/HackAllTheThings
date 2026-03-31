<?php
/**
 * Fix duplicate advertisers in managed_advertisers table
 * Keeps the one with highest total_ads, removes the rest
 */
header('Content-Type: application/json');

$basePath = dirname(dirname(__DIR__));
$config = require $basePath . '/config/config.php';

if (($_GET['token'] ?? '') !== ($config['ingest_token'] ?? 'ads-intelligent-2024')) {
    http_response_code(403);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

require_once $basePath . '/src/Database.php';
$db = Database::getInstance($config['db']);

// Find duplicates
$dupes = $db->fetchAll(
    "SELECT advertiser_id, COUNT(*) as cnt
     FROM managed_advertisers
     GROUP BY advertiser_id
     HAVING cnt > 1"
);

$removed = 0;
foreach ($dupes as $d) {
    // Keep the one with most ads or earliest created
    $rows = $db->fetchAll(
        "SELECT id, advertiser_id, name, status, total_ads
         FROM managed_advertisers
         WHERE advertiser_id = ?
         ORDER BY total_ads DESC, id ASC",
        [$d['advertiser_id']]
    );

    // Keep first, delete rest
    for ($i = 1; $i < count($rows); $i++) {
        $db->query("DELETE FROM managed_advertisers WHERE id = ?", [$rows[$i]['id']]);
        $removed++;
    }
}

// Also add UNIQUE constraint if not already there
try {
    $db->query("ALTER TABLE managed_advertisers ADD UNIQUE INDEX uk_advertiser_id (advertiser_id)");
    $indexAdded = true;
} catch (Exception $e) {
    $indexAdded = $e->getMessage();
}

echo json_encode([
    'success' => true,
    'duplicates_found' => count($dupes),
    'rows_removed' => $removed,
    'unique_index' => $indexAdded,
], JSON_PRETTY_PRINT);
