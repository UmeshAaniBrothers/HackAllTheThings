<?php

/**
 * ChangeDetector - Ad Change Detection Engine
 *
 * Detects new ads, content updates, removed ads, and revived ads.
 * Logs all detection activity to scrape_logs.
 */
class ChangeDetector
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Run all detection routines and return a summary.
     */
    public function runAll(): array
    {
        $this->log("Starting change detection...");

        $results = [
            'new_ads'     => $this->detectNewAds(),
            'updated_ads' => $this->detectUpdates(),
            'removed_ads' => $this->detectRemovedAds(),
            'revived_ads' => $this->detectRevivals(),
        ];

        $this->log("Detection complete: " . json_encode($results));

        // Log results per advertiser
        $this->logResultsByAdvertiser($results);

        return $results;
    }

    /**
     * Detect new ads that haven't been fully processed.
     * New ads are those created in the last detection cycle.
     */
    public function detectNewAds(): int
    {
        $result = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM ads WHERE created_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)"
        );

        $count = (int) $result;
        $this->log("New ads detected: {$count}");
        return $count;
    }

    /**
     * Detect content updates by comparing hash signatures.
     * When the hash changes, a new ad_details record should already exist.
     * This counts ads with multiple detail versions in recent timeframe.
     */
    public function detectUpdates(): int
    {
        $result = $this->db->fetchColumn(
            "SELECT COUNT(DISTINCT ad.creative_id)
             FROM ads ad
             INNER JOIN ad_details det ON ad.creative_id = det.creative_id
             WHERE det.snapshot_date >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)
             AND (SELECT COUNT(*) FROM ad_details d2 WHERE d2.creative_id = ad.creative_id) > 1"
        );

        $count = (int) $result;
        $this->log("Updated ads detected: {$count}");
        return $count;
    }

    /**
     * Detect removed ads - those not seen in the last 48 hours.
     * Mark them as inactive.
     */
    public function detectRemovedAds(int $hoursThreshold = 48): int
    {
        $stmt = $this->db->query(
            "UPDATE ads SET status = 'inactive'
             WHERE status = 'active'
             AND last_seen < DATE_SUB(NOW(), INTERVAL ? HOUR)",
            [$hoursThreshold]
        );

        $count = $stmt->rowCount();
        $this->log("Removed (inactive) ads: {$count} (threshold: {$hoursThreshold}h)");
        return $count;
    }

    /**
     * Detect revived ads - previously inactive ads that appeared again.
     */
    public function detectRevivals(): int
    {
        $result = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM ads
             WHERE status = 'active'
             AND last_seen >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)
             AND first_seen < DATE_SUB(NOW(), INTERVAL 48 HOUR)"
        );

        $count = (int) $result;
        $this->log("Revived ads detected: {$count}");
        return $count;
    }

    /**
     * Log detection results grouped by advertiser.
     */
    private function logResultsByAdvertiser(array $results): void
    {
        $advertisers = $this->db->fetchAll(
            "SELECT DISTINCT advertiser_id FROM ads WHERE last_seen >= DATE_SUB(NOW(), INTERVAL 1 DAY)"
        );

        foreach ($advertisers as $row) {
            $advertiserId = $row['advertiser_id'];

            $adsFound = (int) $this->db->fetchColumn(
                "SELECT COUNT(*) FROM ads WHERE advertiser_id = ?",
                [$advertiserId]
            );

            $newAds = (int) $this->db->fetchColumn(
                "SELECT COUNT(*) FROM ads WHERE advertiser_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)",
                [$advertiserId]
            );

            $this->db->insert('scrape_logs', [
                'advertiser_id' => $advertiserId,
                'ads_found'     => $adsFound,
                'new_ads'       => $newAds,
                'updated_ads'   => $results['updated_ads'],
                'removed_ads'   => $results['removed_ads'],
                'status'        => 'success',
            ]);
        }
    }

    /**
     * Get detection summary for a specific advertiser.
     */
    public function getAdvertiserSummary(string $advertiserId): array
    {
        return [
            'total_ads' => (int) $this->db->fetchColumn(
                "SELECT COUNT(*) FROM ads WHERE advertiser_id = ?",
                [$advertiserId]
            ),
            'active_ads' => (int) $this->db->fetchColumn(
                "SELECT COUNT(*) FROM ads WHERE advertiser_id = ? AND status = 'active'",
                [$advertiserId]
            ),
            'inactive_ads' => (int) $this->db->fetchColumn(
                "SELECT COUNT(*) FROM ads WHERE advertiser_id = ? AND status = 'inactive'",
                [$advertiserId]
            ),
            'last_scrape' => $this->db->fetchOne(
                "SELECT * FROM scrape_logs WHERE advertiser_id = ? ORDER BY created_at DESC LIMIT 1",
                [$advertiserId]
            ),
        ];
    }

    /**
     * Log a message with timestamp.
     */
    private function log(string $message): void
    {
        $timestamp = date('Y-m-d H:i:s');
        echo "[{$timestamp}] DETECTOR: {$message}\n";
    }
}
