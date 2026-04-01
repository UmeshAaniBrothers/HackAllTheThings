<?php

/**
 * API: Overview Stats
 * Returns dashboard overview KPIs, charts, top entities, and summary data.
 *
 * Query params:
 *   advertiser_id  – optional, filter by advertiser
 *   time_period    – 1d | 7d | 30d | 90d | all (default: all)
 *   ad_type        – optional, filter by ad type (video/image/text)
 *   status         – optional, filter by status (active/inactive)
 *   country        – optional, filter by country (2-letter code)
 *   drill_period   – optional, drill into a specific period from the timeline
 *                     Formats: 2026-03 (month), 2026-03-15 (day),
 *                              2026-03-15 14:00 (hour), 2026-W12 (week)
 *   fast           – optional, when 1 returns only stats/timeline/breakdowns
 */

header('Content-Type: application/json');

$basePath = dirname(dirname(__DIR__));
$config = require $basePath . '/config/config.php';

require_once $basePath . '/src/Database.php';

/**
 * Convert a time_period string to a SQL condition on the given date column.
 * Returns ['sql' => string, 'params' => array].
 * When period is 'all', returns a no-op condition.
 */
function buildTimeFilter(string $column, string $period)
{
    $map = array(
        '1d'  => 'INTERVAL 1 DAY',
        '7d'  => 'INTERVAL 7 DAY',
        '30d' => 'INTERVAL 30 DAY',
        '90d' => 'INTERVAL 90 DAY',
    );

    if (!isset($map[$period])) {
        return array('sql' => '1=1', 'params' => array());
    }

    return array(
        'sql'    => "{$column} >= DATE_SUB(NOW(), {$map[$period]})",
        'params' => array(),
    );
}

/**
 * Parse a drill_period string into a SQL condition on the given date column.
 * Supported formats:
 *   2026-03          → month range
 *   2026-03-15       → single day
 *   2026-03-15 14:00 → single hour
 *   2026-W12         → ISO week via YEARWEEK()
 *
 * Returns ['sql' => string, 'params' => array] or null if not parseable.
 */
function parseDrillPeriod(string $column, string $drillPeriod)
{
    $drillPeriod = trim($drillPeriod);

    // Hour: 2026-03-15 14:00
    if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $drillPeriod)) {
        return array(
            'sql'    => "{$column} >= ? AND {$column} < ? + INTERVAL 1 HOUR",
            'params' => array($drillPeriod . ':00', $drillPeriod . ':00'),
        );
    }

    // Day: 2026-03-15
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $drillPeriod)) {
        return array(
            'sql'    => "{$column} >= ? AND {$column} < ? + INTERVAL 1 DAY",
            'params' => array($drillPeriod . ' 00:00:00', $drillPeriod . ' 00:00:00'),
        );
    }

    // Week: 2026-W12
    if (preg_match('/^(\d{4})-W(\d{1,2})$/', $drillPeriod, $m)) {
        $year = (int) $m[1];
        $week = (int) $m[2];
        return array(
            'sql'    => "YEARWEEK({$column}, 3) = ?",
            'params' => array($year * 100 + $week),
        );
    }

    // Month: 2026-03
    if (preg_match('/^\d{4}-\d{2}$/', $drillPeriod)) {
        $start = $drillPeriod . '-01 00:00:00';
        return array(
            'sql'    => "{$column} >= ? AND {$column} < ? + INTERVAL 1 MONTH",
            'params' => array($start, $start),
        );
    }

    return null;
}

/**
 * Build the combined WHERE clause from time + advertiser + drill-down filters.
 *
 * $extras is an associative array with optional keys:
 *   'ad_type'      => string (video/image/text)
 *   'status'       => string (active/inactive)
 *   'country'      => string (2-letter code)
 *   'drill_period' => string (period to drill into)
 *
 * $dateCol and $advCol allow table-alias prefixing.
 * $adTypeCol, $statusCol allow overriding column names for aliased tables.
 */
