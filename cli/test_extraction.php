<?php

/**
 * Test script: Shows exactly what text and country data is extracted from raw payloads.
 * Run: php cli/test_extraction.php
 * Or via HTTP: https://yoursite.com/cli/test_extraction.php?token=ads-intelligent-2024
 */

$isCli = (php_sapi_name() === 'cli');
if (!$isCli) header('Content-Type: text/plain; charset=utf-8');

set_time_limit(120);
$basePath = dirname(__DIR__);
$config = require $basePath . '/config/config.php';

if (!$isCli) {
    $authToken = $config['ingest_token'] ?? 'ads-intelligent-2024';
    $providedToken = $_GET['token'] ?? $_SERVER['HTTP_X_AUTH_TOKEN'] ?? '';
    if ($providedToken !== $authToken) {
        http_response_code(403);
        echo "Invalid token\n";
        exit;
    }
}

require_once $basePath . '/src/Database.php';
require_once $basePath . '/src/AssetManager.php';
require_once $basePath . '/src/Processor.php';

$db = Database::getInstance($config['db']);

echo "=== AD TEXT & COUNTRY EXTRACTION TEST ===\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

// ─── PART 1: Test protobuf extraction from raw payloads ───
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "PART 1: RAW PAYLOAD PROTOBUF EXTRACTION\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Get recent raw payloads (both processed and unprocessed)
$payloads = $db->fetchAll(
    "SELECT id, advertiser_id, raw_json, processed_flag FROM raw_payloads ORDER BY id DESC LIMIT 3"
);

echo "Found " . count($payloads) . " raw payloads to test.\n\n";

$assetManager = new AssetManager($config['storage'] ?? []);
$processor = new Processor($db, $assetManager);

// Use reflection to call private methods
$refClass = new ReflectionClass($processor);

$parseMethod = $refClass->getMethod('parseProtobufCreative');
$parseMethod->setAccessible(true);

$extractCountries = $refClass->getMethod('extractCountries');
$extractCountries->setAccessible(true);

