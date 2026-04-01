<?php

/**
 * API: Overview Stats
 * Returns dashboard overview KPIs, charts, top entities, and summary data.
 *
 * Query params:
 *   advertiser_id  – optional, filter by advertiser
 *   time_period    – 1d | 7d | 30d | 90d | all (default: all)
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
function buildTimeFilter(string $column, string $period): array
{
    $map = [
        '1d'  => 'INTERVAL 1 DAY',
        '7d'  => 'INTERVAL 7 DAY',
        '30d' => 'INTERVAL 30 DAY',
        '90d' => 'INTERVAL 90 DAY',
    ];

    if (!isset($map[$period])) {
        return ['sql' => '1=1', 'params' => []];
    }

    return [
        'sql'    => "{$column} >= DATE_SUB(NOW(), {$map[$period]})",
        'params' => [],
    ];
}

/**
 * Build the combined WHERE clause from time + advertiser filters.
 * $tableAlias is the alias used for the ads table (e.g. 'a' or '').
 */
function buildWhereClause(string $timePeriod, ?string $advertiserId, string $dateCol = 'first_seen', string $advCol = 'advertiser_id'): array
{
    $conditions = [];
    $params = [];

    $tf = buildTimeFilter($dateCol, $timePeriod);
    if ($tf['sql'] !== '1=1') {
        $conditions[] = $tf['sql'];
        $params = array_merge($params, $tf['params']);
    }

    if ($advertiserId) {
        $conditions[] = "{$advCol} = ?";
        $params[] = $advertiserId;
    }

    $where = $conditions ? implode(' AND ', $conditions) : '1=1';

    return ['sql' => $where, 'params' => $params];
}

/**
 * Return the DATE_FORMAT expression + grouping label for the timeline,
 * adapted to the selected time period.
 */
function timelineGranularity(string $period): array
{
    switch ($period) {
        case '1d':
        case '7d':
            return [
                'format' => "DATE_FORMAT(first_seen, '%Y-%m-%d %H:00')",
                'label'  => 'hour',
            ];
        case '30d':
            return [
                'format' => "DATE_FORMAT(first_seen, '%Y-%m-%d')",
                'label'  => 'day',
            ];
        case '90d':
            return [
                'format' => "CONCAT(YEAR(first_seen), '-W', LPAD(WEEK(first_seen, 3), 2, '0'))",
                'label'  => 'week',
            ];
        default: // 'all'
            return [
                'format' => "DATE_FORMAT(first_seen, '%Y-%m')",
                'label'  => 'month',
            ];
    }
}

