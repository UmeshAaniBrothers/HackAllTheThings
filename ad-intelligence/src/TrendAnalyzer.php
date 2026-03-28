<?php

/**
 * TrendAnalyzer - Velocity, Burst, and Seasonality Detection
 *
 * Analyzes advertiser behavior over time to detect
 * scaling patterns, campaign launches, and seasonal trends.
 */
class TrendAnalyzer
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Run full trend analysis for all active advertisers.
     */
    public function analyzeAll(): array
    {
        $advertisers = $this->db->fetchAll(
            "SELECT DISTINCT advertiser_id FROM ads WHERE status = 'active'"
        );

        $results = ['snapshots' => 0, 'bursts' => 0, 'patterns' => 0];

        foreach ($advertisers as $row) {
            $r = $this->analyzeAdvertiser($row['advertiser_id']);
            $results['snapshots']++;
            $results['bursts'] += $r['is_burst'] ? 1 : 0;
            $results['patterns'] += $r['patterns_detected'];
        }

        $this->log("Trend analysis complete: " . json_encode($results));
        return $results;
    }

    /**
     * Analyze trends for a single advertiser.
     */
    public function analyzeAdvertiser(string $advertiserId): array
    {
        $today = date('Y-m-d');

        // Daily counts
        $launched = (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM ads WHERE advertiser_id = ? AND DATE(created_at) = ?",
            [$advertiserId, $today]
        );

        $stopped = (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM ads WHERE advertiser_id = ? AND status = 'inactive' AND DATE(last_seen) = ?",
            [$advertiserId, $today]
        );

        $active = (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM ads WHERE advertiser_id = ? AND status = 'active'",
            [$advertiserId]
        );

        $newCountries = (int) $this->db->fetchColumn(
            "SELECT COUNT(DISTINCT country) FROM ad_targeting t
             INNER JOIN ads a ON t.creative_id = a.creative_id
             WHERE a.advertiser_id = ? AND DATE(t.detected_at) = ?",
            [$advertiserId, $today]
        );

        // Calculate velocity (7-day average)
        $avgVelocity = (float) ($this->db->fetchColumn(
            "SELECT AVG(ads_launched) FROM trend_snapshots
             WHERE advertiser_id = ? AND snapshot_date >= DATE_SUB(?, INTERVAL 7 DAY)",
            [$advertiserId, $today]
        ) ?? 0);

        $velocityScore = $avgVelocity > 0 ? $launched / $avgVelocity : 0;

        // Burst detection (2x+ above 7-day average)
        $isBurst = ($avgVelocity > 0 && $launched >= $avgVelocity * 2);
        $burstMagnitude = $avgVelocity > 0 ? round($launched / $avgVelocity, 2) : 0;

        // Store daily snapshot
        $this->db->query(
            "INSERT INTO trend_snapshots (advertiser_id, snapshot_date, ads_launched, ads_stopped, active_ads, new_countries, velocity_score, is_burst, burst_magnitude)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE ads_launched = VALUES(ads_launched), ads_stopped = VALUES(ads_stopped),
                 active_ads = VALUES(active_ads), new_countries = VALUES(new_countries),
                 velocity_score = VALUES(velocity_score), is_burst = VALUES(is_burst), burst_magnitude = VALUES(burst_magnitude)",
            [$advertiserId, $today, $launched, $stopped, $active, $newCountries, $velocityScore, $isBurst ? 1 : 0, $burstMagnitude]
        );

        // Pattern detection
        $patternsDetected = 0;

        if ($isBurst) {
            $this->recordPattern('burst', $advertiserId,
                "Campaign burst detected: {$launched} ads launched ({$burstMagnitude}x normal)",
                min(100, $burstMagnitude * 30),
                ['launched' => $launched, 'avg' => $avgVelocity, 'magnitude' => $burstMagnitude]
            );
            $patternsDetected++;
        }

        // Scaling detection (3+ consecutive days of growth)
        if ($this->detectScaling($advertiserId)) {
            $patternsDetected++;
        }

        // Decline detection
        if ($this->detectDecline($advertiserId)) {
            $patternsDetected++;
        }

        return [
            'is_burst'          => $isBurst,
            'burst_magnitude'   => $burstMagnitude,
            'velocity_score'    => $velocityScore,
            'patterns_detected' => $patternsDetected,
        ];
    }

    /**
     * Detect scaling behavior (3+ consecutive growth days).
     */
    private function detectScaling(string $advertiserId): bool
    {
        $recent = $this->db->fetchAll(
            "SELECT ads_launched FROM trend_snapshots
             WHERE advertiser_id = ? ORDER BY snapshot_date DESC LIMIT 5",
            [$advertiserId]
        );

        if (count($recent) < 3) return false;

        $growing = 0;
        for ($i = 0; $i < count($recent) - 1; $i++) {
            if ($recent[$i]['ads_launched'] > $recent[$i + 1]['ads_launched']) {
                $growing++;
            } else {
                break;
            }
        }

        if ($growing >= 3) {
            $this->recordPattern('scaling', $advertiserId,
                "Scaling detected: {$growing} consecutive days of ad growth",
                min(100, $growing * 25),
                ['consecutive_growth_days' => $growing]
            );
            return true;
        }

        return false;
    }

    /**
     * Detect decline behavior.
     */
    private function detectDecline(string $advertiserId): bool
    {
        $recent = $this->db->fetchAll(
            "SELECT ads_launched, ads_stopped FROM trend_snapshots
             WHERE advertiser_id = ? ORDER BY snapshot_date DESC LIMIT 5",
            [$advertiserId]
        );

        if (count($recent) < 3) return false;

        $declining = 0;
        for ($i = 0; $i < count($recent) - 1; $i++) {
            if ($recent[$i]['ads_stopped'] > $recent[$i]['ads_launched']) {
                $declining++;
            } else {
                break;
            }
        }

        if ($declining >= 3) {
            $this->recordPattern('decline', $advertiserId,
                "Decline detected: {$declining} consecutive days of net ad loss",
                min(100, $declining * 25),
                ['consecutive_decline_days' => $declining]
            );
            return true;
        }

        return false;
    }

    /**
     * Detect seasonality patterns.
     */
    public function detectSeasonality(string $advertiserId): array
    {
        $monthly = $this->db->fetchAll(
            "SELECT MONTH(created_at) as month, COUNT(*) as ad_count
             FROM ads WHERE advertiser_id = ?
             GROUP BY MONTH(created_at)
             ORDER BY month",
            [$advertiserId]
        );

        if (count($monthly) < 3) return [];

        $avg = array_sum(array_column($monthly, 'ad_count')) / count($monthly);
        $peaks = [];

        foreach ($monthly as $m) {
            if ($m['ad_count'] > $avg * 1.5) {
                $peaks[] = [
                    'month'     => $m['month'],
                    'ad_count'  => $m['ad_count'],
                    'magnitude' => round($m['ad_count'] / $avg, 2),
                ];
            }
        }

        if (!empty($peaks)) {
            $this->recordPattern('seasonality', $advertiserId,
                "Seasonal peaks in months: " . implode(', ', array_column($peaks, 'month')),
                70,
                ['peaks' => $peaks, 'monthly_avg' => round($avg, 1)]
            );
        }

        return $peaks;
    }

    /**
     * Get velocity trend data.
     */
    public function getVelocityTrend(string $advertiserId, int $days = 30): array
    {
        return $this->db->fetchAll(
            "SELECT snapshot_date, ads_launched, ads_stopped, active_ads, velocity_score, is_burst
             FROM trend_snapshots
             WHERE advertiser_id = ? AND snapshot_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
             ORDER BY snapshot_date ASC",
            [$advertiserId, $days]
        );
    }

    /**
     * Get detected patterns.
     */
    public function getPatterns(?string $advertiserId = null, int $limit = 50): array
    {
        if ($advertiserId) {
            return $this->db->fetchAll(
                "SELECT * FROM detected_patterns WHERE advertiser_id = ? ORDER BY detected_at DESC LIMIT ?",
                [$advertiserId, $limit]
            );
        }

        return $this->db->fetchAll(
            "SELECT * FROM detected_patterns ORDER BY detected_at DESC LIMIT ?",
            [$limit]
        );
    }

    private function recordPattern(string $type, string $advertiserId, string $description, float $confidence, array $metadata): void
    {
        $this->db->insert('detected_patterns', [
            'pattern_type'  => $type,
            'advertiser_id' => $advertiserId,
            'description'   => $description,
            'confidence'    => $confidence,
            'metadata'      => json_encode($metadata),
        ]);
    }

    private function log(string $message): void
    {
        echo "[" . date('Y-m-d H:i:s') . "] TRENDS: {$message}\n";
    }
}
