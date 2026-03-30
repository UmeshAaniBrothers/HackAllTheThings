<?php
/**
 * Debug: Examine raw payloads to find per-ad country/region data
 * Shows the actual protobuf fields 8-11 and any other geographic data
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

    // Get a few raw payloads
    $payloads = $db->fetchAll(
        "SELECT id, advertiser_id, raw_json FROM raw_payloads ORDER BY id DESC LIMIT 3"
    );

    $results = [];
    foreach ($payloads as $row) {
        $data = json_decode($row['raw_json'], true);
        if (!$data) continue;

        $ads = $data['1'] ?? $data[1] ?? [];
        if (!is_array($ads)) continue;

        foreach (array_slice($ads, 0, 5) as $idx => $creative) {
            $creativeId = $creative['2'] ?? $creative[2] ?? 'unknown';
            $adInfo = [
                'payload_id' => $row['id'],
                'creative_id' => $creativeId,
                'all_keys' => array_keys($creative),
            ];

            // Dump fields 5-15 (looking for country/region data)
            for ($f = 5; $f <= 20; $f++) {
                $val = $creative[(string)$f] ?? $creative[$f] ?? null;
                if ($val !== null) {
                    $adInfo["field_{$f}"] = $val;
                }
            }

            // Also check inside field 3 for any geographic hints
            $field3 = $creative['3'] ?? $creative[3] ?? null;
            if (is_array($field3)) {
                $adInfo['field_3_keys'] = array_keys($field3);
                // Check sub-fields of field 3
                for ($sf = 1; $sf <= 20; $sf++) {
                    $sub = $field3[(string)$sf] ?? $field3[$sf] ?? null;
                    if ($sub !== null) {
                        if (is_array($sub)) {
                            $adInfo["field_3.{$sf}_keys"] = array_keys($sub);
                            $adInfo["field_3.{$sf}_sample"] = json_encode($sub);
                        } else {
                            $adInfo["field_3.{$sf}"] = $sub;
                        }
                    }
                }
            }

            $results[] = $adInfo;
        }
    }

    // Also check: how many ads have targeting vs not
    $stats = $db->fetchOne(
        "SELECT
            (SELECT COUNT(*) FROM ads) as total_ads,
            (SELECT COUNT(DISTINCT creative_id) FROM ad_targeting) as ads_with_targeting,
            (SELECT COUNT(DISTINCT country) FROM ad_targeting) as unique_countries,
            (SELECT GROUP_CONCAT(DISTINCT country ORDER BY country) FROM ad_targeting) as countries_list"
    );

    echo json_encode([
        'success' => true,
        'targeting_stats' => $stats,
        'raw_samples' => $results,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
