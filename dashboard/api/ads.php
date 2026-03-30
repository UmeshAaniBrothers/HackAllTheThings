<?php

/**
 * API: Ad Listing
 * Returns filtered, paginated ad listings for the explorer.
 */

header('Content-Type: application/json');

$basePath = dirname(dirname(__DIR__));
$config = require $basePath . '/config/config.php';

require_once $basePath . '/src/Database.php';

try {
    $db = Database::getInstance($config['db']);

    // Parse filters
    $advertiserId = isset($_GET['advertiser_id']) ? trim($_GET['advertiser_id']) : null;
    $country = isset($_GET['country']) ? trim($_GET['country']) : null;
    $platform = isset($_GET['platform']) ? trim($_GET['platform']) : null;
    $adType = isset($_GET['ad_type']) ? trim($_GET['ad_type']) : null;
    $status = isset($_GET['status']) ? trim($_GET['status']) : null;
    $dateFrom = isset($_GET['date_from']) ? trim($_GET['date_from']) : null;
    $dateTo = isset($_GET['date_to']) ? trim($_GET['date_to']) : null;
    $search = isset($_GET['search']) ? trim($_GET['search']) : null;
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = min(100, max(10, (int) ($_GET['per_page'] ?? 20)));

    $where = ['1=1'];
    $params = [];

    if ($advertiserId) {
        $where[] = 'a.advertiser_id = ?';
        $params[] = $advertiserId;
    }
    if ($adType && in_array($adType, ['text', 'image', 'video'])) {
        $where[] = 'a.ad_type = ?';
        $params[] = $adType;
    }
    if ($status && in_array($status, ['active', 'inactive'])) {
        $where[] = 'a.status = ?';
        $params[] = $status;
    }
    if ($dateFrom) {
        $where[] = 'a.first_seen >= ?';
        $params[] = $dateFrom;
    }
    if ($dateTo) {
        $where[] = 'a.last_seen <= ?';
        $params[] = $dateTo;
    }

    // Country/platform filter via subquery
    if ($country) {
        $where[] = 'EXISTS (SELECT 1 FROM ad_targeting t WHERE t.creative_id = a.creative_id AND t.country = ?)';
        $params[] = $country;
    }
    if ($platform) {
        $where[] = 'EXISTS (SELECT 1 FROM ad_targeting t WHERE t.creative_id = a.creative_id AND t.platform = ?)';
        $params[] = $platform;
    }

    // Search in headline/description
    if ($search) {
        $where[] = 'EXISTS (SELECT 1 FROM ad_details d WHERE d.creative_id = a.creative_id AND (d.headline LIKE ? OR d.description LIKE ?))';
        $searchParam = '%' . $search . '%';
        $params[] = $searchParam;
        $params[] = $searchParam;
    }

    $whereClause = implode(' AND ', $where);

    // Count total
    $total = (int) $db->fetchColumn(
        "SELECT COUNT(*) FROM ads a WHERE {$whereClause}",
        $params
    );

    // Fetch page
    $offset = ($page - 1) * $perPage;
    $fetchParams = array_merge($params, [$perPage, $offset]);

    $ads = $db->fetchAll(
        "SELECT a.creative_id, a.advertiser_id, a.ad_type, a.first_seen, a.last_seen, a.status,
                d.headline, d.description, d.cta, d.landing_url,
                (SELECT GROUP_CONCAT(DISTINCT t.country) FROM ad_targeting t WHERE t.creative_id = a.creative_id) as countries,
                (SELECT GROUP_CONCAT(DISTINCT t.platform) FROM ad_targeting t WHERE t.creative_id = a.creative_id) as platforms,
                (SELECT original_url FROM ad_assets ass WHERE ass.creative_id = a.creative_id AND ass.type IN ('image','preview') LIMIT 1) as preview_image,
                adv.name as advertiser_name
         FROM ads a
         LEFT JOIN ad_details d ON a.creative_id = d.creative_id
             AND d.id = (SELECT MAX(id) FROM ad_details WHERE creative_id = a.creative_id)
         LEFT JOIN managed_advertisers adv ON a.advertiser_id = adv.advertiser_id
         WHERE {$whereClause}
         ORDER BY a.last_seen DESC
         LIMIT ? OFFSET ?",
        $fetchParams
    );

    // Get available filter values
    $filterOptions = [
        'advertisers' => $db->fetchAll("SELECT DISTINCT advertiser_id FROM ads ORDER BY advertiser_id"),
        'countries'   => $db->fetchAll("SELECT DISTINCT country FROM ad_targeting ORDER BY country"),
        'platforms'   => $db->fetchAll("SELECT DISTINCT platform FROM ad_targeting ORDER BY platform"),
    ];

    echo json_encode([
        'success'        => true,
        'ads'            => $ads,
        'total'          => $total,
        'page'           => $page,
        'per_page'       => $perPage,
        'total_pages'    => ceil($total / $perPage),
        'filter_options' => $filterOptions,
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
