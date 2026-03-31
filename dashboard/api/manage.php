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
        case 'run_all':            runFullPipeline($db, $config); break;
        case 'scrape':             triggerScrape($db); break;
        case 'analyze':            runAnalysis($db, $config); break;
        case 'search_advertisers': searchAdvertisers($db); break;
        case 'test_connection':    testConnection($db); break;
        case 'fetch_all':          fetchAllAdvertisers($db, $config); break;
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

function extractYouTubeUrls($db): void
{
    $basePath = dirname(dirname(__DIR__));
    $config = require $basePath . '/config/config.php';
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

function runFullPipeline(Database $db, array $config): void
{
    $advertiserId = trim($_GET['advertiser_id'] ?? $_POST['advertiser_id'] ?? '');
    $advertiserName = trim($_GET['advertiser_name'] ?? $_POST['advertiser_name'] ?? '');

    if (empty($advertiserId)) {
        echo json_encode(['success' => false, 'error' => 'advertiser_id is required']);
        return;
    }

    // Step 1: Ensure advertiser is tracked
    $existing = $db->fetchOne("SELECT id FROM managed_advertisers WHERE advertiser_id = ?", [$advertiserId]);
    if (!$existing) {
        $db->insert('managed_advertisers', [
            'advertiser_id' => $advertiserId,
            'name'          => $advertiserName ?: $advertiserId,
            'status'        => 'new',
        ]);
    }

    // Step 2: Process any pending payloads
    $assetManager = new AssetManager($config['storage'] ?? []);
    $processor = new Processor($db, $assetManager);

    ob_start();
    $processed = $processor->processAll();
    $textEnriched = $processor->enrichAdText();
    $ytExtracted = $processor->extractYouTubeUrls();
    $ytEnriched = $processor->enrichYouTubeMetadata();
    $storeEnriched = $processor->enrichStoreUrlsFromPreview();
    ob_get_clean();

    // Step 3: Update stats
    try {
        $db->query(
            "UPDATE managed_advertisers ma SET
                ma.active_ads = (SELECT COUNT(*) FROM ads a WHERE a.advertiser_id = ma.advertiser_id AND a.status = 'active'),
                ma.total_ads = (SELECT COUNT(*) FROM ads a WHERE a.advertiser_id = ma.advertiser_id)
             WHERE ma.advertiser_id = ?",
            [$advertiserId]
        );
    } catch (Exception $e) { /* non-critical */ }

    $stats = $db->fetchOne(
        "SELECT COUNT(*) as total,
                SUM(status = 'active') as active,
                SUM(ad_type = 'text') as text_ads,
                SUM(ad_type = 'image') as image_ads,
                SUM(ad_type = 'video') as video_ads
         FROM ads WHERE advertiser_id = ?",
        [$advertiserId]
    );

    echo json_encode([
        'success' => true,
        'message' => "Processed {$processed} payloads, {$textEnriched} text, {$ytExtracted} YouTube, {$ytEnriched} enriched, {$storeEnriched} apps",
        'stats'   => $stats,
    ]);
}

function triggerScrape(Database $db): void
{
    $advertiserId = trim($_GET['advertiser_id'] ?? $_POST['advertiser_id'] ?? '');
    if (empty($advertiserId)) {
        echo json_encode(['success' => false, 'error' => 'advertiser_id is required']);
        return;
    }

    // Server-side scraping is not supported — must use CLI tool from local Mac
    echo json_encode([
        'success' => false,
        'error'   => 'Server-side scraping is not available. Use the CLI tool from your Mac: php cli/scrape.php fetch ' . $advertiserId,
    ]);
}

function runAnalysis(Database $db, array $config): void
{
    $assetManager = new AssetManager($config['storage'] ?? []);
    $processor = new Processor($db, $assetManager);

    ob_start();
    $textEnriched = $processor->enrichAdText();
    $ytEnriched = $processor->enrichYouTubeMetadata();
    $storeEnriched = $processor->enrichStoreUrlsFromPreview();
    ob_get_clean();

    echo json_encode([
        'success' => true,
        'message' => "Analysis complete: {$textEnriched} text enriched, {$ytEnriched} YouTube enriched, {$storeEnriched} apps detected",
        'results' => [
            'text_enriched'  => $textEnriched,
            'youtube_enriched' => $ytEnriched,
            'apps_detected'  => $storeEnriched,
        ],
    ]);
}

function searchAdvertisers(Database $db): void
{
    $keyword = trim($_GET['keyword'] ?? $_POST['keyword'] ?? '');
    if (empty($keyword)) {
        echo json_encode(['success' => false, 'error' => 'keyword is required']);
        return;
    }

    // Search in managed_advertisers by name or ID
    $results = $db->fetchAll(
        "SELECT advertiser_id, name, status, total_ads
         FROM managed_advertisers
         WHERE (name LIKE ? OR advertiser_id LIKE ?)
           AND status NOT IN ('deleted')
         ORDER BY total_ads DESC
         LIMIT 20",
        ['%' . $keyword . '%', '%' . $keyword . '%']
    );

    // Also search in ads table for advertiser IDs we may not be tracking
    $untracked = $db->fetchAll(
        "SELECT DISTINCT a.advertiser_id,
                COALESCE(ma.name, a.advertiser_id) as name,
                COUNT(*) as total_ads
         FROM ads a
         LEFT JOIN managed_advertisers ma ON a.advertiser_id = ma.advertiser_id
         WHERE a.advertiser_id LIKE ?
           AND a.advertiser_id NOT IN (SELECT advertiser_id FROM managed_advertisers)
         GROUP BY a.advertiser_id
         ORDER BY total_ads DESC
         LIMIT 10",
        ['%' . $keyword . '%']
    );

    echo json_encode([
        'success' => true,
        'results' => array_merge($results, $untracked),
    ]);
}

function fetchAllAdvertisers(Database $db, array $config): void
{
    set_time_limit(300);
    $assetManager = new AssetManager($config['storage'] ?? []);
    $processor = new Processor($db, $assetManager);

    ob_start();

    $results = [];

    // Same pipeline as cron/process.php
    try { $results['ads_processed'] = $processor->processAll(); } catch (Exception $e) { $results['ads_processed'] = 'error: ' . $e->getMessage(); }
    try { $results['text_enriched'] = $processor->enrichAdText(); } catch (Exception $e) { $results['text_enriched'] = 0; }
    try { $results['youtube_extracted'] = $processor->extractYouTubeUrls(); } catch (Exception $e) { $results['youtube_extracted'] = 0; }
    try { $results['youtube_enriched'] = $processor->enrichYouTubeMetadata(); } catch (Exception $e) { $results['youtube_enriched'] = 0; }
    try { $results['store_urls'] = $processor->enrichStoreUrlsFromPreview(); } catch (Exception $e) { $results['store_urls'] = 0; }
    try { $results['countries_enriched'] = $processor->enrichCountriesFromGoogle(); } catch (Exception $e) { $results['countries_enriched'] = 0; }
    try { $results['products_detected'] = $processor->detectProducts(); } catch (Exception $e) { $results['products_detected'] = 0; }
    try { $results['products_redetected'] = $processor->redetectWebProducts(); } catch (Exception $e) { $results['products_redetected'] = 0; }
    try { $results['app_metadata'] = $processor->enrichAppMetadata(); } catch (Exception $e) { $results['app_metadata'] = 0; }

    // Update advertiser stats
    try {
        $db->query(
            "UPDATE managed_advertisers ma SET
                ma.active_ads = (SELECT COUNT(*) FROM ads a WHERE a.advertiser_id = ma.advertiser_id AND a.status = 'active'),
                ma.total_ads = (SELECT COUNT(*) FROM ads a WHERE a.advertiser_id = ma.advertiser_id)
             WHERE ma.status IN ('active', 'new')"
        );
    } catch (Exception $e) {}

    ob_get_clean();

    echo json_encode([
        'success' => true,
        'message' => 'Full pipeline complete!',
        'results' => $results,
    ]);
}

function testConnection(Database $db): void
{
    $result = $db->fetchOne("SELECT 1 as ok, NOW() as server_time");
    $adCount = (int) $db->fetchColumn("SELECT COUNT(*) FROM ads");
    $advCount = (int) $db->fetchColumn("SELECT COUNT(*) FROM managed_advertisers WHERE status != 'deleted'");

    echo json_encode([
        'success'     => true,
        'message'     => "Database OK. {$adCount} ads, {$advCount} advertisers tracked.",
        'server_time' => $result['server_time'],
        'ad_count'    => $adCount,
    ]);
}
