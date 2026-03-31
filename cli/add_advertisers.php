<?php
/**
 * Add advertisers from advertisers.txt
 *
 * Usage: php cli/add_advertisers.php
 *
 * Reads ../advertisers.txt, adds each via the server API,
 * then triggers a scrape for new ones.
 */

$basePath = dirname(__DIR__);
$serverUrl = 'https://phpstack-1170423-6314737.cloudwaysapps.com';
$token = 'ads-intelligent-2024';

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

$added = 0;
$existing = 0;
$reactivated = 0;
$newIds = [];

foreach ($advertisers as $adv) {
    $displayName = $adv['name'] ?: $adv['id'];

    // Auto-fetch name from Google if not provided
    if (empty($adv['name'])) {
        $adv['name'] = fetchAdvertiserName($adv['id']);
        $displayName = $adv['name'] ?: $adv['id'];
    }

    // Add via server API
    $apiUrl = $serverUrl . '/dashboard/api/manage.php?action=add_advertiser'
        . '&advertiser_id=' . urlencode($adv['id'])
        . '&advertiser_name=' . urlencode($adv['name'] ?: $adv['id']);

    $resp = callApi($apiUrl);

    if (!$resp || !$resp['success']) {
        echo "❌ Failed: {$displayName} — " . ($resp['error'] ?? 'unknown error') . "\n";
        continue;
    }

    $msg = $resp['message'] ?? '';
    if (strpos($msg, 'already tracked') !== false) {
        echo "✅ Already tracked: {$displayName} ({$adv['id']})\n";
        $existing++;
    } elseif (strpos($msg, 'reactivated') !== false) {
        echo "🔄 Reactivated: {$displayName} ({$adv['id']})\n";
        $reactivated++;
        $newIds[] = $adv['id'];
    } else {
        echo "➕ Added: {$displayName} ({$adv['id']})\n";
        $added++;
        $newIds[] = $adv['id'];
    }
}

echo "\n📊 Summary: {$added} added, {$reactivated} reactivated, {$existing} already existed\n";

// Trigger scrape for new advertisers
if (!empty($newIds)) {
    echo "\n🔍 Triggering scrape on server for new advertisers...\n\n";

    echo "  Triggering server cron (this runs in background on server)...\n";

    $scrapeUrl = $serverUrl . '/cron/process.php?token=' . urlencode($token);
    $ch = curl_init($scrapeUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT => 'AdsIntelligent-CLI/1.0',
    ]);
    curl_exec($ch); // Fire and forget — don't wait for response

    echo "\n✅ All done!\n";
    echo "  • Advertisers added to server database\n";
    echo "  • Server cron triggered — it will scrape ads in the background\n";
    echo "  • Enrichment (countries, text, YouTube, apps) runs automatically\n";
    echo "\n  Check progress at: {$serverUrl}/dashboard/\n";
} else {
    echo "\nNo new advertisers to scrape.\n";
}

// ──────────────────────────────────────

function callApi(string $url): ?array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT => 'AdsIntelligent-CLI/1.0',
    ]);
    $resp = curl_exec($ch);
    curl_close($ch);
    return $resp ? json_decode($resp, true) : null;
}

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
        $title = preg_replace('/\s*[-–|]\s*Google\s+Ads\s+Transparency.*$/i', '', $title);
        if ($title && $title !== 'Google Ads Transparency Center') {
            return $title;
        }
    }
    return '';
}
