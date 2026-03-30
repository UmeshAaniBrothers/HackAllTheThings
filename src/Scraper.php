<?php

/**
 * Scraper - Google Ads Transparency Center Data Collector
 *
 * Fetches advertiser data from the Google Ads Transparency Center
 * reverse-engineered API, handles pagination, and stores raw payloads.
 *
 * API details:
 *   Endpoint: POST https://adstransparency.google.com/anji/_/rpc/SearchService/SearchCreatives?authuser=0
 *   Body: application/x-www-form-urlencoded with f.req= containing protobuf-style JSON
 *   Response: JSON with numeric keys ("1" = creatives array, "2" = next page token)
 */
class Scraper
{
    private Database $db;
    private array $config;
    private string $baseUrl = 'https://adstransparency.google.com';

    public function __construct(Database $db, array $config)
    {
        $this->db = $db;
        $this->config = $config;
    }

    /**
     * Fetch all ads for a given advertiser, following pagination.
     */
    public function fetchAdvertiser(string $advertiserId): array
    {
        $allAds = [];
        $pageToken = null;
        $pageCount = 0;

        $this->log("Starting fetch for advertiser: {$advertiserId}");

        do {
            $response = $this->fetchPage($advertiserId, $pageToken);

            if ($response === null) {
                $this->log("Failed to fetch page " . ($pageCount + 1) . " for {$advertiserId}");
                break;
            }

            // Store raw payload
            $this->storeRawPayload($advertiserId, json_encode($response));

            // Extract ads from response
            $ads = $this->extractAdsFromResponse($response);
            $allAds = array_merge($allAds, $ads);

            // Get next page token
            $pageToken = $this->extractNextPageToken($response);
            $pageCount++;

            $this->log("Page {$pageCount}: found " . count($ads) . " ads");
        } while ($pageToken !== null);

        $this->log("Completed fetch for {$advertiserId}: " . count($allAds) . " total ads across {$pageCount} pages");

        return $allAds;
    }

    /**
     * Search for advertisers by keyword using SearchSuggestions endpoint.
     */
    public function searchAdvertisers(string $keyword, int $limit = 10): array
    {
        $url = $this->baseUrl . '/anji/_/rpc/SearchService/SearchSuggestions?authuser=0';

        $freqJson = json_encode([
            '1' => $keyword,
            '2' => $limit,
            '3' => $limit,
        ]);

        $body = 'f.req=' . urlencode($freqJson);
        $response = $this->makeRequest($url, $body);

        if ($response === null) {
            return [];
        }

        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            return [];
        }

        $results = [];
        $suggestions = $decoded['1'] ?? $decoded[1] ?? [];
        if (is_array($suggestions)) {
            foreach ($suggestions as $item) {
                $results[] = [
                    'advertiser_id' => $item['1'] ?? $item[1] ?? null,
                    'name'          => $item['2'] ?? $item[2] ?? null,
                ];
            }
        }

