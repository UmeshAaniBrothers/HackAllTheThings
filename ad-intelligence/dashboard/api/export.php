<?php

/**
 * API: Export & Reports
 * Generates CSV, HTML/PDF exports and manages scheduled reports.
 */

$basePath = dirname(dirname(__DIR__));
$config = require $basePath . '/config/config.php';

require_once $basePath . '/src/Database.php';
require_once $basePath . '/src/ExportManager.php';

try {
    $db = Database::getInstance($config['db']);
    $exportPath = $config['storage']['export_path'] ?? dirname(__DIR__, 2) . '/storage/exports/';
    $exporter = new ExportManager($db, $exportPath);

    $action = $_GET['action'] ?? 'csv';

    switch ($action) {
        case 'csv':
            $filters = [
                'advertiser_id' => $_GET['advertiser_id'] ?? null,
                'status'        => $_GET['status'] ?? null,
                'ad_type'       => $_GET['ad_type'] ?? null,
            ];
            $filepath = $exporter->exportAdsCsv($filters);
            $filename = basename($filepath);

            header('Content-Type: text/csv');
            header("Content-Disposition: attachment; filename=\"{$filename}\"");
            readfile($filepath);
            unlink($filepath);
            break;

        case 'report':
            $type = $_GET['type'] ?? 'overview';
            $params = [
                'advertiser_id' => $_GET['advertiser_id'] ?? null,
                'watchlist_id'  => $_GET['watchlist_id'] ?? null,
            ];
            $filepath = $exporter->generateHtmlReport($type, $params);
            $filename = basename($filepath);

            header('Content-Type: text/html');
            header("Content-Disposition: attachment; filename=\"{$filename}\"");
            readfile($filepath);
            unlink($filepath);
            break;

        case 'json':
            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="export_' . date('Y-m-d') . '.json"');

            $filters = ['advertiser_id' => $_GET['advertiser_id'] ?? null];
            $filepath = $exporter->exportAdsCsv($filters); // Reuse filter logic
            // Actually export as JSON
            $ads = $db->fetchAll("SELECT a.*, d.headline, d.cta FROM ads a LEFT JOIN ad_details d ON a.creative_id = d.creative_id AND d.id = (SELECT MAX(id) FROM ad_details WHERE creative_id = a.creative_id) ORDER BY a.last_seen DESC LIMIT 5000");
            echo json_encode($ads, JSON_PRETTY_PRINT);
            break;

        default:
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Unknown action. Use: csv, report, json']);
    }

} catch (Exception $e) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
