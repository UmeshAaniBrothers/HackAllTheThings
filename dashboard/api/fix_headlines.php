<?php
/**
 * Fix: Cleans bad headlines and landing URLs from ad_details.
 * - Removes "Cannot find global object" JS error text from headlines
 * - Removes displayads-formats preview URLs saved as landing URLs
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

    // Count and clear bad headlines (JS error text captured as headline)
    $badCount = (int) $db->fetchColumn(
        "SELECT COUNT(*) FROM ad_details WHERE headline LIKE '%Cannot find%' OR headline LIKE '%global object%' OR headline LIKE '%Error(%'"
    );

    if ($badCount > 0) {
        $db->query(
            "UPDATE ad_details SET headline = NULL, description = NULL
             WHERE headline LIKE '%Cannot find%' OR headline LIKE '%global object%' OR headline LIKE '%Error(%'"
        );
    }

    // Clear landing_urls that are actually preview URLs (not real landing pages)
    $badUrlCount = (int) $db->fetchColumn(
        "SELECT COUNT(*) FROM ad_details WHERE landing_url LIKE '%displayads-formats%'"
    );

    if ($badUrlCount > 0) {
        $db->query(
            "UPDATE ad_details SET landing_url = NULL
             WHERE landing_url LIKE '%displayads-formats%'"
        );
    }

    // Also clear descriptions that are just "by [channel name]" from YouTube (not real ad descriptions)
    // Keep these since they provide some info

    echo json_encode([
        'success' => true,
        'bad_headlines_cleared' => $badCount,
        'bad_landing_urls_cleared' => $badUrlCount,
        'message' => "Cleared {$badCount} bad headlines and {$badUrlCount} bad landing URLs. Run cron/process.php to re-enrich."
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
