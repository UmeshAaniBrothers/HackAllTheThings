<?php

/**
 * API: Tagging System
 * CRUD for tags and ad-tag associations.
 */

header('Content-Type: application/json');

$basePath = dirname(dirname(__DIR__));
$config = require $basePath . '/config/config.php';

require_once $basePath . '/src/Database.php';
require_once $basePath . '/src/TaggingSystem.php';

try {
    $db = Database::getInstance($config['db']);
    $tags = new TaggingSystem($db);

    $action = $_GET['action'] ?? 'list';

    switch ($action) {
        case 'list':
            echo json_encode(['success' => true, 'tags' => $tags->getAllTags()]);
            break;

        case 'ad_tags':
            $creativeId = trim($_GET['creative_id'] ?? '');
            echo json_encode(['success' => true, 'tags' => $tags->getAdTags($creativeId)]);
            break;

        case 'by_tag':
            $tagId = (int) ($_GET['tag_id'] ?? 0);
            echo json_encode(['success' => true, 'ads' => $tags->getAdsByTag($tagId)]);
            break;

        case 'tag_ad':
            $creativeId = trim($_GET['creative_id'] ?? $_POST['creative_id'] ?? '');
            $tagId = (int) ($_GET['tag_id'] ?? $_POST['tag_id'] ?? 0);
            $result = $tags->tagAd($creativeId, $tagId);
            echo json_encode(['success' => $result]);
            break;

        case 'untag_ad':
            $creativeId = trim($_GET['creative_id'] ?? $_POST['creative_id'] ?? '');
            $tagId = (int) ($_GET['tag_id'] ?? $_POST['tag_id'] ?? 0);
            $tags->untagAd($creativeId, $tagId);
            echo json_encode(['success' => true]);
            break;

        case 'create':
            $name = trim($_GET['name'] ?? $_POST['name'] ?? '');
            $color = trim($_GET['color'] ?? $_POST['color'] ?? '#6c757d');
            if (empty($name)) {
                echo json_encode(['success' => false, 'error' => 'Name required']);
                break;
            }
            $id = $tags->createTag($name, 'manual', $color);
            echo json_encode(['success' => true, 'id' => $id]);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Unknown action']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
