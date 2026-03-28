<?php

/**
 * Watchlist - Competitor Tracking & Grouping System
 *
 * Manages watchlists of advertisers, supports grouping
 * (e.g., "Finance Apps"), and generates daily summaries.
 */
class Watchlist
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    // =========================================================
    // CRUD Operations
    // =========================================================

    public function create(string $name, ?string $groupLabel = null, ?string $description = null, ?int $userId = null): string
    {
        return $this->db->insert('watchlists', [
            'user_id'     => $userId,
            'name'        => $name,
            'description' => $description,
            'group_label' => $groupLabel,
        ]);
    }

    public function addAdvertiser(int $watchlistId, string $advertiserId, ?string $name = null): string
    {
        return $this->db->insert('watchlist_advertisers', [
            'watchlist_id'    => $watchlistId,
            'advertiser_id'   => $advertiserId,
            'advertiser_name' => $name,
        ]);
    }

    public function removeAdvertiser(int $watchlistId, string $advertiserId): int
    {
        return $this->db->query(
            "DELETE FROM watchlist_advertisers WHERE watchlist_id = ? AND advertiser_id = ?",
            [$watchlistId, $advertiserId]
        )->rowCount();
    }

    public function delete(int $watchlistId): int
    {
        return $this->db->query("DELETE FROM watchlists WHERE id = ?", [$watchlistId])->rowCount();
    }

    public function getAll(?int $userId = null): array
    {
        $where = $userId ? 'WHERE w.user_id = ?' : '';
        $params = $userId ? [$userId] : [];

        return $this->db->fetchAll(
            "SELECT w.*, COUNT(wa.id) as advertiser_count
             FROM watchlists w
             LEFT JOIN watchlist_advertisers wa ON w.id = wa.watchlist_id
             {$where}
             GROUP BY w.id
             ORDER BY w.name ASC",
            $params
        );
    }

    public function getById(int $watchlistId): ?array
    {
        $watchlist = $this->db->fetchOne("SELECT * FROM watchlists WHERE id = ?", [$watchlistId]);
        if (!$watchlist) return null;

        $watchlist['advertisers'] = $this->db->fetchAll(
            "SELECT wa.*,
                    (SELECT COUNT(*) FROM ads WHERE advertiser_id = wa.advertiser_id) as total_ads,
                    (SELECT COUNT(*) FROM ads WHERE advertiser_id = wa.advertiser_id AND status = 'active') as active_ads,
                    (SELECT MAX(last_seen) FROM ads WHERE advertiser_id = wa.advertiser_id) as last_activity
             FROM watchlist_advertisers wa
             WHERE wa.watchlist_id = ?
             ORDER BY wa.advertiser_name ASC",
            [$watchlistId]
        );

        return $watchlist;
    }

    public function getGroups(): array
    {
        return $this->db->fetchAll(
            "SELECT group_label, COUNT(*) as watchlist_count
             FROM watchlists
             WHERE group_label IS NOT NULL
             GROUP BY group_label
             ORDER BY group_label ASC"
        );
    }

    // =========================================================
    // Daily Summary
    // =========================================================

    /**
     * Generate daily summary for a watchlist.
     */
    public function getDailySummary(int $watchlistId): array
    {
        $watchlist = $this->getById($watchlistId);
        if (!$watchlist) return [];

        $advertiserIds = array_column($watchlist['advertisers'], 'advertiser_id');
        if (empty($advertiserIds)) return ['watchlist' => $watchlist, 'summaries' => []];

        $summaries = [];
        foreach ($advertiserIds as $advId) {
            $summaries[$advId] = [
                'new_ads' => (int) $this->db->fetchColumn(
                    "SELECT COUNT(*) FROM ads WHERE advertiser_id = ? AND DATE(created_at) = CURDATE()",
                    [$advId]
                ),
                'stopped_ads' => (int) $this->db->fetchColumn(
                    "SELECT COUNT(*) FROM ads WHERE advertiser_id = ? AND status = 'inactive' AND DATE(last_seen) = CURDATE()",
                    [$advId]
                ),
                'active_ads' => (int) $this->db->fetchColumn(
                    "SELECT COUNT(*) FROM ads WHERE advertiser_id = ? AND status = 'active'",
                    [$advId]
                ),
                'content_changes' => (int) $this->db->fetchColumn(
                    "SELECT COUNT(*) FROM ad_details d
                     INNER JOIN ads a ON d.creative_id = a.creative_id
                     WHERE a.advertiser_id = ? AND DATE(d.snapshot_date) = CURDATE()
                     AND (SELECT COUNT(*) FROM ad_details WHERE creative_id = d.creative_id) > 1",
                    [$advId]
                ),
                'new_countries' => $this->db->fetchAll(
                    "SELECT DISTINCT t.country FROM ad_targeting t
                     INNER JOIN ads a ON t.creative_id = a.creative_id
                     WHERE a.advertiser_id = ? AND DATE(t.detected_at) = CURDATE()",
                    [$advId]
                ),
            ];
        }

        return ['watchlist' => $watchlist, 'summaries' => $summaries];
    }

    /**
     * Get change log for a specific advertiser.
     */
    public function getAdvertiserChangeLog(string $advertiserId, int $days = 7): array
    {
        return $this->db->fetchAll(
            "SELECT a.creative_id, a.ad_type, a.status, a.first_seen, a.last_seen,
                    d.headline, d.cta, d.snapshot_date,
                    CASE
                        WHEN a.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY) THEN 'new'
                        WHEN a.status = 'inactive' AND a.last_seen >= DATE_SUB(NOW(), INTERVAL ? DAY) THEN 'stopped'
                        ELSE 'active'
                    END as change_type
             FROM ads a
             LEFT JOIN ad_details d ON a.creative_id = d.creative_id
                 AND d.id = (SELECT MAX(id) FROM ad_details WHERE creative_id = a.creative_id)
             WHERE a.advertiser_id = ?
             AND (a.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY) OR a.last_seen >= DATE_SUB(NOW(), INTERVAL ? DAY))
             ORDER BY a.last_seen DESC
             LIMIT 100",
            [$days, $days, $advertiserId, $days, $days]
        );
    }
}