try {
    $db = Database::getInstance($config['db']);

    $advertiserId = isset($_GET['advertiser_id']) ? trim($_GET['advertiser_id']) : null;
    $timePeriod   = isset($_GET['time_period']) ? trim($_GET['time_period']) : 'all';

    // Validate time_period
    if (!in_array($timePeriod, ['1d', '7d', '30d', '90d', 'all'], true)) {
        $timePeriod = 'all';
    }

    // Reusable WHERE fragments
    $w  = buildWhereClause($timePeriod, $advertiserId);              // columns: first_seen, advertiser_id
    $wa = buildWhereClause($timePeriod, $advertiserId, 'a.first_seen', 'a.advertiser_id'); // aliased

    // ---------------------------------------------------------------
    // Core stats
    // ---------------------------------------------------------------
    $stats = $db->fetchOne(
        "SELECT COUNT(*) as total_ads,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_ads,
                SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive_ads,
                SUM(CASE WHEN ad_type = 'video' THEN 1 ELSE 0 END) as video_ads,
                SUM(CASE WHEN ad_type = 'image' THEN 1 ELSE 0 END) as image_ads,
                SUM(CASE WHEN ad_type = 'text' THEN 1 ELSE 0 END) as text_ads,
                SUM(view_count) as total_views,
                COUNT(DISTINCT advertiser_id) as total_advertisers
         FROM ads WHERE {$w['sql']}",
        $w['params']
    );

    // Pending payloads (global, not time-filtered)
    $pending = $db->fetchColumn("SELECT COUNT(*) FROM raw_payloads WHERE processed_flag = 0");
    $stats['pending_payloads'] = (int) $pending;

    // Total apps detected (time-filtered via ads join)
    $appWhere = buildWhereClause($timePeriod, $advertiserId, 'a.first_seen', 'a.advertiser_id');
    if ($timePeriod !== 'all' || $advertiserId) {
        $stats['total_apps'] = (int) $db->fetchColumn(
            "SELECT COUNT(DISTINCT p.id)
             FROM ad_products p
             INNER JOIN ad_product_map pm ON p.id = pm.product_id
             INNER JOIN ads a ON a.creative_id = pm.creative_id
             WHERE p.store_platform IN ('ios', 'playstore') AND {$appWhere['sql']}",
            $appWhere['params']
        );
    } else {
        $stats['total_apps'] = (int) $db->fetchColumn(
            "SELECT COUNT(*) FROM ad_products WHERE store_platform IN ('ios', 'playstore')"
        );
    }

    // Total YouTube videos (time-filtered via ads join)
    if ($timePeriod !== 'all' || $advertiserId) {
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
    if ($timePeriod !== 'all' || $advertiserId) {
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

    // New ads in last 24h, 7d (always based on real time, independent of time_period)
    $advFilter24 = $advertiserId ? " AND advertiser_id = ?" : "";
    $advParams   = $advertiserId ? [$advertiserId] : [];

    $stats['new_ads_24h'] = (int) $db->fetchColumn(
        "SELECT COUNT(*) FROM ads WHERE first_seen >= DATE_SUB(NOW(), INTERVAL 24 HOUR)" . $advFilter24,
        $advParams
    );
    $stats['new_ads_7d'] = (int) $db->fetchColumn(
        "SELECT COUNT(*) FROM ads WHERE first_seen >= DATE_SUB(NOW(), INTERVAL 7 DAY)" . $advFilter24,
        $advParams
    );

    // New ads within the selected period (ads whose first_seen falls in the period)
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
    $timelineLimit = match ($timePeriod) {
        '1d'  => 24,
        '7d'  => 168,   // 7 * 24
        '30d' => 30,
        '90d' => 13,    // ~13 weeks
        default => 12,  // 12 months
    };

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
    // Top apps (enhanced with app_metadata)
    // ---------------------------------------------------------------
    $topAppsWhere = "p.store_platform IN ('ios', 'playstore') AND p.product_name != 'Unknown'";
    if ($wa['sql'] !== '1=1') {
        $topAppsWhere .= " AND {$wa['sql']}";
    }
    $topApps = $db->fetchAll(
        "SELECT p.id, p.product_name, p.store_platform, p.store_url,
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
    // Top countries (time + advertiser filtered)
    // ---------------------------------------------------------------
    if ($timePeriod !== 'all' || $advertiserId) {
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
    // Top YouTube videos (enhanced with youtube_metadata)
    // ---------------------------------------------------------------
    $videoWhere = $wa['sql'];
    $videoParams = $wa['params'];

    // Two-step approach: get top videos by view_count first, then count ads
    $topVideosRaw = $db->fetchAll(
        "SELECT ym.video_id, ym.title, ym.channel_name, ym.view_count, ym.like_count, ym.comment_count,
                ym.thumbnail_url, ym.duration, ym.publish_date
         FROM youtube_metadata ym
         WHERE ym.view_count > 0
         ORDER BY ym.view_count DESC
         LIMIT 30"
    );

    // Filter by advertiser/time if needed, and count ads per video
    $topVideos = [];
    if (!empty($topVideosRaw)) {
        foreach ($topVideosRaw as $v) {
            // Count ads referencing this video (use indexed creative_id lookup)
            $adCountQuery = "SELECT COUNT(DISTINCT a.creative_id)
                FROM ad_assets aa
                JOIN ads a ON a.creative_id = aa.creative_id
                WHERE aa.type = 'video' AND aa.original_url LIKE ? AND {$videoWhere}";
            $vParams = array_merge(['%' . $v['video_id'] . '%'], $videoParams);
            $adCount = (int) $db->fetchColumn($adCountQuery, $vParams);

            if ($adCount > 0 || $videoWhere === '1=1') {
                $v['ad_count'] = $adCount;
                $topVideos[] = $v;
                if (count($topVideos) >= 6) break;
            }
        }
    }

    // ---------------------------------------------------------------
    // Recent activity (last 20 ads, time-filtered)
    // ---------------------------------------------------------------
    $recentWhere = $w['sql'] !== '1=1' ? "WHERE {$w['sql']}" : "";
    // Need aliased version for the ads table
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
    // Top advertisers (time-filtered, only when no specific advertiser selected)
    // ---------------------------------------------------------------
    $topAdvertisers = [];
    if (!$advertiserId) {
        $tf = buildTimeFilter('a.first_seen', $timePeriod);
        $topAdvWhere = $tf['sql'] !== '1=1' ? "WHERE {$tf['sql']}" : "";
        $topAdvertisers = $db->fetchAll(
            "SELECT a.advertiser_id, COALESCE(ma.name, a.advertiser_id) as name,
                    COUNT(*) as total_ads,
                    SUM(a.status = 'active') as active_ads
             FROM ads a
             LEFT JOIN managed_advertisers ma ON a.advertiser_id = ma.advertiser_id
             {$topAdvWhere}
             GROUP BY a.advertiser_id
             ORDER BY total_ads DESC
             LIMIT 5",
            $tf['params']
        );
    }

    // ---------------------------------------------------------------
    // Advertiser list for dropdown (NOT time-filtered)
    // ---------------------------------------------------------------
    $advertisers = $db->fetchAll(
        "SELECT ma.advertiser_id, ma.name, ma.status, ma.total_ads, ma.active_ads, ma.last_fetched_at,
                COALESCE(recent.new_ads_24h, 0) as new_ads_24h,
                COALESCE(recent.new_ads_7d, 0) as new_ads_7d
         FROM managed_advertisers ma
         LEFT JOIN (
             SELECT advertiser_id,
                    SUM(CASE WHEN first_seen >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 ELSE 0 END) as new_ads_24h,
                    SUM(CASE WHEN first_seen >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as new_ads_7d
             FROM ads
             WHERE first_seen >= DATE_SUB(NOW(), INTERVAL 7 DAY)
             GROUP BY advertiser_id
         ) recent ON recent.advertiser_id = ma.advertiser_id
         WHERE ma.status NOT IN ('deleted')
         ORDER BY ma.total_ads DESC"
    );

    echo json_encode([
        'success'            => true,
        'time_period'        => $timePeriod,
        'stats'              => $stats,
        'advertisers'        => $advertisers,
        'recent_activity'    => $recentActivity,
        'ad_type_breakdown'  => $adTypeBreakdown,
        'status_breakdown'   => $statusBreakdown,
        'timeline'           => $timeline,
        'timeline_granularity' => $gran['label'],
        'top_advertisers'    => $topAdvertisers,
        'top_apps'           => $topApps,
        'top_countries'      => $topCountries,
        'top_videos'         => $topVideos,
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
