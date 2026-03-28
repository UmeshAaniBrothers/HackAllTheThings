<?php

/**
 * ComparisonEngine - Cross-Advertiser Comparison
 *
 * Compares two or more advertisers across multiple dimensions:
 * ad count, geo spread, creative diversity, campaign duration, etc.
 */
class ComparisonEngine
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Compare two advertisers across all metrics.
     */
    public function compare(string $advA, string $advB): array
    {
        return [
            'advertiser_a' => $this->getMetrics($advA),
            'advertiser_b' => $this->getMetrics($advB),
            'comparison'   => $this->computeDifferences($advA, $advB),
        ];
    }

    /**
     * Compare multiple advertisers.
     */
    public function compareMultiple(array $advertiserIds): array
    {
        $results = [];
        foreach ($advertiserIds as $id) {
            $results[$id] = $this->getMetrics($id);
        }

        return [
            'advertisers' => $results,
            'rankings'    => $this->computeRankings($results),
        ];
    }

    /**
     * Get comprehensive metrics for an advertiser.
     */
    private function getMetrics(string $advertiserId): array
    {
        // Ad counts
        $totalAds = (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM ads WHERE advertiser_id = ?", [$advertiserId]
        );
        $activeAds = (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM ads WHERE advertiser_id = ? AND status = 'active'", [$advertiserId]
        );

        // Geo spread
        $countries = $this->db->fetchAll(
            "SELECT DISTINCT t.country FROM ad_targeting t
             INNER JOIN ads a ON t.creative_id = a.creative_id
             WHERE a.advertiser_id = ?", [$advertiserId]
        );
        $geoSpread = count($countries);

        // Platform spread
        $platforms = $this->db->fetchAll(
            "SELECT DISTINCT t.platform FROM ad_targeting t
             INNER JOIN ads a ON t.creative_id = a.creative_id
             WHERE a.advertiser_id = ?", [$advertiserId]
        );

        // Creative diversity
        $adTypes = $this->db->fetchAll(
            "SELECT ad_type, COUNT(*) as cnt FROM ads WHERE advertiser_id = ? GROUP BY ad_type",
            [$advertiserId]
        );
        $uniqueTypes = count($adTypes);

        // Unique headlines
        $uniqueHeadlines = (int) $this->db->fetchColumn(
            "SELECT COUNT(DISTINCT d.headline) FROM ad_details d
             INNER JOIN ads a ON d.creative_id = a.creative_id
             WHERE a.advertiser_id = ? AND d.headline IS NOT NULL", [$advertiserId]
        );

        // Unique CTAs
        $uniqueCtas = (int) $this->db->fetchColumn(
            "SELECT COUNT(DISTINCT d.cta) FROM ad_details d
             INNER JOIN ads a ON d.creative_id = a.creative_id
             WHERE a.advertiser_id = ? AND d.cta IS NOT NULL AND d.cta != ''", [$advertiserId]
        );

        // Campaign duration
        $avgDuration = (float) ($this->db->fetchColumn(
            "SELECT AVG(TIMESTAMPDIFF(DAY, first_seen, last_seen))
             FROM ads WHERE advertiser_id = ? AND status = 'active'", [$advertiserId]
        ) ?? 0);

        // Velocity (ads per week, last 30 days)
        $recentAds = (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM ads WHERE advertiser_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
            [$advertiserId]
        );
        $velocity = round($recentAds / 4.3, 1); // ads per week

        // Top domains
        $topDomains = $this->db->fetchAll(
            "SELECT SUBSTRING_INDEX(SUBSTRING_INDEX(REPLACE(REPLACE(d.landing_url,'https://',''),'http://',''), '/', 1), '?', 1) as domain,
                    COUNT(*) as cnt
             FROM ad_details d
             INNER JOIN ads a ON d.creative_id = a.creative_id
             WHERE a.advertiser_id = ? AND d.landing_url IS NOT NULL
             GROUP BY domain ORDER BY cnt DESC LIMIT 5",
            [$advertiserId]
        );

        return [
            'advertiser_id'     => $advertiserId,
            'total_ads'         => $totalAds,
            'active_ads'        => $activeAds,
            'inactive_ads'      => $totalAds - $activeAds,
            'geo_spread'        => $geoSpread,
            'countries'         => array_column($countries, 'country'),
            'platforms'         => array_column($platforms, 'platform'),
            'ad_type_count'     => $uniqueTypes,
            'ad_types'          => $adTypes,
            'unique_headlines'  => $uniqueHeadlines,
            'unique_ctas'       => $uniqueCtas,
            'avg_duration_days' => round($avgDuration, 1),
            'velocity_per_week' => $velocity,
            'top_domains'       => $topDomains,
        ];
    }

    /**
     * Compute head-to-head differences.
     */
    private function computeDifferences(string $advA, string $advB): array
    {
        $a = $this->getMetrics($advA);
        $b = $this->getMetrics($advB);

        $metrics = ['total_ads', 'active_ads', 'geo_spread', 'ad_type_count', 'unique_headlines', 'unique_ctas', 'avg_duration_days', 'velocity_per_week'];

        $diffs = [];
        foreach ($metrics as $metric) {
            $valA = $a[$metric] ?? 0;
            $valB = $b[$metric] ?? 0;
            $diffs[$metric] = [
                'a' => $valA,
                'b' => $valB,
                'diff' => $valA - $valB,
                'winner' => $valA > $valB ? 'a' : ($valB > $valA ? 'b' : 'tie'),
            ];
        }

        // Shared countries
        $sharedCountries = array_intersect($a['countries'], $b['countries']);
        $diffs['shared_countries'] = count($sharedCountries);
        $diffs['unique_countries_a'] = array_diff($a['countries'], $b['countries']);
        $diffs['unique_countries_b'] = array_diff($b['countries'], $a['countries']);

        return $diffs;
    }

    /**
     * Compute rankings across multiple advertisers.
     */
    private function computeRankings(array $metrics): array
    {
        $rankings = [];
        $fields = ['total_ads', 'active_ads', 'geo_spread', 'velocity_per_week', 'unique_headlines'];

        foreach ($fields as $field) {
            $values = [];
            foreach ($metrics as $advId => $m) {
                $values[$advId] = $m[$field] ?? 0;
            }
            arsort($values);
            $rankings[$field] = array_keys($values);
        }

        return $rankings;
    }

    private function log(string $message): void
    {
        echo "[" . date('Y-m-d H:i:s') . "] COMPARE: {$message}\n";
    }
}