function buildWhereClause(
    string $timePeriod,
    $advertiserId,
    string $dateCol = 'first_seen',
    string $advCol = 'advertiser_id',
    array $extras = array(),
    $adTypeCol = null,
    $statusCol = null
) {
    $conditions = array();
    $params = array();

    // Time period filter
    $tf = buildTimeFilter($dateCol, $timePeriod);
    if ($tf['sql'] !== '1=1') {
        $conditions[] = $tf['sql'];
        $params = array_merge($params, $tf['params']);
    }

    // Advertiser filter
    if ($advertiserId) {
        $conditions[] = "{$advCol} = ?";
        $params[] = $advertiserId;
    }

    // Drill period filter (overrides time_period granularity for drill-down)
    if (!empty($extras['drill_period'])) {
        $dp = parseDrillPeriod($dateCol, $extras['drill_period']);
        if ($dp !== null) {
            $conditions[] = $dp['sql'];
            $params = array_merge($params, $dp['params']);
        }
    }

    // Ad type filter
    if (!empty($extras['ad_type'])) {
        $col = $adTypeCol ? $adTypeCol : 'ad_type';
        $conditions[] = "{$col} = ?";
        $params[] = $extras['ad_type'];
    }

    // Status filter
    if (!empty($extras['status'])) {
        $col = $statusCol ? $statusCol : 'status';
        $conditions[] = "{$col} = ?";
        $params[] = $extras['status'];
    }

    // Country filter (subquery on ad_targeting)
    if (!empty($extras['country'])) {
        // Determine the creative_id column based on the date column prefix
        $creativeCol = 'creative_id';
        if (strpos($dateCol, '.') !== false) {
            $parts = explode('.', $dateCol);
            $creativeCol = $parts[0] . '.creative_id';
        }
        $conditions[] = "{$creativeCol} IN (SELECT creative_id FROM ad_targeting WHERE country = ?)";
        $params[] = $extras['country'];
    }

    $where = $conditions ? implode(' AND ', $conditions) : '1=1';

    return array('sql' => $where, 'params' => $params);
}

/**
 * Return the DATE_FORMAT expression + grouping label for the timeline,
 * adapted to the selected time period.
 */
function timelineGranularity(string $period)
{
    switch ($period) {
        case '1d':
        case '7d':
            return array(
                'format' => "DATE_FORMAT(first_seen, '%Y-%m-%d %H:00')",
                'label'  => 'hour',
            );
        case '30d':
            return array(
                'format' => "DATE_FORMAT(first_seen, '%Y-%m-%d')",
                'label'  => 'day',
            );
        case '90d':
            return array(
                'format' => "CONCAT(YEAR(first_seen), '-W', LPAD(WEEK(first_seen, 3), 2, '0'))",
                'label'  => 'week',
            );
        default: // 'all'
            return array(
                'format' => "DATE_FORMAT(first_seen, '%Y-%m')",
                'label'  => 'month',
            );
    }
}

