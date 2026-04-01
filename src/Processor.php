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
    public function processAll(int $limit = 0): int
    {
        $sql = "SELECT id, advertiser_id, raw_json FROM raw_payloads WHERE processed_flag = 0 ORDER BY id ASC";
        if ($limit > 0) {
            $sql .= " LIMIT {$limit}";
        }
        $rows = $this->db->fetchAll($sql);

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

        // Auto-save advertiser name to managed_advertisers if we have one
        if ($advertiserName && $advertiserId) {
            $this->autoSaveAdvertiserName($advertiserId, $advertiserName);
        }

        // Format: 1=text, 2=image, 3=video
        $format = $c['4'] ?? $c[4] ?? null;
        $typeMap = [1 => 'text', 2 => 'image', 3 => 'video'];
        $adType = 'text';
        if (is_numeric($format) && isset($typeMap[(int)$format])) {
            $adType = $typeMap[(int)$format];
        }

        // Timestamps — try multiple nested positions
        $firstSeen = $this->extractTimestampFromField($c['6'] ?? $c[6] ?? null);
        $lastSeen = $this->extractTimestampFromField($c['7'] ?? $c[7] ?? null);

        // ── Content from field 3 (DEEP extraction) ──
        $content = $c['3'] ?? $c[3] ?? [];
        $headline = null;
        $description = null;
        $cta = null;
        $previewUrl = null;
        $imageUrl = null;
        $allTextStrings = []; // collect all readable text from field 3

        if (is_array($content)) {
            // Field 3.1 — primary content block
            $f31 = $content['1'] ?? $content[1] ?? null;
            if (is_array($f31)) {
                $previewUrl = $this->extractStringVal($f31, ['4']);
                // Try all subkeys for text content
                foreach (['1', '2', '3', '5', '6', '7', '8'] as $k) {
                    $val = $f31[$k] ?? $f31[(int)$k] ?? null;
                    if (is_string($val) && strlen($val) >= 3 && !preg_match('/^https?:/', $val) && !preg_match('/^[A-Z]{2}$/', $val)) {
                        $allTextStrings[] = $val;
                    } elseif (is_array($val)) {
                        $this->collectAllStrings($val, $allTextStrings, 3);
                    }
                }
            } elseif (is_string($f31) && strlen($f31) >= 3) {
                $allTextStrings[] = $f31;
            }

            // Field 3.2 — structured text content (often has headline/desc/CTA)
            $f32 = $content['2'] ?? $content[2] ?? null;
            if (is_array($f32)) {
                $this->collectAllStrings($f32, $allTextStrings, 3);
            } elseif (is_string($f32) && strlen($f32) >= 3) {
                $allTextStrings[] = $f32;
            }

            // Field 3.3 — image/HTML content
            $f33 = $content['3'] ?? $content[3] ?? null;
            if (is_array($f33)) {
                $imgHtml = $f33['2'] ?? $f33[2] ?? null;
                if (is_string($imgHtml) && preg_match('/src=["\']([^"\']+)/', $imgHtml, $m)) {
                    $imageUrl = $m[1];
                }
                // Also collect text from field 3.3
                $this->collectAllStrings($f33, $allTextStrings, 3);
            }

            // Field 3.4, 3.5+ — additional content blocks
            foreach (['4', '5', '6', '7', '8'] as $ck) {
                $fExtra = $content[$ck] ?? $content[(int)$ck] ?? null;
                if (is_array($fExtra)) {
                    $this->collectAllStrings($fExtra, $allTextStrings, 3);
                } elseif (is_string($fExtra) && strlen($fExtra) >= 3) {
                    $allTextStrings[] = $fExtra;
                }
            }
        }

        // Assign headline/description/cta from collected text
        // Filter out URLs, short codes, and obvious non-text
        $cleanTexts = [];
        foreach ($allTextStrings as $t) {
            $t = trim($t);
            if (strlen($t) < 3) continue;
            if (preg_match('/^https?:\/\//', $t)) continue;
            if (preg_match('/https?:\/\//', $t)) continue; // any URL anywhere in string
            if (preg_match('/googlesyndication|googleusercontent|doubleclick|googleapis|gstatic|tpc\.google/i', $t)) continue; // Google ad infra URLs
            if (preg_match('/\.(jpg|jpeg|png|gif|webp|svg|mp4|mp3|css|js|woff|ttf)(\?|$)/i', $t)) continue; // file extensions
            if (preg_match('/^[A-Z]{2}$/', $t)) continue; // country codes
            if (preg_match('/^(AR|CR)\d/', $t)) continue; // advertiser/creative IDs
            if (preg_match('/^\d+$/', $t)) continue; // pure numbers
            if (preg_match('/^[a-f0-9]{32,}$/i', $t)) continue; // hashes
            if (preg_match('/^[\d\/]+$/', $t)) continue; // date-like or path-like numbers
            if (preg_match('/Cannot find|global object|Error\(|undefined|function\s*\(|var |const |let |return |throw /i', $t)) continue; // JS code/errors
            $cleanTexts[] = $t;
        }
        $cleanTexts = array_values(array_unique($cleanTexts));

        if (!empty($cleanTexts)) {
            $headline = $cleanTexts[0];
            if (isset($cleanTexts[1])) $description = $cleanTexts[1];
            if (isset($cleanTexts[2])) {
                // If third text is short (< 30 chars), it's likely a CTA
                if (strlen($cleanTexts[2]) <= 30) {
                    $cta = $cleanTexts[2];
                } else {
                    // Append to description
                    $description = ($description ? $description . ' ' : '') . $cleanTexts[2];
                }
            }
        }

        // Build assets
        $assets = [];
        if ($previewUrl) $assets[] = ['type' => 'preview', 'url' => $previewUrl];
        if ($imageUrl) $assets[] = ['type' => 'image', 'url' => $imageUrl];

        // Platform — try field 13, also check if it's an array
        $platformId = $c['13'] ?? $c[13] ?? null;
        $platforms = [];
        if (is_numeric($platformId)) {
            $pMap = [1 => 'Google Search', 2 => 'YouTube', 3 => 'Google Display', 4 => 'Google Shopping', 5 => 'Google Maps', 6 => 'Google Play'];
            $platforms[] = $pMap[(int)$platformId] ?? 'Platform_' . $platformId;
        } elseif (is_array($platformId)) {
            // Could be an array of platform IDs
            $pMap = [1 => 'Google Search', 2 => 'YouTube', 3 => 'Google Display', 4 => 'Google Shopping', 5 => 'Google Maps', 6 => 'Google Play'];
            foreach ($platformId as $pid) {
                if (is_numeric($pid)) {
                    $platforms[] = $pMap[(int)$pid] ?? 'Platform_' . $pid;
                }
            }
        }

        // Country/region targeting — DEEP extraction from fields 8, 9, 10, 11
        $countries = $this->extractCountries($c);

        // Field 5 — device/audience targeting (discover and log)
        $field5 = $c['5'] ?? $c[5] ?? null;
        $extraMeta = [];
        if ($field5 !== null) {
            $extraMeta['field_5'] = $field5;
        }

        // Discover unknown fields (14+) — log first time only
        $knownFields = ['1','2','3','4','5','6','7','8','9','10','11','12','13'];
        foreach ($c as $key => $val) {
            $skey = (string)$key;
            if (!in_array($skey, $knownFields) && $val !== null) {
                $extraMeta['field_' . $skey] = is_scalar($val) ? $val : '(complex)';
            }
        }
        if (!empty($extraMeta)) {
            $this->logDiscoveredFields($creativeId, $extraMeta);
        }

        $hashHeadline = is_string($headline) ? $headline : '';
        $hashDesc = is_string($description) ? $description : '';
        $hashCta = is_string($cta) ? $cta : '';
        $hashSig = $this->generateHash($hashHeadline, $hashDesc, $hashCta);

        return [
            'creative_id'     => $creativeId,
            'advertiser_id'   => $advertiserId ?: $fallbackAdvertiserId,
            'advertiser_name' => $advertiserName,
            'ad_type'         => $adType,
            'headline'        => is_string($headline) ? $headline : null,
            'description'     => is_string($description) ? $description : null,
            'cta'             => is_string($cta) ? $cta : null,
            'landing_url'     => $previewUrl,
            'first_seen'      => $firstSeen,
            'last_seen'       => $lastSeen,
            'hash_signature'  => $hashSig,
            'assets'          => $assets,
            'countries'       => $countries,
            'platforms'       => $platforms,
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
     * Only creates a new version when content actually changes (hash differs).
     */
    private function insertDetails(array $ad): void
    {
        // Fetch the PREVIOUS hash from the ads table BEFORE upsertAd updates it.
        // Since upsertAd runs first and updates hash_signature, we need to compare
        // the new hash against the latest ad_details content instead.
        $latestDetail = $this->db->fetchOne(
            "SELECT id, headline, description, cta FROM ad_details WHERE creative_id = ? ORDER BY snapshot_date DESC LIMIT 1",
            [$ad['creative_id']]
        );

        if ($latestDetail === null) {
            // First entry — always insert
            $this->db->insert('ad_details', [
                'creative_id'     => $ad['creative_id'],
                'headline'        => $ad['headline'],
                'description'     => $ad['description'],
                'cta'             => $ad['cta'],
                'landing_url'     => $ad['landing_url'],
                'headline_source' => !empty($ad['headline']) ? 'ad' : null,
            ]);
            return;
        }

        // Compare actual content to detect real changes
        $oldHash = $this->generateHash(
            $latestDetail['headline'] ?? '',
            $latestDetail['description'] ?? '',
            $latestDetail['cta'] ?? ''
        );

        if ($oldHash !== $ad['hash_signature']) {
            // Content changed — insert new version
            $this->db->insert('ad_details', [
                'creative_id'     => $ad['creative_id'],
                'headline'        => $ad['headline'],
                'description'     => $ad['description'],
                'cta'             => $ad['cta'],
                'landing_url'     => $ad['landing_url'],
                'headline_source' => !empty($ad['headline']) ? 'ad' : null,
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

            // Skip downloading preview URLs (Google JS files, not useful locally)
            // and displayads-formats URLs — YouTube extraction handles video content
            $isPreviewUrl = strpos($url, 'displayads-formats') !== false;

            if ($type === 'preview' || $isPreviewUrl) {
                // Store URL reference only, don't download
                $localPath = null;
            } elseif (isset($asset['base64'])) {
                $localPath = $this->assetManager->saveBase64Asset(
                    $asset['base64'],
                    $ad['creative_id'],
                    $type
                );
            } elseif ($url) {
                $localPath = $this->assetManager->downloadAsset($url, $ad['creative_id'], $type);
            }

            // DB column is ENUM('image','video','text') — map 'preview' to 'image'
            $dbType = $type;
            if ($dbType === 'preview') $dbType = 'image';
            if (!in_array($dbType, ['image', 'video', 'text'])) $dbType = 'image';

            // Extract YouTube video_id for fast lookups
            $videoId = null;
            if ($dbType === 'video' && $url) {
                if (preg_match('/[?&]v=([\w-]{11})/', $url, $m)) {
                    $videoId = $m[1];
                } elseif (preg_match('/youtu\.be\/([\w-]{11})/', $url, $m)) {
                    $videoId = $m[1];
                }
            }

            $insertData = [
                'creative_id'  => $ad['creative_id'],
                'type'         => $dbType,
                'original_url' => $url,
                'local_path'   => $localPath,
            ];
            // Only include video_id if column exists (added by migration)
            if ($videoId !== null) {
                $insertData['video_id'] = $videoId;
            }

            $this->db->insert('ad_assets', $insertData);
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
     * Extract country codes from protobuf fields 8, 9, 10, 11.
     *
     * Google Ads Transparency encodes geographic targeting in these fields.
     * Countries appear as 2-letter ISO codes (e.g. "US", "IN", "GB")
     * nested at various depths in the protobuf structure.
     *
     * Known structures:
     *   Field 8: Often contains [{1: "US"}, {1: "CA"}] or {"1": {"1": "US"}}
     *   Field 9: May contain region-level targeting
     *   Field 10: May contain [{1: 2356}] (numeric geo IDs from Google Ads API)
     *   Field 11: Additional geo constraints
     *
     * We also map Google Ads geographic criterion IDs to country codes.
     */
    private function extractCountries(array $c): array
    {
        $countries = [];

        // Fields 8-11 may contain country/region targeting
        foreach (['8', '9', '10', '11'] as $fieldKey) {
            $field = $c[$fieldKey] ?? $c[(int)$fieldKey] ?? null;
            if ($field === null) continue;

            $this->collectCountryCodes($field, $countries);
        }

        // Also check field 3 content for country context (some ads embed region in content)
        // And check if any collected numeric IDs can be mapped
        $numericIds = [];
        foreach (['8', '9', '10', '11'] as $fieldKey) {
            $field = $c[$fieldKey] ?? $c[(int)$fieldKey] ?? null;
            if ($field === null) continue;
            $this->collectNumericGeoIds($field, $numericIds);
        }

        // Map Google Ads geo criterion IDs to country codes
        foreach ($numericIds as $geoId) {
            $mapped = $this->mapGeoIdToCountry($geoId);
            if ($mapped) {
                $countries[] = $mapped;
            }
        }

        return array_values(array_unique($countries));
    }

    /**
     * Recursively collect 2-letter country codes from a protobuf field value.
     * Also handles 3-letter region codes and longer location strings.
     */
    private function collectCountryCodes($value, array &$countries, int $depth = 0): void
    {
        if ($depth > 8) return; // deeper recursion for complex nesting

        if (is_string($value)) {
            // Exact 2-letter uppercase = country code
            if (preg_match('/^[A-Z]{2}$/', $value)) {
                $countries[] = $value;
                return;
            }
            // Some responses have lowercase country codes
            if (preg_match('/^[a-z]{2}$/', $value)) {
                $countries[] = strtoupper($value);
                return;
            }
            // Region codes like "US-CA" — extract the country part
            if (preg_match('/^([A-Z]{2})-[A-Z0-9]{1,3}$/', $value, $m)) {
                $countries[] = $m[1];
                return;
            }
            // Full country names — map common ones
            $nameMap = $this->getCountryNameMap();
            $lower = strtolower(trim($value));
            if (isset($nameMap[$lower])) {
                $countries[] = $nameMap[$lower];
                return;
            }
        }

        if (is_array($value)) {
            foreach ($value as $item) {
                $this->collectCountryCodes($item, $countries, $depth + 1);
            }
        }
    }

    /**
     * Collect numeric geographic IDs from protobuf fields.
     * Google Ads uses criterion IDs (e.g., 2356 = India, 2840 = United States).
     */
    private function collectNumericGeoIds($value, array &$ids, int $depth = 0): void
    {
        if ($depth > 8) return;

        if (is_numeric($value)) {
            $num = (int)$value;
            // Google geo criterion IDs are typically in range 1000-9999 for countries
            if ($num >= 1000 && $num <= 9999) {
                $ids[] = $num;
            }
        }

        if (is_array($value)) {
            foreach ($value as $item) {
                $this->collectNumericGeoIds($item, $ids, $depth + 1);
            }
        }
    }

    /**
     * Map Google Ads geographic criterion ID to ISO country code.
     * These are the official Google Ads geo target constants for countries.
     */
    private function mapGeoIdToCountry(int $id): ?string
    {
        $map = [
            2004 => 'AF', 2008 => 'AL', 2012 => 'DZ', 2020 => 'AD', 2024 => 'AO',
            2032 => 'AR', 2036 => 'AU', 2040 => 'AT', 2050 => 'BD', 2056 => 'BE',
            2076 => 'BR', 2100 => 'BG', 2116 => 'KH', 2120 => 'CM', 2124 => 'CA',
            2152 => 'CL', 2156 => 'CN', 2170 => 'CO', 2188 => 'CR', 2191 => 'HR',
            2196 => 'CY', 2203 => 'CZ', 2208 => 'DK', 2218 => 'EC', 2818 => 'EG',
            2233 => 'EE', 2231 => 'ET', 2246 => 'FI', 2250 => 'FR', 2268 => 'GE',
            2276 => 'DE', 2288 => 'GH', 2300 => 'GR', 2320 => 'GT', 2344 => 'HK',
            2348 => 'HU', 2352 => 'IS', 2356 => 'IN', 2360 => 'ID', 2364 => 'IR',
            2368 => 'IQ', 2372 => 'IE', 2376 => 'IL', 2380 => 'IT', 2392 => 'JP',
            2400 => 'JO', 2398 => 'KZ', 2404 => 'KE', 2410 => 'KR', 2414 => 'KW',
            2422 => 'LB', 2434 => 'LY', 2440 => 'LT', 2442 => 'LU', 2458 => 'MY',
            2484 => 'MX', 2504 => 'MA', 2508 => 'MZ', 2524 => 'NP', 2528 => 'NL',
            2554 => 'NZ', 2566 => 'NG', 2578 => 'NO', 2586 => 'PK', 2604 => 'PE',
            2608 => 'PH', 2616 => 'PL', 2620 => 'PT', 2634 => 'QA', 2642 => 'RO',
            2643 => 'RU', 2682 => 'SA', 2688 => 'RS', 2702 => 'SG', 2703 => 'SK',
            2710 => 'ZA', 2724 => 'ES', 2144 => 'LK', 2752 => 'SE', 2756 => 'CH',
            2158 => 'TW', 2764 => 'TH', 2792 => 'TR', 2804 => 'UA', 2784 => 'AE',
            2826 => 'GB', 2840 => 'US', 2858 => 'UY', 2860 => 'UZ', 2704 => 'VN',
        ];
        return $map[$id] ?? null;
    }

    /**
     * Map of common country name strings to ISO codes.
     */
    private function getCountryNameMap(): array
    {
        return [
            'india' => 'IN', 'united states' => 'US', 'united kingdom' => 'GB',
            'canada' => 'CA', 'australia' => 'AU', 'germany' => 'DE', 'france' => 'FR',
            'japan' => 'JP', 'brazil' => 'BR', 'spain' => 'ES', 'italy' => 'IT',
            'mexico' => 'MX', 'indonesia' => 'ID', 'turkey' => 'TR', 'south korea' => 'KR',
            'russia' => 'RU', 'netherlands' => 'NL', 'saudi arabia' => 'SA',
            'united arab emirates' => 'AE', 'pakistan' => 'PK', 'bangladesh' => 'BD',
            'nigeria' => 'NG', 'philippines' => 'PH', 'vietnam' => 'VN', 'thailand' => 'TH',
            'egypt' => 'EG', 'malaysia' => 'MY', 'singapore' => 'SG', 'south africa' => 'ZA',
            'colombia' => 'CO', 'argentina' => 'AR', 'poland' => 'PL', 'israel' => 'IL',
            'new zealand' => 'NZ', 'ireland' => 'IE', 'sweden' => 'SE', 'switzerland' => 'CH',
            'norway' => 'NO', 'denmark' => 'DK', 'finland' => 'FI', 'portugal' => 'PT',
            'hong kong' => 'HK', 'taiwan' => 'TW', 'chile' => 'CL', 'peru' => 'PE',
            'kenya' => 'KE', 'ghana' => 'GH', 'nepal' => 'NP', 'sri lanka' => 'LK',
        ];
    }

    /**
     * Recursively collect all readable strings from a nested structure.
     * Used to extract text from deeply nested protobuf content fields.
     */
    private function collectAllStrings($value, array &$strings, int $maxDepth, int $depth = 0): void
    {
        if ($depth > $maxDepth) return;

        if (is_string($value) && strlen($value) >= 3 && strlen($value) <= 500) {
            $strings[] = $value;
            return;
        }

        if (is_array($value)) {
            foreach ($value as $item) {
                $this->collectAllStrings($item, $strings, $maxDepth, $depth + 1);
            }
        }
    }

    /**
     * Extract timestamp from a protobuf field that might be nested.
     * Handles: scalar, {1: ts}, {1: {1: ts}}, [ts]
     */
    private function extractTimestampFromField($field): ?string
    {
        if ($field === null) return null;

        if (is_scalar($field)) {
            return $this->parseTimestamp($field);
        }

        if (is_array($field)) {
            // Try common nested positions
            foreach (['1', 1, '2', 2, 0] as $k) {
                if (isset($field[$k])) {
                    if (is_scalar($field[$k]) && is_numeric($field[$k])) {
                        return $this->parseTimestamp($field[$k]);
                    }
                    if (is_array($field[$k])) {
                        // One more level deep
                        foreach (['1', 1, 0] as $k2) {
                            if (isset($field[$k][$k2]) && is_scalar($field[$k][$k2]) && is_numeric($field[$k][$k2])) {
                                return $this->parseTimestamp($field[$k][$k2]);
                            }
                        }
                    }
                }
            }
        }

        return null;
    }

    /**
     * Auto-save advertiser name from protobuf field 12 to managed_advertisers.
     */
    private function autoSaveAdvertiserName(string $advertiserId, string $name): void
    {
        $existing = $this->db->fetchOne(
            "SELECT id, name FROM managed_advertisers WHERE advertiser_id = ?",
            [$advertiserId]
        );

        if (!$existing) {
            try {
                $this->db->insert('managed_advertisers', [
                    'advertiser_id' => $advertiserId,
                    'name'          => $name,
                    'status'        => 'active',
                ]);
            } catch (\Exception $e) { /* duplicate key — ok */ }
        } elseif ($existing['name'] === $existing['name'] && ($existing['name'] === $advertiserId || $existing['name'] === '')) {
            // Update name only if it's currently set to the ID itself or empty
            $this->db->update('managed_advertisers', ['name' => $name], 'advertiser_id = ?', [$advertiserId]);
        }
    }

    /**
     * Log discovered unknown protobuf fields to a file for analysis.
     */
    private function logDiscoveredFields(string $creativeId, array $fields): void
    {
        static $loggedCount = 0;
        if ($loggedCount > 50) return; // limit per run
        $loggedCount++;

        $logDir = dirname(__DIR__) . '/logs';
        if (!is_dir($logDir)) @mkdir($logDir, 0755, true);

        $logFile = $logDir . '/discovered_fields.log';
        $line = date('Y-m-d H:i:s') . " | {$creativeId} | " . json_encode($fields) . "\n";
        @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
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
     * Enrich ads that are missing headline/description by fetching preview content.js
     * from Google Ads Transparency and extracting text content.
     */
    public function enrichAdText()
    {
        $ads = $this->db->fetchAll(
            "SELECT a.creative_id, ass.original_url as preview_url
             FROM ads a
             INNER JOIN ad_assets ass ON a.creative_id = ass.creative_id
                AND ass.original_url LIKE '%displayads-formats%'
             WHERE NOT EXISTS (
                 SELECT 1 FROM ad_details d
                 WHERE d.creative_id = a.creative_id
                   AND d.headline IS NOT NULL AND d.headline != ''
                   AND d.headline NOT LIKE '%Cannot find%'
                   AND d.headline NOT LIKE '%global object%'
                   AND d.headline NOT LIKE '%Error%'
             )
             GROUP BY a.creative_id
             ORDER BY a.last_seen DESC
             LIMIT 100"
        );

        if (empty($ads)) return 0;

        $enriched = 0;

        foreach ($ads as $ad) {
            if (empty($ad['preview_url'])) continue;

            $data = $this->fetchPreviewData($ad['preview_url']);
            if (!$data) continue;

            $headline    = $data['headline'] ?? null;
            $description = $data['description'] ?? null;
            $cta         = $data['cta'] ?? null;

            // Hard guard: NEVER save JS error text or URLs as headline/description
            if ($headline && preg_match('/Cannot find|global object|Error\(|undefined|function\s*\(/i', $headline)) {
                $headline = null;
            }
            if ($headline && preg_match('/^https?:\/\/|googlesyndication|googleusercontent|doubleclick|tpc\.google|\.(jpg|png|gif|mp4)(\?|$)/i', $headline)) {
                $headline = null;
            }
            if ($description && preg_match('/Cannot find|global object|Error\(|undefined|function\s*\(/i', $description)) {
                $description = null;
            }
            $landingUrl  = $data['landing_url'] ?? null;
            $displayUrl  = $data['display_url'] ?? null;
            $adWidth     = $data['ad_width'] ?? null;
            $adHeight    = $data['ad_height'] ?? null;
            $headlines   = $data['headlines'] ?? [];
            $descriptions = $data['descriptions'] ?? [];
            $trackingIds = $data['tracking_ids'] ?? [];
            $imageUrls   = $data['image_urls'] ?? [];

            if (!$headline && !$description) continue;

            // Check if detail row exists
            $existing = $this->db->fetchOne(
                "SELECT id, headline, landing_url FROM ad_details WHERE creative_id = ? ORDER BY id DESC LIMIT 1",
                [$ad['creative_id']]
            );

            $detailData = [];
            if ($headline) $detailData['headline'] = $headline;
            if ($description) $detailData['description'] = $description;
            if ($cta) $detailData['cta'] = $cta;
            if ($landingUrl) $detailData['landing_url'] = $landingUrl;
            if ($displayUrl) $detailData['display_url'] = $displayUrl;
            if ($adWidth) $detailData['ad_width'] = (int)$adWidth;
            if ($adHeight) $detailData['ad_height'] = (int)$adHeight;
            if (!empty($headlines)) $detailData['headlines_json'] = json_encode(array_slice($headlines, 0, 10));
            if (!empty($descriptions)) $detailData['descriptions_json'] = json_encode(array_slice($descriptions, 0, 5));
            if (!empty($trackingIds)) $detailData['tracking_ids_json'] = json_encode($trackingIds);

            if ($existing) {
                $updateFields = [];
                $existingHeadlineBad = empty($existing['headline'])
                    || stripos($existing['headline'], 'Cannot find') !== false
                    || stripos($existing['headline'], 'global object') !== false;
                if (!empty($detailData['headline']) && $existingHeadlineBad) {
                    $updateFields['headline'] = $detailData['headline'];
                    $updateFields['headline_source'] = 'preview';
                }
                // Always update these if we have new data
                foreach (['description', 'cta', 'landing_url', 'display_url', 'ad_width', 'ad_height', 'headlines_json', 'descriptions_json', 'tracking_ids_json'] as $field) {
                    if (!empty($detailData[$field])) {
                        $updateFields[$field] = $detailData[$field];
                    }
                }
                if (!empty($updateFields)) {
                    $this->db->update('ad_details', $updateFields, 'id = ?', [$existing['id']]);
                    $enriched++;
                }
            } else {
                $detailData['creative_id'] = $ad['creative_id'];
                $detailData['headline_source'] = !empty($detailData['headline']) ? 'preview' : null;
                $this->db->insert('ad_details', $detailData);
                $enriched++;
            }

            // Save discovered image URLs as ad_assets
            foreach ($imageUrls as $imgUrl) {
                $existsImg = $this->db->fetchOne(
                    "SELECT id FROM ad_assets WHERE creative_id = ? AND original_url = ?",
                    [$ad['creative_id'], $imgUrl]
                );
                if (!$existsImg) {
                    $this->db->insert('ad_assets', [
                        'creative_id'  => $ad['creative_id'],
                        'type'         => 'image',
                        'original_url' => $imgUrl,
                        'local_path'   => null,
                    ]);
                }
            }

            usleep(300000);
        }

        $this->log("Enriched text for {$enriched} ads from preview content");
        return $enriched;
    }

    /**
     * Fetch per-ad country targeting from Google via LookupService/GetCreativeById.
     * This calls Google's API directly — works from any server, no IP block.
     */
    public function enrichCountriesFromGoogle()
    {
        // Get ads that have only 1 country or no countries
        $ads = $this->db->fetchAll(
            "SELECT a.creative_id, a.advertiser_id,
                    (SELECT COUNT(DISTINCT t.country) FROM ad_targeting t WHERE t.creative_id = a.creative_id) as country_count
             FROM ads a
             WHERE a.status = 'active'
             HAVING country_count <= 1
             ORDER BY a.last_seen DESC
             LIMIT 50"
        );

        if (empty($ads)) return 0;

        $this->log("Enriching countries for " . count($ads) . " ads via Google Lookup API");

        $geoMap = $this->getGeoIdMap();
        $enriched = 0;

        // Init Google session once
        $cookieFile = tempnam(sys_get_temp_dir(), 'gads_');
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => 'https://adstransparency.google.com/?region=anywhere',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_COOKIEJAR      => $cookieFile,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
            CURLOPT_ENCODING       => 'gzip, deflate, br',
        ]);
        curl_exec($ch);
        curl_close($ch);

        sleep(1);

        foreach ($ads as $i => $ad) {
            $creativeId = $ad['creative_id'];
            $advertiserId = $ad['advertiser_id'];

            // Call LookupService/GetCreativeById
            $reqData = json_encode([
                '1' => $advertiserId,
                '2' => $creativeId,
                '5' => ['1' => 1],
            ]);

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL            => 'https://adstransparency.google.com/anji/_/rpc/LookupService/GetCreativeById?authuser=0',
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => 'f.req=' . urlencode($reqData),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 15,
                CURLOPT_COOKIEFILE     => $cookieFile,
                CURLOPT_COOKIEJAR      => $cookieFile,
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/x-www-form-urlencoded',
                    'Origin: https://adstransparency.google.com',
                    'Referer: https://adstransparency.google.com/',
                ],
                CURLOPT_USERAGENT      => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
                CURLOPT_ENCODING       => 'gzip, deflate, br',
            ]);

            $resp = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 429) {
                $this->log("Rate limited at ad #{$i}, stopping");
                break;
            }

            if ($httpCode === 401 || $httpCode === 403) {
                $this->log("Session expired (HTTP {$httpCode}) at ad #{$i}, stopping");
                break;
            }

            if (!$resp || $httpCode !== 200) {
                usleep(2000000);
                continue;
            }

            $data = json_decode($resp, true);
            $creative = $data['1'] ?? [];
            $countryEntries = $creative['17'] ?? [];

            if (empty($countryEntries)) {
                usleep(1500000);
                continue;
            }

            // Extract country codes from geo criterion IDs
            $countries = [];
            foreach ($countryEntries as $entry) {
                $geoId = (int)($entry['1'] ?? 0);
                if ($geoId > 0 && isset($geoMap[$geoId])) {
                    $countries[] = $geoMap[$geoId];
                }
            }

            if (!empty($countries)) {
                // Insert targeting rows
                foreach ($countries as $country) {
                    $exists = $this->db->fetchOne(
                        "SELECT id FROM ad_targeting WHERE creative_id = ? AND country = ?",
                        [$creativeId, $country]
                    );
                    if (!$exists) {
                        $this->db->insert('ad_targeting', [
                            'creative_id' => $creativeId,
                            'country'     => $country,
                            'platform'    => 'Google Ads',
                        ]);
                    }
                }
                $enriched++;
                $this->log("  {$creativeId}: " . count($countries) . " countries");
            }

            usleep(1500000); // 1.5s between requests
        }

        @unlink($cookieFile);
        $this->log("Enriched countries for {$enriched} ads");
        return $enriched;
    }

    /**
     * Full geo criterion ID to country code map.
     */
    private function getGeoIdMap(): array
    {
        return [
            2004 => 'AF', 2008 => 'AL', 2012 => 'DZ', 2016 => 'AS', 2020 => 'AD',
            2024 => 'AO', 2010 => 'AQ', 2028 => 'AG', 2032 => 'AR', 2051 => 'AM',
            2036 => 'AU', 2040 => 'AT', 2031 => 'AZ', 2048 => 'BH', 2050 => 'BD',
            2052 => 'BB', 2056 => 'BE', 2068 => 'BO', 2070 => 'BA', 2076 => 'BR',
            2096 => 'BN', 2100 => 'BG', 2116 => 'KH', 2120 => 'CM', 2124 => 'CA',
            2144 => 'LK', 2152 => 'CL', 2156 => 'CN', 2158 => 'TW', 2170 => 'CO',
            2188 => 'CR', 2191 => 'HR', 2196 => 'CY', 2203 => 'CZ', 2208 => 'DK',
            2214 => 'DO', 2218 => 'EC', 2818 => 'EG', 2222 => 'SV', 2231 => 'ET',
            2233 => 'EE', 2242 => 'FJ', 2246 => 'FI', 2250 => 'FR', 2268 => 'GE',
            2276 => 'DE', 2288 => 'GH', 2300 => 'GR', 2308 => 'GD', 2320 => 'GT',
            2332 => 'HT', 2340 => 'HN', 2344 => 'HK', 2348 => 'HU', 2352 => 'IS',
            2356 => 'IN', 2360 => 'ID', 2364 => 'IR', 2368 => 'IQ', 2372 => 'IE',
            2376 => 'IL', 2380 => 'IT', 2384 => 'CI', 2388 => 'JM', 2392 => 'JP',
            2398 => 'KZ', 2400 => 'JO', 2404 => 'KE', 2410 => 'KR', 2414 => 'KW',
            2417 => 'KG', 2418 => 'LA', 2422 => 'LB', 2428 => 'LV', 2434 => 'LY',
            2440 => 'LT', 2442 => 'LU', 2446 => 'MO', 2450 => 'MG', 2458 => 'MY',
            2462 => 'MV', 2466 => 'ML', 2470 => 'MT', 2484 => 'MX', 2496 => 'MN',
            2498 => 'MD', 2504 => 'MA', 2508 => 'MZ', 2512 => 'MM', 2516 => 'NA',
            2524 => 'NP', 2528 => 'NL', 2540 => 'NC', 2554 => 'NZ', 2558 => 'NI',
            2566 => 'NG', 2578 => 'NO', 2586 => 'PK', 2591 => 'PA', 2598 => 'PG',
            2600 => 'PY', 2604 => 'PE', 2608 => 'PH', 2616 => 'PL', 2620 => 'PT',
            2630 => 'PR', 2634 => 'QA', 2638 => 'RE', 2642 => 'RO', 2643 => 'RU',
            2646 => 'RW', 2682 => 'SA', 2686 => 'SN', 2688 => 'RS', 2702 => 'SG',
            2703 => 'SK', 2704 => 'VN', 2705 => 'SI', 2710 => 'ZA', 2724 => 'ES',
            2740 => 'SR', 2752 => 'SE', 2756 => 'CH', 2760 => 'SY', 2764 => 'TH',
            2780 => 'TT', 2784 => 'AE', 2788 => 'TN', 2792 => 'TR', 2800 => 'UG',
            2804 => 'UA', 2807 => 'MK', 2826 => 'GB', 2834 => 'TZ', 2840 => 'US',
            2854 => 'BF', 2858 => 'UY', 2860 => 'UZ', 2862 => 'VE', 2887 => 'YE',
            2894 => 'ZM', 2716 => 'ZW', 2312 => 'GP', 2254 => 'GF', 2474 => 'MQ',
        ];
    }

    /**
     * Extract YouTube video IDs from Google Ads Transparency preview content.
     * Only extracts YouTube URLs — does NOT touch app/store detection.
     */
    public function extractYouTubeUrls()
    {
        $ads = $this->db->fetchAll(
            "SELECT a.creative_id, a.advertiser_id, ass.original_url as preview_url
             FROM ads a
             INNER JOIN ad_assets ass ON a.creative_id = ass.creative_id
                AND ass.original_url LIKE '%displayads-formats%'
             WHERE a.ad_type = 'video'
               AND NOT EXISTS (
                   SELECT 1 FROM ad_assets v
                   WHERE v.creative_id = a.creative_id
                     AND v.type = 'video'
                     AND v.original_url LIKE '%youtube.com%'
               )
             GROUP BY a.creative_id
             ORDER BY a.last_seen DESC
             LIMIT 100"
        );

        if (empty($ads)) {
            return 0;
        }

        $extracted = 0;

        foreach ($ads as $ad) {
            $previewUrl = $ad['preview_url'];
            if (empty($previewUrl)) continue;

            $data = $this->fetchPreviewData($previewUrl);
            if (!$data) continue;

            $ytId = $data['youtube_id'];
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

        $this->log("Extracted {$extracted} YouTube URLs from video ads");
        return $extracted;
    }

    /**
     * Detect apps from Google Ads Transparency preview content.js ONLY.
     * Fetches the preview URL (displayads-formats.googleusercontent.com) and
     * extracts App Store / Play Store URLs embedded in the ad creative.
     * This is the ONLY source of app/store detection.
     */
    public function enrichStoreUrlsFromPreview()
    {
        // Get ALL ads with a preview URL that don't yet have an ios/playstore product
        $ads = $this->db->fetchAll(
            "SELECT a.creative_id, a.advertiser_id, ass.original_url as preview_url
             FROM ads a
             INNER JOIN ad_assets ass ON a.creative_id = ass.creative_id
                AND ass.original_url LIKE '%displayads-formats%'
             WHERE NOT EXISTS (
                 SELECT 1 FROM ad_product_map pm
                 INNER JOIN ad_products p ON pm.product_id = p.id
                 WHERE pm.creative_id = a.creative_id
                   AND p.store_platform IN ('ios', 'playstore')
                   AND p.store_url IS NOT NULL AND p.store_url != '' AND p.store_url != 'not_found'
             )
             GROUP BY a.creative_id
             ORDER BY a.last_seen DESC
             LIMIT 100"
        );

        if (empty($ads)) {
            return 0;
        }

        $enriched = 0;

        foreach ($ads as $ad) {
            $previewUrl = $ad['preview_url'];
            if (empty($previewUrl)) continue;

            $data = $this->fetchPreviewData($previewUrl);
            if (!$data || !$data['store_url']) continue;

            $this->saveStoreUrlForAd($ad['creative_id'], $ad['advertiser_id'], $data['store_url'], $data['store_platform']);
            $enriched++;

            usleep(300000); // 300ms rate limit
        }

        $this->log("Enriched {$enriched} store URLs from preview content");
        return $enriched;
    }

    /**
     * Save a store URL for an ad by updating or creating the product record.
     */
    private function saveStoreUrlForAd($creativeId, $advertiserId, $storeUrl, $storePlatform)
    {
        // Check if this ad already has a product mapping
        $mapping = $this->db->fetchOne(
            "SELECT pm.product_id, p.product_name, p.store_url, p.store_platform
             FROM ad_product_map pm
             INNER JOIN ad_products p ON pm.product_id = p.id
             WHERE pm.creative_id = ?
             LIMIT 1",
            [$creativeId]
        );

        if ($mapping) {
            // Update existing product with the store URL
            $currentUrl = $mapping['store_url'] ?? '';
            if (empty($currentUrl) || $currentUrl === 'not_found' || $mapping['store_platform'] === 'web') {
                $this->db->update('ad_products', [
                    'store_url'      => $storeUrl,
                    'store_platform' => $storePlatform,
                    'product_type'   => 'app',
                ], 'id = ?', [$mapping['product_id']]);
            }
        } else {
            // No mapping exists — create product + mapping from store URL
            $productName = $this->deriveProductNameFromStoreUrl($storeUrl, $storePlatform);

            $productId = $this->findOrCreateProduct(
                $advertiserId,
                $productName,
                'app',
                $storePlatform,
                $storeUrl
            );

            if ($productId) {
                $this->db->insert('ad_product_map', [
                    'creative_id' => $creativeId,
                    'product_id'  => $productId,
                ]);
            }
        }
    }

    /**
     * Derive a readable product name from a store URL.
     * Fetches real app name from App Store / Play Store.
     */
    private function deriveProductNameFromStoreUrl($storeUrl, $storePlatform)
    {
        if ($storePlatform === 'ios') {
            // Extract numeric ID from App Store URL
            if (preg_match('/id(\d+)/', $storeUrl, $m)) {
                $appId = $m[1];
                $name = $this->fetchAppStoreAppName($appId);
                if ($name) return $name;
            }
            // Fallback: slug from URL
            if (preg_match('/\/app\/([^\/]+)\//', $storeUrl, $m)) {
                return ucwords(str_replace('-', ' ', $m[1]));
            }
        } elseif ($storePlatform === 'playstore') {
            // Extract package name from Play Store URL
            if (preg_match('/id=([a-zA-Z0-9._]+)/', $storeUrl, $m)) {
                $packageName = $m[1];
                $name = $this->fetchPlayStoreAppName($packageName);
                if ($name) return $name;
                return $this->packageToAppName($packageName);
            }
        }
        return 'Unknown App';
    }

    /**
     * Fetch app name from Apple iTunes Lookup API.
     */
    private function fetchAppStoreAppName($appId)
    {
        $url = 'https://itunes.apple.com/lookup?id=' . $appId;
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($resp && $code === 200) {
            $data = json_decode($resp, true);
            if ($data && isset($data['results'][0]['trackName'])) {
                return $data['results'][0]['trackName'];
            }
        }
        return null;
    }

    /**
     * Fetch app name from Play Store page title.
     */
    private function fetchPlayStoreAppName($packageName)
    {
        $url = 'https://play.google.com/store/apps/details?id=' . $packageName . '&hl=en';
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($resp && $code === 200) {
            // Extract title from <title>App Name - Apps on Google Play</title>
            if (preg_match('/<title>([^<]+?)(?:\s*-\s*Apps on Google Play)?<\/title>/i', $resp, $m)) {
                $name = trim($m[1]);
                if ($name && $name !== 'Google Play' && strlen($name) < 200) {
                    return $name;
                }
            }
        }
        return null;
    }

    /**
     * Fetch a Google preview content.js URL and extract ALL available data:
     * YouTube video ID, store URLs, headline, description, CTA, landing URL,
     * ad dimensions, tracking IDs, multiple headline variations.
     */
    private function fetchPreviewData($previewUrl)
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
        $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close($ch);

        if ($response === false || $httpCode !== 200 || strlen($response) < 50) {
            return null;
        }

        $result = [
            'youtube_id' => null, 'store_url' => null, 'store_platform' => null,
            'headline' => null, 'description' => null, 'cta' => null,
            'landing_url' => null, 'display_url' => null,
            'headlines' => [], 'descriptions' => [],
            'ad_width' => null, 'ad_height' => null,
            'tracking_ids' => [],
            'image_urls' => [],
        ];

        // Prepare decoded content (original + URL-decoded + hex-decoded)
        $decoded = $response;
        if (strpos($response, '%20') !== false || strpos($response, '%3A') !== false) {
            $decoded .= "\n" . urldecode($response);
        }
        // Unicode-unescaping
        if (strpos($response, '\\u') !== false) {
            $decoded .= "\n" . (json_decode('"' . str_replace('"', '\\"', $response) . '"') ?: '');
        }
        // Hex escape decoding (\x3e = >, \x3c = <, \x22 = ")
        if (strpos($response, '\\x') !== false) {
            $hexDecoded = preg_replace_callback('/\\\\x([0-9a-fA-F]{2})/', function($m) {
                return chr(hexdec($m[1]));
            }, $response);
            $decoded .= "\n" . $hexDecoded;
        }

        // ── YouTube ID ──
        // Google preview uses 'video_videoId': 'XXXXXXXXXXX' (single quotes)
        if (preg_match('/[\'"]video_videoId[\'"]\s*:\s*[\'"]([a-zA-Z0-9_-]{11})[\'"]/', $decoded, $m)) {
            $result['youtube_id'] = $m[1];
        } elseif (preg_match('/ytimg\.com\/vi\/([a-zA-Z0-9_-]{11})\//', $decoded, $m)) {
            $result['youtube_id'] = $m[1];
        } elseif (preg_match('/youtube\.com\/(?:embed\/|watch\?v=|v\/)([a-zA-Z0-9_-]{11})/', $decoded, $m)) {
            $result['youtube_id'] = $m[1];
        } elseif (preg_match('/youtu\.be\/([a-zA-Z0-9_-]{11})/', $decoded, $m)) {
            $result['youtube_id'] = $m[1];
        }

        // ── App Store URL ──
        // Google preview uses 'appId': 'com.package.name' and 'appStore': '2' (Play) or '1' (iOS)
        $appId = null;
        $appStoreType = null;
        if (preg_match('/[\'"]appId[\'"]\s*:\s*[\'"]([a-zA-Z0-9._]+)[\'"]/', $decoded, $ai)) {
            $appId = $ai[1];
        }
        if (preg_match('/[\'"]appStore[\'"]\s*:\s*[\'"]?(\d+)[\'"]?/', $decoded, $as)) {
            $appStoreType = $as[1];
        }

        // Build store URL from appId + appStore type
        if ($appId && $appStoreType === '2') {
            $result['store_url'] = 'https://play.google.com/store/apps/details?id=' . $appId;
            $result['store_platform'] = 'playstore';
        } elseif ($appId && $appStoreType === '1') {
            // iOS apps use numeric IDs — appId might be numeric or bundle ID
            if (preg_match('/^\d+$/', $appId)) {
                $result['store_url'] = 'https://apps.apple.com/app/id' . $appId;
            } else {
                $result['store_url'] = 'https://apps.apple.com/app/' . $appId;
            }
            $result['store_platform'] = 'ios';
        }

        // Fallback: direct URL patterns
        if (!$result['store_url'] && preg_match('/(?:itunes\.apple\.com|apps\.apple\.com)(?:%2F|\/)+(?:[a-z]{2}(?:%2F|\/)+)?app(?:%2F|\/)+(?:[^%"\'\\\\&\s]*(?:%2F|\/)+)?id(\d+)/', $decoded, $m)) {
            $result['store_url'] = 'https://apps.apple.com/app/id' . $m[1];
            $result['store_platform'] = 'ios';
        }
        if (!$result['store_url'] && preg_match('/play\.google\.com(?:%2F|\/)+store(?:%2F|\/)+apps(?:%2F|\/)+details(?:%3F|\?)id(?:%3D|=)([a-zA-Z0-9._]+)/', $decoded, $m)) {
            $result['store_url'] = 'https://play.google.com/store/apps/details?id=' . $m[1];
            $result['store_platform'] = 'playstore';
        }

        // ── Ad Dimensions (width x height) ──
        if (preg_match('/(?:width|w)["\'\s:=]+(\d{2,4}).*?(?:height|h)["\'\s:=]+(\d{2,4})/', $decoded, $dm)) {
            $result['ad_width'] = (int)$dm[1];
            $result['ad_height'] = (int)$dm[2];
        } elseif (preg_match('/(\d{2,4})\s*[xX×]\s*(\d{2,4})/', $decoded, $dm)) {
            $result['ad_width'] = (int)$dm[1];
            $result['ad_height'] = (int)$dm[2];
        }

        // ── Landing URL (final destination) ──
        // Pattern 1: adurl or clickurl parameter
        if (preg_match('/(?:adurl|clickurl|click_url|redirect|landing_?url|finalUrl|final_url|destinationUrl|destination_url)["\'\s:=]+["\']?(https?[^"\'\\\\&\s]{10,500})/', $decoded, $lm)) {
            $result['landing_url'] = urldecode($lm[1]);
        }
        // Pattern 2: googleadservices redirect — extract final URL
        if (!$result['landing_url'] && preg_match('/googleadservices\.com.*?(?:adurl|url)=(https?(?:%3A|:)[^&\s"\']{10,500})/', $decoded, $lm)) {
            $result['landing_url'] = urldecode($lm[1]);
        }
        // Pattern 3: Any https URL that's not google/youtube/doubleclick (likely landing page)
        if (!$result['landing_url'] && preg_match_all('/https?:\/\/(?!(?:www\.)?(?:google|youtube|doubleclick|googlesyndication|googleapis|gstatic|ytimg|rr\d+---)\b)[a-zA-Z0-9][-a-zA-Z0-9.]+\.[a-z]{2,}[^\s"\'\\\\,;)]{0,300}/', $decoded, $urls)) {
            usort($urls[0], function($a, $b) { return strlen($b) - strlen($a); });
            foreach ($urls[0] as $url) {
                if (strlen($url) > 15 && !preg_match('/\.(js|css|png|jpg|gif|svg|woff|ttf)(\?|$)/i', $url)) {
                    $result['landing_url'] = urldecode($url);
                    break;
                }
            }
        }

        // ── Display URL ──
        if (preg_match('/[\'"](?:displayUrl|display_url|visible_url)[\'"]\s*:\s*[\'"]([^"\']{5,100})[\'"]/', $decoded, $du)) {
            $result['display_url'] = $du[1];
        }

        // ══════════════════════════════════════════════════
        // ── Headlines — Google Ads preview uses SINGLE QUOTES ──
        // ══════════════════════════════════════════════════
        $headlines = [];

        // H0: Google Ads preview HTML targets: ochAppName, ochEndCardAppName
        if (preg_match_all('/och(?:EndCard)?AppName[^>]*>([^<]{2,200})</', $decoded, $ochH)) {
            $headlines = array_merge($headlines, $ochH[1]);
        }

        // H1: Google UAC preview format: 'appName': 'App Name Here'
        if (preg_match('/[\'"]appName[\'"]\s*:\s*[\'"]([^"\']{3,200})[\'"]/', $decoded, $an)) {
            $headlines[] = $an[1];
        }
        // H2: 'shortAppName' (may differ from appName)
        if (preg_match('/[\'"]shortAppName[\'"]\s*:\s*[\'"]([^"\']{3,200})[\'"]/', $decoded, $san)) {
            $headlines[] = $san[1];
        }
        // H3: JSON double-quote "headline"/"headlines" fields
        if (preg_match_all('/[\'"]headlines?[\'"]\s*:\s*[\'"]([^"\']{3,200})[\'"]/', $decoded, $hm)) {
            $headlines = array_merge($headlines, $hm[1]);
        }
        // H4: Array of headlines
        if (preg_match('/[\'"]headlines?[\'"]\s*:\s*\[([^\]]+)\]/', $decoded, $hArr)) {
            if (preg_match_all('/[\'"]([^"\']{3,200})[\'"]/', $hArr[1], $hItems)) {
                $headlines = array_merge($headlines, $hItems[1]);
            }
        }
        // H5: "title" / 'title' field
        if (preg_match_all('/[\'"]title[\'"]\s*:\s*[\'"]([^"\']{3,200})[\'"]/', $decoded, $tm)) {
            $headlines = array_merge($headlines, $tm[1]);
        }
        // H6: HTML heading tags
        if (preg_match_all('/<h[1-3][^>]*>([^<]{3,200})<\/h[1-3]>/i', $decoded, $hTags)) {
            $headlines = array_merge($headlines, array_map('trim', $hTags[1]));
        }
        // H7: Div/span with headline-like classes
        if (preg_match_all('/<(?:div|span)[^>]*class="[^"]*(?:headline|title|header)[^"]*"[^>]*>([^<]{3,200})/i', $decoded, $hCls)) {
            $headlines = array_merge($headlines, array_map('trim', $hCls[1]));
        }
        // H8: Bold text or emphasized text
        if (preg_match_all('/<(?:b|strong|em)[^>]*>([^<]{5,150})<\/(?:b|strong|em)>/i', $decoded, $bTags)) {
            $headlines = array_merge($headlines, array_map('trim', $bTags[1]));
        }

        // Deduplicate and clean — exclude JS error messages and code artifacts
        $headlines = array_values(array_unique(array_filter($headlines, function($h) {
            $h = trim($h);
            return strlen($h) >= 3 && strlen($h) <= 200
                && !preg_match('/^https?:/', $h)
                && !preg_match('/^\{|\}$/', $h)
                && !preg_match('/Cannot find|Error|undefined|function|var |const |let |null|true|false|^\d+$/i', $h)
                && preg_match('/[a-zA-Z]{2,}/', $h);
        })));
        $result['headlines'] = array_slice($headlines, 0, 10);
        if (!empty($headlines)) {
            $result['headline'] = $headlines[0];
        }

        // ══════════════════════════════════════════════════
        // ── Descriptions — Google Ads preview format ──
        // ══════════════════════════════════════════════════
        $descriptions = [];

        // D0: Google Ads preview HTML targets: ochDescription, ochBody
        if (preg_match_all('/ochDescription[^>]*>([^<]{5,500})</', $decoded, $ochD)) {
            $descriptions = array_merge($descriptions, $ochD[1]);
        }
        if (preg_match_all('/ochBody[^>]*>([^<]{5,500})</', $decoded, $ochB)) {
            $descriptions = array_merge($descriptions, $ochB[1]);
        }

        // D1: Google UAC: 'shortDescription': 'text' or 'longDescription'
        if (preg_match('/[\'"]shortDescription[\'"]\s*:\s*[\'"]([^"\']{5,500})[\'"]/', $decoded, $sd)) {
            $descriptions[] = $sd[1];
        }
        if (preg_match('/[\'"]longDescription[\'"]\s*:\s*[\'"]([^"\']{5,2000})[\'"]/', $decoded, $ld)) {
            $descriptions[] = $ld[1];
        }
        // D2: JSON "description"/"descriptions" (single or double quotes)
        if (preg_match_all('/[\'"]descriptions?[\'"]\s*:\s*[\'"]([^"\']{8,500})[\'"]/', $decoded, $dm)) {
            $descriptions = array_merge($descriptions, $dm[1]);
        }
        // D3: Array of descriptions
        if (preg_match('/[\'"]descriptions?[\'"]\s*:\s*\[([^\]]+)\]/', $decoded, $dArr)) {
            if (preg_match_all('/[\'"]([^"\']{8,500})[\'"]/', $dArr[1], $dItems)) {
                $descriptions = array_merge($descriptions, $dItems[1]);
            }
        }
        // D4: "body" / "bodyText" fields
        if (preg_match_all('/[\'"](?:body|bodyText|body_text)[\'"]\s*:\s*[\'"]([^"\']{8,500})[\'"]/', $decoded, $bm)) {
            $descriptions = array_merge($descriptions, $bm[1]);
        }
        // D5: Paragraph tags
        if (preg_match_all('/<p[^>]*>([^<]{8,500})<\/p>/i', $decoded, $pTags)) {
            $descriptions = array_merge($descriptions, array_map('trim', $pTags[1]));
        }

        $descriptions = array_values(array_unique(array_filter($descriptions, function($d) {
            $d = trim($d);
            return strlen($d) >= 8 && preg_match('/[a-zA-Z]{3,}/', $d)
                && !preg_match('/^https?:/', $d)
                && !preg_match('/Cannot find|Error|undefined|function/i', $d);
        })));
        $result['descriptions'] = array_slice($descriptions, 0, 5);
        if (empty($result['description']) && !empty($descriptions)) {
            $result['description'] = $descriptions[0];
        }

        // ══════════════════════════════════════════════════
        // ── CTA (Call to Action) — Google uses 'callToAction' / 'callToActionInstall' ──
        // ══════════════════════════════════════════════════
        // C0: Google Ads preview HTML targets: ochButton, ochEndCardButton
        if (preg_match('/och(?:EndCard)?Button[^>]*>([^<]{2,50})</', $decoded, $ochC)) {
            $result['cta'] = trim($ochC[1]);
        }
        // C1: Google UAC format: 'callToAction': 'INSTALL' or 'callToActionInstall'
        if (!$result['cta'] && preg_match('/[\'"](?:callToAction|callToActionInstall)[\'"]\s*:\s*[\'"]([^"\']{2,50})[\'"]/', $decoded, $cm)) {
            $result['cta'] = $cm[1];
        }
        // C2: JSON CTA fields (double quotes)
        if (!$result['cta'] && preg_match('/[\'"](?:cta|call_to_action|buttonText|button_text|actionText|action_text)[\'"]\s*:\s*[\'"]([^"\']{2,50})[\'"]/', $decoded, $cm)) {
            $result['cta'] = $cm[1];
        }
        // C3: Button/anchor text
        if (!$result['cta'] && preg_match('/<(?:button|a)[^>]*(?:class=["\'][^"\']*(?:cta|btn|button|action)[^"\']*["\']|)[^>]*>([^<]{2,40})<\/(?:button|a)>/i', $decoded, $bt)) {
            $result['cta'] = trim($bt[1]);
        }
        // C4: Common CTA patterns in text
        if (!$result['cta'] && preg_match('/\b(Learn More|Sign Up|Download|Install|Shop Now|Get Started|Buy Now|Try Free|Subscribe|Apply Now|Book Now|Contact Us|Get Offer|Play Now|Watch Now|Order Now|See More|Read More|Try Now|Start Free|Claim Offer|Save Now|Register|Join Free|Explore|Discover|Open App|Pre-Register)\b/i', $decoded, $cm)) {
            $result['cta'] = $cm[1];
        }

        // ── App metadata from preview (developer, category, icon) ──
        if (preg_match('/[\'"]developer[\'"]\s*:\s*[\'"]([^"\']{2,200})[\'"]/', $decoded, $dev)) {
            $result['developer'] = $dev[1];
        }
        if (preg_match('/[\'"]appCategory[\'"]\s*:\s*[\'"]([^"\']{2,100})[\'"]/', $decoded, $cat)) {
            $result['app_category'] = $cat[1];
        }
        if (preg_match('/[\'"]appIconHighRes[\'"]\s*:\s*[\'"]([^"\']{10,500})[\'"]/', $decoded, $ico)) {
            $iconUrl = $ico[1];
            if (strpos($iconUrl, '//') === 0) $iconUrl = 'https:' . $iconUrl;
            $result['app_icon'] = $iconUrl;
        }
        if (preg_match('/[\'"]appStoreName[\'"]\s*:\s*[\'"]([^"\']{2,50})[\'"]/', $decoded, $sn)) {
            $result['store_name'] = $sn[1];
        }

        // ── Tracking IDs ──
        $trackingIds = [];
        // Google Analytics
        if (preg_match_all('/\b(UA-\d{4,10}-\d{1,4})\b/', $decoded, $ga)) {
            foreach ($ga[1] as $id) $trackingIds[] = ['type' => 'ga_ua', 'id' => $id];
        }
        if (preg_match_all('/\b(G-[A-Z0-9]{10,12})\b/', $decoded, $ga4)) {
            foreach ($ga4[1] as $id) $trackingIds[] = ['type' => 'ga4', 'id' => $id];
        }
        // Google Tag Manager
        if (preg_match_all('/\b(GTM-[A-Z0-9]{6,8})\b/', $decoded, $gtm)) {
            foreach ($gtm[1] as $id) $trackingIds[] = ['type' => 'gtm', 'id' => $id];
        }
        // Facebook Pixel
        if (preg_match_all('/(?:fbq|facebook).*?["\'](\d{15,16})["\']/', $decoded, $fb)) {
            foreach ($fb[1] as $id) $trackingIds[] = ['type' => 'fb_pixel', 'id' => $id];
        }
        $result['tracking_ids'] = $trackingIds;

        // ── Image URLs (all non-tracker images) ──
        $images = [];
        if (preg_match_all('/(?:src|href|url)\s*[=(:]\s*["\']?(https?:\/\/[^"\')\s,;]{10,500}\.(?:jpg|jpeg|png|gif|webp|svg)(?:\?[^"\')\s]*)?)/i', $decoded, $imgUrls)) {
            foreach ($imgUrls[1] as $imgUrl) {
                $imgUrl = urldecode($imgUrl);
                if (!preg_match('/(?:google|doubleclick|gstatic|analytics)/', $imgUrl)) {
                    $images[] = $imgUrl;
                }
            }
        }
        $result['image_urls'] = array_values(array_unique(array_slice($images, 0, 10)));

        return $result;
    }

    /**
     * Fetch YouTube metadata (title, view count, thumbnail) for video ads.
     * Uses YouTube oEmbed for title and page scraping for view count.
     * Updates ad_details with title and view count info.
     *
     * Two modes:
     * 1. First fetch: ads with no view_count or no headline (always fetch)
     * 2. Refresh: ads already enriched but youtube_metadata.fetched_at > 15 days ago
     *
     * Returns number of ads enriched.
     */
    public function enrichYouTubeMetadata()
    {
        // Part 1: First-time fetch — no view_count or no headline
        $newAds = $this->db->fetchAll(
            "SELECT a.creative_id, a.view_count,
                    (SELECT original_url FROM ad_assets v WHERE v.creative_id = a.creative_id AND v.type = 'video' AND v.original_url LIKE '%youtube.com%' LIMIT 1) as youtube_url,
                    (SELECT d2.headline FROM ad_details d2 WHERE d2.creative_id = a.creative_id ORDER BY d2.id DESC LIMIT 1) as current_headline
             FROM ads a
             WHERE a.ad_type = 'video'
               AND EXISTS (SELECT 1 FROM ad_assets v WHERE v.creative_id = a.creative_id AND v.type = 'video' AND v.original_url LIKE '%youtube.com%')
               AND (a.view_count = 0 OR a.view_count IS NULL
                    OR NOT EXISTS (SELECT 1 FROM ad_details d WHERE d.creative_id = a.creative_id AND d.headline IS NOT NULL AND d.headline != ''))
             ORDER BY a.last_seen DESC
             LIMIT 50"
        );

        // Part 2: Refresh — already have view_count, but fetched_at > 15 days ago
        $staleAds = $this->db->fetchAll(
            "SELECT a.creative_id, a.view_count,
                    ass.original_url as youtube_url,
                    (SELECT d2.headline FROM ad_details d2 WHERE d2.creative_id = a.creative_id ORDER BY d2.id DESC LIMIT 1) as current_headline
             FROM ads a
             INNER JOIN ad_assets ass ON ass.creative_id = a.creative_id AND ass.type = 'video' AND ass.original_url LIKE '%youtube.com%'
             INNER JOIN youtube_metadata ym ON CONCAT('https://www.youtube.com/watch?v=', ym.video_id) COLLATE utf8mb4_unicode_ci = ass.original_url COLLATE utf8mb4_unicode_ci
             WHERE a.ad_type = 'video'
               AND a.view_count > 0
               AND ym.fetched_at < DATE_SUB(NOW(), INTERVAL 1 DAY)
             ORDER BY ym.fetched_at ASC
             LIMIT 30"
        );

        $ads = array_merge($newAds, $staleAds);

        // Deduplicate by creative_id
        $seen = [];
        $ads = array_filter($ads, function($ad) use (&$seen) {
            if (isset($seen[$ad['creative_id']])) return false;
            $seen[$ad['creative_id']] = true;
            return true;
        });

        if (empty($ads)) {
            return 0;
        }

        $enriched = 0;
        $failed = 0;

        foreach ($ads as $ad) {
            $youtubeUrl = $ad['youtube_url'];
            if (empty($youtubeUrl)) continue;

            // Extract video ID
            $videoId = null;
            if (preg_match('/[?&]v=([a-zA-Z0-9_-]{11})/', $youtubeUrl, $m)) {
                $videoId = $m[1];
            }
            if (!$videoId) continue;

            $meta = $this->fetchYouTubeMetadata($videoId);
            if (!$meta) {
                $failed++;
                // If too many failures, YouTube may be rate-limiting — stop early
                if ($failed >= 5) {
                    $this->log("Stopping YouTube enrichment early: {$failed} consecutive failures");
                    break;
                }
                continue;
            }
            $failed = 0; // Reset on success

            // Build description from YouTube metadata
            $headline = $meta['title'] ?: null;
            $description = '';
            if ($meta['view_count'] !== null) {
                $description = number_format($meta['view_count']) . ' views';
            }
            if ($meta['author']) {
                $description .= ($description ? ' | ' : '') . 'by ' . $meta['author'];
            }

            // Store numeric view_count in ads table for sorting
            if ($meta['view_count'] !== null) {
                $this->db->update('ads', [
                    'view_count' => (int) $meta['view_count'],
                ], 'creative_id = ?', [$ad['creative_id']]);
            }

            // Check if this is a refresh (already has view_count) or first-time
            $isRefresh = !empty($ad['view_count']) && $ad['view_count'] > 0;

            // Update existing detail or insert new one
            $existingDetail = $this->db->fetchOne(
                "SELECT id, headline, headline_source FROM ad_details WHERE creative_id = ? ORDER BY id DESC LIMIT 1",
                [$ad['creative_id']]
            );

            if ($existingDetail) {
                $updateData = ['description' => $description ?: null];
                // Only set headline from YouTube if no real ad headline exists
                $hasRealHeadline = !empty($existingDetail['headline']) && $existingDetail['headline_source'] === 'ad';
                if (!$hasRealHeadline && $headline) {
                    $updateData['headline'] = $headline;
                    $updateData['headline_source'] = 'youtube';
                }
                $this->db->update('ad_details', $updateData, 'id = ?', [$existingDetail['id']]);
            } else {
                $this->db->insert('ad_details', [
                    'creative_id'     => $ad['creative_id'],
                    'headline'        => $headline,
                    'description'     => $description ?: null,
                    'headline_source' => 'youtube',
                ]);
            }

            // Ensure YouTube thumbnail is stored as image asset
            $thumbUrl = 'https://i.ytimg.com/vi/' . $videoId . '/hqdefault.jpg';
            $existsThumb = $this->db->fetchOne(
                "SELECT id FROM ad_assets WHERE creative_id = ? AND original_url = ?",
                [$ad['creative_id'], $thumbUrl]
            );
            if (!$existsThumb) {
                $this->db->insert('ad_assets', [
                    'creative_id'  => $ad['creative_id'],
                    'type'         => 'image',
                    'original_url' => $thumbUrl,
                    'local_path'   => null,
                ]);
            }

            // Save to youtube_metadata table for reuse across profiles
            $this->saveYouTubeMetadata($videoId, $meta);

            $enriched++;
            usleep(300000); // 300ms between YouTube requests
        }

        $newCount = count($newAds);
        $staleCount = count($staleAds);
        $this->log("YouTube enrichment: {$enriched} updated ({$newCount} new, {$staleCount} refreshed after 15d)");

        // Auto-assign new videos to video groups based on keywords
        if ($enriched > 0) {
            $this->autoAssignVideoGroups();
        }

        return $enriched;
    }

    /**
     * Detect products/apps from ad headlines and landing URLs.
     * Groups ads by detected product name per advertiser.
     * Returns number of new product mappings created.
     */
    public function detectProducts()
    {
        // Get unmapped ads with headlines, URLs, and ad platform info (batched)
        $ads = $this->db->fetchAll(
            "SELECT a.creative_id, a.advertiser_id, d.headline, d.landing_url,
                    (SELECT GROUP_CONCAT(ass.original_url SEPARATOR '||')
                     FROM ad_assets ass WHERE ass.creative_id = a.creative_id) as all_asset_urls,
                    (SELECT GROUP_CONCAT(DISTINCT t.platform SEPARATOR '||')
                     FROM ad_targeting t WHERE t.creative_id = a.creative_id) as ad_platforms
             FROM ads a
             LEFT JOIN ad_details d ON a.creative_id = d.creative_id
                 AND d.id = (SELECT MAX(id) FROM ad_details WHERE creative_id = a.creative_id)
             WHERE NOT EXISTS (
                 SELECT 1 FROM ad_product_map pm WHERE pm.creative_id = a.creative_id
             )
             ORDER BY a.advertiser_id, a.last_seen DESC
             LIMIT 100"
        );

        if (empty($ads)) {
            return 0;
        }

        // Pre-load store URLs from raw payloads per advertiser (fast indexed query)
        $advertiserStoreUrls = $this->preloadStoreUrlsFromPayloads($ads);

        $mapped = 0;

        foreach ($ads as $ad) {
            $result = $this->detectProductForAd($ad, $advertiserStoreUrls);

            // Find or create product
            $productId = $this->findOrCreateProduct(
                $ad['advertiser_id'],
                $result['name'],
                $result['type'],
                $result['platform'],
                $result['url']
            );

            if ($productId) {
                $exists = $this->db->fetchOne(
                    "SELECT id FROM ad_product_map WHERE creative_id = ? AND product_id = ?",
                    [$ad['creative_id'], $productId]
                );
                if (!$exists) {
                    $this->db->insert('ad_product_map', [
                        'creative_id' => $ad['creative_id'],
                        'product_id'  => $productId,
                    ]);
                    $mapped++;
                }
            }
        }

        $this->log("Detected products for {$mapped} ads");

        // Merge duplicate products (same store_url across advertisers)
        if ($mapped > 0) {
            $this->mergeDuplicateProducts();
            $this->autoAssignAppGroups();
        }

        return $mapped;
    }

    /**
     * Merge duplicate products that share the same store_url.
     * Keeps the one with best metadata, migrates all references.
     */
    public function mergeDuplicateProducts(): int
    {
        $pdo = $this->db->getPdo();

        // Find duplicates: same store_url, different product IDs
        $dupes = $pdo->query("
            SELECT store_url, COUNT(*) as cnt, GROUP_CONCAT(id ORDER BY id) as ids
            FROM ad_products
            WHERE store_url IS NOT NULL AND store_url != '' AND store_url != 'not_found'
              AND store_platform IN ('ios', 'playstore')
            GROUP BY store_url
            HAVING cnt > 1
            LIMIT 200
        ")->fetchAll(\PDO::FETCH_ASSOC);

        if (empty($dupes)) return 0;

        $merged = 0;

        foreach ($dupes as $dupe) {
            $ids = array_map('intval', explode(',', $dupe['ids']));

            // Pick keeper: prefer one with app_metadata, then most ad mappings
            $bestId = null;
            $bestScore = -1;
            foreach ($ids as $pid) {
                $hasMeta = (int)$pdo->query("SELECT COUNT(*) FROM app_metadata WHERE product_id = {$pid}")->fetchColumn();
                $adCount = (int)$pdo->query("SELECT COUNT(*) FROM ad_product_map WHERE product_id = {$pid}")->fetchColumn();
                $score = $hasMeta * 10000 + $adCount;
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestId = $pid;
                }
            }

            $dupeIds = array_filter($ids, fn($id) => $id !== $bestId);
            if (empty($dupeIds)) continue;

            $ph = implode(',', $dupeIds);

            // Migrate ad_product_map
            $pdo->exec("INSERT IGNORE INTO ad_product_map (creative_id, product_id)
                         SELECT pm.creative_id, {$bestId} FROM ad_product_map pm WHERE pm.product_id IN ({$ph})");
            $pdo->exec("DELETE FROM ad_product_map WHERE product_id IN ({$ph})");

            // Migrate app_group_members
            try {
                $pdo->exec("INSERT IGNORE INTO app_group_members (group_id, product_id, matched_keyword, auto_assigned)
                             SELECT agm.group_id, {$bestId}, agm.matched_keyword, agm.auto_assigned
                             FROM app_group_members agm WHERE agm.product_id IN ({$ph})");
                $pdo->exec("DELETE FROM app_group_members WHERE product_id IN ({$ph})");
            } catch (\Exception $e) { /* table may not exist */ }

            // Delete duplicate app_metadata
            $pdo->exec("DELETE FROM app_metadata WHERE product_id IN ({$ph})");

            // Delete duplicate products
            $pdo->exec("DELETE FROM ad_products WHERE id IN ({$ph})");

            $merged += count($dupeIds);
        }

        if ($merged > 0) {
            $this->log("Merged {$merged} duplicate products");
        }
        return $merged;
    }

    /**
     * Auto-assign unassigned products to app groups based on keyword matching.
     */
    public function autoAssignAppGroups(): int
    {
        $pdo = $this->db->getPdo();

        // Check if app_groups table exists
        try {
            $pdo->query("SELECT 1 FROM app_groups LIMIT 1");
        } catch (\Exception $e) {
            return 0; // Table doesn't exist yet
        }

        // Get all group IDs and their keywords
        $groupKeywords = []; // group_id => [keywords]
        $rows = $pdo->query("SELECT g.id, k.keyword FROM app_groups g JOIN app_group_keywords k ON k.group_id = g.id")->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $groupKeywords[$row['id']][] = $row['keyword'];
        }
        if (empty($groupKeywords)) return 0;

        $assigned = 0;
        $insertStmt = $pdo->prepare(
            "INSERT IGNORE INTO app_group_members (group_id, product_id, matched_keyword, auto_assigned) VALUES (?, ?, ?, 1)"
        );

        foreach ($groupKeywords as $groupId => $keywords) {
            // Find all products matching any keyword in this group
            $matchedIds = [];
            $matchedKeyword = [];
            foreach ($keywords as $kw) {
                // Word boundary regex: "art" matches "art" but not "smart"/"start"
                $regex = '(^|[^a-zA-Z])' . preg_quote(strtolower($kw), '/') . '([^a-zA-Z]|$)';
                $matches = $pdo->prepare("
                    SELECT DISTINCT p.id FROM ad_products p
                    LEFT JOIN app_metadata a ON a.product_id = p.id
                    WHERE LOWER(p.product_name) REGEXP ? OR LOWER(COALESCE(a.app_name, '')) REGEXP ?
                          OR LOWER(COALESCE(a.category, '')) REGEXP ? OR LOWER(COALESCE(a.description, '')) REGEXP ?
                ");
                $matches->execute([$regex, $regex, $regex, $regex]);
                foreach ($matches->fetchAll(\PDO::FETCH_COLUMN) as $pid) {
                    $matchedIds[$pid] = true;
                    if (!isset($matchedKeyword[$pid])) $matchedKeyword[$pid] = $kw;
                }
            }

            // Remove auto-assigned members that no longer match any keyword
            if (!empty($matchedIds)) {
                $ph = implode(',', array_fill(0, count($matchedIds), '?'));
                $pdo->prepare("DELETE FROM app_group_members WHERE group_id = ? AND auto_assigned = 1 AND product_id NOT IN ($ph)")
                    ->execute(array_merge([$groupId], array_keys($matchedIds)));
            } else {
                $pdo->prepare("DELETE FROM app_group_members WHERE group_id = ? AND auto_assigned = 1")->execute([$groupId]);
            }

            // Add new matches
            foreach ($matchedKeyword as $pid => $kw) {
                $insertStmt->execute([$groupId, $pid, $kw]);
                if ($insertStmt->rowCount() > 0) $assigned++;
            }
        }

        if ($assigned > 0) {
            $this->log("Auto-assigned {$assigned} products to app groups");
        }
        return $assigned;
    }

    /**
     * Auto-assign unassigned videos to video groups based on keyword matching.
     */
    public function autoAssignVideoGroups(): int
    {
        $pdo = $this->db->getPdo();

        // Check if video_groups table exists
        try {
            $pdo->query("SELECT 1 FROM video_groups LIMIT 1");
        } catch (\Exception $e) {
            return 0; // Table doesn't exist yet
        }

        $groupKeywords = [];
        $rows = $pdo->query("SELECT g.id, k.keyword FROM video_groups g JOIN video_group_keywords k ON k.group_id = g.id")->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $groupKeywords[$row['id']][] = $row['keyword'];
        }
        if (empty($groupKeywords)) return 0;

        $assigned = 0;
        $insertStmt = $pdo->prepare(
            "INSERT IGNORE INTO video_group_members (group_id, video_id, matched_keyword, auto_assigned) VALUES (?, ?, ?, 1)"
        );

        foreach ($groupKeywords as $groupId => $keywords) {
            $matchedIds = [];
            $matchedKeyword = [];
            foreach ($keywords as $kw) {
                $regex = '(^|[^a-zA-Z])' . preg_quote(strtolower($kw), '/') . '([^a-zA-Z]|$)';
                $matches = $pdo->prepare("
                    SELECT video_id FROM youtube_metadata
                    WHERE LOWER(COALESCE(title, '')) REGEXP ?
                       OR LOWER(COALESCE(channel_name, '')) REGEXP ?
                       OR LOWER(COALESCE(description, '')) REGEXP ?
                ");
                $matches->execute([$regex, $regex, $regex]);
                foreach ($matches->fetchAll(\PDO::FETCH_COLUMN) as $vid) {
                    $matchedIds[$vid] = true;
                    if (!isset($matchedKeyword[$vid])) $matchedKeyword[$vid] = $kw;
                }
            }

            // Remove auto-assigned members that no longer match any keyword
            if (!empty($matchedIds)) {
                $ph = implode(',', array_fill(0, count($matchedIds), '?'));
                $pdo->prepare("DELETE FROM video_group_members WHERE group_id = ? AND auto_assigned = 1 AND video_id NOT IN ($ph)")
                    ->execute(array_merge([$groupId], array_keys($matchedIds)));
            } else {
                $pdo->prepare("DELETE FROM video_group_members WHERE group_id = ? AND auto_assigned = 1")->execute([$groupId]);
            }

            // Add new matches
            foreach ($matchedKeyword as $vid => $kw) {
                $insertStmt->execute([$groupId, $vid, $kw]);
                if ($insertStmt->rowCount() > 0) $assigned++;
            }
        }

        if ($assigned > 0) {
            $this->log("Auto-assigned {$assigned} videos to video groups");
        }
        return $assigned;
    }

    /**
     * Re-detect products that were incorrectly classified as 'web'.
     * Removes old web product mappings and re-processes those ads.
     * Returns number of ads re-mapped.
     */
    public function redetectWebProducts()
    {
        // Find ads mapped to web products that might actually be apps
        $webAds = $this->db->fetchAll(
            "SELECT a.creative_id, a.advertiser_id, d.headline, d.landing_url,
                    (SELECT GROUP_CONCAT(ass.original_url SEPARATOR '||')
                     FROM ad_assets ass WHERE ass.creative_id = a.creative_id) as all_asset_urls,
                    (SELECT GROUP_CONCAT(DISTINCT t.platform SEPARATOR '||')
                     FROM ad_targeting t WHERE t.creative_id = a.creative_id) as ad_platforms,
                    pm.id as map_id, pm.product_id
             FROM ads a
             INNER JOIN ad_product_map pm ON a.creative_id = pm.creative_id
             INNER JOIN ad_products p ON pm.product_id = p.id AND p.store_platform = 'web'
             LEFT JOIN ad_details d ON a.creative_id = d.creative_id
                 AND d.id = (SELECT MAX(id) FROM ad_details WHERE creative_id = a.creative_id)
             ORDER BY a.advertiser_id, a.last_seen DESC
             LIMIT 100"
        );

        if (empty($webAds)) {
            return 0;
        }

        $advertiserStoreUrls = $this->preloadStoreUrlsFromPayloads($webAds);
        $remapped = 0;

        foreach ($webAds as $ad) {
            $result = $this->detectProductForAd($ad, $advertiserStoreUrls);

            // Re-map if we now detect it as an app (ios or playstore)
            if ($result['platform'] !== 'web') {
                // Remove old web mapping
                $this->db->query("DELETE FROM ad_product_map WHERE id = ?", [$ad['map_id']]);

                $productId = $this->findOrCreateProduct(
                    $ad['advertiser_id'],
                    $result['name'],
                    $result['type'],
                    $result['platform'],
                    $result['url']
                );

                if ($productId) {
                    $this->db->insert('ad_product_map', [
                        'creative_id' => $ad['creative_id'],
                        'product_id'  => $productId,
                    ]);
                    $remapped++;
                }
            }
        }

        // Clean up orphaned web products with no remaining mappings
        $this->db->query(
            "DELETE FROM ad_products WHERE store_platform = 'web'
             AND id NOT IN (SELECT product_id FROM ad_product_map)"
        );

        $this->log("Re-detected {$remapped} ads from web to app store products");
        return $remapped;
    }

    /**
     * Detect product info for a single ad from all available sources.
     */
    private function detectProductForAd(array $ad, array $advertiserStoreUrls): array
    {
        $productName = null;
        $productType = 'other';
        $storePlatform = 'web';
        $storeUrl = null;

        $headline = isset($ad['headline']) ? trim($ad['headline']) : '';
        $landingUrl = isset($ad['landing_url']) ? trim($ad['landing_url']) : '';
        $allUrls = isset($ad['all_asset_urls']) ? $ad['all_asset_urls'] : '';
        $adPlatforms = isset($ad['ad_platforms']) ? $ad['ad_platforms'] : '';

        // Collect all URLs to search for store links
        $urlsToCheck = $landingUrl;
        if ($allUrls) {
            $urlsToCheck .= '||' . $allUrls;
        }

        // Priority 1: Detect from Play Store / App Store URLs in ad URLs
        $storeResult = $this->extractStoreInfoFromUrls($urlsToCheck);
        if ($storeResult) {
            return $storeResult;
        }

        // Priority 2: Check pre-loaded store URLs from raw payloads for this advertiser
        $advId = $ad['advertiser_id'];
        if (isset($advertiserStoreUrls[$advId])) {
            foreach ($advertiserStoreUrls[$advId] as $payloadStoreUrl) {
                $storeResult = $this->extractStoreInfoFromUrls($payloadStoreUrl);
                if ($storeResult) {
                    return $storeResult;
                }
            }
        }

        // Priority 3: Use ad platform indicator (Google Play = playstore app)
        $isGooglePlay = (stripos($adPlatforms, 'Google Play') !== false);

        // Priority 4: Extract product name from headline
        // Only classify as playstore/ios if there's actual platform evidence (not just keywords)
        if ($headline !== '') {
            $productName = $this->extractProductFromTitle($headline);
            if ($productName && $isGooglePlay) {
                $productType = 'app';
                $storePlatform = 'playstore';
            }
        }

        // Priority 5: Use headline as-is if short enough
        if (!$productName && $headline !== '' && strlen($headline) <= 60) {
            $productName = $headline;
            if ($isGooglePlay) {
                $productType = 'app';
                $storePlatform = 'playstore';
            }
        }

        // Fallback: Unknown
        if (!$productName || $productName === '') {
            $productName = 'Unknown';
            $productType = 'other';
            $storePlatform = 'web';
        }

        // Normalize
        $productName = trim($productName);
        if (strlen($productName) > 255) {
            $productName = substr($productName, 0, 252) . '...';
        }

        return [
            'name'     => $productName,
            'type'     => $productType,
            'platform' => $storePlatform,
            'url'      => $storeUrl,
        ];
    }

    /**
     * Extract Play Store / App Store info from a URL string.
     * Returns associative array with name/type/platform/url or null if not found.
     */
    private function extractStoreInfoFromUrls($urlsToCheck)
    {
        if (!$urlsToCheck) return null;

        // Check for Play Store URLs (handle both normal and escaped slashes)
        if (preg_match('/play\.google\.com(?:\\\\\/|\/)+store(?:\\\\\/|\/)+apps(?:\\\\\/|\/)+details\?id=([^&\s|"\'\\\\]+)/', $urlsToCheck, $m)) {
            $packageName = $m[1];
            return [
                'name'     => $this->packageToAppName($packageName),
                'type'     => 'app',
                'platform' => 'playstore',
                'url'      => 'https://play.google.com/store/apps/details?id=' . $packageName,
            ];
        }

        // Check for App Store URLs (handle both normal and escaped slashes)
        if (preg_match('/apps\.apple\.com(?:\\\\\/|\/)+(?:[a-z]{2}(?:\\\\\/|\/)+)?app(?:\\\\\/|\/)+(?:([^\/\s|"\'\\\\]+)(?:\\\\\/|\/)+)?id(\d+)/', $urlsToCheck, $m)) {
            $slug = isset($m[1]) && $m[1] ? $m[1] : '';
            $appId = $m[2];
            $storeUrl = 'https://apps.apple.com/app/id' . $appId;

            // Try to get real name from iTunes API
            $productName = $this->fetchAppStoreAppName($appId);
            if (!$productName && $slug) {
                $productName = ucwords(str_replace('-', ' ', $slug));
            }
            if (!$productName) {
                $productName = 'iOS App ' . $appId;
            }

            return [
                'name'     => $productName,
                'type'     => 'app',
                'platform' => 'ios',
                'url'      => $storeUrl,
            ];
        }

        // Fallback: apps.apple.com without /id pattern
        if (preg_match('/apps\.apple\.com(?:\\\\\/|\/)+[a-z]{2}(?:\\\\\/|\/)+app(?:\\\\\/|\/)+([^\/\s|"\'\\\\]+)/', $urlsToCheck, $m)) {
            $slug = $m[1];
            $productName = ucwords(str_replace('-', ' ', $slug));
            preg_match('/(https?:\/\/apps\.apple\.com\/[^\s|"\'\\\\]+)/', $urlsToCheck, $fullUrl);
            return [
                'name'     => $productName,
                'type'     => 'app',
                'platform' => 'ios',
                'url'      => isset($fullUrl[1]) ? stripslashes($fullUrl[1]) : null,
            ];
        }

        return null;
    }

    /**
     * Pre-load store URLs from raw payloads for the advertisers in the given ads batch.
     * Uses indexed advertiser_id column instead of slow LIKE search on raw_json.
     * Returns: [advertiser_id => [store_url1, store_url2, ...]]
     */
    private function preloadStoreUrlsFromPayloads(array $ads): array
    {
        // Collect unique advertiser IDs
        $advertiserIds = array_unique(array_column($ads, 'advertiser_id'));
        if (empty($advertiserIds)) return [];

        $result = [];
        foreach ($advertiserIds as $advId) {
            // Use indexed advertiser_id column (fast query)
            $rows = $this->db->fetchAll(
                "SELECT raw_json FROM raw_payloads WHERE advertiser_id = ? ORDER BY id DESC LIMIT 5",
                [$advId]
            );

            $urls = [];
            foreach ($rows as $row) {
                $json = $row['raw_json'];
                // Find all Play Store URLs (handle escaped slashes in JSON)
                if (preg_match_all('/play\.google\.com(?:\\\\\/|\/)+store(?:\\\\\/|\/)+apps(?:\\\\\/|\/)+details\?id=([^&\s"\'\\\\]+)/', $json, $matches)) {
                    foreach ($matches[0] as $match) {
                        $urls[] = $match;
                    }
                }
                // Find all App Store URLs
                if (preg_match_all('/(https?:(?:\\\\\/|\/)+(?:\\\\\/|\/)*apps\.apple\.com(?:\\\\\/|\/)+[^\s"\'\\\\]+)/', $json, $matches)) {
                    foreach ($matches[0] as $match) {
                        $urls[] = stripslashes($match);
                    }
                }
            }

            if (!empty($urls)) {
                $result[$advId] = array_unique($urls);
            }
        }

        return $result;
    }

    /**
     * Extract a product/app name from a YouTube video title.
     * Looks for common patterns like "AppName - Feature", "AppName | Trailer", etc.
     */
    private function extractProductFromTitle($title)
    {
        $title = trim($title);
        if ($title === '') return null;

        // Common separators: " - ", " | ", " : ", " — "
        $separators = array(' - ', ' | ', ' : ', " \xe2\x80\x94 ", ' // ');
        foreach ($separators as $sep) {
            if (strpos($title, $sep) !== false) {
                $parts = explode($sep, $title, 2);
                $first = trim($parts[0]);
                // The product name is usually the first part before separator
                if (strlen($first) >= 2 && strlen($first) <= 80) {
                    return $first;
                }
            }
        }

        // If title is short (< 50 chars), it's likely just the product name
        if (strlen($title) <= 50) {
            return $title;
        }

        // Take first meaningful phrase (before first comma or period)
        if (preg_match('/^([^,\.!]+)/', $title, $m)) {
            $phrase = trim($m[1]);
            if (strlen($phrase) >= 2 && strlen($phrase) <= 80) {
                return $phrase;
            }
        }

        return null;
    }

    /**
     * Convert Android package name to a readable app name.
     */
    private function packageToAppName($packageName)
    {
        // com.example.myapp -> My App
        $parts = explode('.', $packageName);
        $last = end($parts);
        // Convert camelCase or snake_case to words
        $name = preg_replace('/([a-z])([A-Z])/', '$1 $2', $last);
        $name = str_replace(array('_', '-'), ' ', $name);
        return ucwords($name);
    }


    /**
     * Find existing product or create a new one.
     */
    private function findOrCreateProduct($advertiserId, $productName, $productType, $storePlatform, $storeUrl)
    {
        // Reject JS error text and video filenames as product names
        if ($productName && preg_match('/Cannot find|global object|Error\(|undefined|function\s*\(/i', $productName)) {
            $productName = 'Unknown';
        }
        if ($productName && preg_match('/\.(mp4|mov|avi|webm|mkv)$/i', $productName)) {
            $productName = 'Unknown';
        }
        if ($productName && preg_match('/^GGL_/i', $productName)) {
            $productName = 'Unknown';
        }

        // Match by store_url first (most reliable), then by name
        $existing = null;
        if ($storeUrl) {
            $existing = $this->db->fetchOne(
                "SELECT id FROM ad_products WHERE advertiser_id = ? AND store_url = ?",
                [$advertiserId, $storeUrl]
            );
        }
        if (!$existing) {
            $existing = $this->db->fetchOne(
                "SELECT id FROM ad_products WHERE advertiser_id = ? AND product_name = ?",
                [$advertiserId, $productName]
            );
        }

        if ($existing) {
            // Update store_url/platform if we now have better info
            $updateData = [];
            if ($storeUrl) {
                $updateData['store_url'] = $storeUrl;
            }
            if ($storePlatform !== 'web') {
                $updateData['store_platform'] = $storePlatform;
                $updateData['product_type'] = $productType;
            }
            if (!empty($updateData)) {
                $this->db->update('ad_products', $updateData, 'id = ?', [$existing['id']]);
            }
            return $existing['id'];
        }

        $lastId = $this->db->insert('ad_products', [
            'advertiser_id'  => $advertiserId,
            'product_name'   => $productName,
            'product_type'   => $productType,
            'store_platform' => $storePlatform,
            'store_url'      => $storeUrl,
        ]);

        return $lastId ? (int) $lastId : null;
    }

    /**
     * Save YouTube metadata to the youtube_metadata table.
     */
    private function saveYouTubeMetadata(string $videoId, array $meta): void
    {
        $existing = $this->db->fetchOne(
            "SELECT id FROM youtube_metadata WHERE video_id = ?",
            [$videoId]
        );

        $data = [
            'video_id'      => $videoId,
            'title'         => $meta['title'] ?? null,
            'channel_name'  => $meta['channel_name'] ?? $meta['author'] ?? null,
            'view_count'    => $meta['view_count'] ?? 0,
            'thumbnail_url' => $meta['thumbnail_url'] ?? $meta['thumbnail'] ?? ('https://i.ytimg.com/vi/' . $videoId . '/hqdefault.jpg'),
            'fetched_at'    => date('Y-m-d H:i:s'),
        ];

        if ($existing) {
            unset($data['video_id']);
            $this->db->update('youtube_metadata', $data, 'id = ?', [$existing['id']]);
        } else {
            $this->db->insert('youtube_metadata', $data);
        }
    }

    /**
     * Fetch and save app metadata from App Store / Play Store.
     * Populates the app_metadata table for products that don't have metadata yet.
     */
    public function enrichAppMetadata()
    {
        $products = $this->db->fetchAll(
            "SELECT p.id AS product_id, p.store_platform, p.store_url, p.product_name
             FROM ad_products p
             LEFT JOIN app_metadata am ON am.product_id = p.id
             WHERE p.store_platform IN ('ios', 'playstore')
               AND p.store_url IS NOT NULL AND p.store_url != '' AND p.store_url != 'not_found'
               AND am.id IS NULL
             ORDER BY (SELECT COUNT(*) FROM ad_product_map pm WHERE pm.product_id = p.id) DESC
             LIMIT 50"
        );

        if (empty($products)) return 0;

        $enriched = 0;

        foreach ($products as $product) {
            $meta = null;

            if ($product['store_platform'] === 'ios') {
                $meta = $this->fetchAppStoreMetadata($product['store_url']);
            } elseif ($product['store_platform'] === 'playstore') {
                $meta = $this->fetchPlayStoreMetadata($product['store_url']);
            }

            if (!$meta) {
                // Insert minimal record to avoid retrying
                $this->db->insert('app_metadata', [
                    'product_id'     => $product['product_id'],
                    'store_platform' => $product['store_platform'],
                    'store_url'      => $product['store_url'],
                    'app_name'       => $product['product_name'],
                    'fetched_at'     => date('Y-m-d H:i:s'),
                ]);
                continue;
            }

            $this->db->insert('app_metadata', array_merge($meta, [
                'product_id'     => $product['product_id'],
                'store_platform' => $product['store_platform'],
                'store_url'      => $product['store_url'],
                'fetched_at'     => date('Y-m-d H:i:s'),
            ]));

            // Update product name if we got a better one
            if (!empty($meta['app_name']) && $meta['app_name'] !== $product['product_name']) {
                $this->db->update('ad_products', [
                    'product_name' => $meta['app_name'],
                ], 'id = ?', [$product['product_id']]);
            }

            $enriched++;
            usleep(500000); // 500ms rate limit
        }

        $this->log("Enriched metadata for {$enriched} apps");
        return $enriched;
    }

    /**
     * Fetch iOS app metadata from iTunes Lookup API.
     */
    private function fetchAppStoreMetadata(string $storeUrl): ?array
    {
        if (!preg_match('/id(\d+)/', $storeUrl, $m)) return null;
        $appId = $m[1];

        $url = 'https://itunes.apple.com/lookup?id=' . $appId;
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!$resp || $code !== 200) return null;
        $data = json_decode($resp, true);
        if (!$data || empty($data['results'][0])) return null;

        $r = $data['results'][0];
        $screenshots = [];
        if (!empty($r['screenshotUrls'])) $screenshots = array_slice($r['screenshotUrls'], 0, 8);

        return [
            'bundle_id'      => $r['bundleId'] ?? null,
            'app_name'       => $r['trackName'] ?? null,
            'icon_url'       => $r['artworkUrl512'] ?? $r['artworkUrl100'] ?? null,
            'developer_name' => $r['artistName'] ?? null,
            'developer_url'  => $r['artistViewUrl'] ?? null,
            'description'    => isset($r['description']) ? mb_substr($r['description'], 0, 5000) : null,
            'category'       => $r['primaryGenreName'] ?? null,
            'rating'         => $r['averageUserRating'] ?? null,
            'rating_count'   => $r['userRatingCount'] ?? 0,
            'price'          => isset($r['formattedPrice']) ? $r['formattedPrice'] : ($r['price'] == 0 ? 'Free' : '$' . $r['price']),
            'release_date'   => isset($r['releaseDate']) ? date('Y-m-d', strtotime($r['releaseDate'])) : null,
            'last_updated'   => isset($r['currentVersionReleaseDate']) ? date('Y-m-d', strtotime($r['currentVersionReleaseDate'])) : null,
            'version'        => $r['version'] ?? null,
            'screenshots'    => json_encode($screenshots),
        ];
    }

    /**
     * Fetch Android app metadata from Play Store page scraping.
     */
    private function fetchPlayStoreMetadata(string $storeUrl): ?array
    {
        if (!preg_match('/id=([a-zA-Z0-9._]+)/', $storeUrl, $m)) return null;
        $packageName = $m[1];

        $url = 'https://play.google.com/store/apps/details?id=' . $packageName . '&hl=en';
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
        ]);
        $html = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!$html || $code !== 200) return null;

        $meta = [
            'bundle_id'      => $packageName,
            'app_name'       => null,
            'icon_url'       => null,
            'developer_name' => null,
            'description'    => null,
            'category'       => null,
            'rating'         => null,
            'rating_count'   => 0,
            'price'          => 'Free',
            'downloads'      => null,
            'screenshots'    => null,
        ];

        // Title
        if (preg_match('/<title>([^<]+?)(?:\s*-\s*Apps on Google Play)?<\/title>/i', $html, $tm)) {
            $meta['app_name'] = trim($tm[1]);
        }

        // Icon (og:image or itemprop image)
        if (preg_match('/property="og:image"\s+content="([^"]+)"/i', $html, $im)) {
            $meta['icon_url'] = $im[1];
        }

        // Developer
        if (preg_match('/class="Vbfug auoIOc"[^>]*><a[^>]*><span>([^<]+)<\/span>/i', $html, $dm)) {
            $meta['developer_name'] = $dm[1];
        }

        // Rating
        if (preg_match('/itemprop="starRating"[^>]*>.*?(\d+\.?\d*)/s', $html, $rm)) {
            $meta['rating'] = floatval($rm[1]);
        } elseif (preg_match('/"ratingValue":"(\d+\.?\d*)"/', $html, $rm)) {
            $meta['rating'] = floatval($rm[1]);
        }

        // Downloads
        if (preg_match('/(\d[\d,]*\+?)\s*downloads/i', $html, $dlm)) {
            $meta['downloads'] = $dlm[1];
        } elseif (preg_match('/"numDownloads":"([^"]+)"/', $html, $dlm)) {
            $meta['downloads'] = $dlm[1];
        }

        // Category
        if (preg_match('/itemprop="genre"[^>]*content="([^"]+)"/i', $html, $gm)) {
            $meta['category'] = $gm[1];
        } elseif (preg_match('/"genre":"([^"]+)"/', $html, $gm)) {
            $meta['category'] = $gm[1];
        }

        // Description from meta
        if (preg_match('/property="og:description"\s+content="([^"]+)"/i', $html, $descm)) {
            $meta['description'] = html_entity_decode($descm[1], ENT_QUOTES, 'UTF-8');
        }

        // Screenshots from srcset of img[data-screenshot-item-index]
        $screenshots = [];
        if (preg_match_all('/img[^>]+srcset="([^"]+)"[^>]+data-screenshot/i', $html, $ssm)) {
            foreach ($ssm[1] as $srcset) {
                $parts = explode(',', $srcset);
                $lastPart = trim(end($parts));
                $imgUrl = preg_replace('/\s+\d+w$/', '', $lastPart);
                if ($imgUrl) $screenshots[] = $imgUrl;
            }
        }
        if (!empty($screenshots)) {
            $meta['screenshots'] = json_encode(array_slice($screenshots, 0, 8));
        }

        return $meta;
    }

    /**
     * Fetch YouTube video metadata: title, author, view count.
     */
    private function fetchYouTubeMetadata($videoId)
    {
        $result = ['title' => null, 'author' => null, 'view_count' => null, 'thumbnail' => null];

        // Step 1: oEmbed for title and author
        $oembedUrl = 'https://www.youtube.com/oembed?url=https://www.youtube.com/watch?v=' . $videoId . '&format=json';
        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL            => $oembedUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
            CURLOPT_ENCODING       => 'gzip, deflate',
        ));
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($resp && $code === 200) {
            $data = json_decode($resp, true);
            if ($data) {
                $result['title'] = isset($data['title']) ? $data['title'] : null;
                $result['author'] = isset($data['author_name']) ? $data['author_name'] : null;
                $result['thumbnail'] = isset($data['thumbnail_url']) ? $data['thumbnail_url'] : null;
            }
        }

        // Step 2: Fetch watch page for view count
        $watchUrl = 'https://www.youtube.com/watch?v=' . $videoId;
        $ch2 = curl_init();
        curl_setopt_array($ch2, array(
            CURLOPT_URL            => $watchUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
            CURLOPT_ENCODING       => 'gzip, deflate',
        ));
        $html = curl_exec($ch2);
        $code2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
        curl_close($ch2);

        if ($html && $code2 === 200) {
            if (preg_match('/"viewCount":"(\d+)"/', $html, $m)) {
                $result['view_count'] = (int)$m[1];
            }
        }

        // Return null if we got nothing useful
        if (!$result['title'] && $result['view_count'] === null) {
            return null;
        }

        return $result;
    }
}
