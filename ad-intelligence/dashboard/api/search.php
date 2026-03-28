<?php

/**
 * API: Advanced Search Engine
 * Search by keyword, domain, CTA type, country+platform combo.
 */

header('Content-Type: application/json');

$basePath = dirname(dirname(__DIR__));
$config = require $basePath . '/config/config.php';

require_once $basePath . '/src/Database.php';

try {
    $db = Database::getInstance($config['db']);

    $keyword = isset($_GET['q']) ? trim($_GET['q']) : null;
    $domain = isset($_GET['domain']) ? trim($_GET['domain']) : null;
    $ctaType = isset($_GET['cta']) ? trim($_GET['cta']) : null;
    $country = isset($_GET['country']) ? trim($_GET['country']) : null;
    $platform = isset($_GET['platform']) ? trim($_GET['platform']) : null;
    $adType = isset($_GET['ad_type']) ? trim($_GET['ad_type']) : null;
    $tag = isset($_GET['tag']) ? trim($_GET['tag']) : null;
    $sentiment = isset($_GET['sentiment']) ? trim($_GET['sentiment']) : null;
    $hookType = isset($_GET['hook']) ? trim($_GET['hook']) : null;
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = min(100, max(10, (int) ($_GET['per_page'] ?? 20)));

    $where = ['1=1'];
    $params = [];
    $joins = [];

    // Keyword search (headline + description)
    if ($keyword) {
        $joins['ad_details'] = "LEFT JOIN ad_details d ON a.creative_id = d.creative_id AND d.id = (SELECT MAX(id) FROM ad_details WHERE creative_id = a.creative_id)";
        $where[] = "(d.headline LIKE ? OR d.description LIKE ? OR d.cta LIKE ?)";
        $searchParam = '%' . $keyword . '%';
        $params = array_merge($params, [$searchParam, $searchParam, $searchParam]);
    } else {
        $joins['ad_details'] = "LEFT JOIN ad_details d ON a.creative_id = d.creative_id AND d.id = (SELECT MAX(id) FROM ad_details WHERE creative_id = a.creative_id)";
    }

    // Domain search
    if ($domain) {
        $where[] = "d.landing_url LIKE ?";
        $params[] = '%' . $domain . '%';
    }

    // CTA type search
    if ($ctaType) {
        $where[] = "d.cta LIKE ?";
        $params[] = '%' . $ctaType . '%';
    }

    // Ad type filter
    if ($adType && in_array($adType, ['text', 'image', 'video'])) {
        $where[] = "a.ad_type = ?";
        $params[] = $adType;
    }

    // Country + platform combo
    if ($country || $platform) {
        $targetWhere = [];
        if ($country) {
            $targetWhere[] = "t.country = ?";
            $params[] = $country;
        }
        if ($platform) {
            $targetWhere[] = "t.platform = ?";
            $params[] = $platform;
        }
        $where[] = "EXISTS (SELECT 1 FROM ad_targeting t WHERE t.creative_id = a.creative_id AND " . implode(' AND ', $targetWhere) . ")";
    }

    // Tag filter
    if ($tag) {
        $where[] = "EXISTS (SELECT 1 FROM ad_tags at INNER JOIN tags tg ON at.tag_id = tg.id WHERE at.creative_id = a.creative_id AND tg.name = ?)";
        $params[] = $tag;
    }

    // Sentiment filter
    if ($sentiment) {
        $where[] = "EXISTS (SELECT 1 FROM ai_ad_analysis ai WHERE ai.creative_id = a.creative_id AND ai.sentiment = ?)";
        $params[] = $sentiment;
    }

    // Hook type filter
    if ($hookType) {
        $where[] = "EXISTS (SELECT 1 FROM ai_ad_analysis ai WHERE ai.creative_id = a.creative_id AND ai.hooks_detected LIKE ?)";
        $params[] = '%"' . $hookType . '"%';
    }

    $whereClause = implode(' AND ', $where);
    $joinClause = implode(' ', $joins);

    // Count
    $total = (int) $db->fetchColumn(
        "SELECT COUNT(DISTINCT a.creative_id) FROM ads a {$joinClause} WHERE {$whereClause}",
        $params
    );

    // Fetch results
    $offset = ($page - 1) * $perPage;
    $fetchParams = array_merge($params, [$perPage, $offset]);

    $results = $db->fetchAll(
        "SELECT DISTINCT a.creative_id, a.advertiser_id, a.ad_type, a.status, a.first_seen, a.last_seen,
                d.headline, d.description, d.cta, d.landing_url,
                (SELECT GROUP_CONCAT(DISTINCT t.country) FROM ad_targeting t WHERE t.creative_id = a.creative_id) as countries,
                (SELECT GROUP_CONCAT(DISTINCT t.platform) FROM ad_targeting t WHERE t.creative_id = a.creative_id) as platforms
         FROM ads a
         {$joinClause}
         WHERE {$whereClause}
         ORDER BY a.last_seen DESC
         LIMIT ? OFFSET ?",
        $fetchParams
    );

    echo json_encode([
        'success'     => true,
        'results'     => $results,
        'total'       => $total,
        'page'        => $page,
        'per_page'    => $perPage,
        'total_pages' => ceil($total / $perPage),
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