        return array_filter($results, fn($r) => $r['advertiser_id'] !== null);
    }

    /**
     * Fetch a single page of ads for an advertiser.
     */
    private function fetchPage(string $advertiserId, ?string $pageToken = null): ?array
    {
        $url = $this->baseUrl . '/anji/_/rpc/SearchService/SearchCreatives?authuser=0';

        // Build the protobuf-style JSON payload
        $freqData = [
            '2' => 100,  // results per page (max 100)
            '3' => [
                '12' => ['1' => '', '2' => true],
                '13' => ['1' => [$advertiserId]],
            ],
            '7' => ['1' => 1],
        ];

        if ($pageToken !== null) {
            $freqData['4'] = $pageToken;
        }

        $freqJson = json_encode($freqData);
        $body = 'f.req=' . urlencode($freqJson);

        $jsonResponse = $this->makeRequest($url, $body);

        if ($jsonResponse === null) {
            return null;
        }

        $decoded = json_decode($jsonResponse, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->log("JSON decode error: " . json_last_error_msg());
            return null;
        }

        return $decoded;
    }

    /**
     * Make an HTTP POST request with retry logic.
     * Body is already URL-encoded (f.req=...).
     */
    private function makeRequest(string $url, string $body): ?string
    {
        $maxRetries = $this->config['max_retries'] ?? 3;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            $ch = curl_init();

            curl_setopt_array($ch, [
                CURLOPT_URL            => $url,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $body,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/x-www-form-urlencoded',
                    'Accept: */*',
                    'Accept-Language: en-US,en;q=0.9',
                    'Origin: https://adstransparency.google.com',
                    'Referer: https://adstransparency.google.com/',
                ],
                CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_ENCODING       => 'gzip, deflate, br',
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($response !== false && $httpCode === 200) {
                return $response;
            }

            $this->log("Request attempt {$attempt}/{$maxRetries} failed. HTTP: {$httpCode}, Error: {$curlError}");

            if ($attempt < $maxRetries) {
                sleep(pow(2, $attempt));
            }
        }

        return null;
    }

    /**
     * Extract ads array from the API response (protobuf-style numeric keys).
     */
    private function extractAdsFromResponse(array $response): array
    {
        $ads = [];

        // Response: "1" = array of creative wrappers, each has "2" = creative data
        $creatives = $response['1'] ?? $response[1] ?? [];

        if (!is_array($creatives)) {
            return $ads;
        }

        foreach ($creatives as $wrapper) {
            // Each item in the array may be wrapped: {"2": {creative data}}
            $creative = $wrapper['2'] ?? $wrapper[2] ?? $wrapper;

            if (!is_array($creative)) {
                continue;
            }

            $ad = $this->parseCreative($creative);
            if ($ad['creative_id'] !== null) {
                $ads[] = $ad;
            }
        }

        return $ads;
    }

    /**
     * Parse a single creative from protobuf-style response into structured array.
     */
    private function parseCreative(array $c): array
    {
        // Try both named keys and numeric keys
        $creativeId  = $c['creativeId']    ?? $c['1'] ?? $c[1] ?? $c[0] ?? null;
        $advertiserId = $c['advertiserId'] ?? $c['2'] ?? $c[2] ?? $c[1] ?? null;
        $headline    = $c['headline']      ?? $c['3'] ?? $c[3] ?? $c[2] ?? null;
        $description = $c['description']   ?? $c['4'] ?? $c[4] ?? $c[3] ?? null;
        $cta         = $c['callToAction']  ?? $c['5'] ?? $c[5] ?? $c[4] ?? null;
        $landingUrl  = $c['landingPageUrl'] ?? $c['6'] ?? $c[6] ?? $c[5] ?? null;
        $firstSeen   = $c['firstShown']    ?? $c['9'] ?? $c[9] ?? $c[8] ?? null;
        $lastSeen    = $c['lastShown']     ?? $c['10'] ?? $c[10] ?? $c[9] ?? null;

        // Extract nested text content if present
        if (is_array($headline)) {
            $headline = $headline['1'] ?? $headline[1] ?? $headline[0] ?? json_encode($headline);
        }
        if (is_array($description)) {
            $description = $description['1'] ?? $description[1] ?? $description[0] ?? json_encode($description);
        }
        if (is_array($cta)) {
            $cta = $cta['1'] ?? $cta[1] ?? $cta[0] ?? json_encode($cta);
        }
        if (is_array($landingUrl)) {
            $landingUrl = $landingUrl['1'] ?? $landingUrl[1] ?? $landingUrl[0] ?? null;
        }

        // Ensure creative_id is a string
        if (is_array($creativeId)) {
            $creativeId = $creativeId['1'] ?? $creativeId[1] ?? $creativeId[0] ?? null;
        }
        if (is_array($advertiserId)) {
            $advertiserId = $advertiserId['1'] ?? $advertiserId[1] ?? $advertiserId[0] ?? null;
        }

        return [
            'creative_id'   => is_string($creativeId) ? $creativeId : null,
            'advertiser_id' => is_string($advertiserId) ? $advertiserId : null,
            'ad_type'       => $this->determineAdType($c),
            'headline'      => is_string($headline) ? $headline : null,
            'description'   => is_string($description) ? $description : null,
            'cta'           => is_string($cta) ? $cta : null,
            'landing_url'   => is_string($landingUrl) ? $landingUrl : null,
            'assets'        => $this->extractAssets($c),
            'countries'     => $this->extractCountries($c),
            'platforms'     => $this->extractPlatforms($c),
            'first_seen'    => $firstSeen,
            'last_seen'     => $lastSeen,
        ];
    }

    /**
     * Determine ad type from creative data.
     */
    private function determineAdType(array $creative): string
    {
        $type = $creative['type'] ?? $creative['adType'] ?? $creative['11'] ?? $creative[11] ?? null;

        if (is_string($type)) {
            $type = strtolower($type);
            if (in_array($type, ['text', 'image', 'video'])) {
                return $type;
            }
        }

        // Numeric type mapping from protobuf
        if (is_numeric($type)) {
            $typeMap = [1 => 'text', 2 => 'image', 3 => 'video'];
            if (isset($typeMap[(int)$type])) {
                return $typeMap[(int)$type];
            }
        }

        // Detect from assets
        $assets = $creative['assets'] ?? $creative['mediaAssets'] ?? $creative['7'] ?? $creative[7] ?? $creative['8'] ?? $creative[8] ?? [];
        if (is_array($assets)) {
            foreach ($assets as $asset) {
                if (!is_array($asset)) continue;
                $assetType = $asset['type'] ?? $asset['1'] ?? $asset[1] ?? '';
                $assetUrl = $asset['url'] ?? $asset['2'] ?? $asset[2] ?? $asset['3'] ?? $asset[3] ?? '';

                if (is_string($assetType) && stripos($assetType, 'video') !== false) return 'video';
                if (is_string($assetUrl)) {
                    if (preg_match('/\.(mp4|webm|avi|mov)/i', $assetUrl)) return 'video';
                    if (preg_match('/youtube\.com|youtu\.be/i', $assetUrl)) return 'video';
                    if (preg_match('/\.(jpg|jpeg|png|gif|webp|svg)/i', $assetUrl)) return 'image';
                }
                if (is_numeric($assetType)) {
                    if ((int)$assetType === 2 || (int)$assetType === 3) return 'video';
                    if ((int)$assetType === 1) return 'image';
                }
            }
        }

        return 'text';
    }

    /**
     * Extract asset URLs from creative data.
     */
    private function extractAssets(array $creative): array
    {
        $assets = [];
        // Try multiple possible keys for assets
        $rawAssets = $creative['assets'] ?? $creative['mediaAssets']
            ?? $creative['7'] ?? $creative[7]
            ?? $creative['8'] ?? $creative[8]
            ?? $creative['6'] ?? $creative[6]
            ?? [];

        if (!is_array($rawAssets)) {
            return $assets;
        }

        foreach ($rawAssets as $asset) {
            if (!is_array($asset)) {
                // Could be a direct URL string
                if (is_string($asset) && (str_starts_with($asset, 'http') || str_starts_with($asset, '//'))) {
                    $assets[] = ['type' => 'image', 'url' => $asset];
                }
                continue;
            }

            $type = $asset['type'] ?? $asset['1'] ?? $asset[1] ?? 'image';
            $url = $asset['url'] ?? $asset['imageUrl'] ?? $asset['videoUrl']
                ?? $asset['2'] ?? $asset[2]
                ?? $asset['3'] ?? $asset[3]
                ?? null;

            if (is_array($url)) {
                $url = $url['1'] ?? $url[1] ?? $url[0] ?? null;
            }

            // Convert numeric type
            if (is_numeric($type)) {
                $type = match((int)$type) {
                    1 => 'image',
                    2, 3 => 'video',
                    default => 'image',
                };
            }

            $entry = [
                'type' => is_string($type) ? $type : 'image',
                'url'  => is_string($url) ? $url : null,
            ];

            if (isset($asset['base64']) || isset($asset['encodedData'])) {
                $entry['base64'] = $asset['base64'] ?? $asset['encodedData'];
            }

            if ($entry['url'] !== null || isset($entry['base64'])) {
                $assets[] = $entry;
            }
        }

        return $assets;
    }

    /**
     * Extract targeted countries from creative data.
     */
    private function extractCountries(array $creative): array
    {
        $countries = $creative['countries'] ?? $creative['targetedCountries']
            ?? $creative['10'] ?? $creative[10]
            ?? $creative['12'] ?? $creative[12]
            ?? $creative['14'] ?? $creative[14]
            ?? [];

        if (!is_array($countries)) {
            return [];
        }

        // Flatten if nested
        $result = [];
        foreach ($countries as $c) {
            if (is_string($c) && !empty($c)) {
                $result[] = $c;
            } elseif (is_array($c)) {
                $val = $c['1'] ?? $c[1] ?? $c[0] ?? null;
                if (is_string($val) && !empty($val)) {
                    $result[] = $val;
                }
            }
        }

        return $result;
    }

    /**
     * Extract platforms from creative data.
     */
    private function extractPlatforms(array $creative): array
    {
        $platforms = $creative['platforms'] ?? $creative['adPlatforms']
            ?? $creative['11'] ?? $creative[11]
            ?? $creative['13'] ?? $creative[13]
            ?? $creative['15'] ?? $creative[15]
            ?? [];

        if (!is_array($platforms)) {
            return [];
        }

        $result = [];
        foreach ($platforms as $p) {
            if (is_string($p) && !empty($p)) {
                $result[] = $p;
            } elseif (is_array($p)) {
                $val = $p['1'] ?? $p[1] ?? $p[0] ?? null;
                if (is_string($val) && !empty($val)) {
                    $result[] = $val;
                }
            } elseif (is_numeric($p)) {
                // Map numeric platform IDs to names
                $platformMap = [
                    1 => 'Google Search',
                    2 => 'YouTube',
                    3 => 'Google Display',
                    4 => 'Google Shopping',
                    5 => 'Google Maps',
                    6 => 'Google Play',
                ];
                $result[] = $platformMap[(int)$p] ?? 'Platform_' . $p;
            }
        }

        return $result;
    }

    /**
     * Extract the next page token for pagination.
     * In the protobuf response, it's at key "2".
     */
    private function extractNextPageToken(array $response): ?string
    {
        $token = $response['2'] ?? $response[2]
            ?? $response['nextPageToken'] ?? $response['paginationToken']
            ?? null;
        return is_string($token) && $token !== '' ? $token : null;
    }

    /**
     * Store raw API response in the database.
     */
    public function storeRawPayload(string $advertiserId, string $json): string
    {
        return $this->db->insert('raw_payloads', [
            'advertiser_id' => $advertiserId,
            'raw_json'      => $json,
            'processed_flag' => 0,
        ]);
    }

    /**
     * Log a message with timestamp.
     */
    private function log(string $message): void
    {
        $timestamp = date('Y-m-d H:i:s');
        echo "[{$timestamp}] {$message}\n";
    }
}
