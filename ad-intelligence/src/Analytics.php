<?php

/**
 * Analytics - Campaign & Creative Intelligence Engine
 *
 * Provides metrics, geo analytics, creative analytics,
 * and advertiser intelligence scoring.
 */
class Analytics
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Get overview statistics, optionally filtered by advertiser.
     */
    public function getOverviewStats(?string $advertiserId = null): array
    {
        $where = '';
        $params = [];

        if ($advertiserId) {
            $where = ' WHERE advertiser_id = ?';
            $params = [$advertiserId];
        }

        $totalAds = (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM ads{$where}", $params
        );

        $activeAds = (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM ads{$where}" . ($where ? " AND" : " WHERE") . " status = 'active'",
            $params
        );

        $inactiveAds = $totalAds - $activeAds;

        $newToday = (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM ads{$where}" . ($where ? " AND" : " WHERE") . " DATE(created_at) = CURDATE()",
            $params
        );

        $avgDuration = $this->db->fetchColumn(
            "SELECT AVG(TIMESTAMPDIFF(DAY, first_seen, last_seen)) FROM ads{$where}" . ($where ? " AND" : " WHERE") . " status = 'active'",
            $params
        );

        $adTypes = $this->db->fetchAll(
            "SELECT ad_type, COUNT(*) as count FROM ads{$where} GROUP BY ad_type",
            $params
        );

        return [
            'total_ads'        => $totalAds,
            'active_ads'       => $activeAds,
            'inactive_ads'     => $inactiveAds,
            'new_today'        => $newToday,
            'avg_campaign_days' => round((float) ($avgDuration ?? 0), 1),
            'ad_types'         => $adTypes,
        ];
    }

    /**
     * Get daily ad creation velocity.
     */
    public function getAdVelocity(?string $advertiserId = null, int $days = 30): array
    {
        $where = '';
        $params = [];

        if ($advertiserId) {
            $where = ' AND advertiser_id = ?';
            $params = [$advertiserId];
        }

        $params = array_merge([$days], $params);

        return $this->db->fetchAll(
            "SELECT DATE(created_at) as date, COUNT(*) as count
             FROM ads
             WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY){$where}
             GROUP BY DATE(created_at)
             ORDER BY date ASC",
            $params
        );
    }

    /**
     * Get geographic distribution of ads.
     */
    public function getGeoDistribution(?string $advertiserId = null): array
    {
        $where = '';
        $params = [];

        if ($advertiserId) {
            $where = ' AND a.advertiser_id = ?';
            $params = [$advertiserId];
        }

        return $this->db->fetchAll(
            "SELECT t.country, COUNT(DISTINCT t.creative_id) as ad_count
             FROM ad_targeting t
             INNER JOIN ads a ON t.creative_id = a.creative_id
             WHERE 1=1{$where}
             GROUP BY t.country
             ORDER BY ad_count DESC",
            $params
        );
    }

    /**
     * Get geo expansion timeline - when new countries were targeted.
     */
    public function getGeoExpansionTimeline(?string $advertiserId = null): array
    {
        $where = '';
        $params = [];

        if ($advertiserId) {
            $where = ' AND a.advertiser_id = ?';
            $params = [$advertiserId];
        }

        return $this->db->fetchAll(
            "SELECT t.country, MIN(t.detected_at) as first_targeted, COUNT(DISTINCT t.creative_id) as ad_count
             FROM ad_targeting t
             INNER JOIN ads a ON t.creative_id = a.creative_id
             WHERE 1=1{$where}
             GROUP BY t.country
             ORDER BY first_targeted ASC",
            $params
        );
    }

    /**
     * Get platform distribution.
     */
    public function getPlatformDistribution(?string $advertiserId = null): array
    {
        $where = '';
        $params = [];

        if ($advertiserId) {
            $where = ' AND a.advertiser_id = ?';
            $params = [$advertiserId];
        }

        return $this->db->fetchAll(
            "SELECT t.platform, COUNT(DISTINCT t.creative_id) as ad_count
             FROM ad_targeting t
             INNER JOIN ads a ON t.creative_id = a.creative_id
             WHERE 1=1{$where}
             GROUP BY t.platform
             ORDER BY ad_count DESC",
            $params
        );
    }

    /**
     * Get creative analytics - most used headlines, CTAs, format distribution.
     */
    public function getCreativeAnalytics(?string $advertiserId = null): array
    {
        $where = '';
        $params = [];

        if ($advertiserId) {
            $where = ' AND a.advertiser_id = ?';
            $params = [$advertiserId];
        }

        // Top headlines
        $topHeadlines = $this->db->fetchAll(
            "SELECT d.headline, COUNT(*) as usage_count
             FROM ad_details d
             INNER JOIN ads a ON d.creative_id = a.creative_id
             WHERE d.headline IS NOT NULL AND d.headline != ''{$where}
             GROUP BY d.headline
             ORDER BY usage_count DESC
             LIMIT 20",
            $params
        );

        // Top CTAs
        $topCtas = $this->db->fetchAll(
            "SELECT d.cta, COUNT(*) as usage_count
             FROM ad_details d
             INNER JOIN ads a ON d.creative_id = a.creative_id
             WHERE d.cta IS NOT NULL AND d.cta != ''{$where}
             GROUP BY d.cta
             ORDER BY usage_count DESC
             LIMIT 20",
            $params
        );

        // Format distribution
        $formatDist = $this->db->fetchAll(
            "SELECT ad_type, COUNT(*) as count
             FROM ads a
             WHERE 1=1" . str_replace('AND a.advertiser_id', 'AND advertiser_id', $where) . "
             GROUP BY ad_type",
            $params
        );

        // Top landing domains
        $topDomains = $this->db->fetchAll(
            "SELECT
                SUBSTRING_INDEX(SUBSTRING_INDEX(REPLACE(REPLACE(d.landing_url, 'https://', ''), 'http://', ''), '/', 1), '?', 1) as domain,
                COUNT(*) as usage_count
             FROM ad_details d
             INNER JOIN ads a ON d.creative_id = a.creative_id
             WHERE d.landing_url IS NOT NULL AND d.landing_url != ''{$where}
             GROUP BY domain
             ORDER BY usage_count DESC
             LIMIT 20",
            $params
        );

        return [
            'top_headlines' => $topHeadlines,
            'top_ctas'      => $topCtas,
            'format_distribution' => $formatDist,
            'top_domains'   => $topDomains,
        ];
    }

    /**
     * Get campaign timeline data for visualization.
     */
    public function getCampaignTimeline(?string $advertiserId = null, ?string $from = null, ?string $to = null): array
    {
        $where = '1=1';
        $params = [];

        if ($advertiserId) {
            $where .= ' AND advertiser_id = ?';
            $params[] = $advertiserId;
        }
        if ($from) {
            $where .= ' AND first_seen >= ?';
            $params[] = $from;
        }
        if ($to) {
            $where .= ' AND first_seen <= ?';
            $params[] = $to;
        }

        return $this->db->fetchAll(
            "SELECT creative_id, advertiser_id, ad_type, first_seen, last_seen, status
             FROM ads
             WHERE {$where}
             ORDER BY first_seen ASC",
            $params
        );
    }

    /**
     * Get advertiser intelligence score.
     *
     * Score formula (0-100):
     * - Active ads count:    up to 30 points
     * - Ad creation velocity: up to 30 points
     * - Geo expansion:       up to 20 points
     * - Format diversity:    up to 20 points
     */
    public function getIntelligenceScore(string $advertiserId): array
    {
        // Active ads (max 30 points, 1 point per ad, capped)
        $activeCount = (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM ads WHERE advertiser_id = ? AND status = 'active'",
            [$advertiserId]
        );
        $activeScore = min(30, $activeCount);

        // Velocity - ads created in last 7 days (max 30 points, 3 points per ad)
        $recentAds = (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM ads WHERE advertiser_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
            [$advertiserId]
        );
        $velocityScore = min(30, $recentAds * 3);

        // Geo expansion - unique countries (max 20 points, 2 points per country)
        $countryCount = (int) $this->db->fetchColumn(
            "SELECT COUNT(DISTINCT t.country)
             FROM ad_targeting t
             INNER JOIN ads a ON t.creative_id = a.creative_id
             WHERE a.advertiser_id = ?",
            [$advertiserId]
        );
        $geoScore = min(20, $countryCount * 2);

        // Format diversity (max 20 points)
        $formatCount = (int) $this->db->fetchColumn(
            "SELECT COUNT(DISTINCT ad_type) FROM ads WHERE advertiser_id = ?",
            [$advertiserId]
        );
        $diversityScore = min(20, $formatCount * 7);

        $totalScore = $activeScore + $velocityScore + $geoScore + $diversityScore;

        return [
            'total_score'     => $totalScore,
            'active_score'    => $activeScore,
            'velocity_score'  => $velocityScore,
            'geo_score'       => $geoScore,
            'diversity_score' => $diversityScore,
            'details' => [
                'active_ads'      => $activeCount,
                'recent_ads_7d'   => $recentAds,
                'countries'       => $countryCount,
                'ad_formats'      => $formatCount,
            ],
        ];
    }

    /**
     * Get list of all tracked advertisers with basic stats.
     */
    public function getAdvertiserList(): array
    {
        return $this->db->fetchAll(
            "SELECT
                advertiser_id,
                COUNT(*) as total_ads,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_ads,
                MIN(first_seen) as tracking_since,
                MAX(last_seen) as last_activity
             FROM ads
             GROUP BY advertiser_id
             ORDER BY total_ads DESC"
        );
    }

    /**
     * Get recent activity feed.
     */
    public function getRecentActivity(int $limit = 50): array
    {
        return $this->db->fetchAll(
            "SELECT a.creative_id, a.advertiser_id, a.ad_type, a.status, a.created_at,
                    d.headline, d.cta
             FROM ads a
             LEFT JOIN ad_details d ON a.creative_id = d.creative_id
             AND d.id = (SELECT MAX(id) FROM ad_details WHERE creative_id = a.creative_id)
             ORDER BY a.created_at DESC
             LIMIT ?",
            [$limit]
        );
    }
}
