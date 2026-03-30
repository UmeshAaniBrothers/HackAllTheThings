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
 *
 * Requires a session cookie obtained by first visiting the main page.
 */
class Scraper
{
    /** @var Database */
    private $db;
    /** @var array */
    private $config;
    /** @var string */
    private $baseUrl = 'https://adstransparency.google.com';
    /** @var string|null */
    private $cookieFile = null;
    /** @var array */
    private $errors = [];

    public function __construct($db, $config)
    {
        $this->db = $db;
        $this->config = $config;
    }

    public function __destruct()
    {
        if ($this->cookieFile && file_exists($this->cookieFile)) {
            @unlink($this->cookieFile);
        }
    }

    /**
     * Get accumulated errors from the last operation.
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * Initialize session by visiting the main page to get cookies.
     * Must be called before any API requests.
     */
    /**
     * @return bool
     */
    public function initSession()
    {
        $this->cookieFile = tempnam(sys_get_temp_dir(), 'gads_cookie_');

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $this->baseUrl . '/?region=anywhere',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_COOKIEJAR      => $this->cookieFile,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_USERAGENT      => $this->getUserAgent(),
            CURLOPT_ENCODING       => 'gzip, deflate, br',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: en-US,en;q=0.9',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            $this->log("Session init failed: HTTP {$httpCode}, Error: {$curlError}");
            $this->errors[] = "Session init failed: HTTP {$httpCode}" . ($curlError ? " ({$curlError})" : "");
            return false;
        }

        // Check if we got a CAPTCHA page
        if (strpos($response, 'recaptcha') !== false || $httpCode === 429) {
            $this->log("Session init blocked by CAPTCHA/rate-limit (HTTP {$httpCode})");
            $this->errors[] = "Blocked by Google CAPTCHA/rate-limit. Try again later or from a different IP.";
            return false;
        }

