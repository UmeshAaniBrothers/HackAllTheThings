<?php
/**
 * Auto-Deploy Webhook
 * Triggers Cloudways git deployment when called.
 *
 * Usage:
 *   GET /dashboard/api/autodeploy.php
 *   GET /dashboard/api/autodeploy.php?token=ads-intelligent-2024
 *
 * Can be added as a GitHub webhook or called manually after git push.
 */

header('Content-Type: application/json');

$basePath = dirname(dirname(__DIR__));
$config = require $basePath . '/config/config.php';

// Optional token check
$authToken = $config['ingest_token'] ?? 'ads-intelligent-2024';
$providedToken = $_GET['token'] ?? $_SERVER['HTTP_X_DEPLOY_TOKEN'] ?? null;

// Allow without token for GitHub webhooks (they use signatures), or with token for manual
if ($providedToken && $providedToken !== $authToken) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid token']);
    exit;
}

// Cloudways Git Auto-Deploy settings
$cloudwaysConfig = [
    'server_id'   => '1170423',
    'app_id'      => '6314737',
    'git_url'     => 'git@github.com:UmeshAaniBrothers/HackAllTheThings.git',
    'branch_name' => 'claude/ad-intelligence-dashboard-Cw2P8',
];

$deployUrl = 'https://phpstack-1170423-6314737.cloudwaysapps.com/gitautodeploy.php?' . http_build_query($cloudwaysConfig);

// Trigger the Cloudways deploy
$ch = curl_init($deployUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => true,
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error    = curl_error($ch);
curl_close($ch);

$result = json_decode($response, true);

echo json_encode([
    'success'       => ($result['status'] ?? false) === true,
    'operation_id'  => $result['operation_id'] ?? null,
    'deploy_url'    => $deployUrl,
    'http_code'     => $httpCode,
    'error'         => $error ?: null,
    'cloudways_raw' => $result,
    'timestamp'     => date('Y-m-d H:i:s'),
], JSON_PRETTY_PRINT);
