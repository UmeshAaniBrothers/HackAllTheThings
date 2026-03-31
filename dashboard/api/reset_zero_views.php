<?php
/**
 * Reset video ads with view_count=0 back to NULL so they get retried.
 * These were rate-limited (429) during the bulk fetch.
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

$count = (int) $db->fetchColumn(
    "SELECT COUNT(*) FROM ads a
     INNER JOIN ad_assets ass ON ass.creative_id = a.creative_id AND ass.type = 'video' AND ass.original_url LIKE '%youtube.com%'
     WHERE a.ad_type = 'video' AND a.view_count = 0"
);

if ($count > 0) {
    $db->query(
        "UPDATE ads a
         INNER JOIN ad_assets ass ON ass.creative_id = a.creative_id AND ass.type = 'video' AND ass.original_url LIKE '%youtube.com%'
         SET a.view_count = NULL
         WHERE a.ad_type = 'video' AND a.view_count = 0"
    );
}

echo json_encode([
    'success' => true,
    'reset_count' => $count,
    'message' => "Reset {$count} videos back to NULL. They'll be retried on next cron run."
], JSON_PRETTY_PRINT);
