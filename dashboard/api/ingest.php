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
        case 'set_country':
            setCountry($db);
            break;
        case 'backfill_countries':
            backfillCountries($db);
            break;
        case 'set_ad_countries':
            setAdCountries($db);
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
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data || empty($data['advertiser_id']) || empty($data['payload'])) {
        echo json_encode(['success' => false, 'error' => 'advertiser_id and payload required']);
        return;
    }

    $db->insert('raw_payloads', [
        'advertiser_id'  => $data['advertiser_id'],
        'raw_json'       => is_string($data['payload']) ? $data['payload'] : json_encode($data['payload']),
        'processed_flag' => 0,
    ]);

    echo json_encode(['success' => true, 'message' => 'Payload stored']);
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
        $insertData = [
            'advertiser_id' => $data['advertiser_id'],
            'name'          => $data['name'] ?? $data['advertiser_id'],
            'status'        => $data['status'] ?? 'new',
        ];
        if (!empty($data['region'])) {
            $insertData['region'] = strtoupper(trim($data['region']));
        }
        $db->insert('managed_advertisers', $insertData);
    } else {
        $updates = [];
        if (isset($data['name'])) $updates['name'] = $data['name'];
        if (isset($data['status'])) $updates['status'] = $data['status'];
        if (!empty($data['region'])) $updates['region'] = strtoupper(trim($data['region']));
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

/**
 * Set country for all ads of an advertiser.
 * Called by CLI after fetching with a specific region.
 */
function setCountry($db)
{
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data || empty($data['advertiser_id']) || empty($data['country'])) {
        echo json_encode(['success' => false, 'error' => 'advertiser_id and country required']);
        return;
    }

    $advertiserId = $data['advertiser_id'];
    $country = strtoupper(trim($data['country']));

    // Also store region on the advertiser for future auto-assignment
    $db->query(
        "UPDATE managed_advertisers SET region = ? WHERE advertiser_id = ? AND (region IS NULL OR region = '')",
        [$country, $advertiserId]
    );

    // Get all ads for this advertiser that don't have this country yet
    $ads = $db->fetchAll(
        "SELECT a.creative_id FROM ads a
         WHERE a.advertiser_id = ?
           AND NOT EXISTS (
               SELECT 1 FROM ad_targeting t
               WHERE t.creative_id = a.creative_id AND t.country = ?
           )",
        [$advertiserId, $country]
    );

    $updated = 0;
    foreach ($ads as $ad) {
        $db->insert('ad_targeting', [
            'creative_id' => $ad['creative_id'],
            'country'     => $country,
            'platform'    => 'Google Ads',
        ]);
        $updated++;
    }

    echo json_encode([
        'success' => true,
        'updated' => $updated,
        'message' => "Set country {$country} for {$updated} ads",
    ]);
}

/**
 * Backfill countries: set region on advertisers from advertisers.txt,
 * then assign country to all ads missing targeting based on advertiser region.
 */
function backfillCountries($db)
{
    $basePath = dirname(dirname(__DIR__));
    $results = ['advertisers_updated' => 0, 'ads_backfilled' => 0, 'details' => []];

    // Step 1: Read advertisers.txt and set region on managed_advertisers
    $advFile = $basePath . '/cli/advertisers.txt';
    if (file_exists($advFile)) {
        $lines = file($advFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || $line[0] === '#') continue;
            $parts = explode('|', $line);
            if (count($parts) >= 3) {
                $advId = trim($parts[0]);
                $region = strtoupper(trim($parts[2]));
                if (!empty($region)) {
                    $db->query(
                        "UPDATE managed_advertisers SET region = ? WHERE advertiser_id = ? AND (region IS NULL OR region = '')",
                        [$region, $advId]
                    );
                    $results['advertisers_updated']++;
                }
            }
        }
    }

    // Step 2: For each advertiser with a region, assign country to ads missing targeting
    $advertisers = $db->fetchAll(
        "SELECT advertiser_id, region FROM managed_advertisers WHERE region IS NOT NULL AND region != ''"
    );

    foreach ($advertisers as $adv) {
        $ads = $db->fetchAll(
            "SELECT a.creative_id FROM ads a
             WHERE a.advertiser_id = ?
               AND NOT EXISTS (
                   SELECT 1 FROM ad_targeting t WHERE t.creative_id = a.creative_id
               )",
            [$adv['advertiser_id']]
        );

        $count = 0;
        foreach ($ads as $ad) {
            $db->insert('ad_targeting', [
                'creative_id' => $ad['creative_id'],
                'country'     => $adv['region'],
                'platform'    => 'Google Ads',
            ]);
            $count++;
        }

        if ($count > 0) {
            $results['ads_backfilled'] += $count;
            $results['details'][] = "{$adv['advertiser_id']}: {$count} ads → {$adv['region']}";
        }
    }

    echo json_encode([
        'success' => true,
        'message' => "Updated {$results['advertisers_updated']} advertisers, backfilled {$results['ads_backfilled']} ads",
        'details' => $results['details'],
    ]);
}

/**
 * Set per-ad country targeting from multi-region scan.
 * Receives: { advertiser_id, ad_countries: { creative_id: [country1, country2, ...] } }
 * This REPLACES existing targeting for each ad with the scan results.
 */
function setAdCountries($db)
{
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data || empty($data['ad_countries'])) {
        echo json_encode(['success' => false, 'error' => 'ad_countries map required']);
        return;
    }

    $adCountries = $data['ad_countries'];
    $totalUpdated = 0;
    $totalNew = 0;
    $adsProcessed = 0;

    foreach ($adCountries as $creativeId => $countries) {
        if (!is_array($countries) || empty($countries)) continue;

        // Verify this ad exists in our database
        $exists = $db->fetchOne("SELECT creative_id FROM ads WHERE creative_id = ?", [$creativeId]);
        if (!$exists) continue;

        $adsProcessed++;

        // Get existing countries for this ad
        $existing = $db->fetchAll(
            "SELECT country FROM ad_targeting WHERE creative_id = ?",
            [$creativeId]
        );
        $existingCountries = array_column($existing, 'country');

        // Add new countries that don't exist yet
        foreach ($countries as $country) {
            $country = strtoupper(trim($country));
            if (empty($country) || strlen($country) !== 2) continue;

            if (!in_array($country, $existingCountries)) {
                $db->insert('ad_targeting', [
                    'creative_id' => $creativeId,
                    'country'     => $country,
                    'platform'    => 'Google Ads',
                ]);
                $totalNew++;
            }
            $totalUpdated++;
        }
    }

    echo json_encode([
        'success' => true,
        'ads_processed' => $adsProcessed,
        'countries_added' => $totalNew,
        'total_entries' => $totalUpdated,
        'message' => "Processed {$adsProcessed} ads, added {$totalNew} new country entries",
    ]);
}