$adCount = 0;
foreach ($payloads as $payload) {
    $data = json_decode($payload['raw_json'], true);
    if (!$data) continue;

    $creatives = $data['1'] ?? $data[1] ?? $data['creatives'] ?? [];
    if (!is_array($creatives)) continue;

    echo "── Payload #{$payload['id']} | Advertiser: {$payload['advertiser_id']} | Processed: " . ($payload['processed_flag'] ? 'Yes' : 'No') . " ──\n";
    echo "   Contains " . count($creatives) . " creatives\n\n";

    $shown = 0;
    foreach ($creatives as $creative) {
        if (!is_array($creative)) continue;
        if ($shown >= 5) {
            echo "   ... (showing first 5 of " . count($creatives) . " creatives)\n\n";
            break;
        }

        // Check if protobuf format
        $key2 = $creative['2'] ?? $creative[2] ?? null;
        $key1 = $creative['1'] ?? $creative[1] ?? null;
        $isProtobuf = (is_string($key2) && strpos($key2, 'CR') === 0) || (is_string($key1) && strpos($key1, 'AR') === 0);

        if (!$isProtobuf) {
            echo "   [CREATIVE] Legacy format — skipping\n\n";
            $shown++;
            continue;
        }

        $creativeId = $key2 ?? '?';
        echo "   [CREATIVE: $creativeId]\n";

        // Parse using the actual method
        $ad = $parseMethod->invoke($processor, $creative, $payload['advertiser_id']);

        // Show raw field 3 structure for debugging
        $content = $creative['3'] ?? $creative[3] ?? [];
        echo "   ┌─ RAW FIELD 3 KEYS: " . (is_array($content) ? implode(', ', array_keys($content)) : gettype($content)) . "\n";
        if (is_array($content)) {
            foreach ($content as $fk => $fv) {
                if (is_string($fv)) {
                    echo "   │  3.$fk = \"" . substr($fv, 0, 100) . "\"\n";
                } elseif (is_array($fv)) {
                    $subkeys = array_keys($fv);
                    echo "   │  3.$fk = [keys: " . implode(', ', array_slice($subkeys, 0, 10)) . "]\n";
                    foreach ($fv as $sk => $sv) {
                        if (is_string($sv) && strlen($sv) >= 3 && strlen($sv) <= 200) {
                            echo "   │    3.$fk.$sk = \"" . substr($sv, 0, 120) . "\"\n";
                        }
                    }
                } else {
                    echo "   │  3.$fk = " . var_export($fv, true) . "\n";
                }
            }
        }

        // Show raw fields 8-11 for country debugging
        echo "   │\n";
        foreach (['8', '9', '10', '11'] as $fk) {
            $fv = $creative[$fk] ?? $creative[(int)$fk] ?? null;
            if ($fv !== null) {
                if (is_scalar($fv)) {
                    echo "   │  FIELD $fk = " . var_export($fv, true) . "\n";
                } elseif (is_array($fv)) {
                    echo "   │  FIELD $fk = " . json_encode($fv, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
                }
            }
        }

        echo "   │\n";
        echo "   ├─ EXTRACTED TEXT:\n";
        echo "   │  Headline:    " . ($ad['headline'] ?? '(none)') . "\n";
        echo "   │  Description: " . ($ad['description'] ?? '(none)') . "\n";
        echo "   │  CTA:         " . ($ad['cta'] ?? '(none)') . "\n";
        echo "   │  Ad Type:     " . ($ad['ad_type'] ?? '?') . "\n";
        echo "   │  Adv Name:    " . ($ad['advertiser_name'] ?? '(none)') . "\n";
        echo "   │\n";
        echo "   ├─ EXTRACTED COUNTRIES: " . (!empty($ad['countries']) ? implode(', ', $ad['countries']) : '(none)') . "\n";
        echo "   ├─ PLATFORMS: " . (!empty($ad['platforms']) ? implode(', ', $ad['platforms']) : '(none)') . "\n";
        echo "   ├─ FIRST SEEN: " . ($ad['first_seen'] ?? '(none)') . "\n";
        echo "   └─ LAST SEEN:  " . ($ad['last_seen'] ?? '(none)') . "\n\n";

        $shown++;
        $adCount++;
    }
}

// ─── PART 2: Test preview content extraction ───
echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "PART 2: PREVIEW CONTENT TEXT EXTRACTION\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$fetchMethod = $refClass->getMethod('fetchPreviewData');
$fetchMethod->setAccessible(true);

$previewAds = $db->fetchAll(
    "SELECT a.creative_id, ass.original_url as preview_url,
            d.headline as existing_headline
     FROM ads a
     INNER JOIN ad_assets ass ON a.creative_id = ass.creative_id
        AND ass.original_url LIKE '%displayads-formats%'
     LEFT JOIN ad_details d ON a.creative_id = d.creative_id
        AND d.id = (SELECT MAX(id) FROM ad_details WHERE creative_id = a.creative_id)
     GROUP BY a.creative_id
     ORDER BY a.last_seen DESC
     LIMIT 5"
);

echo "Testing preview extraction on " . count($previewAds) . " ads...\n\n";

foreach ($previewAds as $pa) {
    echo "   [CREATIVE: {$pa['creative_id']}]\n";
    echo "   Existing Headline: " . ($pa['existing_headline'] ?? '(none)') . "\n";
    echo "   Preview URL: " . substr($pa['preview_url'], 0, 100) . "...\n";

    $data = $fetchMethod->invoke($processor, $pa['preview_url']);
    if (!$data) {
        echo "   ✗ Failed to fetch preview data\n\n";
        continue;
    }

    echo "   ┌─ EXTRACTED FROM PREVIEW:\n";
    echo "   │  Headline:      " . ($data['headline'] ?? '(none)') . "\n";
    echo "   │  Description:   " . ($data['description'] ?? '(none)') . "\n";
    echo "   │  CTA:           " . ($data['cta'] ?? '(none)') . "\n";
    echo "   │  Landing URL:   " . ($data['landing_url'] ?? '(none)') . "\n";
    echo "   │  Display URL:   " . ($data['display_url'] ?? '(none)') . "\n";
    echo "   │  YouTube ID:    " . ($data['youtube_id'] ?? '(none)') . "\n";
    echo "   │  Store URL:     " . ($data['store_url'] ?? '(none)') . "\n";
    echo "   │  Store Platform:" . ($data['store_platform'] ?? '(none)') . "\n";
    echo "   │  Dimensions:    " . ($data['ad_width'] ? $data['ad_width'] . 'x' . $data['ad_height'] : '(none)') . "\n";
    echo "   │  Headlines[]:   " . count($data['headlines']) . " variations" . ($data['headlines'] ? ' → ' . implode(' | ', array_slice($data['headlines'], 0, 5)) : '') . "\n";
    echo "   │  Descriptions[]:" . count($data['descriptions']) . " variations\n";
    echo "   │  Tracking IDs:  " . count($data['tracking_ids']) . ($data['tracking_ids'] ? ' → ' . json_encode($data['tracking_ids']) : '') . "\n";
    echo "   │  Image URLs:    " . count($data['image_urls']) . "\n";
    echo "   └─\n\n";

    usleep(500000); // 500ms between requests
}

// ─── PART 3: Database summary of existing extracted data ───
echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "PART 3: DATABASE EXTRACTION SUMMARY\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$stats = [];

// Ads with/without text
$stats['total_ads'] = (int)$db->fetchColumn("SELECT COUNT(*) FROM ads");
$stats['with_headline'] = (int)$db->fetchColumn(
    "SELECT COUNT(DISTINCT a.creative_id) FROM ads a
     INNER JOIN ad_details d ON a.creative_id = d.creative_id
     WHERE d.headline IS NOT NULL AND d.headline != ''"
);
$stats['without_headline'] = $stats['total_ads'] - $stats['with_headline'];

$stats['with_description'] = (int)$db->fetchColumn(
    "SELECT COUNT(DISTINCT a.creative_id) FROM ads a
     INNER JOIN ad_details d ON a.creative_id = d.creative_id
     WHERE d.description IS NOT NULL AND d.description != ''"
);

$stats['with_cta'] = (int)$db->fetchColumn(
    "SELECT COUNT(DISTINCT a.creative_id) FROM ads a
     INNER JOIN ad_details d ON a.creative_id = d.creative_id
     WHERE d.cta IS NOT NULL AND d.cta != ''"
);

$stats['with_landing_url'] = (int)$db->fetchColumn(
    "SELECT COUNT(DISTINCT a.creative_id) FROM ads a
     INNER JOIN ad_details d ON a.creative_id = d.creative_id
     WHERE d.landing_url IS NOT NULL AND d.landing_url != ''"
);

// Country targeting
$stats['ads_with_country'] = (int)$db->fetchColumn(
    "SELECT COUNT(DISTINCT creative_id) FROM ad_targeting WHERE country IS NOT NULL AND country != ''"
);
$stats['ads_without_country'] = $stats['total_ads'] - $stats['ads_with_country'];

$stats['unique_countries'] = (int)$db->fetchColumn(
    "SELECT COUNT(DISTINCT country) FROM ad_targeting WHERE country IS NOT NULL AND country != ''"
);

// Country distribution
$countryDist = $db->fetchAll(
    "SELECT country, COUNT(DISTINCT creative_id) as ad_count
     FROM ad_targeting
     WHERE country IS NOT NULL AND country != ''
     GROUP BY country ORDER BY ad_count DESC LIMIT 20"
);

echo "   TEXT EXTRACTION:\n";
echo "   ├─ Total Ads:          {$stats['total_ads']}\n";
echo "   ├─ With Headline:      {$stats['with_headline']} (" . ($stats['total_ads'] > 0 ? round($stats['with_headline']/$stats['total_ads']*100) : 0) . "%)\n";
echo "   ├─ Without Headline:   {$stats['without_headline']} (" . ($stats['total_ads'] > 0 ? round($stats['without_headline']/$stats['total_ads']*100) : 0) . "%)\n";
echo "   ├─ With Description:   {$stats['with_description']} (" . ($stats['total_ads'] > 0 ? round($stats['with_description']/$stats['total_ads']*100) : 0) . "%)\n";
echo "   ├─ With CTA:           {$stats['with_cta']}\n";
echo "   └─ With Landing URL:   {$stats['with_landing_url']}\n\n";

echo "   COUNTRY TARGETING:\n";
echo "   ├─ Ads With Country:   {$stats['ads_with_country']} (" . ($stats['total_ads'] > 0 ? round($stats['ads_with_country']/$stats['total_ads']*100) : 0) . "%)\n";
echo "   ├─ Without Country:    {$stats['ads_without_country']}\n";
echo "   ├─ Unique Countries:   {$stats['unique_countries']}\n";
echo "   └─ Distribution:\n";
foreach ($countryDist as $cd) {
    echo "      {$cd['country']}: {$cd['ad_count']} ads\n";
}

// Show sample headlines
echo "\n   SAMPLE HEADLINES (latest 10):\n";
$sampleHeadlines = $db->fetchAll(
    "SELECT d.headline, a.creative_id, a.ad_type
     FROM ad_details d
     INNER JOIN ads a ON d.creative_id = a.creative_id
     WHERE d.headline IS NOT NULL AND d.headline != ''
     ORDER BY d.id DESC LIMIT 10"
);
foreach ($sampleHeadlines as $sh) {
    echo "   │ [{$sh['ad_type']}] " . substr($sh['headline'], 0, 80) . " ({$sh['creative_id']})\n";
}

echo "\n\n=== TEST COMPLETE ===\n";
