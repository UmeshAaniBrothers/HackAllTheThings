<?php

/**
 * Background processor — run via cron on Cloudways.
 *
 * Processes raw payloads one at a time, then extracts YouTube URLs.
 * Safe to run frequently (every 1-2 minutes) — skips if nothing to do.
 *
 * Cron example (every 2 minutes):
 *   * /2 * * * * cd /home/master/applications/APPID/public_html && php cron/process.php >> cron/process.log 2>&1
 *
 * Can also be triggered via HTTP:
 *   https://yoursite.com/cron/process.php?token=ads-intelligent-2024
 */

// Allow running from CLI or HTTP
$isCli = (php_sapi_name() === 'cli');

if (!$isCli) {
    header('Content-Type: application/json');
}

set_time_limit(300);
ini_set('display_errors', 0);
error_reporting(E_ALL);

$basePath = dirname(__DIR__);
$config = require $basePath . '/config/config.php';

// Auth check for HTTP requests
if (!$isCli) {
    $authToken = $config['ingest_token'] ?? 'ads-intelligent-2024';
    $providedToken = $_GET['token'] ?? $_SERVER['HTTP_X_AUTH_TOKEN'] ?? '';
    if ($providedToken !== $authToken) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid token']);
        exit;
    }
}

require_once $basePath . '/src/Database.php';
require_once $basePath . '/src/AssetManager.php';
require_once $basePath . '/src/Processor.php';

try {
    $db = Database::getInstance($config['db']);
    $assetManager = new AssetManager($config['storage'] ?? array());
    $processor = new Processor($db, $assetManager);

    // Step 1: Process raw payloads one at a time
    $unprocessed = $db->fetchAll(
        "SELECT id, advertiser_id, raw_json FROM raw_payloads WHERE processed_flag = 0 ORDER BY id ASC LIMIT 10"
    );

    $processed = 0;
    foreach ($unprocessed as $row) {
        try {
            // Use reflection or direct call to process single payload
            ob_start();
            // processAll processes all unprocessed, but we mark them one by one
            // So we process one, mark it, move to next
            $count = $processor->processAll();
            ob_get_clean();
            $processed += $count;
            break; // processAll handles all at once, so break after first call
        } catch (Exception $e) {
            logMsg("Error processing payload {$row['id']}: " . $e->getMessage());
            // Mark as processed to avoid infinite retry
            $db->update('raw_payloads', ['processed_flag' => 2], 'id = ?', [$row['id']]);
        }
    }

    // Step 2: Extract YouTube URLs
    $ytExtracted = 0;
    try {
        $ytExtracted = $processor->extractYouTubeUrls();
    } catch (Exception $e) {
        logMsg("YouTube extraction error: " . $e->getMessage());
    }

    // Step 3: Enrich YouTube metadata (title, view count, thumbnail)
    $ytEnriched = 0;
    try {
        $ytEnriched = $processor->enrichYouTubeMetadata();
    } catch (Exception $e) {
        logMsg("YouTube enrichment error: " . $e->getMessage());
    }

    // Step 4: Detect products/apps from headlines and landing URLs
    $productsDetected = 0;
    try {
        $productsDetected = $processor->detectProducts();
    } catch (Exception $e) {
        logMsg("Product detection error: " . $e->getMessage());
    }

    // Step 5: Update advertiser stats
    try {
        $db->query(
            "UPDATE managed_advertisers ma SET
                ma.active_ads = (SELECT COUNT(*) FROM ads a WHERE a.advertiser_id = ma.advertiser_id AND a.status = 'active'),
                ma.total_ads = (SELECT COUNT(*) FROM ads a WHERE a.advertiser_id = ma.advertiser_id)
             WHERE ma.status IN ('active', 'new')"
        );
    } catch (Exception $e) { /* non-critical */ }

    // Count remaining work
    $remainingEnrich = (int) $db->fetchColumn(
        "SELECT COUNT(*) FROM ads a
         WHERE a.ad_type = 'video'
           AND EXISTS (SELECT 1 FROM ad_assets v WHERE v.creative_id = a.creative_id AND v.type = 'video' AND v.original_url LIKE '%youtube.com%')
           AND (a.view_count = 0 OR a.view_count IS NULL
                OR NOT EXISTS (SELECT 1 FROM ad_details d WHERE d.creative_id = a.creative_id AND d.headline IS NOT NULL AND d.headline != ''))"
    );
    $remainingExtract = (int) $db->fetchColumn(
        "SELECT COUNT(*) FROM ads a
         INNER JOIN ad_assets ass ON a.creative_id = ass.creative_id AND ass.original_url LIKE '%displayads-formats%'
         WHERE a.ad_type = 'video'
           AND NOT EXISTS (SELECT 1 FROM ad_assets v WHERE v.creative_id = a.creative_id AND v.type = 'video' AND v.original_url LIKE '%youtube.com%')"
    );

    $result = [
        'success'   => true,
        'processed' => $processed,
        'youtube'   => $ytExtracted,
        'enriched'  => $ytEnriched,
        'products'  => $productsDetected,
        'pending'   => count($unprocessed),
        'remaining_extract' => $remainingExtract,
        'remaining_enrich'  => $remainingEnrich,
        'message'   => ($remainingExtract > 0 || $remainingEnrich > 0) ? 'Call again to process more. ' . $remainingExtract . ' need YouTube URL, ' . $remainingEnrich . ' need view counts.' : 'All done!',
    ];

    if ($isCli) {
        if ($processed > 0 || $ytExtracted > 0 || $ytEnriched > 0 || $productsDetected > 0) {
            logMsg("Processed {$processed} payloads, extracted {$ytExtracted} YouTube URLs, enriched {$ytEnriched} videos, detected {$productsDetected} product mappings");
        }
    } else {
        echo json_encode($result);
    }

} catch (Throwable $e) {
    $msg = 'CRON ERROR: ' . $e->getMessage();
    if ($isCli) {
        logMsg($msg);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function logMsg($msg)
{
    echo '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n";
}