        $this->log("Session initialized (cookie file: " . basename($this->cookieFile) . ")");
        return true;
    }

    /**
     * Fetch all ads for a given advertiser, following pagination.
     */
    public function fetchAdvertiser($advertiserId)
    {
        $this->errors = [];
        $allAds = [];
        $pageToken = null;
        $pageCount = 0;

        $this->log("Starting fetch for advertiser: {$advertiserId}");

        // Initialize session if not done
        if (!$this->cookieFile) {
            if (!$this->initSession()) {
                $this->log("Cannot fetch: session initialization failed");
                return $allAds;
            }
            // Small delay after session init
            usleep(500000); // 0.5s
        }

        do {
            $response = $this->fetchPage($advertiserId, $pageToken);

            if ($response === null) {
                $this->log("Failed to fetch page " . ($pageCount + 1) . " for {$advertiserId}");
                break;
            }

            // Check for empty response
            if (empty($response)) {
                $this->log("Empty response on page " . ($pageCount + 1) . " — advertiser may not exist or have no ads");
                $this->errors[] = "Empty response from API. The advertiser ID may be invalid or have no ads in the Transparency Center.";
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

            // Rate-limit: delay between pages
            if ($pageToken !== null) {
                $delay = ($this->config['page_delay_ms'] ?? 1500) * 1000; // default 1.5s
                usleep($delay);
            }
        } while ($pageToken !== null);

        $this->log("Completed fetch for {$advertiserId}: " . count($allAds) . " total ads across {$pageCount} pages");

        return $allAds;
    }

    /**
     * Search for advertisers by keyword using SearchSuggestions endpoint.
     */
    public function searchAdvertisers($keyword, $limit = 10)
    {
        $this->errors = [];

        if (!$this->cookieFile) {
            if (!$this->initSession()) {
                return [];
            }
            usleep(300000);
        }

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

        return array_filter($results, function($r) { return $r['advertiser_id'] !== null; });
    }

    /**
     * Test if the API is reachable (no CAPTCHA, no rate-limit).
     */
    public function testConnection()
    {
        $this->errors = [];

        if (!$this->initSession()) {
            return ['ok' => false, 'error' => implode('; ', $this->errors)];
        }

        usleep(300000);

        // Try a small suggestion query
        $url = $this->baseUrl . '/anji/_/rpc/SearchService/SearchSuggestions?authuser=0';
        $body = 'f.req=' . urlencode(json_encode(['1' => 'Google', '2' => 3, '3' => 3]));
        $response = $this->makeRequest($url, $body);

        if ($response === null) {
            return ['ok' => false, 'error' => implode('; ', $this->errors)];
        }

        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['ok' => false, 'error' => 'Invalid JSON response: ' . json_last_error_msg()];
        }

        if (empty($decoded)) {
            return ['ok' => false, 'error' => 'API returned empty response'];
        }

        $count = count($decoded['1'] ?? $decoded[1] ?? []);
        return ['ok' => true, 'message' => "API reachable. Got {$count} suggestion results."];
    }

    /**
     * Fetch a single page of ads for an advertiser.
     */
    private function fetchPage($advertiserId, $pageToken = null)
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
            $this->errors[] = "JSON decode error: " . json_last_error_msg();
            return null;
        }

        return $decoded;
    }

    /**
     * Make an HTTP POST request with cookie session and retry logic.
     */
    private function makeRequest($url, $body)
    {
        $maxRetries = $this->config['max_retries'] ?? 3;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            $ch = curl_init();

            $opts = [
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
                CURLOPT_USERAGENT      => $this->getUserAgent(),
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_ENCODING       => 'gzip, deflate, br',
            ];

            // Attach cookies if session was initialized
            if ($this->cookieFile && file_exists($this->cookieFile)) {
                $opts[CURLOPT_COOKIEFILE] = $this->cookieFile;
                $opts[CURLOPT_COOKIEJAR]  = $this->cookieFile;
            }

            curl_setopt_array($ch, $opts);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($response !== false && $httpCode === 200) {
                // Check if response is actually HTML (CAPTCHA page)
                if (is_string($response) && strpos($response, '<!DOCTYPE') !== false) {
                    $this->log("Attempt {$attempt}/{$maxRetries}: Got HTML instead of JSON (likely CAPTCHA)");
                    $this->errors[] = "Blocked by CAPTCHA on attempt {$attempt}";
                    if ($attempt < $maxRetries) {
                        sleep(pow(2, $attempt) + rand(1, 3));
                    }
                    continue;
                }
                return $response;
            }

            $detail = "HTTP {$httpCode}";
            if ($curlError) $detail .= ", curl: {$curlError}";
            if ($httpCode === 429) $detail .= " (rate limited)";
            if ($response !== false && strpos($response, 'recaptcha') !== false) $detail .= " (CAPTCHA)";

            $this->log("Request attempt {$attempt}/{$maxRetries} failed. {$detail}");
            $this->errors[] = "Attempt {$attempt}: {$detail}";

            if ($attempt < $maxRetries) {
                $delay = pow(2, $attempt) + rand(1, 3);
                $this->log("Waiting {$delay}s before retry...");
                sleep($delay);

                // Re-init session on 429
                if ($httpCode === 429 && $attempt === 1) {
                    $this->log("Re-initializing session after rate limit...");
                    $this->initSession();
                    sleep(2);
                }
            }
        }

        return null;
    }

    /**
     * Extract ads array from the API response.
     *
     * Response structure (protobuf-style numeric keys):
     *   "1" = array of ad items
     *   "1"[n]."1" = advertiser_id
     *   "1"[n]."2" = creative_id
     *   "1"[n]."3" = ad content/preview (nested)
     *   "1"[n]."4" = format (1=text, 2=image, 3=video)
     *   "1"[n]."6"."1" = first shown timestamp (unix seconds)
     *   "1"[n]."7"."1" = last shown timestamp (unix seconds)
     *   "1"[n]."12" = advertiser name
     *   "2" = next page token
     *   "4" = estimated total count (lower)
     *   "5" = estimated total count (upper)
     */
    private function extractAdsFromResponse($response)
    {
        $ads = [];

        $creatives = $response['1'] ?? $response[1] ?? [];

        if (!is_array($creatives)) {
            return $ads;
        }

        foreach ($creatives as $item) {
            if (!is_array($item)) {
                continue;
            }

            $ad = $this->parseCreative($item);
            if ($ad['creative_id'] !== null) {
                $ads[] = $ad;
            }
        }

        return $ads;
    }

    /**
     * Parse a single creative item from the response.
     */
    private function parseCreative($c)
    {
        // Correct field mapping per protobuf structure
        $advertiserId = $this->extractStringField($c, ['1']);
        $creativeId   = $this->extractStringField($c, ['2']);
        $advertiserName = $this->extractStringField($c, ['12']);

        // Format: 1=text, 2=image, 3=video
        $format = $c['4'] ?? $c[4] ?? null;
        $typeMap = [1 => 'text', 2 => 'image', 3 => 'video'];
        $adType = 'text';
        if (is_numeric($format) && isset($typeMap[(int)$format])) {
            $adType = $typeMap[(int)$format];
        }

        // Timestamps: nested under "6" and "7"
        $firstSeen = null;
        $lastSeen = null;
        $field6 = $c['6'] ?? $c[6] ?? null;
        $field7 = $c['7'] ?? $c[7] ?? null;
        if (is_array($field6)) {
            $firstSeen = $field6['1'] ?? $field6[1] ?? null;
        } elseif (is_string($field6) || is_numeric($field6)) {
            $firstSeen = $field6;
        }
        if (is_array($field7)) {
            $lastSeen = $field7['1'] ?? $field7[1] ?? null;
        } elseif (is_string($field7) || is_numeric($field7)) {
            $lastSeen = $field7;
        }

        // Convert unix timestamps to dates
        if (is_numeric($firstSeen) && (int)$firstSeen > 1000000000) {
            $firstSeen = date('Y-m-d', (int)$firstSeen);
        }
        if (is_numeric($lastSeen) && (int)$lastSeen > 1000000000) {
            $lastSeen = date('Y-m-d', (int)$lastSeen);
        }

        // Extract content from field "3" (ad preview/content)
        $content = $c['3'] ?? $c[3] ?? [];
        $headline = null;
        $description = null;
        $landingUrl = null;
        $previewUrl = null;
        $imageHtml = null;

        if (is_array($content)) {
            // Field 3.1.4 = preview URL (displayads JS URL)
            $field31 = $content['1'] ?? $content[1] ?? null;
            if (is_array($field31)) {
                $previewUrl = $field31['4'] ?? $field31[4] ?? null;
                $headline = $this->extractStringField($field31, ['1', '2', '3']);
            }

            // Field 3.3.2 = HTML img tag (for image ads)
            $field33 = $content['3'] ?? $content[3] ?? null;
            if (is_array($field33)) {
                $imageHtml = $field33['2'] ?? $field33[2] ?? null;
            }

            // Try to extract text content
            if ($headline === null) {
                $headline = $this->extractStringField($content, ['1', '2']);
            }
            $description = $this->extractStringField($content, ['4', '5']);
            $landingUrl = $this->extractStringField($content, ['6', '7', '8']);
        }

        // Extract image URL from HTML img tag
        $imageUrl = null;
        if (is_string($imageHtml) && preg_match('/src=["\']([^"\']+)/', $imageHtml, $m)) {
            $imageUrl = $m[1];
        }

        // Build assets array
        $assets = [];
        if ($previewUrl && is_string($previewUrl)) {
            $assets[] = ['type' => 'preview', 'url' => $previewUrl];
        }
        if ($imageUrl) {
            $assets[] = ['type' => 'image', 'url' => $imageUrl];
        }

        // Platform indicator (field 13)
        $platformId = $c['13'] ?? $c[13] ?? null;
        $platforms = [];
        if (is_numeric($platformId)) {
            $platformMap = [
                1 => 'Google Search',
                2 => 'YouTube',
                3 => 'Google Display',
                4 => 'Google Shopping',
                5 => 'Google Maps',
                6 => 'Google Play',
            ];
            $platforms[] = $platformMap[(int)$platformId] ?? 'Platform_' . $platformId;
        }

        // Verified flag (field 16)
        $verified = !empty($c['16'] ?? $c[16] ?? false);

        return [
            'creative_id'     => is_string($creativeId) ? $creativeId : (is_numeric($creativeId) ? (string)$creativeId : null),
            'advertiser_id'   => is_string($advertiserId) ? $advertiserId : (is_numeric($advertiserId) ? (string)$advertiserId : null),
            'advertiser_name' => is_string($advertiserName) ? $advertiserName : null,
            'ad_type'         => $adType,
            'headline'        => is_string($headline) ? $headline : null,
            'description'     => is_string($description) ? $description : null,
            'cta'             => null,
            'landing_url'     => is_string($landingUrl) ? $landingUrl : null,
            'preview_url'     => is_string($previewUrl) ? $previewUrl : null,
            'assets'          => $assets,
            'countries'       => [],
            'platforms'       => $platforms,
            'first_seen'      => $firstSeen,
            'last_seen'       => $lastSeen,
            'verified'        => $verified,
        ];
    }

    /**
     * Try to extract a string value from an array, checking multiple keys.
     */
    private function extractStringField($data, $keys)
    {
        foreach ($keys as $key) {
            $val = $data[$key] ?? $data[(int)$key] ?? null;
            if (is_string($val) && $val !== '') {
                return $val;
            }
            if (is_numeric($val)) {
                return (string)$val;
            }
            if (is_array($val)) {
                // Try first nested value
                $nested = $val['1'] ?? $val[1] ?? $val[0] ?? null;
                if (is_string($nested) && $nested !== '') {
                    return $nested;
                }
            }
        }
        return null;
    }

    /**
     * Extract the next page token for pagination.
     */
    private function extractNextPageToken($response)
    {
        $token = $response['2'] ?? $response[2] ?? null;
        return is_string($token) && $token !== '' ? $token : null;
    }

    /**
     * Store raw API response in the database.
     */
    public function storeRawPayload($advertiserId, $json)
    {
        return $this->db->insert('raw_payloads', [
            'advertiser_id'  => $advertiserId,
            'raw_json'       => $json,
            'processed_flag' => 0,
        ]);
    }

    /**
     * Rotate between user agents to reduce fingerprinting.
     */
    private function getUserAgent()
    {
        $agents = [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:132.0) Gecko/20100101 Firefox/132.0',
            'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
        ];
        return $agents[array_rand($agents)];
    }

    /**
     * Log a message with timestamp.
     */
    private function log($message)
    {
        $timestamp = date('Y-m-d H:i:s');
        echo "[{$timestamp}] {$message}\n";
    }
}