try {
    $db = Database::getInstance($config['db']);

    // ---------------------------------------------------------------
    // Parse & validate input params
    // ---------------------------------------------------------------
    $advertiserId = isset($_GET['advertiser_id']) ? trim($_GET['advertiser_id']) : null;
    $timePeriod   = isset($_GET['time_period']) ? trim($_GET['time_period']) : 'all';
    $fastMode     = !empty($_GET['fast']);

    if (!in_array($timePeriod, array('1d', '7d', '30d', '90d', 'all'), true)) {
        $timePeriod = 'all';
    }

    // Drill-down filters
    $extras = array();

    $adType = isset($_GET['ad_type']) ? trim($_GET['ad_type']) : null;
    if ($adType && in_array($adType, array('video', 'image', 'text'), true)) {
        $extras['ad_type'] = $adType;
    }

    $status = isset($_GET['status']) ? trim($_GET['status']) : null;
    if ($status && in_array($status, array('active', 'inactive'), true)) {
        $extras['status'] = $status;
    }

    $country = isset($_GET['country']) ? strtoupper(trim($_GET['country'])) : null;
    if ($country && preg_match('/^[A-Z]{2}$/', $country)) {
        $extras['country'] = $country;
    }

    $drillPeriod = isset($_GET['drill_period']) ? trim($_GET['drill_period']) : null;
    if ($drillPeriod) {
        $extras['drill_period'] = $drillPeriod;
    }

    // Reusable WHERE fragments
    $w  = buildWhereClause($timePeriod, $advertiserId, 'first_seen', 'advertiser_id', $extras);
    $wa = buildWhereClause($timePeriod, $advertiserId, 'a.first_seen', 'a.advertiser_id', $extras, 'a.ad_type', 'a.status');

    // ---------------------------------------------------------------
    // Core stats (consolidated: includes new_ads_24h / new_ads_7d)
    // ---------------------------------------------------------------
    $stats = $db->fetchOne(
        "SELECT COUNT(*) as total_ads,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_ads,
                SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive_ads,
                SUM(CASE WHEN ad_type = 'video' THEN 1 ELSE 0 END) as video_ads,
                SUM(CASE WHEN ad_type = 'image' THEN 1 ELSE 0 END) as image_ads,
                SUM(CASE WHEN ad_type = 'text' THEN 1 ELSE 0 END) as text_ads,
                SUM(view_count) as total_views,
                COUNT(DISTINCT advertiser_id) as total_advertisers,
                SUM(CASE WHEN first_seen >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 ELSE 0 END) as new_ads_24h,
                SUM(CASE WHEN first_seen >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as new_ads_7d
         FROM ads WHERE {$w['sql']}",
        $w['params']
    );

    // Pending payloads (global, not time-filtered)
    $pending = $db->fetchColumn("SELECT COUNT(*) FROM raw_payloads WHERE processed_flag = 0");
    $stats['pending_payloads'] = (int) $pending;

    // Total apps detected (time-filtered via ads join)
    if ($timePeriod !== 'all' || $advertiserId || !empty($extras)) {
        $stats['total_apps'] = (int) $db->fetchColumn(
            "SELECT COUNT(DISTINCT p.id)
             FROM ad_products p
             INNER JOIN ad_product_map pm ON p.id = pm.product_id
             INNER JOIN ads a ON a.creative_id = pm.creative_id
             WHERE p.store_platform IN ('ios', 'playstore') AND {$wa['sql']}",
            $wa['params']
        );
    } else {
        $stats['total_apps'] = (int) $db->fetchColumn(
            "SELECT COUNT(*) FROM ad_products WHERE store_platform IN ('ios', 'playstore')"
        );
    }

    // Total YouTube videos (time-filtered via ads join)
    if ($timePeriod !== 'all' || $advertiserId || !empty($extras)) {
        $stats['total_videos'] = (int) $db->fetchColumn(
            "SELECT COUNT(DISTINCT aa.original_url)
             FROM ad_assets aa
             INNER JOIN ads a ON a.creative_id = aa.creative_id
             WHERE aa.type = 'video' AND aa.original_url LIKE '%youtube.com%' AND {$wa['sql']}",
            $wa['params']
        );
    } else {
        $stats['total_videos'] = (int) $db->fetchColumn(
            "SELECT COUNT(DISTINCT original_url) FROM ad_assets WHERE type = 'video' AND original_url LIKE '%youtube.com%'"
        );
    }

    // Total countries (time-filtered via ads join)
    if ($timePeriod !== 'all' || $advertiserId || !empty($extras)) {
        $stats['total_countries'] = (int) $db->fetchColumn(
            "SELECT COUNT(DISTINCT t.country)
             FROM ad_targeting t
             INNER JOIN ads a ON a.creative_id = t.creative_id
             WHERE {$wa['sql']}",
            $wa['params']
        );
    } else {
        $stats['total_countries'] = (int) $db->fetchColumn(
            "SELECT COUNT(DISTINCT country) FROM ad_targeting"
        );
    }

    // New ads within the selected period
    if ($timePeriod !== 'all') {
        $tf = buildTimeFilter('first_seen', $timePeriod);
        $periodParams = $tf['params'];
        $periodSql = $tf['sql'];
        if ($advertiserId) {
            $periodSql .= ' AND advertiser_id = ?';
            $periodParams[] = $advertiserId;
        }
        $stats['new_ads_period'] = (int) $db->fetchColumn(
            "SELECT COUNT(*) FROM ads WHERE {$periodSql}",
            $periodParams
        );
    } else {
        $stats['new_ads_period'] = (int) $stats['total_ads'];
    }

    // New apps in last 7d (global stat)
    $stats['new_apps_7d'] = (int) $db->fetchColumn(
        "SELECT COUNT(*) FROM ad_products WHERE store_platform IN ('ios', 'playstore') AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
    );

    // ---------------------------------------------------------------
    // Timeline (adaptive granularity)
    // ---------------------------------------------------------------
    $gran = timelineGranularity($timePeriod);
    $timelineLimits = array('1d' => 24, '7d' => 168, '30d' => 30, '90d' => 13);
    $timelineLimit = isset($timelineLimits[$timePeriod]) ? $timelineLimits[$timePeriod] : 12;

    $timeline = $db->fetchAll(
        "SELECT {$gran['format']} as period, COUNT(*) as count
         FROM ads
         WHERE {$w['sql']}
         GROUP BY period
         ORDER BY period DESC
         LIMIT {$timelineLimit}",
        $w['params']
    );
    $timeline = array_reverse($timeline);

    // ---------------------------------------------------------------
    // Ad type breakdown (pie chart)
    // ---------------------------------------------------------------
    $adTypeBreakdown = $db->fetchAll(
        "SELECT ad_type, COUNT(*) as count FROM ads WHERE {$w['sql']} GROUP BY ad_type ORDER BY count DESC",
        $w['params']
    );

    // ---------------------------------------------------------------
    // Status breakdown
    // ---------------------------------------------------------------
    $statusBreakdown = $db->fetchAll(
        "SELECT status, COUNT(*) as count FROM ads WHERE {$w['sql']} GROUP BY status ORDER BY count DESC",
        $w['params']
    );

    // ---------------------------------------------------------------
    // Build response — fast mode returns here
    // ---------------------------------------------------------------
    $response = array(
        'success'              => true,
        'time_period'          => $timePeriod,
        'filters'              => array(
            'advertiser_id' => $advertiserId,
            'ad_type'       => isset($extras['ad_type']) ? $extras['ad_type'] : null,
            'status'        => isset($extras['status']) ? $extras['status'] : null,
            'country'       => isset($extras['country']) ? $extras['country'] : null,
            'drill_period'  => isset($extras['drill_period']) ? $extras['drill_period'] : null,
        ),
        'stats'                => $stats,
        'timeline'             => $timeline,
        'timeline_granularity' => $gran['label'],
        'ad_type_breakdown'    => $adTypeBreakdown,
        'status_breakdown'     => $statusBreakdown,
    );

    if ($fastMode) {
        echo json_encode($response);
        exit;
    }

    // ---------------------------------------------------------------
    // Expensive entity queries (skipped in fast mode)
    // ---------------------------------------------------------------

    // Top apps (enhanced with app_metadata)
    $topAppsWhere = "p.store_platform IN ('ios', 'playstore') AND p.product_name != 'Unknown'";
    if ($wa['sql'] !== '1=1') {
        $topAppsWhere .= " AND {$wa['sql']}";
    }
    $topApps = $db->fetchAll(
        "SELECT p.id, COALESCE(MAX(NULLIF(am.app_name, '')), p.product_name) as product_name, p.store_platform, p.store_url,
                MAX(am.icon_url) as icon_url, MAX(am.rating) as rating, MAX(am.downloads) as downloads,
                COUNT(DISTINCT pm.creative_id) as ad_count,
                COALESCE(SUM(a.view_count), 0) as total_views
         FROM ad_products p
         INNER JOIN ad_product_map pm ON p.id = pm.product_id
         INNER JOIN ads a ON a.creative_id = pm.creative_id
         LEFT JOIN app_metadata am ON am.product_id = p.id
         WHERE {$topAppsWhere}
         GROUP BY p.id
         ORDER BY ad_count DESC
         LIMIT 10",
        $wa['params']
    );

    // ---------------------------------------------------------------
    // Top countries (time + advertiser + drill filtered)
    // ---------------------------------------------------------------
    if ($timePeriod !== 'all' || $advertiserId || !empty($extras)) {
        $topCountries = $db->fetchAll(
            "SELECT t.country, COUNT(DISTINCT t.creative_id) as ad_count
             FROM ad_targeting t
             INNER JOIN ads a ON a.creative_id = t.creative_id
             WHERE {$wa['sql']}
             GROUP BY t.country
             ORDER BY ad_count DESC
             LIMIT 10",
            $wa['params']
        );
    } else {
        $topCountries = $db->fetchAll(
            "SELECT country, COUNT(DISTINCT creative_id) as ad_count
             FROM ad_targeting
             GROUP BY country
             ORDER BY ad_count DESC
             LIMIT 10"
        );
    }

    // ---------------------------------------------------------------
    // Top YouTube videos (single JOIN query, no N+1)
    // ---------------------------------------------------------------
    $videoJoinFilter = $wa['sql'] !== '1=1' ? "AND {$wa['sql']}" : "";
    $videoParams = $wa['params'];

    $topVideos = $db->fetchAll(
        "SELECT ym.video_id, ym.title, ym.channel_name, ym.view_count, ym.like_count,
                ym.comment_count, ym.thumbnail_url, ym.duration, ym.publish_date,
                COUNT(DISTINCT a.creative_id) as ad_count
         FROM youtube_metadata ym
         LEFT JOIN ad_assets aa ON aa.type = 'video'
            AND aa.original_url = CONCAT('https://www.youtube.com/watch?v=', ym.video_id)
         LEFT JOIN ads a ON a.creative_id = aa.creative_id {$videoJoinFilter}
         WHERE ym.view_count > 0
         GROUP BY ym.video_id
         ORDER BY ym.view_count DESC
         LIMIT 6",
        $videoParams
    );

    // ---------------------------------------------------------------
    // Recent activity (last 20 ads, filtered)
    // ---------------------------------------------------------------
    $recentWhereA = $wa['sql'] !== '1=1' ? "WHERE {$wa['sql']}" : "";

    $recentActivity = $db->fetchAll(
        "SELECT a.creative_id, a.advertiser_id, a.ad_type, a.status, a.last_seen,
                d.headline,
                COALESCE(adv.name, a.advertiser_id) as advertiser_name
         FROM ads a
         LEFT JOIN ad_details d ON a.creative_id = d.creative_id
             AND d.id = (SELECT MAX(id) FROM ad_details WHERE creative_id = a.creative_id)
         LEFT JOIN managed_advertisers adv ON a.advertiser_id = adv.advertiser_id
         {$recentWhereA}
         ORDER BY a.last_seen DESC
         LIMIT 20",
        $wa['params']
    );

    // ---------------------------------------------------------------
    // Top advertisers (filtered, only when no specific advertiser selected)
    // ---------------------------------------------------------------
    $topAdvertisers = array();
    if (!$advertiserId) {
        $topAdvWhere = buildWhereClause($timePeriod, null, 'a.first_seen', 'a.advertiser_id', $extras, 'a.ad_type', 'a.status');
        $topAdvWhereStr = $topAdvWhere['sql'] !== '1=1' ? "WHERE {$topAdvWhere['sql']}" : "";
        $topAdvertisers = $db->fetchAll(
            "SELECT a.advertiser_id, COALESCE(ma.name, a.advertiser_id) as name,
                    COUNT(*) as total_ads,
                    SUM(a.status = 'active') as active_ads
             FROM ads a
             LEFT JOIN managed_advertisers ma ON a.advertiser_id = ma.advertiser_id
             {$topAdvWhereStr}
             GROUP BY a.advertiser_id
             ORDER BY total_ads DESC
             LIMIT 5",
            $topAdvWhere['params']
        );
    }

    // ---------------------------------------------------------------
    // Full response
    // ---------------------------------------------------------------
    $response['recent_activity']  = $recentActivity;
    $response['top_advertisers']  = $topAdvertisers;
    $response['top_apps']         = $topApps;
    $response['top_countries']    = $topCountries;
    $response['top_videos']       = $topVideos;

    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(array('success' => false, 'error' => $e->getMessage()));
}
