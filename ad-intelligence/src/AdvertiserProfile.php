<?php

/**
 * AdvertiserProfile - Advertiser DNA Profiling
 *
 * Builds comprehensive profiles per advertiser including
 * lifetime stats, dominant patterns, and behavioral DNA.
 */
class AdvertiserProfile
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Update profiles for all active advertisers.
     */
    public function updateAll(): int
    {
        $advertisers = $this->db->fetchAll(
            "SELECT DISTINCT advertiser_id FROM ads"
        );

        $updated = 0;
        foreach ($advertisers as $row) {
            $this->updateProfile($row['advertiser_id']);
            $updated++;
        }

        $this->log("Updated {$updated} advertiser profiles");
        return $updated;
    }

    /**
     * Build/update profile for a single advertiser.
     */
    public function updateProfile(string $advertiserId): array
    {
        $profile = $this->computeProfile($advertiserId);

        $existing = $this->db->fetchOne(
            "SELECT advertiser_id FROM advertiser_profiles WHERE advertiser_id = ?",
            [$advertiserId]
        );

        $data = [
            'total_lifetime_ads'        => $profile['total_lifetime_ads'],
            'active_duration_days'      => $profile['active_duration_days'],
            'dominant_ad_type'          => $profile['dominant_ad_type'],
            'dominant_cta_style'        => $profile['dominant_cta_style'],
            'primary_countries'         => json_encode($profile['primary_countries']),
            'primary_platforms'         => json_encode($profile['primary_platforms']),
            'avg_campaign_duration_days' => $profile['avg_campaign_duration_days'],
            'ad_frequency_per_week'     => $profile['ad_frequency_per_week'],
            'intelligence_score'        => $profile['intelligence_score'],
            'profile_updated_at'        => date('Y-m-d H:i:s'),
        ];

        if ($existing) {
            $this->db->update('advertiser_profiles', $data, 'advertiser_id = ?', [$advertiserId]);
        } else {
            $data['advertiser_id'] = $advertiserId;
            $this->db->insert('advertiser_profiles', $data);
        }

        return $profile;
    }

    /**
     * Compute full profile data.
     */
    private function computeProfile(string $advertiserId): array
    {
        // Total lifetime ads
        $totalAds = (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM ads WHERE advertiser_id = ?",
            [$advertiserId]
        );

        // Active duration
        $dateRange = $this->db->fetchOne(
            "SELECT MIN(first_seen) as earliest, MAX(last_seen) as latest FROM ads WHERE advertiser_id = ?",
            [$advertiserId]
        );
        $activeDays = 0;
        if ($dateRange && $dateRange['earliest'] && $dateRange['latest']) {
            $activeDays = (new DateTime($dateRange['earliest']))->diff(new DateTime($dateRange['latest']))->days;
        }

        // Dominant ad type
        $dominantType = $this->db->fetchOne(
            "SELECT ad_type, COUNT(*) as cnt FROM ads WHERE advertiser_id = ? GROUP BY ad_type ORDER BY cnt DESC LIMIT 1",
            [$advertiserId]
        );

        // Dominant CTA style
        $dominantCta = $this->db->fetchOne(
            "SELECT d.cta, COUNT(*) as cnt
             FROM ad_details d
             INNER JOIN ads a ON d.creative_id = a.creative_id
             WHERE a.advertiser_id = ? AND d.cta IS NOT NULL AND d.cta != ''
             GROUP BY d.cta ORDER BY cnt DESC LIMIT 1",
            [$advertiserId]
        );

        // Primary countries (top 5)
        $countries = $this->db->fetchAll(
            "SELECT t.country, COUNT(DISTINCT t.creative_id) as cnt
             FROM ad_targeting t
             INNER JOIN ads a ON t.creative_id = a.creative_id
             WHERE a.advertiser_id = ?
             GROUP BY t.country ORDER BY cnt DESC LIMIT 5",
            [$advertiserId]
        );

        // Primary platforms
        $platforms = $this->db->fetchAll(
            "SELECT t.platform, COUNT(DISTINCT t.creative_id) as cnt
             FROM ad_targeting t
             INNER JOIN ads a ON t.creative_id = a.creative_id
             WHERE a.advertiser_id = ?
             GROUP BY t.platform ORDER BY cnt DESC LIMIT 5",
            [$advertiserId]
        );

        // Average campaign duration
        $avgDuration = (float) ($this->db->fetchColumn(
            "SELECT AVG(TIMESTAMPDIFF(DAY, first_seen, last_seen))
             FROM ads WHERE advertiser_id = ? AND first_seen != last_seen",
            [$advertiserId]
        ) ?? 0);

        // Ad frequency (ads per week over active period)
        $weeksActive = max(1, $activeDays / 7);
        $adFrequency = round($totalAds / $weeksActive, 2);

        // Intelligence score
        $activeAds = (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM ads WHERE advertiser_id = ? AND status = 'active'",
            [$advertiserId]
        );
        $countryCount = count($countries);
        $formatCount = (int) $this->db->fetchColumn(
            "SELECT COUNT(DISTINCT ad_type) FROM ads WHERE advertiser_id = ?",
            [$advertiserId]
        );

        $score = min(30, $activeAds) + min(30, (int) ($adFrequency * 3)) + min(20, $countryCount * 2) + min(20, $formatCount * 7);

        return [
            'advertiser_id'             => $advertiserId,
            'total_lifetime_ads'        => $totalAds,
            'active_duration_days'      => $activeDays,
            'dominant_ad_type'          => $dominantType['ad_type'] ?? null,
            'dominant_cta_style'        => $dominantCta['cta'] ?? null,
            'primary_countries'         => array_column($countries, 'country'),
            'primary_platforms'         => array_column($platforms, 'platform'),
            'avg_campaign_duration_days' => round($avgDuration, 1),
            'ad_frequency_per_week'     => $adFrequency,
            'intelligence_score'        => $score,
        ];
    }

    /**
     * Get profile for display.
     */
    public function getProfile(string $advertiserId): ?array
    {
        $profile = $this->db->fetchOne(
            "SELECT * FROM advertiser_profiles WHERE advertiser_id = ?",
            [$advertiserId]
        );

        if ($profile) {
            $profile['primary_countries'] = json_decode($profile['primary_countries'] ?? '[]', true);
            $profile['primary_platforms'] = json_decode($profile['primary_platforms'] ?? '[]', true);
        }

        return $profile;
    }

    /**
     * Get all profiles ranked by intelligence score.
     */
    public function getAllProfiles(): array
    {
        $profiles = $this->db->fetchAll(
            "SELECT * FROM advertiser_profiles ORDER BY intelligence_score DESC"
        );

        foreach ($profiles as &$p) {
            $p['primary_countries'] = json_decode($p['primary_countries'] ?? '[]', true);
            $p['primary_platforms'] = json_decode($p['primary_platforms'] ?? '[]', true);
        }

        return $profiles;
    }

    private function log(string $message): void
    {
        echo "[" . date('Y-m-d H:i:s') . "] PROFILES: {$message}\n";
    }
}
