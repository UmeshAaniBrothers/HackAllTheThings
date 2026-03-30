<?php
header('Content-Type: text/plain');
echo "PHP Version: " . phpversion() . "\n\n";

$basePath = dirname(dirname(__DIR__));

// Test 1: Config
echo "=== Config ===\n";
$configFile = $basePath . '/config/config.php';
if (file_exists($configFile)) {
    try {
        $config = require $configFile;
        echo "Config loaded OK\n";
    } catch (Throwable $e) {
        echo "Config error: " . $e->getMessage() . "\n";
    }
} else {
    echo "config.php NOT FOUND\n";
}

// Test 2: Source files
echo "\n=== Source Files ===\n";
$files = ['Database.php', 'AssetManager.php', 'Processor.php'];
foreach ($files as $f) {
    $path = $basePath . '/src/' . $f;
    echo file_exists($path) ? "$f OK\n" : "$f NOT FOUND\n";
}

// Test 3: Database connection
echo "\n=== Database ===\n";
try {
    require_once $basePath . '/src/Database.php';
    $db = Database::getInstance($config['db']);
    echo "Connected OK\n";

    $adCount = $db->fetchColumn("SELECT COUNT(*) FROM ads");
    $pendingPayloads = $db->fetchColumn("SELECT COUNT(*) FROM raw_payloads WHERE processed_flag = 0");
    echo "Ads: {$adCount}, Pending payloads: {$pendingPayloads}\n";
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

// Test 4: Tables
echo "\n=== Tables ===\n";
$tables = ['ads', 'ad_details', 'ad_assets', 'ad_targeting', 'ad_products', 'ad_product_map', 'raw_payloads', 'managed_advertisers', 'scrape_logs'];
foreach ($tables as $t) {
    try {
        $count = $db->fetchColumn("SELECT COUNT(*) FROM {$t}");
        echo "{$t}: {$count} rows\n";
    } catch (Throwable $e) {
        echo "{$t}: NOT FOUND\n";
    }
}

echo "\n=== Done ===\n";
