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

    // Step 1: Process raw payloads
    $processed = 0;
    try {
        $processed = $processor->processAll();
    } catch (Exception $e) {
        logMsg("Payload processing error: " . $e->getMessage());
    }

    // Step 1b: Enrich ad text (headline, description) from preview content
    $textEnriched = 0;
    try {
        $textEnriched = $processor->enrichAdText();
    } catch (Exception $e) {
        logMsg("Text enrichment error: " . $e->getMessage());
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

    // Step 4: Detect apps from Google Ads Transparency preview content only
    $storeEnriched = 0;
    try {
        $storeEnriched = $processor->enrichStoreUrlsFromPreview();
    } catch (Exception $e) {
        logMsg("Store URL enrichment error: " . $e->getMessage());
    }

    // Step 4b: Enrich per-ad countries from Google Lookup API
    $countriesEnriched = 0;
    try {
        $countriesEnriched = $processor->enrichCountriesFromGoogle();
    } catch (Exception $e) {
        logMsg("Country enrichment error: " . $e->getMessage());
    }

    // Step 5: Detect products from headlines/URLs for unmapped ads
    $productsMapped = 0;
    try {
        $productsMapped = $processor->detectProducts();
    } catch (Exception $e) {
        logMsg("Product detection error: " . $e->getMessage());
    }

    // Step 5b: Re-detect web products that might be apps
    $redetected = 0;
    try {
        $redetected = $processor->redetectWebProducts();
    } catch (Exception $e) {
        logMsg("Product re-detection error: " . $e->getMessage());
    }

    // Step 6: Fetch app metadata (icons, ratings, descriptions) from stores
    $appMetaEnriched = 0;
    try {
        $appMetaEnriched = $processor->enrichAppMetadata();
    } catch (Exception $e) {
        logMsg("App metadata enrichment error: " . $e->getMessage());
    }

    // Step 7: Update advertiser stats
    try {
        $db->query(
            "UPDATE managed_advertisers ma SET
                ma.active_ads = (SELECT COUNT(*) FROM ads a WHERE a.advertiser_id = ma.advertiser_id AND a.status = 'active'),
                ma.total_ads = (SELECT COUNT(*) FROM ads a WHERE a.advertiser_id = ma.advertiser_id)
             WHERE ma.status IN ('active', 'new')"
        );
    } catch (Exception $e) { /* non-critical */ }

    // Step 8: Log scrape activity
    try {
        if ($processed > 0) {
            // Get distinct advertisers from processed payloads
            $recentAdvs = $db->fetchAll(
                "SELECT DISTINCT advertiser_id FROM raw_payloads WHERE processed_flag = 1 ORDER BY id DESC LIMIT 20"
            );
            foreach ($recentAdvs as $advRow) {
                $advId = $advRow['advertiser_id'];
                $stats = $db->fetchOne(
                    "SELECT COUNT(*) as total, SUM(status = 'active') as active FROM ads WHERE advertiser_id = ?",
                    [$advId]
                );
                $db->insert('scrape_logs', [
                    'advertiser_id' => $advId,
                    'ads_found'     => (int)($stats['total'] ?? 0),
                    'new_ads'       => $processed,
                    'status'        => 'success',
                ]);
            }
        }
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

    // Count remaining ads without app store link
    $remainingStoreUrls = (int) $db->fetchColumn(
        "SELECT COUNT(*) FROM ads a
         INNER JOIN ad_assets ass ON a.creative_id = ass.creative_id
            AND ass.original_url LIKE '%displayads-formats%'
         WHERE NOT EXISTS (
             SELECT 1 FROM ad_product_map pm
             INNER JOIN ad_products p ON pm.product_id = p.id
             WHERE pm.creative_id = a.creative_id
               AND p.store_platform IN ('ios', 'playstore')
               AND p.store_url IS NOT NULL AND p.store_url != '' AND p.store_url != 'not_found'
         )"
    );

    // Count ads missing headline
    $remainingText = (int) $db->fetchColumn(
        "SELECT COUNT(*) FROM ads a
         INNER JOIN ad_assets ass ON a.creative_id = ass.creative_id AND ass.original_url LIKE '%displayads-formats%'
         WHERE NOT EXISTS (
             SELECT 1 FROM ad_details d WHERE d.creative_id = a.creative_id AND d.headline IS NOT NULL AND d.headline != ''
         )"
    );

    // Count remaining apps needing metadata
    $remainingAppMeta = (int) $db->fetchColumn(
        "SELECT COUNT(*) FROM ad_products p
         LEFT JOIN app_metadata am ON am.product_id = p.id
         WHERE p.store_platform IN ('ios','playstore')
           AND p.store_url IS NOT NULL AND p.store_url != '' AND p.store_url != 'not_found'
           AND am.id IS NULL"
    );

    $result = [
        'success'   => true,
        'processed' => $processed,
        'text_enriched' => $textEnriched,
        'countries_enriched' => $countriesEnriched,
        'youtube'   => $ytExtracted,
        'enriched'  => $ytEnriched,
        'store_urls_detected' => $storeEnriched,
        'products_mapped' => $productsMapped,
        'products_redetected' => $redetected,
        'app_meta_enriched' => $appMetaEnriched,
        'remaining_text' => $remainingText,
        'remaining_extract' => $remainingExtract,
        'remaining_enrich'  => $remainingEnrich,
        'remaining_store_urls' => $remainingStoreUrls,
        'remaining_app_meta' => $remainingAppMeta,
        'message'   => ($remainingExtract > 0 || $remainingEnrich > 0 || $remainingStoreUrls > 0 || $remainingText > 0 || $remainingAppMeta > 0) ? 'Call again to process more.' : 'All done!',
    ];

    if ($isCli) {
        if ($processed > 0 || $textEnriched > 0 || $ytExtracted > 0 || $ytEnriched > 0 || $storeEnriched > 0 || $productsMapped > 0 || $appMetaEnriched > 0) {
            logMsg("Processed {$processed}, text {$textEnriched}, youtube {$ytExtracted}, enriched {$ytEnriched}, store {$storeEnriched}, products {$productsMapped}, app_meta {$appMetaEnriched}");
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
