<?php

/**
 * CreativeFingerprint - Similarity Detection & A/B Testing
 *
 * Groups similar creatives using text similarity hashing,
 * detects A/B testing patterns, and clusters ad variations.
 */
class CreativeFingerprint
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Process all unfingerprinted ads.
     */
    public function processAll(): int
    {
        $ads = $this->db->fetchAll(
            "SELECT a.creative_id, a.advertiser_id, d.headline, d.description, d.cta
             FROM ads a
             LEFT JOIN ad_details d ON a.creative_id = d.creative_id
                 AND d.id = (SELECT MAX(id) FROM ad_details WHERE creative_id = a.creative_id)
             WHERE a.creative_id NOT IN (SELECT creative_id FROM creative_fingerprints)"
        );

        $processed = 0;
        foreach ($ads as $ad) {
            $this->fingerprint($ad);
            $processed++;
        }

        // Run clustering after fingerprinting
        $this->clusterSimilar();

        $this->log("Fingerprinted {$processed} ads");
        return $processed;
    }

    /**
     * Generate fingerprint for a single ad.
     */
    private function fingerprint(array $ad): void
    {
        $textContent = implode(' ', array_filter([
            $ad['headline'] ?? '',
            $ad['description'] ?? '',
            $ad['cta'] ?? '',
        ]));

        $textFingerprint = $this->generateSimHash($textContent);

        $this->db->insert('creative_fingerprints', [
            'creative_id'      => $ad['creative_id'],
            'text_fingerprint'  => $textFingerprint,
            'image_fingerprint' => null, // Set if image processing available
        ]);
    }

    /**
     * Generate SimHash for text similarity comparison.
     * SimHash produces similar hashes for similar text.
     */
    private function generateSimHash(string $text): string
    {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^\w\s]/', '', $text);
        $words = preg_split('/\s+/', $text);

        if (empty($words)) {
            return str_repeat('0', 64);
        }

        // Generate 64-bit SimHash
        $vector = array_fill(0, 64, 0);

        foreach ($words as $word) {
            if (strlen($word) < 2) continue;

            // Generate shingles (2-grams and 3-grams)
            $shingles = [];
            for ($i = 0; $i <= strlen($word) - 2; $i++) {
                $shingles[] = substr($word, $i, 2);
                if ($i <= strlen($word) - 3) {
                    $shingles[] = substr($word, $i, 3);
                }
            }
            $shingles[] = $word; // Include full word

            foreach ($shingles as $shingle) {
                $hash = md5($shingle); // 128-bit hash
                $bits = $this->hexToBits(substr($hash, 0, 16)); // Use first 64 bits

                for ($i = 0; $i < 64; $i++) {
                    $vector[$i] += ($bits[$i] === '1') ? 1 : -1;
                }
            }
        }

        // Convert vector to binary hash
        $simhash = '';
        foreach ($vector as $val) {
            $simhash .= $val >= 0 ? '1' : '0';
        }

        return hash('sha256', $simhash); // Normalize to fixed-length
    }

    /**
     * Convert hex string to binary string.
     */
    private function hexToBits(string $hex): string
    {
        $bits = '';
        for ($i = 0; $i < strlen($hex); $i++) {
            $bits .= str_pad(base_convert($hex[$i], 16, 2), 4, '0', STR_PAD_LEFT);
        }
        return $bits;
    }

    /**
     * Calculate Hamming distance between two fingerprints.
     */
    public function hammingDistance(string $fp1, string $fp2): int
    {
        // For SHA-256 hashes, compare character-by-character
        $distance = 0;
        $len = min(strlen($fp1), strlen($fp2));

        for ($i = 0; $i < $len; $i++) {
            if ($fp1[$i] !== $fp2[$i]) {
                $distance++;
            }
        }

        return $distance;
    }

    /**
     * Cluster similar ads together.
     */
    public function clusterSimilar(): int
    {
        $advertisers = $this->db->fetchAll(
            "SELECT DISTINCT a.advertiser_id
             FROM creative_fingerprints cf
             INNER JOIN ads a ON cf.creative_id = a.creative_id
             WHERE cf.cluster_id IS NULL"
        );

        $clustered = 0;

        foreach ($advertisers as $row) {
            $clustered += $this->clusterAdvertiserAds($row['advertiser_id']);
        }

        $this->log("Clustered {$clustered} ads");
        return $clustered;
    }

    /**
     * Cluster ads within a single advertiser.
     */
    private function clusterAdvertiserAds(string $advertiserId): int
    {
        $fingerprints = $this->db->fetchAll(
            "SELECT cf.*, a.advertiser_id
             FROM creative_fingerprints cf
             INNER JOIN ads a ON cf.creative_id = a.creative_id
             WHERE a.advertiser_id = ? AND cf.cluster_id IS NULL
             ORDER BY cf.created_at ASC",
            [$advertiserId]
        );

        if (count($fingerprints) < 2) return 0;

        $clustered = 0;
        $assigned = [];

        foreach ($fingerprints as $i => $fp1) {
            if (isset($assigned[$fp1['creative_id']])) continue;

            $similar = [$fp1];

            foreach ($fingerprints as $j => $fp2) {
                if ($i >= $j || isset($assigned[$fp2['creative_id']])) continue;

                $distance = $this->hammingDistance($fp1['text_fingerprint'], $fp2['text_fingerprint']);

                // Similar if distance is within threshold (< 20% different)
                if ($distance < (strlen($fp1['text_fingerprint']) * 0.2)) {
                    $similar[] = $fp2;
                    $assigned[$fp2['creative_id']] = true;
                }
            }

            if (count($similar) >= 2) {
                // Create cluster
                $isAbTest = $this->isLikelyAbTest($similar);
                $clusterId = $this->db->insert('creative_clusters', [
                    'advertiser_id'      => $advertiserId,
                    'cluster_name'       => 'Cluster ' . date('Y-m-d-His'),
                    'member_count'       => count($similar),
                    'is_ab_test'         => $isAbTest ? 1 : 0,
                    'primary_creative_id' => $similar[0]['creative_id'],
                ]);

                foreach ($similar as $s) {
                    $this->db->update('creative_fingerprints', ['cluster_id' => $clusterId], 'creative_id = ?', [$s['creative_id']]);
                    $clustered++;
                }

                $assigned[$fp1['creative_id']] = true;
            }
        }

        return $clustered;
    }

    /**
     * Determine if a cluster likely represents A/B testing.
     */
    private function isLikelyAbTest(array $similar): bool
    {
        if (count($similar) < 2 || count($similar) > 10) return false;

        // Check temporal proximity (launched within 48 hours of each other)
        $creativeIds = array_column($similar, 'creative_id');
        $placeholders = implode(',', array_fill(0, count($creativeIds), '?'));

        $ads = $this->db->fetchAll(
            "SELECT first_seen FROM ads WHERE creative_id IN ({$placeholders}) ORDER BY first_seen ASC",
            $creativeIds
        );

        if (count($ads) < 2) return false;

        $firstDate = new DateTime($ads[0]['first_seen']);
        $lastDate = new DateTime(end($ads)['first_seen']);
        $daysDiff = $firstDate->diff($lastDate)->days;

        return $daysDiff <= 2;
    }

    /**
     * Get clusters for an advertiser.
     */
    public function getClusters(string $advertiserId): array
    {
        $clusters = $this->db->fetchAll(
            "SELECT * FROM creative_clusters WHERE advertiser_id = ? ORDER BY created_at DESC",
            [$advertiserId]
        );

        foreach ($clusters as &$cluster) {
            $cluster['members'] = $this->db->fetchAll(
                "SELECT cf.creative_id, d.headline, d.cta
                 FROM creative_fingerprints cf
                 LEFT JOIN ad_details d ON cf.creative_id = d.creative_id
                     AND d.id = (SELECT MAX(id) FROM ad_details WHERE creative_id = cf.creative_id)
                 WHERE cf.cluster_id = ?",
                [$cluster['id']]
            );
        }

        return $clusters;
    }

    /**
     * Detect A/B tests.
     */
    public function getAbTests(?string $advertiserId = null): array
    {
        $where = $advertiserId ? 'WHERE advertiser_id = ? AND' : 'WHERE';
        $params = $advertiserId ? [$advertiserId] : [];

        return $this->db->fetchAll(
            "SELECT cc.*,
                    (SELECT headline FROM ad_details WHERE creative_id = cc.primary_creative_id ORDER BY id DESC LIMIT 1) as primary_headline
             FROM creative_clusters cc
             {$where} cc.is_ab_test = 1
             ORDER BY cc.created_at DESC
             LIMIT 50",
            $params
        );
    }

    private function log(string $message): void
    {
        echo "[" . date('Y-m-d H:i:s') . "] FINGERPRINT: {$message}\n";
    }
}
