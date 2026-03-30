<?php

/**
 * API: Manage Advertisers & Pipeline
 *
 * Uses the existing `managed_advertisers` table for tracking.
 */

// Catch all PHP errors and return as JSON instead of HTML
ini_set('display_errors', 0);
error_reporting(E_ALL);
set_error_handler(function($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

header('Content-Type: application/json');
set_time_limit(300);

$basePath = dirname(dirname(__DIR__));
$config = require $basePath . '/config/config.php';

require_once $basePath . '/src/Database.php';
require_once $basePath . '/src/Scraper.php';
require_once $basePath . '/src/AssetManager.php';
require_once $basePath . '/src/Processor.php';

$action = $_GET['action'] ?? $_POST['action'] ?? 'status';

try {
    $db = Database::getInstance($config['db']);

    switch ($action) {
        case 'add_advertiser':  addAdvertiser($db); break;
        case 'scrape':          scrapeAdvertiser($db, $config); break;
        case 'process':         processPayloads($db, $config); break;
        case 'analyze':         runAnalysis($db, $basePath); break;
        case 'run_all':         runFullPipeline($db, $config, $basePath); break;
        case 'status':          getStatus($db); break;
        case 'remove_advertiser': removeAdvertiser($db); break;
        case 'test_connection':   testApiConnection($db, $config); break;
        case 'search_advertisers': searchAdvertisers($db, $config); break;
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

function scrapeAdvertiser(Database $db, array $config): void
{
    $advertiserId = trim($_GET['advertiser_id'] ?? $_POST['advertiser_id'] ?? '');

    if (empty($advertiserId)) {
        echo json_encode(['success' => false, 'error' => 'advertiser_id is required']);
        return;
    }

    // Mark as fetching
    $db->query(
        "UPDATE managed_advertisers SET status = 'fetching' WHERE advertiser_id = ?",
        [$advertiserId]
    );

    $scraper = new Scraper($db, $config['scraper']);

    ob_start();
    try {
        $ads = $scraper->fetchAdvertiser($advertiserId);
    } catch (Exception $e) {
        ob_get_clean();
        $db->query(
            "UPDATE managed_advertisers SET status = 'error', error_message = ? WHERE advertiser_id = ?",
            [$e->getMessage(), $advertiserId]
        );
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        return;
    }
    $output = ob_get_clean();
    $scraperErrors = $scraper->getErrors();

    $adsCount = count($ads);

    // If zero ads and there were errors, report as failure
    if ($adsCount === 0 && !empty($scraperErrors)) {
        $errorMsg = implode('; ', $scraperErrors);
        $db->query(
            "UPDATE managed_advertisers SET status = 'error', error_message = ? WHERE advertiser_id = ?",
            [$errorMsg, $advertiserId]
        );
        echo json_encode([
            'success' => false,
            'error'   => 'Fetch failed: ' . $errorMsg,
            'log'     => $output,
        ]);
        return;
    }

    // Update managed_advertisers
    $db->query(
        "UPDATE managed_advertisers SET
            status = 'active',
            total_ads = ?,
            last_fetch_ads = ?,
            last_fetched_at = NOW(),
            fetch_count = fetch_count + 1,
            error_message = NULL
         WHERE advertiser_id = ?",
        [$adsCount, $adsCount, $advertiserId]
    );

    // Log scrape
    $db->insert('scrape_logs', [
        'advertiser_id' => $advertiserId,
        'ads_found'     => $adsCount,
        'new_ads'       => $adsCount,
        'status'        => $adsCount > 0 ? 'success' : 'partial',
    ]);

    // Also log to advertiser_fetch_log if table exists
    try {
        $db->insert('advertiser_fetch_log', [
            'advertiser_id' => $advertiserId,
            'status'        => $adsCount > 0 ? 'success' : 'failed',
            'ads_found'     => $adsCount,
            'pages_fetched' => 0,
        ]);
    } catch (Exception $e) { /* table may not exist */ }

    echo json_encode([
        'success'   => true,
        'message'   => "Scraped {$adsCount} ads",
        'ads_found' => $adsCount,
        'log'       => $output,
    ]);
}

function processPayloads(Database $db, array $config): void
{
    $assetManager = new AssetManager($config['storage']);
    $processor = new Processor($db, $assetManager);

    ob_start();
    $processed = $processor->processAll();
    $output = ob_get_clean();

    // Update active_ads counts in managed_advertisers
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

function runAnalysis(Database $db, string $basePath): void
{
    $results = [];

    $classes = [
        'ChangeDetector', 'TrendAnalyzer', 'CreativeFingerprint',
        'AdvertiserProfile', 'AIIntelligence', 'TaggingSystem',
    ];
    foreach ($classes as $cls) {
        $file = $basePath . '/src/' . $cls . '.php';
        if (file_exists($file)) require_once $file;
    }

    if (class_exists('ChangeDetector')) {
        try { $results['changes'] = (new ChangeDetector($db))->runAll(); }
        catch (Exception $e) { $results['changes'] = ['error' => $e->getMessage()]; }
    }
    if (class_exists('TrendAnalyzer')) {
        try { $results['trends'] = (new TrendAnalyzer($db))->analyzeAll(); }
        catch (Exception $e) { $results['trends'] = ['error' => $e->getMessage()]; }
    }
    if (class_exists('CreativeFingerprint')) {
        try { $results['fingerprints'] = (new CreativeFingerprint($db))->processAll(); }
        catch (Exception $e) { $results['fingerprints'] = ['error' => $e->getMessage()]; }
    }
    if (class_exists('AdvertiserProfile')) {
        try { $results['profiles'] = (new AdvertiserProfile($db))->updateAll(); }
        catch (Exception $e) { $results['profiles'] = ['error' => $e->getMessage()]; }
    }
    if (class_exists('AIIntelligence')) {
        try { $results['ai'] = (new AIIntelligence($db))->analyzeAll(); }
        catch (Exception $e) { $results['ai'] = ['error' => $e->getMessage()]; }
    }
    if (class_exists('TaggingSystem')) {
        try { $results['tags'] = (new TaggingSystem($db))->autoTagAll(); }
        catch (Exception $e) { $results['tags'] = ['error' => $e->getMessage()]; }
    }

    echo json_encode(['success' => true, 'message' => 'Analysis complete', 'results' => $results]);
}

function runFullPipeline(Database $db, array $config, string $basePath): void
{
    $advertiserId = trim($_GET['advertiser_id'] ?? $_POST['advertiser_id'] ?? '');
    $advertiserName = trim($_GET['advertiser_name'] ?? $_POST['advertiser_name'] ?? '');

    if (empty($advertiserId)) {
        echo json_encode(['success' => false, 'error' => 'advertiser_id is required']);
        return;
    }

    $steps = [];

    // Step 1: Add advertiser if not managed
    $existing = $db->fetchOne(
        "SELECT id FROM managed_advertisers WHERE advertiser_id = ?", [$advertiserId]
    );
    if (!$existing) {
        $db->insert('managed_advertisers', [
            'advertiser_id' => $advertiserId,
            'name'          => $advertiserName ?: $advertiserId,
            'status'        => 'fetching',
        ]);
        $steps[] = ['step' => 'add_advertiser', 'status' => 'done'];
    } else {
        $db->query("UPDATE managed_advertisers SET status = 'fetching' WHERE advertiser_id = ?", [$advertiserId]);
    }

    // Step 2: Scrape
    $scraper = new Scraper($db, $config['scraper']);
    ob_start();
    try {
        $ads = $scraper->fetchAdvertiser($advertiserId);
    } catch (Exception $e) {
        ob_get_clean();
        $db->query(
            "UPDATE managed_advertisers SET status = 'error', error_message = ? WHERE advertiser_id = ?",
            [$e->getMessage(), $advertiserId]
        );
        echo json_encode(['success' => false, 'error' => 'Scrape failed: ' . $e->getMessage()]);
        return;
    }
    $scrapeLog = ob_get_clean();
    $scraperErrors = $scraper->getErrors();
    $adsCount = count($ads);

    // If zero ads and there were errors, report failure with details
    if ($adsCount === 0 && !empty($scraperErrors)) {
        $errorMsg = implode('; ', $scraperErrors);
        $db->query(
            "UPDATE managed_advertisers SET status = 'error', error_message = ? WHERE advertiser_id = ?",
            [$errorMsg, $advertiserId]
        );
        echo json_encode([
            'success' => false,
            'error'   => 'Fetch failed: ' . $errorMsg,
            'log'     => $scrapeLog,
        ]);
        return;
    }
    $steps[] = ['step' => 'scrape', 'status' => 'done', 'ads_found' => $adsCount];

    $db->query(
        "UPDATE managed_advertisers SET total_ads = ?, last_fetch_ads = ?, last_fetched_at = NOW(), fetch_count = fetch_count + 1, error_message = NULL WHERE advertiser_id = ?",
        [$adsCount, $adsCount, $advertiserId]
    );
    $db->insert('scrape_logs', [
        'advertiser_id' => $advertiserId,
        'ads_found'     => $adsCount,
        'new_ads'       => $adsCount,
        'status'        => $adsCount > 0 ? 'success' : 'partial',
    ]);

    // Step 3: Process
    $assetManager = new AssetManager($config['storage']);
    $processor = new Processor($db, $assetManager);
    ob_start();
    $processed = $processor->processAll();
    ob_get_clean();
    $steps[] = ['step' => 'process', 'status' => 'done', 'payloads_processed' => $processed];

    // Step 4: Analysis
    $analysisClasses = [
        'ChangeDetector', 'TrendAnalyzer', 'CreativeFingerprint',
        'AdvertiserProfile', 'AIIntelligence', 'TaggingSystem',
    ];
    foreach ($analysisClasses as $cls) {
        $file = $basePath . '/src/' . $cls . '.php';
        if (file_exists($file)) require_once $file;
    }

    $analysisResults = [];
    if (class_exists('ChangeDetector')) {
        try { $analysisResults['changes'] = (new ChangeDetector($db))->runAll(); } catch (Exception $e) {}
    }
    if (class_exists('TrendAnalyzer')) {
        try { $analysisResults['trends'] = (new TrendAnalyzer($db))->analyzeAll(); } catch (Exception $e) {}
    }
    if (class_exists('CreativeFingerprint')) {
        try { $analysisResults['fingerprints'] = (new CreativeFingerprint($db))->processAll(); } catch (Exception $e) {}
    }
    if (class_exists('AdvertiserProfile')) {
        try { $analysisResults['profiles'] = (new AdvertiserProfile($db))->updateAll(); } catch (Exception $e) {}
    }
    if (class_exists('AIIntelligence')) {
        try { $analysisResults['ai'] = (new AIIntelligence($db))->analyzeAll(); } catch (Exception $e) {}
    }
    if (class_exists('TaggingSystem')) {
        try { $analysisResults['tags'] = (new TaggingSystem($db))->autoTagAll(); } catch (Exception $e) {}
    }
    $steps[] = ['step' => 'analyze', 'status' => 'done', 'results' => $analysisResults];

    // Update final status
    $db->query(
        "UPDATE managed_advertisers SET
            status = 'active',
            active_ads = (SELECT COUNT(*) FROM ads WHERE advertiser_id = ? AND status='active'),
            total_ads = (SELECT COUNT(*) FROM ads WHERE advertiser_id = ?)
         WHERE advertiser_id = ?",
        [$advertiserId, $advertiserId, $advertiserId]
    );

    // Get final stats
    $adStats = $db->fetchOne(
        "SELECT COUNT(*) as total,
                SUM(CASE WHEN status='active' THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN ad_type='text' THEN 1 ELSE 0 END) as text_ads,
                SUM(CASE WHEN ad_type='image' THEN 1 ELSE 0 END) as image_ads,
                SUM(CASE WHEN ad_type='video' THEN 1 ELSE 0 END) as video_ads
         FROM ads WHERE advertiser_id = ?",
        [$advertiserId]
    );

    echo json_encode([
        'success'    => true,
        'message'    => "Full pipeline completed: {$adsCount} ads scraped, {$processed} payloads processed",
        'advertiser' => $advertiserId,
        'stats'      => $adStats,
        'steps'      => $steps,
    ]);
}

function getStatus(Database $db): void
{
    // Get managed advertisers with live ad counts
    $advertisers = $db->fetchAll(
        "SELECT ma.*,
                (SELECT COUNT(*) FROM ads WHERE advertiser_id = ma.advertiser_id) as db_ads_count,
                (SELECT COUNT(*) FROM ads WHERE advertiser_id = ma.advertiser_id AND status='active') as db_active_ads,
                (SELECT COUNT(*) FROM raw_payloads WHERE advertiser_id = ma.advertiser_id AND processed_flag=0) as pending_payloads
         FROM managed_advertisers ma
         WHERE ma.status NOT IN ('deleted')
         ORDER BY ma.updated_at DESC"
    );

    // Global stats
    $globalStats = $db->fetchOne(
        "SELECT COUNT(*) as total_ads,
                SUM(CASE WHEN status='active' THEN 1 ELSE 0 END) as active_ads,
                COUNT(DISTINCT advertiser_id) as total_advertisers,
                (SELECT COUNT(*) FROM raw_payloads WHERE processed_flag=0) as pending_payloads
         FROM ads"
    );

    // Recent scrape logs
    $recentLogs = $db->fetchAll(
        "SELECT * FROM scrape_logs ORDER BY created_at DESC LIMIT 10"
    );

    // Recent fetch logs
    $fetchLogs = [];
    try {
        $fetchLogs = $db->fetchAll(
            "SELECT * FROM advertiser_fetch_log ORDER BY created_at DESC LIMIT 10"
        );
    } catch (Exception $e) { /* table may not exist */ }

    echo json_encode([
        'success'      => true,
        'advertisers'  => $advertisers,
        'global_stats' => $globalStats,
        'recent_logs'  => $recentLogs,
        'fetch_logs'   => $fetchLogs,
    ]);
}

function searchAdvertisers(Database $db, array $config): void
{
    $keyword = trim($_GET['keyword'] ?? $_POST['keyword'] ?? '');
    if (empty($keyword)) {
        echo json_encode(['success' => false, 'error' => 'keyword is required']);
        return;
    }

    $scraper = new Scraper($db, $config['scraper']);
    $results = $scraper->searchAdvertisers($keyword);

    echo json_encode([
        'success' => true,
        'results' => $results,
        'count'   => count($results),
    ]);
}

function testApiConnection(Database $db, array $config): void
{
    $scraper = new Scraper($db, $config['scraper']);
    $result = $scraper->testConnection();
    echo json_encode([
        'success' => $result['ok'],
        'message' => $result['message'] ?? null,
        'error'   => $result['error'] ?? null,
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
