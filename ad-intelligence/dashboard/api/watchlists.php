<?php

/**
 * API: Watchlists
 * CRUD operations for competitor watchlists and daily summaries.
 */

header('Content-Type: application/json');

$basePath = dirname(dirname(__DIR__));
$config = require $basePath . '/config/config.php';

require_once $basePath . '/src/Database.php';
require_once $basePath . '/src/Watchlist.php';

try {
    $db = Database::getInstance($config['db']);
    $watchlist = new Watchlist($db);

    $action = $_GET['action'] ?? 'list';

    switch ($action) {
        case 'list':
            echo json_encode(['success' => true, 'watchlists' => $watchlist->getAll(), 'groups' => $watchlist->getGroups()]);
            break;

        case 'get':
            $id = (int) ($_GET['id'] ?? 0);
            $data = $watchlist->getById($id);
            echo json_encode(['success' => (bool) $data, 'watchlist' => $data]);
            break;

        case 'summary':
            $id = (int) ($_GET['id'] ?? 0);
            echo json_encode(['success' => true, 'data' => $watchlist->getDailySummary($id)]);
            break;

        case 'changelog':
            $advId = trim($_GET['advertiser_id'] ?? '');
            $days = (int) ($_GET['days'] ?? 7);
            echo json_encode(['success' => true, 'changes' => $watchlist->getAdvertiserChangeLog($advId, $days)]);
            break;

        case 'create':
            $name = trim($_GET['name'] ?? $_POST['name'] ?? '');
            $group = trim($_GET['group'] ?? $_POST['group'] ?? '') ?: null;
            if (empty($name)) {
                echo json_encode(['success' => false, 'error' => 'Name required']);
                break;
            }
            $id = $watchlist->create($name, $group);
            echo json_encode(['success' => true, 'id' => $id]);
            break;

        case 'add_advertiser':
            $wId = (int) ($_GET['watchlist_id'] ?? $_POST['watchlist_id'] ?? 0);
            $advId = trim($_GET['advertiser_id'] ?? $_POST['advertiser_id'] ?? '');
            $advName = trim($_GET['advertiser_name'] ?? $_POST['advertiser_name'] ?? '') ?: null;
            $id = $watchlist->addAdvertiser($wId, $advId, $advName);
            echo json_encode(['success' => true, 'id' => $id]);
            break;

        case 'remove_advertiser':
            $wId = (int) ($_GET['watchlist_id'] ?? $_POST['watchlist_id'] ?? 0);
            $advId = trim($_GET['advertiser_id'] ?? $_POST['advertiser_id'] ?? '');
            $watchlist->removeAdvertiser($wId, $advId);
            echo json_encode(['success' => true]);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Unknown action']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
