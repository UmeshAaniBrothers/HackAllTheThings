<?php
header('Content-Type: application/json');

$basePath = dirname(dirname(__DIR__));
$config = require $basePath . '/config/config.php';

$authToken = $config['ingest_token'] ?? 'ads-intelligent-2024';
if (($_GET['token'] ?? '') !== $authToken) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid token']);
    exit;
}

$output = [];
$returnCode = 0;

// Change to project root and pull
chdir($basePath);
exec('git pull origin main 2>&1', $output, $returnCode);

echo json_encode([
    'success' => $returnCode === 0,
    'output' => implode("\n", $output),
    'return_code' => $returnCode,
], JSON_PRETTY_PRINT);
