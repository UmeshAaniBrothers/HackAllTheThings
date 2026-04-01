<?php
/**
 * Video Groups API — Create, manage, and auto-assign video groups
 *
 * Actions:
 *   list, get, create, update, delete, auto_assign, add_member, remove_member, unassigned
 */

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

$basePath = dirname(dirname(__DIR__));
$config = require $basePath . '/config/config.php';
require_once $basePath . '/src/Database.php';

$db = Database::getInstance($config['db']);
$pdo = $db->getPdo();

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

try {
    switch ($action) {

        case 'list':
            $groups = $pdo->query("
                SELECT g.*,
                    (SELECT COUNT(*) FROM video_group_keywords k WHERE k.group_id = g.id) AS keyword_count,
                    (SELECT COUNT(*) FROM video_group_members m WHERE m.group_id = g.id) AS member_count,
                    (SELECT SUM(COALESCE(ym.view_count, 0)) FROM video_group_members m2
                     JOIN youtube_metadata ym ON ym.video_id = m2.video_id
                     WHERE m2.group_id = g.id) AS total_views
                FROM video_groups g
                ORDER BY g.name ASC
            ")->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'groups' => $groups]);
            break;

        case 'get':
            $id = (int)($_GET['id'] ?? 0);
            if (!$id) throw new Exception('Missing group id');

            $group = $pdo->prepare("SELECT * FROM video_groups WHERE id = ?");
            $group->execute([$id]);
            $group = $group->fetch(PDO::FETCH_ASSOC);
            if (!$group) throw new Exception('Group not found');

            $kw = $pdo->prepare("SELECT keyword FROM video_group_keywords WHERE group_id = ? ORDER BY keyword");
            $kw->execute([$id]);
            $group['keywords'] = $kw->fetchAll(PDO::FETCH_COLUMN);

            $members = $pdo->prepare("
                SELECT m.id AS member_id, m.matched_keyword, m.auto_assigned, m.created_at AS assigned_at,
                       ym.video_id, ym.title, ym.channel_name, ym.channel_id, ym.view_count,
                       ym.like_count, ym.comment_count, ym.thumbnail_url, ym.duration, ym.publish_date
                FROM video_group_members m
                JOIN youtube_metadata ym ON ym.video_id = m.video_id
                WHERE m.group_id = ?
                ORDER BY ym.view_count DESC
            ");
            $members->execute([$id]);
            $group['members'] = $members->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'group' => $group]);
            break;

        case 'create':
            $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $name = trim($input['name'] ?? '');
            $description = trim($input['description'] ?? '');
            $color = trim($input['color'] ?? '#6c757d');
            $icon = trim($input['icon'] ?? 'bi-camera-video');
            $keywords = $input['keywords'] ?? [];

            if (!$name) throw new Exception('Group name required');
            if (is_string($keywords)) {
                $keywords = array_filter(array_map('trim', explode(',', $keywords)));
            }
            if (empty($keywords)) throw new Exception('At least one keyword required');

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("INSERT INTO video_groups (name, description, color, icon) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $description, $color, $icon]);
            $groupId = $pdo->lastInsertId();

            $kwStmt = $pdo->prepare("INSERT INTO video_group_keywords (group_id, keyword) VALUES (?, ?)");
            foreach ($keywords as $kw) {
                $kw = strtolower(trim($kw));
                if ($kw) $kwStmt->execute([$groupId, $kw]);
            }

            $pdo->commit();

            $assigned = autoAssignVideoGroup($pdo, $groupId);
            echo json_encode(['success' => true, 'group_id' => $groupId, 'auto_assigned' => $assigned]);
            break;

        case 'update':
            $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $id = (int)($input['id'] ?? $_GET['id'] ?? 0);
            if (!$id) throw new Exception('Missing group id');

            $pdo->beginTransaction();

            $fields = [];
            $params = [];
            if (isset($input['name'])) { $fields[] = 'name = ?'; $params[] = trim($input['name']); }
            if (isset($input['description'])) { $fields[] = 'description = ?'; $params[] = trim($input['description']); }
            if (isset($input['color'])) { $fields[] = 'color = ?'; $params[] = trim($input['color']); }
            if (isset($input['icon'])) { $fields[] = 'icon = ?'; $params[] = trim($input['icon']); }

            if ($fields) {
                $params[] = $id;
                $pdo->prepare("UPDATE video_groups SET " . implode(', ', $fields) . " WHERE id = ?")->execute($params);
            }

            $assigned = 0;
            if (isset($input['keywords'])) {
                $keywords = $input['keywords'];
                if (is_string($keywords)) {
                    $keywords = array_filter(array_map('trim', explode(',', $keywords)));
                }
                $pdo->prepare("DELETE FROM video_group_keywords WHERE group_id = ?")->execute([$id]);
                $kwStmt = $pdo->prepare("INSERT INTO video_group_keywords (group_id, keyword) VALUES (?, ?)");
                foreach ($keywords as $kw) {
                    $kw = strtolower(trim($kw));
                    if ($kw) $kwStmt->execute([$id, $kw]);
                }
                $pdo->prepare("DELETE FROM video_group_members WHERE group_id = ? AND auto_assigned = 1")->execute([$id]);
                $assigned = autoAssignVideoGroup($pdo, $id);
            }

            $pdo->commit();
            echo json_encode(['success' => true, 'auto_assigned' => $assigned]);
            break;

        case 'delete':
            $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
            if (!$id) throw new Exception('Missing group id');
            $pdo->prepare("DELETE FROM video_groups WHERE id = ?")->execute([$id]);
            echo json_encode(['success' => true]);
            break;

        case 'auto_assign':
            $groups = $pdo->query("SELECT id FROM video_groups")->fetchAll(PDO::FETCH_COLUMN);
            $total = 0;
            foreach ($groups as $gid) {
                $total += autoAssignVideoGroup($pdo, $gid);
            }
            echo json_encode(['success' => true, 'total_assigned' => $total]);
            break;

        case 'add_member':
            $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $groupId = (int)($input['group_id'] ?? 0);
            $videoId = trim($input['video_id'] ?? '');
            if (!$groupId || !$videoId) throw new Exception('Missing group_id or video_id');

            $pdo->prepare("INSERT IGNORE INTO video_group_members (group_id, video_id, auto_assigned) VALUES (?, ?, 0)")
                ->execute([$groupId, $videoId]);
            echo json_encode(['success' => true]);
            break;

        case 'remove_member':
            $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $groupId = (int)($input['group_id'] ?? 0);
            $videoId = trim($input['video_id'] ?? '');
            if (!$groupId || !$videoId) throw new Exception('Missing group_id or video_id');

            $pdo->prepare("DELETE FROM video_group_members WHERE group_id = ? AND video_id = ?")
                ->execute([$groupId, $videoId]);
            echo json_encode(['success' => true]);
            break;

        case 'unassigned':
            $videos = $pdo->query("
                SELECT ym.video_id, ym.title, ym.channel_name, ym.view_count, ym.thumbnail_url, ym.duration
                FROM youtube_metadata ym
                WHERE ym.video_id NOT IN (SELECT video_id FROM video_group_members)
                  AND ym.title IS NOT NULL
                ORDER BY ym.view_count DESC
                LIMIT 100
            ")->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'videos' => $videos]);
            break;

        default:
            throw new Exception("Unknown action: {$action}");
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

/**
 * Auto-assign videos to a group based on keywords.
 * Matches against: title, channel_name, description
 */
function autoAssignVideoGroup(PDO $pdo, int $groupId): int
{
    $keywords = $pdo->prepare("SELECT keyword FROM video_group_keywords WHERE group_id = ?");
    $keywords->execute([$groupId]);
    $keywords = $keywords->fetchAll(PDO::FETCH_COLUMN);

    if (empty($keywords)) {
        $pdo->prepare("DELETE FROM video_group_members WHERE group_id = ? AND auto_assigned = 1")->execute([$groupId]);
        return 0;
    }

    // Find all videos matching any keyword
    $matchedIds = [];
    $matchedKeyword = [];

    foreach ($keywords as $kw) {
        $like = '%' . strtolower($kw) . '%';
        $matches = $pdo->prepare("
            SELECT video_id FROM youtube_metadata
            WHERE LOWER(COALESCE(title, '')) LIKE ?
               OR LOWER(COALESCE(channel_name, '')) LIKE ?
               OR LOWER(COALESCE(description, '')) LIKE ?
        ");
        $matches->execute([$like, $like, $like]);

        foreach ($matches->fetchAll(PDO::FETCH_COLUMN) as $vid) {
            $matchedIds[$vid] = true;
            if (!isset($matchedKeyword[$vid])) $matchedKeyword[$vid] = $kw;
        }
    }

    // Remove auto-assigned that no longer match
    if (!empty($matchedIds)) {
        $ph = implode(',', array_fill(0, count($matchedIds), '?'));
        $pdo->prepare("DELETE FROM video_group_members WHERE group_id = ? AND auto_assigned = 1 AND video_id NOT IN ($ph)")
            ->execute(array_merge([$groupId], array_keys($matchedIds)));
    } else {
        $pdo->prepare("DELETE FROM video_group_members WHERE group_id = ? AND auto_assigned = 1")->execute([$groupId]);
    }

    // Add new matches
    $assigned = 0;
    $insertStmt = $pdo->prepare(
        "INSERT IGNORE INTO video_group_members (group_id, video_id, matched_keyword, auto_assigned) VALUES (?, ?, ?, 1)"
    );
    foreach ($matchedKeyword as $vid => $kw) {
        $insertStmt->execute([$groupId, $vid, $kw]);
        if ($insertStmt->rowCount() > 0) $assigned++;
    }

    return $assigned;
}
