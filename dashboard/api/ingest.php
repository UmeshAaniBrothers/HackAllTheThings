<?php

/**
 * API: Ingest - Receive scraped data from remote CLI scraper.
 *
 * This endpoint accepts scraped ad data POSTed from the CLI tool
 * running on a machine that can access Google's API.
 *
 * Actions:
 *   - store_payload: Store raw scraped JSON payload
 *   - store_suggestions: Store search suggestion results
 *   - process: Process stored payloads into ads table
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);
set_error_handler(function($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

header('Content-Type: application/json');
set_time_limit(300);

// Simple auth token (change this in config!)
$basePath = dirname(dirname(__DIR__));
$config = require $basePath . '/config/config.php';
$authToken = $config['ingest_token'] ?? 'ads-intelligent-2024';

// Verify token
$providedToken = $_GET['token'] ?? $_POST['token'] ?? $_SERVER['HTTP_X_AUTH_TOKEN'] ?? '';
if ($providedToken !== $authToken) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid auth token']);
    exit;
}

require_once $basePath . '/src/Database.php';
require_once $basePath . '/src/Scraper.php';
require_once $basePath . '/src/AssetManager.php';
require_once $basePath . '/src/Processor.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    $db = Database::getInstance($config['db']);

    switch ($action) {
        case 'store_payload':
            storePayload($db);
            break;
        case 'store_and_process':
            storeAndProcess($db, $config);
            break;
        case 'update_advertiser':
            updateAdvertiser($db);
            break;
        case 'enrich_ads':
            enrichAds($db);
            break;
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

function storePayload($db)
{
    global $config;

    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data || empty($data['advertiser_id']) || empty($data['payload'])) {
        echo json_encode(['success' => false, 'error' => 'advertiser_id and payload required']);
        return;
    }

    $rawJson = is_string($data['payload']) ? $data['payload'] : json_encode($data['payload']);

    $db->insert('raw_payloads', [
        'advertiser_id'  => $data['advertiser_id'],
        'raw_json'       => $rawJson,
        'processed_flag' => 0,
    ]);

    // Immediately process this payload
    $processed = 0;
    try {
        $assetManager = new AssetManager($config['storage'] ?? array());
        $processor = new Processor($db, $assetManager);
        ob_start();
        $processed = $processor->processAll();
        ob_get_clean();
    } catch (Throwable $e) {
        // Non-critical: will be processed later
    }

    echo json_encode(['success' => true, 'message' => "Stored and processed {$processed} payload(s)"]);
}

function storeAndProcess($db, $config)
{
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data || empty($data['advertiser_id'])) {
        echo json_encode(['success' => false, 'error' => 'advertiser_id required']);
        return;
    }

    $advertiserId = $data['advertiser_id'];
    $advertiserName = $data['advertiser_name'] ?? $advertiserId;
    $payloads = $data['payloads'] ?? [];
    $adsCount = $data['ads_count'] ?? 0;

    // Ensure advertiser exists
    $existing = $db->fetchOne(
        "SELECT id FROM managed_advertisers WHERE advertiser_id = ?",
        [$advertiserId]
    );
    if (!$existing) {
        $db->insert('managed_advertisers', [
            'advertiser_id' => $advertiserId,
            'name'          => $advertiserName,
            'status'        => 'active',
        ]);
    }

    // Store all payloads
    $stored = 0;
    foreach ($payloads as $payload) {
        $db->insert('raw_payloads', [
            'advertiser_id'  => $advertiserId,
            'raw_json'       => is_string($payload) ? $payload : json_encode($payload),
            'processed_flag' => 0,
        ]);
        $stored++;
    }

    // Process payloads
    $assetManager = new AssetManager($config['storage'] ?? []);
    $processor = new Processor($db, $assetManager);
    ob_start();
    $processed = $processor->processAll();
    ob_get_clean();

    // Update advertiser record
    $db->query(
        "UPDATE managed_advertisers SET
            status = 'active',
            total_ads = (SELECT COUNT(*) FROM ads WHERE advertiser_id = ?),
            active_ads = (SELECT COUNT(*) FROM ads WHERE advertiser_id = ? AND status='active'),
            last_fetch_ads = ?,
            last_fetched_at = NOW(),
            fetch_count = fetch_count + 1,
            error_message = NULL
         WHERE advertiser_id = ?",
        [$advertiserId, $advertiserId, $adsCount, $advertiserId]
    );

    // Log
    try {
        $db->insert('scrape_logs', [
            'advertiser_id' => $advertiserId,
            'ads_found'     => $adsCount,
            'new_ads'       => $adsCount,
            'status'        => 'success',
        ]);
    } catch (Throwable $e) { /* non-critical */ }

    // Get final stats
    $stats = $db->fetchOne(
        "SELECT COUNT(*) as total,
                SUM(CASE WHEN status='active' THEN 1 ELSE 0 END) as active
         FROM ads WHERE advertiser_id = ?",
        [$advertiserId]
    );

    echo json_encode([
        'success'   => true,
        'message'   => "Stored {$stored} payloads, processed {$processed}",
        'ads_total' => $stats['total'] ?? 0,
        'ads_active' => $stats['active'] ?? 0,
    ]);
}

