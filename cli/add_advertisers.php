<?php
/**
 * Add advertisers from advertisers.txt
 *
 * Usage: php cli/add_advertisers.php
 *
 * Reads ../advertisers.txt, adds each to managed_advertisers,
 * then triggers a scrape for new ones.
 */

$basePath = dirname(__DIR__);
$config = require $basePath . '/config/config.php';

require_once $basePath . '/src/Database.php';
require_once $basePath . '/src/Processor.php';
require_once $basePath . '/src/AssetManager.php';

$file = $basePath . '/advertisers.txt';
if (!file_exists($file)) {
    echo "❌ advertisers.txt not found. Create it in project root.\n";
    exit(1);
}

$lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$advertisers = [];

foreach ($lines as $line) {
    $line = trim($line);
    if (empty($line) || $line[0] === '#') continue;

    if (strpos($line, '|') !== false) {
        [$id, $name] = array_map('trim', explode('|', $line, 2));
    } else {
        $id = $line;
        $name = '';
    }

    if (!preg_match('/^AR\d+$/', $id)) {
        echo "⚠️  Skipping invalid ID: {$id}\n";
        continue;
    }

    $advertisers[] = ['id' => $id, 'name' => $name];
}

if (empty($advertisers)) {
    echo "No advertisers found in advertisers.txt\n";
    exit(0);
}

echo "📋 Found " . count($advertisers) . " advertiser(s) in advertisers.txt\n\n";

try {
    $db = Database::getInstance($config['db']);
    $added = 0;
    $existing = 0;
    $reactivated = 0;

    foreach ($advertisers as $adv) {
        $row = $db->fetchOne(
            "SELECT id, status, name FROM managed_advertisers WHERE advertiser_id = ?",
            [$adv['id']]
        );

        $displayName = $adv['name'] ?: $adv['id'];

        if ($row) {
            if (in_array($row['status'], ['paused', 'deleted', 'error'])) {
                $db->update('managed_advertisers', [
                    'status' => 'active',
                    'name'   => $adv['name'] ?: $row['name'],
                    'error_message' => null,
                ], 'advertiser_id = ?', [$adv['id']]);
                echo "🔄 Reactivated: {$displayName} ({$adv['id']})\n";
                $reactivated++;
            } else {
                echo "✅ Already tracked: {$displayName} ({$adv['id']}) — {$row['status']}\n";
                $existing++;
            }
        } else {
            // Auto-fetch name from Google if not provided
            if (empty($adv['name'])) {
                $adv['name'] = fetchAdvertiserName($adv['id']);
                $displayName = $adv['name'] ?: $adv['id'];
            }

            $db->insert('managed_advertisers', [
                'advertiser_id' => $adv['id'],
                'name'          => $adv['name'] ?: $adv['id'],
                'status'        => 'new',
            ]);
            echo "➕ Added: {$displayName} ({$adv['id']})\n";
            $added++;
        }
    }

    echo "\n📊 Summary: {$added} added, {$reactivated} reactivated, {$existing} already existed\n";

    // Auto-scrape new advertisers
    if ($added > 0 || $reactivated > 0) {
        echo "\n🔍 Starting scrape for new advertisers...\n\n";

        $serverUrl = $config['server_url'] ?? 'https://phpstack-1170423-6314737.cloudwaysapps.com';
        $token = $config['ingest_token'] ?? 'ads-intelligent-2024';

        $newAdvs = $db->fetchAll(
            "SELECT advertiser_id, name FROM managed_advertisers WHERE status IN ('new', 'active') ORDER BY created_at DESC LIMIT ?",
            [$added + $reactivated]
        );

        foreach ($newAdvs as $na) {
            echo "  Scraping: {$na['name']} ({$na['advertiser_id']})...\n";

            // Call the scrape via local CLI
            $cmd = "php " . escapeshellarg($basePath . '/cli/scrape.php') . " " . escapeshellarg($na['advertiser_id']) . " 2>&1";
            $output = shell_exec($cmd);

            // Show last few lines of output
            $outputLines = array_filter(explode("\n", trim($output)));
            $lastLines = array_slice($outputLines, -3);
            foreach ($lastLines as $ol) {
                echo "    {$ol}\n";
            }
            echo "\n";
        }

        echo "✅ Scraping complete!\n";
    }

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

/**
 * Try to fetch advertiser name from Google Ads Transparency
 */
function fetchAdvertiserName(string $advertiserId): string
{
    $url = 'https://adstransparency.google.com/advertiser/' . urlencode($advertiserId);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
    ]);
    $resp = curl_exec($ch);
    curl_close($ch);

    if ($resp && preg_match('/<title>([^<]+)</', $resp, $m)) {
        $title = trim($m[1]);
        // Remove "Google Ads Transparency Center" suffix
        $title = preg_replace('/\s*[-–|]\s*Google\s+Ads\s+Transparency.*$/i', '', $title);
        if ($title && $title !== 'Google Ads Transparency Center') {
            return $title;
        }
    }

    return '';
}
