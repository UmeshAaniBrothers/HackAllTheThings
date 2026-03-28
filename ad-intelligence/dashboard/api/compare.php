<?php

/**
 * API: Advertiser Comparison
 * Compares two or more advertisers across all metrics.
 */

header('Content-Type: application/json');

$basePath = dirname(dirname(__DIR__));
$config = require $basePath . '/config/config.php';

require_once $basePath . '/src/Database.php';
require_once $basePath . '/src/ComparisonEngine.php';

try {
    $db = Database::getInstance($config['db']);
    $engine = new ComparisonEngine($db);

    $advA = trim($_GET['a'] ?? '');
    $advB = trim($_GET['b'] ?? '');
    $ids = isset($_GET['ids']) ? explode(',', $_GET['ids']) : [];

    if (!empty($ids) && count($ids) >= 2) {
        $result = $engine->compareMultiple(array_map('trim', $ids));
    } elseif ($advA && $advB) {
        $result = $engine->compare($advA, $advB);
    } else {
        echo json_encode(['success' => false, 'error' => 'Provide ?a=ID1&b=ID2 or ?ids=ID1,ID2,ID3']);
        exit;
    }

    echo json_encode(['success' => true, 'data' => $result]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