function updateAdvertiser($db)
{
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data || empty($data['advertiser_id'])) {
        echo json_encode(['success' => false, 'error' => 'advertiser_id required']);
        return;
    }

    $existing = $db->fetchOne(
        "SELECT id FROM managed_advertisers WHERE advertiser_id = ?",
        [$data['advertiser_id']]
    );

    if (!$existing) {
        $db->insert('managed_advertisers', [
            'advertiser_id' => $data['advertiser_id'],
            'name'          => $data['name'] ?? $data['advertiser_id'],
            'status'        => $data['status'] ?? 'new',
        ]);
    } else {
        $updates = [];
        if (isset($data['name'])) $updates['name'] = $data['name'];
        if (isset($data['status'])) $updates['status'] = $data['status'];
        if (!empty($updates)) {
            $db->update('managed_advertisers', $updates, 'advertiser_id = ?', [$data['advertiser_id']]);
        }
    }

    echo json_encode(['success' => true, 'message' => 'Advertiser updated']);
}

/**
 * Enrich ads with YouTube URLs, thumbnails, etc.
 * Called by CLI scraper after extracting video details from preview URLs.
 */
function enrichAds($db)
{
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data || empty($data['enrichments'])) {
        echo json_encode(['success' => false, 'error' => 'enrichments array required']);
        return;
    }

    $enrichments = $data['enrichments'];
    $updated = 0;
    $added = 0;

    foreach ($enrichments as $item) {
        $creativeId = $item['creative_id'] ?? null;
        if (!$creativeId) continue;

        // Add YouTube video asset
        if (!empty($item['youtube_url'])) {
            $exists = $db->fetchOne(
                "SELECT id FROM ad_assets WHERE creative_id = ? AND type = 'video' AND original_url = ?",
                [$creativeId, $item['youtube_url']]
            );
            if (!$exists) {
                $db->insert('ad_assets', [
                    'creative_id'  => $creativeId,
                    'type'         => 'video',
                    'original_url' => $item['youtube_url'],
                    'local_path'   => null,
                ]);
                $added++;
            }
        }

        // Add YouTube thumbnail as image asset
        if (!empty($item['thumbnail'])) {
            $exists = $db->fetchOne(
                "SELECT id FROM ad_assets WHERE creative_id = ? AND type = 'image' AND original_url = ?",
                [$creativeId, $item['thumbnail']]
            );
            if (!$exists) {
                $db->insert('ad_assets', [
                    'creative_id'  => $creativeId,
                    'type'         => 'image',
                    'original_url' => $item['thumbnail'],
                    'local_path'   => null,
                ]);
            }
        }

        $updated++;
    }

    echo json_encode([
        'success' => true,
        'message' => "Enriched {$updated} ads, added {$added} video assets",
    ]);
}
