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
     * Supports both old named-key format AND new protobuf numeric-key format:
     *   "1"[n]."1" = advertiser_id
     *   "1"[n]."2" = creative_id
     *   "1"[n]."3" = content (nested: 3.1.4=preview URL, 3.3.2=image HTML)
     *   "1"[n]."4" = format (1=text, 2=image, 3=video)
     *   "1"[n]."6"."1" = first shown timestamp
     *   "1"[n]."7"."1" = last shown timestamp
     *   "1"[n]."12" = advertiser name
     *   "1"[n]."13" = platform indicator
     */
    private function parseAdsFromPayload(array $data, string $fallbackAdvertiserId): array
    {
        $ads = [];
        $creatives = $data['creatives'] ?? $data['1'] ?? $data[1] ?? [];

        if (!is_array($creatives)) {
            return $ads;
        }

        foreach ($creatives as $creative) {
            if (!is_array($creative)) continue;

            // Detect format: if key "2" is a string that starts with "CR", it's the new protobuf format
            $isProtobuf = false;
            $key2 = $creative['2'] ?? $creative[2] ?? null;
            if (is_string($key2) && strpos($key2, 'CR') === 0) {
                $isProtobuf = true;
            }
            // Also detect by key "1" starting with "AR"
            $key1 = $creative['1'] ?? $creative[1] ?? null;
            if (is_string($key1) && strpos($key1, 'AR') === 0) {
                $isProtobuf = true;
            }

            if ($isProtobuf) {
                $ad = $this->parseProtobufCreative($creative, $fallbackAdvertiserId);
            } else {
                $ad = $this->parseLegacyCreative($creative, $fallbackAdvertiserId);
            }

            if ($ad && !empty($ad['creative_id'])) {
                $ads[] = $ad;
            }
        }

        return $ads;
    }

    /**
     * Parse a creative in the new protobuf numeric-key format.
     */
    private function parseProtobufCreative(array $c, string $fallbackAdvertiserId): array
    {
        $advertiserId = $this->extractStringVal($c, ['1']);
        $creativeId = $this->extractStringVal($c, ['2']);
        $advertiserName = $this->extractStringVal($c, ['12']);

        // Format: 1=text, 2=image, 3=video
        $format = $c['4'] ?? $c[4] ?? null;
        $typeMap = [1 => 'text', 2 => 'image', 3 => 'video'];
        $adType = 'text';
        if (is_numeric($format) && isset($typeMap[(int)$format])) {
            $adType = $typeMap[(int)$format];
        }

        // Timestamps
        $firstSeen = null;
        $lastSeen = null;
        $f6 = $c['6'] ?? $c[6] ?? null;
        $f7 = $c['7'] ?? $c[7] ?? null;
        if (is_array($f6)) $firstSeen = $f6['1'] ?? $f6[1] ?? null;
        elseif (is_scalar($f6)) $firstSeen = $f6;
        if (is_array($f7)) $lastSeen = $f7['1'] ?? $f7[1] ?? null;
        elseif (is_scalar($f7)) $lastSeen = $f7;

        $firstSeen = $this->parseTimestamp($firstSeen);
        $lastSeen = $this->parseTimestamp($lastSeen);

        // Content from field 3
        $content = $c['3'] ?? $c[3] ?? [];
        $headline = null;
        $previewUrl = null;
        $imageUrl = null;

        if (is_array($content)) {
            $f31 = $content['1'] ?? $content[1] ?? null;
            if (is_array($f31)) {
                $previewUrl = $this->extractStringVal($f31, ['4']);
                $headline = $this->extractStringVal($f31, ['1', '2', '3']);
            }
            $f33 = $content['3'] ?? $content[3] ?? null;
            if (is_array($f33)) {
                $imgHtml = $f33['2'] ?? $f33[2] ?? null;
                if (is_string($imgHtml) && preg_match('/src=["\']([^"\']+)/', $imgHtml, $m)) {
                    $imageUrl = $m[1];
                }
            }
        }

        // Build assets
        $assets = [];
        if ($previewUrl) $assets[] = ['type' => 'preview', 'url' => $previewUrl];
        if ($imageUrl) $assets[] = ['type' => 'image', 'url' => $imageUrl];

        // Platform
        $platformId = $c['13'] ?? $c[13] ?? null;
        $platforms = [];
        if (is_numeric($platformId)) {
            $pMap = [1 => 'Google Search', 2 => 'YouTube', 3 => 'Google Display', 4 => 'Google Shopping', 5 => 'Google Maps', 6 => 'Google Play'];
            $platforms[] = $pMap[(int)$platformId] ?? 'Platform_' . $platformId;
        }

        $hashHeadline = is_string($headline) ? $headline : '';
        $hashSig = $this->generateHash($hashHeadline, '', '');

        return [
            'creative_id'    => $creativeId,
            'advertiser_id'  => $advertiserId ?: $fallbackAdvertiserId,
            'ad_type'        => $adType,
            'headline'       => is_string($headline) ? $headline : null,
            'description'    => null,
            'cta'            => null,
            'landing_url'    => $previewUrl,
            'first_seen'     => $firstSeen,
            'last_seen'      => $lastSeen,
            'hash_signature' => $hashSig,
            'assets'         => $assets,
            'countries'      => [],
            'platforms'      => $platforms,
        ];
    }

    /**
     * Parse a creative in the old named-key format (backward compatible).
     */
    private function parseLegacyCreative(array $creative, string $fallbackAdvertiserId): array
    {
        $headline = $this->extractStringVal($creative, ['headline']);
        $description = $this->extractStringVal($creative, ['description']);
        $cta = $this->extractStringVal($creative, ['callToAction']);

        return [
            'creative_id'   => $this->extractStringVal($creative, ['creativeId']),
            'advertiser_id' => $this->extractStringVal($creative, ['advertiserId']) ?: $fallbackAdvertiserId,
            'ad_type'       => $this->determineAdType($creative),
            'headline'      => $headline,
            'description'   => $description,
            'cta'           => $cta,
            'landing_url'   => $this->extractStringVal($creative, ['landingPageUrl']),
            'first_seen'    => $this->parseTimestamp($this->extractField($creative, ['firstShown'])),
            'last_seen'     => $this->parseTimestamp($this->extractField($creative, ['lastShown'])),
            'hash_signature' => $this->generateHash($headline ?: '', $description ?: '', $cta ?: ''),
            'assets'        => $this->extractAssets($creative),
            'countries'     => $this->extractArray($creative, ['countries', 'targetedCountries']),
            'platforms'     => $this->extractArray($creative, ['platforms', 'adPlatforms']),
        ];
    }

    /**
     * Extract a string value from array, skipping arrays/objects.
     */
    private function extractStringVal(array $data, array $keys)
    {
        foreach ($keys as $key) {
            $val = $data[$key] ?? $data[(string)$key] ?? null;
            if (is_string($val) && $val !== '') return $val;
            if (is_numeric($val)) return (string)$val;
            if (is_array($val)) {
                // Try nested first value
                $nested = $val['1'] ?? $val[1] ?? $val[0] ?? null;
                if (is_string($nested) && $nested !== '') return $nested;
            }
        }
        return null;
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

            $validTypes = ['image', 'video', 'text', 'preview'];
            $this->db->insert('ad_assets', [
                'creative_id'  => $ad['creative_id'],
                'type'         => in_array($type, $validTypes) ? $type : 'image',
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
    public function generateHash($headline = '', $description = '', $cta = '')
    {
        // Ensure all values are strings
        if (!is_string($headline)) $headline = is_scalar($headline) ? (string)$headline : '';
        if (!is_string($description)) $description = is_scalar($description) ? (string)$description : '';
        if (!is_string($cta)) $cta = is_scalar($cta) ? (string)$cta : '';

        $content = implode('|', [trim($headline), trim($description), trim($cta)]);
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

    /**
     * Extract YouTube URLs from Google preview content.js for all video ads
     * that don't have a YouTube URL yet.
     * Returns the number of YouTube URLs extracted.
     */
    public function extractYouTubeUrls()
    {
        $ads = $this->db->fetchAll(
            "SELECT a.creative_id, ass.original_url as preview_url
             FROM ads a
             INNER JOIN ad_assets ass ON a.creative_id = ass.creative_id
                AND (ass.type = 'preview' OR (ass.type = 'image' AND ass.original_url LIKE '%displayads-formats%'))
             WHERE a.ad_type = 'video'
               AND NOT EXISTS (
                   SELECT 1 FROM ad_assets v
                   WHERE v.creative_id = a.creative_id
                     AND v.type = 'video'
                     AND v.original_url LIKE '%youtube.com%'
               )
             GROUP BY a.creative_id
             ORDER BY a.last_seen DESC"
        );

        if (empty($ads)) {
            return 0;
        }

        $extracted = 0;

        foreach ($ads as $ad) {
            $previewUrl = $ad['preview_url'];
            if (empty($previewUrl)) continue;

            $ytId = $this->fetchYouTubeIdFromPreview($previewUrl);

            if ($ytId) {
                $youtubeUrl = 'https://www.youtube.com/watch?v=' . $ytId;
                $thumbnail = 'https://i.ytimg.com/vi/' . $ytId . '/hqdefault.jpg';

                $exists = $this->db->fetchOne(
                    "SELECT id FROM ad_assets WHERE creative_id = ? AND type = 'video' AND original_url = ?",
                    [$ad['creative_id'], $youtubeUrl]
                );
                if (!$exists) {
                    $this->db->insert('ad_assets', [
                        'creative_id'  => $ad['creative_id'],
                        'type'         => 'video',
                        'original_url' => $youtubeUrl,
                        'local_path'   => null,
                    ]);
                }

                $exists2 = $this->db->fetchOne(
                    "SELECT id FROM ad_assets WHERE creative_id = ? AND type = 'image' AND original_url = ?",
                    [$ad['creative_id'], $thumbnail]
                );
                if (!$exists2) {
                    $this->db->insert('ad_assets', [
                        'creative_id'  => $ad['creative_id'],
                        'type'         => 'image',
                        'original_url' => $thumbnail,
                        'local_path'   => null,
                    ]);
                }

                $extracted++;
            }

            usleep(300000); // 300ms rate limit
        }

        $this->log("Extracted {$extracted} YouTube URLs from " . count($ads) . " video ads");
        return $extracted;
    }

    /**
     * Fetch a Google preview content.js URL and extract YouTube video ID.
     */
    private function fetchYouTubeIdFromPreview($previewUrl)
    {
        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL            => $previewUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
            CURLOPT_HTTPHEADER     => array(
                'Referer: https://adstransparency.google.com/',
                'Accept: */*',
            ),
            CURLOPT_ENCODING       => 'gzip, deflate',
        ));

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $httpCode !== 200 || strlen($response) < 100) {
            return null;
        }

        if (preg_match('/ytimg\.com\/vi\/([a-zA-Z0-9_-]{11})\//', $response, $matches)) {
            return $matches[1];
        }

        if (preg_match('/youtube\.com\/(?:embed\/|watch\?v=)([a-zA-Z0-9_-]{11})/', $response, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
