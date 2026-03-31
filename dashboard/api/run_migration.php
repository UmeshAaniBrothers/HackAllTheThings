<?php
/**
 * Run database migration: Add headline_source column
 * One-time use, safe to run multiple times
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

    // 1. Add headline_source column if not exists
    $colExists = $db->fetchOne(
        "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ad_details' AND COLUMN_NAME = 'headline_source'"
    );

    if (!$colExists) {
        $db->query("ALTER TABLE ad_details ADD COLUMN headline_source VARCHAR(20) DEFAULT NULL AFTER tracking_ids_json");
        $results['column_added'] = true;
    } else {
        $results['column_added'] = 'already_exists';
    }

    // 2. Backfill: mark YouTube-sourced headlines
    $ytUpdated = 0;
    $ytMatches = $db->fetchAll(
        "SELECT d.id FROM ad_details d
         INNER JOIN ad_assets ass ON ass.creative_id = d.creative_id AND ass.type = 'video' AND ass.original_url LIKE '%youtube.com%'
         INNER JOIN youtube_metadata ym ON CONCAT('https://www.youtube.com/watch?v=', ym.video_id) = ass.original_url
         WHERE d.headline IS NOT NULL AND d.headline = ym.title AND d.headline_source IS NULL"
    );
    if (!empty($ytMatches)) {
        $ids = array_column($ytMatches, 'id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $db->query("UPDATE ad_details SET headline_source = 'youtube' WHERE id IN ({$placeholders})", $ids);
        $ytUpdated = count($ids);
    }
    $results['youtube_headlines_marked'] = $ytUpdated;

    // 3. Mark remaining non-null headlines as 'ad'
    $adUpdated = (int) $db->fetchColumn(
        "SELECT COUNT(*) FROM ad_details WHERE headline IS NOT NULL AND headline != '' AND headline_source IS NULL"
    );
    if ($adUpdated > 0) {
        $db->query("UPDATE ad_details SET headline_source = 'ad' WHERE headline IS NOT NULL AND headline != '' AND headline_source IS NULL");
    }
    $results['ad_headlines_marked'] = $adUpdated;

    // 4. Summary counts
    $results['total_youtube'] = (int) $db->fetchColumn("SELECT COUNT(*) FROM ad_details WHERE headline_source = 'youtube'");
    $results['total_ad'] = (int) $db->fetchColumn("SELECT COUNT(*) FROM ad_details WHERE headline_source = 'ad'");
    $results['total_null'] = (int) $db->fetchColumn("SELECT COUNT(*) FROM ad_details WHERE headline_source IS NULL");

    echo json_encode(['success' => true, 'results' => $results], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
