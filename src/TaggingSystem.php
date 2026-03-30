<?php

/**
 * TaggingSystem - Manual & Auto Tagging for Ads
 *
 * Supports user-defined tags and automatic tag detection
 * based on ad content patterns.
 */
class TaggingSystem
{
    private Database $db;

    // Auto-tag rules: tag_name => [patterns]
    private const AUTO_TAG_RULES = [
        'discount'       => ['% off', 'discount', 'sale', 'clearance', 'save $', 'deal'],
        'free_trial'     => ['free trial', 'try free', 'start free', '0 cost'],
        'urgency'        => ['limited time', 'act now', 'hurry', 'last chance', 'ends soon'],
        'app_install'    => ['download', 'install', 'get the app', 'app store', 'google play'],
        'ecommerce'      => ['buy now', 'shop now', 'add to cart', 'order now', 'free shipping'],
        'lead_gen'       => ['contact us', 'get a quote', 'book a demo', 'schedule call', 'request info'],
        'festival'       => ['black friday', 'cyber monday', 'diwali', 'christmas', 'new year', 'halloween', 'valentines', 'easter'],
        'comparison'     => ['vs', 'better than', 'compared to', 'alternative to', 'switch from'],
        'testimonial'    => ['customer', 'review', 'testimonial', 'rated', 'stars', 'trusted by'],
        'video_content'  => ['watch', 'video', 'tutorial', 'webinar', 'livestream'],
    ];

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    // =========================================================
    // Tag Management
    // =========================================================

    public function createTag(string $name, string $type = 'manual', string $color = '#6c757d'): string
    {
        return $this->db->insert('tags', [
            'name'     => strtolower(trim($name)),
            'tag_type' => $type,
            'color'    => $color,
        ]);
    }

    public function deleteTag(int $tagId): int
    {
        return $this->db->query("DELETE FROM tags WHERE id = ?", [$tagId])->rowCount();
    }

    public function getAllTags(): array
    {
        return $this->db->fetchAll(
            "SELECT t.*, COUNT(at.id) as usage_count
             FROM tags t
             LEFT JOIN ad_tags at ON t.id = at.tag_id
             GROUP BY t.id
             ORDER BY usage_count DESC"
        );
    }

    // =========================================================
    // Tagging Ads
    // =========================================================

    public function tagAd(string $creativeId, int $tagId, string $taggedBy = 'user'): bool
    {
        $exists = $this->db->fetchOne(
            "SELECT id FROM ad_tags WHERE creative_id = ? AND tag_id = ?",
            [$creativeId, $tagId]
        );
        if ($exists) return false;

        $this->db->insert('ad_tags', [
            'creative_id' => $creativeId,
            'tag_id'      => $tagId,
            'tagged_by'   => $taggedBy,
        ]);
        return true;
    }

    public function untagAd(string $creativeId, int $tagId): int
    {
        return $this->db->query(
            "DELETE FROM ad_tags WHERE creative_id = ? AND tag_id = ?",
            [$creativeId, $tagId]
        )->rowCount();
    }

    public function getAdTags(string $creativeId): array
    {
        return $this->db->fetchAll(
            "SELECT t.*, at.tagged_by, at.created_at as tagged_at
             FROM tags t
             INNER JOIN ad_tags at ON t.id = at.tag_id
             WHERE at.creative_id = ?
             ORDER BY t.name ASC",
            [$creativeId]
        );
    }

    public function getAdsByTag(int $tagId, int $limit = 50): array
    {
        return $this->db->fetchAll(
            "SELECT a.*, d.headline, d.cta
             FROM ads a
             INNER JOIN ad_tags at ON a.creative_id = at.creative_id
             LEFT JOIN ad_details d ON a.creative_id = d.creative_id
                 AND d.id = (SELECT MAX(id) FROM ad_details WHERE creative_id = a.creative_id)
             WHERE at.tag_id = ?
             ORDER BY a.last_seen DESC
             LIMIT ?",
            [$tagId, $limit]
        );
    }

    // =========================================================
    // Auto-Tagging
    // =========================================================

    /**
     * Run auto-tagging on all untagged (by system) ads.
     */
    public function autoTagAll(): int
    {
        // Ensure auto-tag categories exist
        $this->ensureAutoTags();

        $ads = $this->db->fetchAll(
            "SELECT a.creative_id, d.headline, d.description, d.cta
             FROM ads a
             LEFT JOIN ad_details d ON a.creative_id = d.creative_id
                 AND d.id = (SELECT MAX(id) FROM ad_details WHERE creative_id = a.creative_id)
             WHERE a.creative_id NOT IN (SELECT DISTINCT creative_id FROM ad_tags WHERE tagged_by = 'system')"
        );

        $tagged = 0;
        foreach ($ads as $ad) {
            $tagged += $this->autoTagAd($ad);
        }

        $this->log("Auto-tagged {$tagged} ad-tag combinations");
        return $tagged;
    }

    /**
     * Auto-tag a single ad based on content patterns.
     */
    private function autoTagAd(array $ad): int
    {
        $text = strtolower(implode(' ', array_filter([
            $ad['headline'] ?? '',
            $ad['description'] ?? '',
            $ad['cta'] ?? '',
        ])));

        $tagged = 0;

        foreach (self::AUTO_TAG_RULES as $tagName => $patterns) {
            foreach ($patterns as $pattern) {
                if (stripos($text, $pattern) !== false) {
                    $tag = $this->db->fetchOne("SELECT id FROM tags WHERE name = ?", [$tagName]);
                    if ($tag && $this->tagAd($ad['creative_id'], $tag['id'], 'system')) {
                        $tagged++;
                    }
                    break; // One match per tag category is enough
                }
            }
        }

        return $tagged;
    }

    /**
     * Ensure all auto-tag categories exist in the tags table.
     */
    private function ensureAutoTags(): void
    {
        $colors = [
            'discount'      => '#dc3545', 'free_trial'  => '#28a745', 'urgency'     => '#ffc107',
            'app_install'   => '#17a2b8', 'ecommerce'   => '#6f42c1', 'lead_gen'    => '#fd7e14',
            'festival'      => '#e83e8c', 'comparison'  => '#20c997', 'testimonial' => '#6610f2',
            'video_content' => '#007bff',
        ];

        foreach (self::AUTO_TAG_RULES as $tagName => $patterns) {
            $exists = $this->db->fetchOne("SELECT id FROM tags WHERE name = ?", [$tagName]);
            if (!$exists) {
                $this->createTag($tagName, 'auto', $colors[$tagName] ?? '#6c757d');
            }
        }
    }

    private function log(string $message): void
    {
        echo "[" . date('Y-m-d H:i:s') . "] TAGS: {$message}\n";
    }
}
