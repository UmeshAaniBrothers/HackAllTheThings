<?php
header('Content-Type: text/plain');
echo "PHP Version: " . phpversion() . "\n\n";

// Test 1: Config
echo "=== Test 1: Config ===\n";
$basePath = dirname(dirname(__DIR__));
$configFile = $basePath . '/config/config.php';
if (file_exists($configFile)) {
    echo "config.php exists\n";
    try {
        $config = require $configFile;
        echo "Config loaded OK\n";
    } catch (Throwable $e) {
        echo "Config error: " . $e->getMessage() . "\n";
    }
} else {
    echo "config.php NOT FOUND at: " . $configFile . "\n";
}

// Test 2: Source files
echo "\n=== Test 2: Source Files ===\n";
$files = ['Database.php', 'Scraper.php', 'AssetManager.php', 'Processor.php'];
foreach ($files as $f) {
    $path = $basePath . '/src/' . $f;
    if (file_exists($path)) {
        echo "$f exists (" . filesize($path) . " bytes)\n";
    } else {
        echo "$f NOT FOUND\n";
    }
}

// Test 3: Try loading classes one by one
echo "\n=== Test 3: Load Classes ===\n";
foreach ($files as $f) {
    $path = $basePath . '/src/' . $f;
    if (!file_exists($path)) continue;
    try {
        require_once $path;
        echo "$f loaded OK\n";
    } catch (Throwable $e) {
        echo "$f FAILED: " . $e->getMessage() . " (line " . $e->getLine() . ")\n";
    }
}

// Test 4: Try Database connection
echo "\n=== Test 4: Database ===\n";
if (class_exists('Database') && isset($config)) {
    try {
        $db = Database::getInstance($config['db']);
        echo "Database connected OK\n";
    } catch (Throwable $e) {
        echo "Database error: " . $e->getMessage() . "\n";
    }
} else {
    echo "Skipped (class or config not available)\n";
}

// Test 5: Try Scraper
echo "\n=== Test 5: Scraper ===\n";
if (class_exists('Scraper') && isset($db) && isset($config)) {
    try {
        $scraper = new Scraper($db, $config['scraper'] ?? []);
        echo "Scraper created OK\n";
    } catch (Throwable $e) {
        echo "Scraper error: " . $e->getMessage() . " (line " . $e->getLine() . ")\n";
    }
} else {
    echo "Skipped\n";
}

echo "\n=== Done ===\n";
