<?php

/**
 * Scraper - Google Ads Transparency Center Data Collector
 *
 * Fetches advertiser data from the Google Ads Transparency Center
 * reverse-engineered API, handles pagination, and stores raw payloads.
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
                $this->log("Failed to fetch page {$pageCount} for {$advertiserId}");
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
     * Fetch a single page of ads for an advertiser.
     */
    private function fetchPage(string $advertiserId, ?string $pageToken = null): ?array
    {
        $params = [
            'advertiser_id' => $advertiserId,
        ];

        if ($pageToken !== null) {
            $params['page_token'] = $pageToken;
        }

        $url = $this->baseUrl . '/anji/_/rpc/SearchCreativeService/SearchCreatives';
        $jsonResponse = $this->makeRequest($url, $params);

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
     * Make an HTTP request with retry logic.
     */
    private function makeRequest(string $url, array $params): ?string
    {
        $maxRetries = $this->config['max_retries'] ?? 3;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            $ch = curl_init();

            curl_setopt_array($ch, [
                CURLOPT_URL            => $url,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => json_encode($params),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/json',
                    'Accept: application/json',
                    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                ],
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_FOLLOWLOCATION => true,
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
                // Exponential backoff on failure
                sleep(pow(2, $attempt));
            }
        }

        return null;
    }

    /**
     * Extract ads array from the API response structure.
     */
    private function extractAdsFromResponse(array $response): array
    {
        // The response structure from Google Ads Transparency Center
        // is nested - adapt based on actual API structure
        $ads = [];

        $creatives = $response['creatives'] ?? $response[1] ?? [];

        if (!is_array($creatives)) {
            return $ads;
        }

        foreach ($creatives as $creative) {
            $ad = [
                'creative_id'   => $creative['creativeId'] ?? $creative[0] ?? null,
                'advertiser_id' => $creative['advertiserId'] ?? $creative[1] ?? null,
                'ad_type'       => $this->determineAdType($creative),
                'headline'      => $creative['headline'] ?? $creative[2] ?? null,
                'description'   => $creative['description'] ?? $creative[3] ?? null,
                'cta'           => $creative['callToAction'] ?? $creative[4] ?? null,
                'landing_url'   => $creative['landingPageUrl'] ?? $creative[5] ?? null,
                'assets'        => $this->extractAssets($creative),
                'countries'     => $this->extractCountries($creative),
                'platforms'     => $this->extractPlatforms($creative),
                'first_seen'    => $creative['firstShown'] ?? $creative[8] ?? null,
                'last_seen'     => $creative['lastShown'] ?? $creative[9] ?? null,
            ];

            if ($ad['creative_id'] !== null) {
                $ads[] = $ad;
            }
        }

        return $ads;
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

        // Detect from assets
        $assets = $creative['assets'] ?? $creative['mediaAssets'] ?? [];
        if (!empty($assets)) {
            foreach ($assets as $asset) {
                $assetType = $asset['type'] ?? '';
                if (stripos($assetType, 'video') !== false) {
                    return 'video';
                }
                if (stripos($assetType, 'image') !== false) {
                    return 'image';
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
        $rawAssets = $creative['assets'] ?? $creative['mediaAssets'] ?? $creative[6] ?? [];

        if (!is_array($rawAssets)) {
            return $assets;
        }

        foreach ($rawAssets as $asset) {
            $assets[] = [
                'type' => $asset['type'] ?? 'image',
                'url'  => $asset['url'] ?? $asset['imageUrl'] ?? $asset['videoUrl'] ?? null,
            ];
        }

        return $assets;
    }

    /**
     * Extract targeted countries from creative data.
     */
    private function extractCountries(array $creative): array
    {
        $countries = $creative['countries'] ?? $creative['targetedCountries'] ?? $creative[10] ?? [];
        return is_array($countries) ? $countries : [];
    }

    /**
     * Extract platforms from creative data.
     */
    private function extractPlatforms(array $creative): array
    {
        $platforms = $creative['platforms'] ?? $creative['adPlatforms'] ?? $creative[11] ?? [];
        return is_array($platforms) ? $platforms : [];
    }

    /**
     * Extract the next page token for pagination.
     */
    private function extractNextPageToken(array $response): ?string
    {
        $token = $response['nextPageToken'] ?? $response['paginationToken'] ?? $response[2] ?? null;
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
