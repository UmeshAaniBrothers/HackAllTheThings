<?php

/**
 * Processor - Raw Data Processing & Normalization
 *
 * Parses raw JSON payloads from the scraper, extracts structured fields,
 * decodes assets, generates hash signatures, and inserts into core tables.
 */
class Processor
{
    private Database $db;
    private AssetManager $assetManager;

    public function __construct(Database $db, AssetManager $assetManager)
    {
        $this->db = $db;
        $this->assetManager = $assetManager;
    }

    /**
     * Process all unprocessed raw payloads.
     * Returns the number of payloads processed.
     */
    public function processAll(): int
    {
        $rows = $this->db->fetchAll(
            "SELECT id, advertiser_id, raw_json FROM raw_payloads WHERE processed_flag = 0 ORDER BY id ASC"
        );

        $processed = 0;

        foreach ($rows as $row) {
            try {
                $this->processPayload($row);
                $this->db->update('raw_payloads', ['processed_flag' => 1], 'id = ?', [$row['id']]);
                $processed++;
            } catch (Exception $e) {
                $this->log("Error processing payload {$row['id']}: " . $e->getMessage());
            }
        }

        $this->log("Processed {$processed} of " . count($rows) . " payloads");
        return $processed;
    }

    /**
     * Process a single raw payload row.
     */
    private function processPayload(array $row): void
    {
        $data = json_decode($row['raw_json'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Invalid JSON in payload ' . $row['id']);
        }

        $ads = $this->parseAdsFromPayload($data, $row['advertiser_id']);

        foreach ($ads as $ad) {
            $this->db->beginTransaction();
            try {
                $this->upsertAd($ad);
                $this->insertDetails($ad);
                $this->insertAssets($ad);
                $this->insertTargeting($ad);
                $this->db->commit();
            } catch (Exception $e) {
                $this->db->rollBack();
                $this->log("Error processing ad {$ad['creative_id']}: " . $e->getMessage());
            }
        }
    }

    /**
     * Parse ads from a raw API payload.
     */
    private function parseAdsFromPayload(array $data, string $fallbackAdvertiserId): array
    {
        $ads = [];
        $creatives = $data['creatives'] ?? $data[1] ?? [];

        if (!is_array($creatives)) {
            return $ads;
        }

        foreach ($creatives as $creative) {
            $headline = $this->extractField($creative, ['headline', 2]);
            $description = $this->extractField($creative, ['description', 3]);
            $cta = $this->extractField($creative, ['callToAction', 4]);

            $ads[] = [
                'creative_id'   => $this->extractField($creative, ['creativeId', 0]),
                'advertiser_id' => $this->extractField($creative, ['advertiserId', 1]) ?? $fallbackAdvertiserId,
                'ad_type'       => $this->determineAdType($creative),
                'headline'      => $headline,
                'description'   => $description,
                'cta'           => $cta,
                'landing_url'   => $this->extractField($creative, ['landingPageUrl', 5]),
                'first_seen'    => $this->parseTimestamp($this->extractField($creative, ['firstShown', 8])),
                'last_seen'     => $this->parseTimestamp($this->extractField($creative, ['lastShown', 9])),
                'hash_signature' => $this->generateHash($headline, $description, $cta),
                'assets'        => $this->extractAssets($creative),
                'countries'     => $this->extractArray($creative, ['countries', 'targetedCountries', 10]),
                'platforms'     => $this->extractArray($creative, ['platforms', 'adPlatforms', 11]),
            ];
        }

        return array_filter($ads, function($ad) { return $ad['creative_id'] !== null; });
    }

    /**
     * Insert or update an ad in the ads table.
     */
    private function upsertAd(array $ad): void
    {
        $existing = $this->db->fetchOne(
            "SELECT creative_id, hash_signature FROM ads WHERE creative_id = ?",
            [$ad['creative_id']]
        );

        $now = date('Y-m-d H:i:s');

        if ($existing === null) {
            $this->db->insert('ads', [
                'creative_id'    => $ad['creative_id'],
                'advertiser_id'  => $ad['advertiser_id'],
                'ad_type'        => $ad['ad_type'],
                'first_seen'     => $ad['first_seen'] ?? $now,
                'last_seen'      => $ad['last_seen'] ?? $now,
                'status'         => 'active',
                'hash_signature' => $ad['hash_signature'],
            ]);
        } else {
            $this->db->update('ads', [
                'last_seen'      => $ad['last_seen'] ?? $now,
                'status'         => 'active',
                'hash_signature' => $ad['hash_signature'],
            ], 'creative_id = ?', [$ad['creative_id']]);
        }
    }

    /**
     * Insert ad content details (versioned snapshot).
     */
    private function insertDetails(array $ad): void
    {
        // Check if content has changed by comparing hash
        $latestDetail = $this->db->fetchOne(
            "SELECT id FROM ad_details WHERE creative_id = ? ORDER BY snapshot_date DESC LIMIT 1",
            [$ad['creative_id']]
        );

        $existingHash = $this->db->fetchColumn(
            "SELECT hash_signature FROM ads WHERE creative_id = ?",
            [$ad['creative_id']]
        );

        // Only insert new detail if hash changed or first entry
        if ($latestDetail === null || $existingHash !== $ad['hash_signature']) {
            $this->db->insert('ad_details', [
                'creative_id' => $ad['creative_id'],
                'headline'    => $ad['headline'],
                'description' => $ad['description'],
                'cta'         => $ad['cta'],
                'landing_url' => $ad['landing_url'],
            ]);
        }
    }

    /**
     * Insert ad assets (images, videos).
     */
    private function insertAssets(array $ad): void
    {
        if (empty($ad['assets'])) {
            return;
        }

        foreach ($ad['assets'] as $asset) {
            $url = $asset['url'] ?? null;
            if ($url === null) {
                continue;
            }

            // Check for duplicate
            $exists = $this->db->fetchOne(
                "SELECT id FROM ad_assets WHERE creative_id = ? AND original_url = ?",
                [$ad['creative_id'], $url]
            );

            if ($exists !== null) {
                continue;
            }

            $type = $asset['type'] ?? 'image';
            $localPath = null;

            // Handle base64-encoded assets
            if (isset($asset['base64'])) {
                $localPath = $this->assetManager->saveBase64Asset(
                    $asset['base64'],
                    $ad['creative_id'],
                    $type
                );
            } elseif ($url) {
                $localPath = $this->assetManager->downloadAsset($url, $ad['creative_id'], $type);
            }

            $this->db->insert('ad_assets', [
                'creative_id'  => $ad['creative_id'],
                'type'         => in_array($type, ['image', 'video', 'text']) ? $type : 'image',
                'original_url' => $url,
                'local_path'   => $localPath,
            ]);
        }
    }

    /**
     * Insert targeting data (country + platform).
     */
    private function insertTargeting(array $ad): void
    {
        $countries = $ad['countries'] ?? [];
        $platforms = $ad['platforms'] ?? [];

        foreach ($countries as $country) {
            if (!is_string($country) || empty($country)) {
                continue;
            }

            foreach ($platforms ?: ['unknown'] as $platform) {
                $exists = $this->db->fetchOne(
                    "SELECT id FROM ad_targeting WHERE creative_id = ? AND country = ? AND platform = ?",
                    [$ad['creative_id'], $country, $platform]
                );

                if ($exists === null) {
                    $this->db->insert('ad_targeting', [
                        'creative_id' => $ad['creative_id'],
                        'country'     => $country,
                        'platform'    => is_string($platform) ? $platform : 'unknown',
                    ]);
                }
            }
        }
    }

    /**
     * Generate SHA-256 hash from ad content for change detection.
     */
    public function generateHash(?string $headline, ?string $description, ?string $cta): string
    {
        $content = implode('|', [
            trim($headline ?? ''),
            trim($description ?? ''),
            trim($cta ?? ''),
        ]);
        return hash('sha256', $content);
    }

    /**
     * Extract a field value trying multiple possible keys.
     */
    private function extractField(array $data, array $keys)
    {
        foreach ($keys as $key) {
            if (isset($data[$key])) {
                return $data[$key];
            }
        }
        return null;
    }

    /**
     * Extract an array field trying multiple possible keys.
     */
    private function extractArray(array $data, array $keys): array
    {
        foreach ($keys as $key) {
            if (isset($data[$key]) && is_array($data[$key])) {
                return $data[$key];
            }
        }
        return [];
    }

    /**
     * Determine ad type from creative data.
     */
    private function determineAdType(array $creative): string
    {
        $type = $creative['type'] ?? $creative['adType'] ?? null;
        if (is_string($type)) {
            $type = strtolower($type);
            if (in_array($type, ['text', 'image', 'video'])) {
                return $type;
            }
        }

        $assets = $creative['assets'] ?? $creative['mediaAssets'] ?? [];
        if (is_array($assets)) {
            foreach ($assets as $asset) {
                $t = $asset['type'] ?? '';
                if (stripos($t, 'video') !== false) return 'video';
                if (stripos($t, 'image') !== false) return 'image';
            }
        }

        return 'text';
    }

    /**
     * Extract assets from creative data structure.
     */
    private function extractAssets(array $creative): array
    {
        $assets = [];
        $rawAssets = $creative['assets'] ?? $creative['mediaAssets'] ?? $creative[6] ?? [];

        if (!is_array($rawAssets)) {
            return $assets;
        }

        foreach ($rawAssets as $asset) {
            $entry = [
                'type' => $asset['type'] ?? 'image',
                'url'  => $asset['url'] ?? $asset['imageUrl'] ?? $asset['videoUrl'] ?? null,
            ];

            // Handle base64 encoded assets
            if (isset($asset['base64']) || isset($asset['encodedData'])) {
                $entry['base64'] = $asset['base64'] ?? $asset['encodedData'];
            }

            $assets[] = $entry;
        }

        return $assets;
    }

    /**
     * Parse various timestamp formats into MySQL datetime.
     */
    private function parseTimestamp($value): ?string
    {
        if ($value === null) {
            return null;
        }

        // Unix timestamp (seconds or milliseconds)
        if (is_numeric($value)) {
            $ts = (int) $value;
            if ($ts > 1e12) {
                $ts = (int) ($ts / 1000); // milliseconds to seconds
            }
            return date('Y-m-d H:i:s', $ts);
        }

        // ISO 8601 or other string formats
        if (is_string($value)) {
            $time = strtotime($value);
            if ($time !== false) {
                return date('Y-m-d H:i:s', $time);
            }
        }

        return null;
    }

    /**
     * Log a message with timestamp.
     */
    private function log(string $message): void
    {
        $timestamp = date('Y-m-d H:i:s');
        echo "[{$timestamp}] PROCESSOR: {$message}\n";
    }
}
