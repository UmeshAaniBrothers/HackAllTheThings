<?php

/**
 * API: Data Quality Dashboard
 * Returns quality metrics, issues, and health status.
 */

header('Content-Type: application/json');

$basePath = dirname(dirname(__DIR__));
$config = require $basePath . '/config/config.php';

require_once $basePath . '/src/Database.php';
require_once $basePath . '/src/DataQuality.php';

try {
    $db = Database::getInstance($config['db']);
    $quality = new DataQuality($db);

    echo json_encode([
        'success' => true,
        'data'    => $quality->getDashboard(),
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
