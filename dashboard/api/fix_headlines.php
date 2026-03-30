<?php
/**
 * One-time fix: Cleans bad "Cannot find global object" headlines from ad_details
 * and resets them so enrichAdText() will re-process them with the new patterns.
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

    // Count bad headlines
    $badCount = (int) $db->fetchColumn(
        "SELECT COUNT(*) FROM ad_details WHERE headline LIKE '%Cannot find%' OR headline LIKE '%global object%'"
    );

    // Clear the bad headlines so enrichAdText() will re-process them
    $db->query(
        "UPDATE ad_details SET headline = NULL, description = NULL
         WHERE headline LIKE '%Cannot find%' OR headline LIKE '%global object%'"
    );

    // Also clear any landing_urls that are actually preview URLs (displayads-formats)
    $fixedUrls = 0;
    $db->query(
        "UPDATE ad_details SET landing_url = NULL
         WHERE landing_url LIKE '%displayads-formats%'"
    );
    $fixedUrls = (int) $db->fetchColumn("SELECT ROW_COUNT()");

    echo json_encode([
        'success' => true,
        'bad_headlines_cleared' => $badCount,
        'bad_landing_urls_cleared' => $fixedUrls,
        'message' => "Cleared {$badCount} bad headlines and {$fixedUrls} bad landing URLs. Run cron/process.php to re-enrich."
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
