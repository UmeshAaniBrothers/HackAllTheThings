<?php

/**
 * API: Alerts & Notifications
 * Returns recent alerts, summary, and notification dashboard data.
 */

header('Content-Type: application/json');

$basePath = dirname(dirname(__DIR__));
$config = require $basePath . '/config/config.php';

require_once $basePath . '/src/Database.php';
require_once $basePath . '/src/AlertManager.php';

try {
    $db = Database::getInstance($config['db']);
    $alerts = new AlertManager($db, $config);

    $recentAlerts = $alerts->getRecentAlerts(50);
    $todaySummary = $alerts->getTodaySummary();

    // Get alert rules
    $rules = $db->fetchAll("SELECT * FROM alert_rules ORDER BY created_at DESC");

    echo json_encode([
        'success'  => true,
        'alerts'   => $recentAlerts,
        'summary'  => $todaySummary,
        'rules'    => $rules,
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
