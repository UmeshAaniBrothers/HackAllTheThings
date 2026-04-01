<?php
/**
 * One-time utility: Fix table collation mismatches.
 * Converts all tables to utf8mb4_unicode_ci for consistency.
 */
header('Content-Type: application/json');

$basePath = dirname(dirname(__DIR__));
$config = require $basePath . '/config/config.php';
require_once $basePath . '/src/Database.php';

try {
    $db = Database::getInstance($config['db']);

    // 1. Report current collations
    $tables = $db->fetchAll(
        "SELECT TABLE_NAME, TABLE_COLLATION
         FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = ?",
        [$config['db']['name']]
    );

    $report = [];
    $fixed  = [];

    $dryRun = isset($_GET['fix']) ? false : true;

    foreach ($tables as $t) {
        $tname = $t['TABLE_NAME'];
        $tcol  = $t['TABLE_COLLATION'];
        $report[] = ['table' => $tname, 'collation' => $tcol];

        if ($tcol !== 'utf8mb4_unicode_ci' && !$dryRun) {
            try {
                $db->query("ALTER TABLE `{$tname}` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                $fixed[] = $tname;
            } catch (Exception $e) {
                $fixed[] = $tname . ' (FAILED: ' . $e->getMessage() . ')';
            }
        }
    }

    // 2. Also check column-level collation mismatches
    $columns = $db->fetchAll(
        "SELECT TABLE_NAME, COLUMN_NAME, COLLATION_NAME
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = ? AND COLLATION_NAME IS NOT NULL AND COLLATION_NAME != 'utf8mb4_unicode_ci'
         ORDER BY TABLE_NAME, COLUMN_NAME",
        [$config['db']['name']]
    );

    echo json_encode([
        'success'   => true,
        'dry_run'   => $dryRun,
        'hint'      => $dryRun ? 'Add ?fix=1 to actually fix collations' : 'Fix applied',
        'tables'    => $report,
        'mismatched_columns' => $columns,
        'fixed'     => $fixed,
        'version'   => 'collation_fix_v1',
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
