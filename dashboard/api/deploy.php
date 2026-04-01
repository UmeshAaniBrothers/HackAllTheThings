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

chdir($basePath);

// Try multiple methods since exec() may be disabled
$output = '';
$returnCode = -1;

// Method 1: proc_open (usually not disabled)
if (function_exists('proc_open')) {
    $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $proc = proc_open('git pull origin main', $descriptors, $pipes, $basePath);
    if (is_resource($proc)) {
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $returnCode = proc_close($proc);
    }
}

// Method 2: shell_exec fallback
if ($returnCode === -1 && function_exists('shell_exec')) {
    $output = shell_exec('cd ' . escapeshellarg($basePath) . ' && git pull origin main 2>&1') ?? '';
    $returnCode = 0;
}

// Method 3: exec fallback
if ($returnCode === -1 && function_exists('exec')) {
    $lines = [];
    exec('git pull origin main 2>&1', $lines, $returnCode);
    $output = implode("\n", $lines);
}

// Method 4: passthru fallback
if ($returnCode === -1 && function_exists('passthru')) {
    ob_start();
    passthru('git pull origin main 2>&1', $returnCode);
    $output = ob_get_clean();
}

if ($returnCode === -1) {
    $output = 'All execution methods (proc_open, shell_exec, exec, passthru) are disabled on this server.';
}

echo json_encode([
    'success' => $returnCode === 0,
    'output' => trim($output),
    'return_code' => $returnCode,
    'method_used' => $returnCode !== -1 ? 'auto' : 'none',
], JSON_PRETTY_PRINT);
