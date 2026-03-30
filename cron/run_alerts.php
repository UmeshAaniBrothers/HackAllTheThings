<?php

/**
 * CRON: Run Alerts
 *
 * Checks all alert rules and sends notifications
 * via configured channels (Email, Telegram, Slack).
 *
 * Schedule: Every 6 hours (after fetch)
 * 30 *​/6 * * * php /path/to/ad-intelligence/cron/run_alerts.php
 */

set_time_limit(0);

$basePath = dirname(__DIR__);
$config = require $basePath . '/config/config.php';

require_once $basePath . '/src/Database.php';
require_once $basePath . '/src/AlertManager.php';

echo "[" . date('Y-m-d H:i:s') . "] === RUN ALERTS START ===\n";

try {
    $db = Database::getInstance($config['db']);
    $alerts = new AlertManager($db, $config);

    $results = $alerts->processAlerts();

    $total = array_sum($results);
    echo "\nAlerts sent: {$total}\n";
    foreach ($results as $type => $count) {
        echo "  {$type}: {$count}\n";
    }

    echo "\n[" . date('Y-m-d H:i:s') . "] === RUN ALERTS COMPLETE ===\n";

} catch (Exception $e) {
    echo "FATAL ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
