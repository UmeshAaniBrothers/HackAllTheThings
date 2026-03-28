<?php

/**
 * DataQuality - Validation, Anomaly Detection, and Data Health
 *
 * Runs validation checks, detects missing fields,
 * identifies anomalies, and maintains data quality scores.
 */
class DataQuality
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Run all data quality checks.
     */
    public function runAll(): array
    {
        $results = [
            'missing_fields'  => $this->checkMissingFields(),
            'duplicates'      => $this->checkDuplicates(),
            'anomalies'       => $this->checkAnomalies(),
            'stale_data'      => $this->checkStaleData(),
            'format_errors'   => $this->checkFormatErrors(),
        ];

        $total = array_sum($results);
        $this->log("Quality check complete: {$total} issues found - " . json_encode($results));
        return $results;
    }

    /**
     * Check for missing required fields.
     */
    private function checkMissingFields(): int
    {
        $issues = 0;

        // Ads without any details
        $noDetails = $this->db->fetchAll(
            "SELECT a.creative_id FROM ads a
             LEFT JOIN ad_details d ON a.creative_id = d.creative_id
             WHERE d.id IS NULL
             LIMIT 100"
        );
        foreach ($noDetails as $row) {
            $this->logIssue('missing_field', 'ad', $row['creative_id'], 'medium', "Ad has no content details");
            $issues++;
        }

        // Ads without targeting data
        $noTargeting = $this->db->fetchAll(
            "SELECT a.creative_id FROM ads a
             LEFT JOIN ad_targeting t ON a.creative_id = t.creative_id
             WHERE t.id IS NULL
             LIMIT 100"
        );
        foreach ($noTargeting as $row) {
            $this->logIssue('missing_field', 'ad', $row['creative_id'], 'low', "Ad has no targeting data");
            $issues++;
        }

        // Details with no headline AND no description
        $emptyContent = (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM ad_details
             WHERE (headline IS NULL OR headline = '') AND (description IS NULL OR description = '')"
        );
        if ($emptyContent > 0) {
            $this->logIssue('missing_field', 'ad_details', null, 'medium', "{$emptyContent} ad details with no headline or description");
            $issues++;
        }

        return $issues;
    }

    /**
     * Check for duplicate data.
     */
    private function checkDuplicates(): int
    {
        $issues = 0;

        // Duplicate assets (same URL for same creative)
        $dupAssets = (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM (
                SELECT creative_id, original_url, COUNT(*) as cnt
                FROM ad_assets
                WHERE original_url IS NOT NULL
                GROUP BY creative_id, original_url
                HAVING cnt > 1
             ) as dups"
        );
        if ($dupAssets > 0) {
            $this->logIssue('duplicate', 'asset', null, 'low', "{$dupAssets} duplicate asset entries");
            $issues++;
        }

        // Duplicate targeting entries
        $dupTargeting = (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM (
                SELECT creative_id, country, platform, COUNT(*) as cnt
                FROM ad_targeting
                GROUP BY creative_id, country, platform
                HAVING cnt > 1
             ) as dups"
        );
        if ($dupTargeting > 0) {
            $this->logIssue('duplicate', 'targeting', null, 'low', "{$dupTargeting} duplicate targeting entries");
            $issues++;
        }

        return $issues;
    }

    /**
     * Check for data anomalies.
     */
    private function checkAnomalies(): int
    {
        $issues = 0;

        // Ads with last_seen before first_seen
        $timeAnomalies = (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM ads WHERE last_seen < first_seen"
        );
        if ($timeAnomalies > 0) {
            $this->logIssue('anomaly', 'ad', null, 'high', "{$timeAnomalies} ads with last_seen before first_seen");
            $issues++;
        }

        // Ads with unrealistic durations (> 5 years)
        $longRunning = (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM ads WHERE TIMESTAMPDIFF(DAY, first_seen, last_seen) > 1825"
        );
        if ($longRunning > 0) {
            $this->logIssue('anomaly', 'ad', null, 'medium', "{$longRunning} ads with unrealistic duration (>5 years)");
            $issues++;
        }

        // Sudden spike in new ads (>10x daily average)
        $todayCount = (int) $this->db->fetchColumn("SELECT COUNT(*) FROM ads WHERE DATE(created_at) = CURDATE()");
        $avgDaily = (float) ($this->db->fetchColumn(
            "SELECT AVG(daily_count) FROM (
                SELECT DATE(created_at) as dt, COUNT(*) as daily_count
                FROM ads
                WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND DATE(created_at) != CURDATE()
                GROUP BY dt
             ) as daily"
        ) ?? 0);

        if ($avgDaily > 0 && $todayCount > $avgDaily * 10) {
            $this->logIssue('anomaly', 'system', null, 'critical',
                "Anomalous spike: {$todayCount} ads today vs {$avgDaily} daily average");
            $issues++;
        }

        return $issues;
    }

    /**
     * Check for stale data.
     */
    private function checkStaleData(): int
    {
        $issues = 0;

        // Unprocessed payloads older than 1 hour
        $stalePayloads = (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM raw_payloads
             WHERE processed_flag = 0 AND created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)"
        );
        if ($stalePayloads > 0) {
            $this->logIssue('stale_data', 'raw_payloads', null, 'high',
                "{$stalePayloads} unprocessed payloads older than 1 hour");
            $issues++;
        }

        // No scrape activity in last 24 hours
        $lastScrape = $this->db->fetchColumn(
            "SELECT MAX(created_at) FROM scrape_logs WHERE status = 'success'"
        );
        if ($lastScrape && strtotime($lastScrape) < strtotime('-24 hours')) {
            $this->logIssue('stale_data', 'system', null, 'critical',
                "No successful scrape in last 24 hours. Last: {$lastScrape}");
            $issues++;
        }

        return $issues;
    }

    /**
     * Check for format errors.
     */
    private function checkFormatErrors(): int
    {
        $issues = 0;

        // Invalid URLs in landing pages
        $invalidUrls = (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM ad_details
             WHERE landing_url IS NOT NULL AND landing_url != ''
             AND landing_url NOT LIKE 'http%'"
        );
        if ($invalidUrls > 0) {
            $this->logIssue('format_error', 'ad_details', null, 'low', "{$invalidUrls} ad details with invalid landing URLs");
            $issues++;
        }

        // Empty hash signatures
        $emptyHashes = (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM ads WHERE hash_signature = '' OR hash_signature IS NULL"
        );
        if ($emptyHashes > 0) {
            $this->logIssue('format_error', 'ad', null, 'high', "{$emptyHashes} ads with empty hash signatures");
            $issues++;
        }

        return $issues;
    }

    /**
     * Log a quality issue.
     */
    private function logIssue(string $type, string $entityType, ?string $entityId, string $severity, string $description): void
    {
        // Avoid duplicate logging for same issue within 24 hours
        $existing = $this->db->fetchOne(
            "SELECT id FROM data_quality_log
             WHERE check_type = ? AND entity_type = ? AND description = ? AND resolved = 0
             AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)",
            [$type, $entityType, $description]
        );

        if ($existing === null) {
            $this->db->insert('data_quality_log', [
                'check_type'  => $type,
                'entity_type' => $entityType,
                'entity_id'   => $entityId,
                'severity'    => $severity,
                'description' => $description,
            ]);
        }
    }

    /**
     * Get quality dashboard data.
     */
    public function getDashboard(): array
    {
        $open = $this->db->fetchAll(
            "SELECT check_type, severity, COUNT(*) as count
             FROM data_quality_log WHERE resolved = 0
             GROUP BY check_type, severity
             ORDER BY FIELD(severity, 'critical', 'high', 'medium', 'low')"
        );

        $recent = $this->db->fetchAll(
            "SELECT * FROM data_quality_log ORDER BY created_at DESC LIMIT 50"
        );

        $totalOpen = (int) $this->db->fetchColumn("SELECT COUNT(*) FROM data_quality_log WHERE resolved = 0");
        $critical = (int) $this->db->fetchColumn("SELECT COUNT(*) FROM data_quality_log WHERE resolved = 0 AND severity = 'critical'");

        return [
            'total_open'  => $totalOpen,
            'critical'    => $critical,
            'by_type'     => $open,
            'recent'      => $recent,
        ];
    }

    /**
     * Resolve an issue.
     */
    public function resolveIssue(int $issueId): int
    {
        return $this->db->update('data_quality_log', ['resolved' => 1], 'id = ?', [$issueId]);
    }

    private function log(string $message): void
    {
        echo "[" . date('Y-m-d H:i:s') . "] QUALITY: {$message}\n";
    }
}
