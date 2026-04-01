<?php
/**
 * App Groups API — Create, manage, and auto-assign app groups
 *
 * Actions:
 *   list          — Get all groups with member counts
 *   get           — Get one group with its apps and keywords
 *   create        — Create group with keywords
 *   update        — Update group name/keywords/color
 *   delete        — Delete a group
 *   auto_assign   — Run auto-assignment for all groups
 *   add_member    — Manually add app to group
 *   remove_member — Remove app from group
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
                    (SELECT COUNT(*) FROM app_group_keywords k WHERE k.group_id = g.id) AS keyword_count,
                    (SELECT COUNT(*) FROM app_group_members m WHERE m.group_id = g.id) AS member_count
                FROM app_groups g
                ORDER BY g.name ASC
            ")->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'groups' => $groups]);
            break;

        case 'get':
            $id = (int)($_GET['id'] ?? 0);
            if (!$id) throw new Exception('Missing group id');

            $group = $pdo->prepare("SELECT * FROM app_groups WHERE id = ?");
            $group->execute([$id]);
            $group = $group->fetch(PDO::FETCH_ASSOC);
            if (!$group) throw new Exception('Group not found');

            // Keywords
            $kw = $pdo->prepare("SELECT keyword FROM app_group_keywords WHERE group_id = ? ORDER BY keyword");
            $kw->execute([$id]);
            $group['keywords'] = $kw->fetchAll(PDO::FETCH_COLUMN);

            // Members (apps)
            $members = $pdo->prepare("
                SELECT m.id AS member_id, m.matched_keyword, m.auto_assigned, m.created_at AS assigned_at,
                       p.id AS product_id, p.product_name, p.product_type, p.store_platform, p.store_url, p.advertiser_id,
                       a.app_name, a.icon_url, a.category, a.rating, a.downloads, a.developer_name,
                       ma.name AS advertiser_name
                FROM app_group_members m
                JOIN ad_products p ON m.product_id = p.id
                LEFT JOIN app_metadata a ON a.product_id = p.id
                LEFT JOIN managed_advertisers ma ON ma.advertiser_id = p.advertiser_id
                WHERE m.group_id = ?
                ORDER BY p.product_name ASC
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
            $icon = trim($input['icon'] ?? 'bi-collection');
            $keywords = $input['keywords'] ?? [];

            if (!$name) throw new Exception('Group name required');
            if (is_string($keywords)) {
                $keywords = array_filter(array_map('trim', explode(',', $keywords)));
            }
            if (empty($keywords)) throw new Exception('At least one keyword required');

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("INSERT INTO app_groups (name, description, color, icon) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $description, $color, $icon]);
            $groupId = $pdo->lastInsertId();

            $kwStmt = $pdo->prepare("INSERT INTO app_group_keywords (group_id, keyword) VALUES (?, ?)");
            foreach ($keywords as $kw) {
                $kw = strtolower(trim($kw));
                if ($kw) $kwStmt->execute([$groupId, $kw]);
            }

            $pdo->commit();

            // Auto-assign existing apps
            $assigned = autoAssignGroup($pdo, $groupId);

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
                $pdo->prepare("UPDATE app_groups SET " . implode(', ', $fields) . " WHERE id = ?")->execute($params);
            }

            // Update keywords if provided
            if (isset($input['keywords'])) {
                $keywords = $input['keywords'];
                if (is_string($keywords)) {
                    $keywords = array_filter(array_map('trim', explode(',', $keywords)));
                }
                $pdo->prepare("DELETE FROM app_group_keywords WHERE group_id = ?")->execute([$id]);
                $kwStmt = $pdo->prepare("INSERT INTO app_group_keywords (group_id, keyword) VALUES (?, ?)");
                foreach ($keywords as $kw) {
                    $kw = strtolower(trim($kw));
                    if ($kw) $kwStmt->execute([$id, $kw]);
                }
                // Re-run auto-assign
                $pdo->prepare("DELETE FROM app_group_members WHERE group_id = ? AND auto_assigned = 1")->execute([$id]);
                $assigned = autoAssignGroup($pdo, $id);
            }

            $pdo->commit();
            echo json_encode(['success' => true, 'auto_assigned' => $assigned ?? 0]);
            break;

        case 'delete':
            $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
            if (!$id) throw new Exception('Missing group id');
            $pdo->prepare("DELETE FROM app_groups WHERE id = ?")->execute([$id]);
            echo json_encode(['success' => true]);
            break;

        case 'auto_assign':
            // Run auto-assignment for ALL groups
            $groups = $pdo->query("SELECT id FROM app_groups")->fetchAll(PDO::FETCH_COLUMN);
            $total = 0;
            foreach ($groups as $gid) {
                $total += autoAssignGroup($pdo, $gid);
            }
            echo json_encode(['success' => true, 'total_assigned' => $total]);
            break;

        case 'add_member':
            $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $groupId = (int)($input['group_id'] ?? 0);
            $productId = (int)($input['product_id'] ?? 0);
            if (!$groupId || !$productId) throw new Exception('Missing group_id or product_id');

            $stmt = $pdo->prepare("INSERT IGNORE INTO app_group_members (group_id, product_id, auto_assigned) VALUES (?, ?, 0)");
            $stmt->execute([$groupId, $productId]);
            echo json_encode(['success' => true]);
            break;

        case 'remove_member':
            $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $groupId = (int)($input['group_id'] ?? 0);
            $productId = (int)($input['product_id'] ?? 0);
            if (!$groupId || !$productId) throw new Exception('Missing group_id or product_id');

            $pdo->prepare("DELETE FROM app_group_members WHERE group_id = ? AND product_id = ?")->execute([$groupId, $productId]);
            echo json_encode(['success' => true]);
            break;

        case 'unassigned':
            // Get apps not in any group
            $apps = $pdo->query("
                SELECT p.id AS product_id, p.product_name, p.product_type, p.store_platform, p.store_url, p.advertiser_id,
                       a.app_name, a.icon_url, a.category, a.rating, a.downloads,
                       ma.name AS advertiser_name
                FROM ad_products p
                LEFT JOIN app_metadata a ON a.product_id = p.id
                LEFT JOIN managed_advertisers ma ON ma.advertiser_id = p.advertiser_id
                WHERE p.id NOT IN (SELECT product_id FROM app_group_members)
                ORDER BY p.product_name ASC
            ")->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'apps' => $apps]);
            break;

        default:
            throw new Exception("Unknown action: {$action}");
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}


/**
 * Auto-assign apps to a group based on its keywords.
 * Matches against: product_name, app_name, category, description
 */
