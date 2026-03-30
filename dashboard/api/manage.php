<?php

/**
 * API: Manage Advertisers & Pipeline
 *
 * Uses the existing `managed_advertisers` table for tracking.
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);
set_error_handler(function($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

header('Content-Type: application/json');
set_time_limit(600);

$basePath = dirname(dirname(__DIR__));
$config = require $basePath . '/config/config.php';

require_once $basePath . '/src/Database.php';
require_once $basePath . '/src/AssetManager.php';
require_once $basePath . '/src/Processor.php';

$action = $_GET['action'] ?? $_POST['action'] ?? 'status';

try {
    $db = Database::getInstance($config['db']);

    switch ($action) {
        case 'add_advertiser':     addAdvertiser($db); break;
        case 'process':            processPayloads($db, $config); break;
        case 'status':             getStatus($db); break;
        case 'remove_advertiser':  removeAdvertiser($db); break;
        case 'extract_youtube':    extractYouTubeUrls($db); break;
        default:
            echo json_encode(['success' => false, 'error' => 'Unknown action: ' . $action]);
    }

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => basename($e->getFile()) . ':' . $e->getLine(),
    ]);
}

// ── Helper Functions ──────────────────────────────────────

function addAdvertiser(Database $db): void
{
    $advertiserId = trim($_GET['advertiser_id'] ?? $_POST['advertiser_id'] ?? '');
    $advertiserName = trim($_GET['advertiser_name'] ?? $_POST['advertiser_name'] ?? '');

    if (empty($advertiserId)) {
        echo json_encode(['success' => false, 'error' => 'advertiser_id is required']);
        return;
    }
    if (empty($advertiserName)) {
        $advertiserName = $advertiserId;
    }

    $existing = $db->fetchOne(
        "SELECT id, status FROM managed_advertisers WHERE advertiser_id = ?",
        [$advertiserId]
    );

    if ($existing) {
        if (in_array($existing['status'], ['paused', 'deleted', 'error'])) {
            $db->update('managed_advertisers', [
                'status' => 'active',
                'name'   => $advertiserName,
                'error_message' => null,
            ], 'advertiser_id = ?', [$advertiserId]);
            echo json_encode(['success' => true, 'message' => 'Advertiser reactivated', 'advertiser_id' => $advertiserId]);
        } else {
            echo json_encode(['success' => true, 'message' => 'Advertiser already tracked', 'advertiser_id' => $advertiserId]);
        }
        return;
    }

    $db->insert('managed_advertisers', [
        'advertiser_id' => $advertiserId,
        'name'          => $advertiserName,
        'status'        => 'new',
    ]);

    echo json_encode(['success' => true, 'message' => 'Advertiser added', 'advertiser_id' => $advertiserId]);
}

function processPayloads(Database $db, array $config): void
{
    $assetManager = new AssetManager($config['storage'] ?? array());
    $processor = new Processor($db, $assetManager);

    ob_start();
    $processed = $processor->processAll();
    $output = ob_get_clean();

    try {
        $db->query(
            "UPDATE managed_advertisers ma SET
                ma.active_ads = (SELECT COUNT(*) FROM ads a WHERE a.advertiser_id = ma.advertiser_id AND a.status = 'active'),
                ma.total_ads = (SELECT COUNT(*) FROM ads a WHERE a.advertiser_id = ma.advertiser_id)
             WHERE ma.status IN ('active', 'new')"
        );
    } catch (Exception $e) { /* non-critical */ }

    echo json_encode([
        'success'   => true,
        'message'   => "Processed {$processed} payloads",
        'processed' => $processed,
        'log'       => $output,
    ]);
}

function getStatus(Database $db): void
{
    $advertisers = $db->fetchAll(
        "SELECT ma.*,
                (SELECT COUNT(*) FROM ads WHERE advertiser_id = ma.advertiser_id) as db_ads_count,
                (SELECT COUNT(*) FROM ads WHERE advertiser_id = ma.advertiser_id AND status='active') as db_active_ads,
                (SELECT COUNT(*) FROM raw_payloads WHERE advertiser_id = ma.advertiser_id AND processed_flag=0) as pending_payloads
         FROM managed_advertisers ma
         WHERE ma.status NOT IN ('deleted')
         ORDER BY ma.updated_at DESC"
    );

    $globalStats = $db->fetchOne(
        "SELECT COUNT(*) as total_ads,
                SUM(CASE WHEN status='active' THEN 1 ELSE 0 END) as active_ads,
                COUNT(DISTINCT advertiser_id) as total_advertisers,
                (SELECT COUNT(*) FROM raw_payloads WHERE processed_flag=0) as pending_payloads
         FROM ads"
    );

    $recentLogs = $db->fetchAll(
        "SELECT * FROM scrape_logs ORDER BY created_at DESC LIMIT 10"
    );

    echo json_encode([
        'success'      => true,
        'advertisers'  => $advertisers,
        'global_stats' => $globalStats,
        'recent_logs'  => $recentLogs,
    ]);
}

function removeAdvertiser(Database $db): void
{
    $advertiserId = trim($_GET['advertiser_id'] ?? $_POST['advertiser_id'] ?? '');
    if (empty($advertiserId)) {
        echo json_encode(['success' => false, 'error' => 'advertiser_id is required']);
        return;
    }

    $db->update('managed_advertisers', ['status' => 'paused'], 'advertiser_id = ?', [$advertiserId]);
    echo json_encode(['success' => true, 'message' => 'Advertiser paused']);
}

function extractYouTubeUrls($db)
{
    global $config;
    $assetManager = new AssetManager($config['storage'] ?? array());
    $processor = new Processor($db, $assetManager);

    ob_start();
    $extracted = $processor->extractYouTubeUrls();
    ob_get_clean();

    echo json_encode([
        'success'   => true,
        'message'   => $extracted > 0
            ? "Extracted {$extracted} YouTube URLs"
            : 'No video ads need YouTube extraction',
        'extracted' => $extracted,
    ]);
}