function autoAssignGroup(PDO $pdo, int $groupId): int
{
    $keywords = $pdo->prepare("SELECT keyword FROM app_group_keywords WHERE group_id = ?");
    $keywords->execute([$groupId]);
    $keywords = $keywords->fetchAll(PDO::FETCH_COLUMN);

    if (empty($keywords)) return 0;

    $assigned = 0;
    $insertStmt = $pdo->prepare(
        "INSERT IGNORE INTO app_group_members (group_id, product_id, matched_keyword, auto_assigned)
         VALUES (?, ?, ?, 1)"
    );

    foreach ($keywords as $kw) {
        $like = '%' . strtolower($kw) . '%';

        // Match against product name, app name, category, and app description
        $matches = $pdo->prepare("
            SELECT DISTINCT p.id
            FROM ad_products p
            LEFT JOIN app_metadata a ON a.product_id = p.id
            WHERE LOWER(p.product_name) LIKE ?
               OR LOWER(COALESCE(a.app_name, '')) LIKE ?
               OR LOWER(COALESCE(a.category, '')) LIKE ?
               OR LOWER(COALESCE(a.description, '')) LIKE ?
        ");
        $matches->execute([$like, $like, $like, $like]);

        foreach ($matches->fetchAll(PDO::FETCH_COLUMN) as $productId) {
            $insertStmt->execute([$groupId, $productId, $kw]);
            if ($insertStmt->rowCount() > 0) $assigned++;
        }
    }

    return $assigned;
}
