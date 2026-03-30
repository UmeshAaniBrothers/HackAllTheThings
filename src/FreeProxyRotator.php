<?php

/**
 * FreeProxyRotator - Fetches and rotates free proxies from public lists.
 *
 * Sources:
 *   - SOCKS5: https://raw.githubusercontent.com/TheSpeedX/SOCKS-List/master/socks5.txt
 *   - SOCKS4: https://raw.githubusercontent.com/TheSpeedX/SOCKS-List/master/socks4.txt
 *   - HTTP:   https://raw.githubusercontent.com/TheSpeedX/SOCKS-List/master/http.txt
 *
 * Caches proxy lists to avoid re-fetching on every request.
 * Tests proxies against Google and remembers working ones.
 */
class FreeProxyRotator
{
    /** @var array */
    private $proxySources = [
        'http'   => 'https://raw.githubusercontent.com/TheSpeedX/SOCKS-List/master/http.txt',
        'socks4' => 'https://raw.githubusercontent.com/TheSpeedX/SOCKS-List/master/socks4.txt',
        'socks5' => 'https://raw.githubusercontent.com/TheSpeedX/SOCKS-List/master/socks5.txt',
    ];

    /** @var array */
    private $proxies = [];

    /** @var array */
    private $workingProxies = [];

    /** @var array */
    private $failedProxies = [];

    /** @var string|null */
    private $cacheDir;

    /** @var int Cache lifetime in seconds (1 hour) */
    private $cacheTtl = 3600;

    /** @var int Max proxies to load per type */
    private $maxPerType = 200;

    /** @var int Current proxy index */
    private $currentIndex = 0;

    public function __construct($cacheDir = null)
    {
        $this->cacheDir = $cacheDir ?: sys_get_temp_dir();
    }

    /**
     * Load proxies from cache or fetch fresh from sources.
     * @param array $types Which proxy types to load: ['http', 'socks4', 'socks5']
     */
    public function loadProxies($types = ['http', 'socks5'])
    {
        $this->proxies = [];

        foreach ($types as $type) {
            if (!isset($this->proxySources[$type])) continue;

            $list = $this->fetchProxyList($type);
            if (!empty($list)) {
                // Shuffle for randomness
                shuffle($list);
                // Limit
                $list = array_slice($list, 0, $this->maxPerType);
                foreach ($list as $proxy) {
                    $this->proxies[] = [
                        'address' => $proxy,
                        'type'    => $type,
                    ];
                }
            }
        }

        // Shuffle the combined list
        shuffle($this->proxies);

        return count($this->proxies);
    }

    /**
     * Get the next proxy to try.
     * @return array|null ['address' => 'ip:port', 'type' => 'http|socks4|socks5']
     */
    public function getNext()
    {
        // First try working proxies
        if (!empty($this->workingProxies)) {
            $proxy = $this->workingProxies[array_rand($this->workingProxies)];
            return $proxy;
        }

        // Then try from the main list
        $attempts = 0;
        $maxAttempts = min(count($this->proxies), 50);

        while ($attempts < $maxAttempts) {
            if ($this->currentIndex >= count($this->proxies)) {
                $this->currentIndex = 0;
            }

            $proxy = $this->proxies[$this->currentIndex];
            $this->currentIndex++;
            $attempts++;

            // Skip known-failed proxies
            if (isset($this->failedProxies[$proxy['address']])) {
                continue;
            }

            return $proxy;
        }

        return null;
    }

    /**
     * Mark a proxy as working.
     */
    public function markWorking($proxy)
    {
        $key = $proxy['address'];
        $this->workingProxies[$key] = $proxy;
        unset($this->failedProxies[$key]);
    }

    /**
     * Mark a proxy as failed.
     */
    public function markFailed($proxy)
    {
        $key = $proxy['address'];
        $this->failedProxies[$key] = true;
        unset($this->workingProxies[$key]);
    }

    /**
     * Get count of loaded proxies.
     */
    public function getCount()
    {
        return count($this->proxies);
    }

    /**
     * Get count of working proxies.
     */
    public function getWorkingCount()
    {
        return count($this->workingProxies);
    }

    /**
     * Apply proxy to a curl handle.
     */
    public function applyCurl($ch, $proxy)
    {
        if (!$proxy) return;

        curl_setopt($ch, CURLOPT_PROXY, $proxy['address']);

        switch ($proxy['type']) {
            case 'socks5':
                curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);
                break;
            case 'socks4':
                curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS4);
                break;
            default:
                curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
                break;
        }

        // Shorter timeout for proxy connections
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    }

    /**
     * Test a proxy by making a request to Google Ads Transparency.
     */
    public function testProxy($proxy)
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => 'https://adstransparency.google.com/?region=anywhere',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $this->applyCurl($ch, $proxy);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $works = ($response !== false && $httpCode === 200 && strpos($response, 'recaptcha') === false);

        if ($works) {
            $this->markWorking($proxy);
        } else {
            $this->markFailed($proxy);
        }

        return $works;
    }

    /**
     * Find working proxies by testing a batch.
     * @param int $needed How many working proxies to find
     * @param int $maxTest Max proxies to test
     * @return int Number of working proxies found
     */
    public function findWorkingProxies($needed = 3, $maxTest = 30)
    {
        if (empty($this->proxies)) {
            $this->loadProxies();
        }

        $tested = 0;
        $found = 0;

        foreach ($this->proxies as $proxy) {
            if ($tested >= $maxTest || $found >= $needed) break;
            if (isset($this->failedProxies[$proxy['address']])) continue;

            $tested++;
            if ($this->testProxy($proxy)) {
                $found++;
            }
        }

        return $found;
    }

    /**
     * Fetch proxy list from source URL with caching.
     */
    private function fetchProxyList($type)
    {
        $url = $this->proxySources[$type];
        $cacheFile = $this->cacheDir . '/proxy_cache_' . $type . '.txt';

        // Check cache
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $this->cacheTtl) {
            $content = file_get_contents($cacheFile);
            if ($content !== false) {
                return $this->parseProxyList($content);
            }
        }

        // Fetch fresh
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT      => 'Mozilla/5.0',
        ]);

        $content = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($content === false || $httpCode !== 200) {
            // Try cache even if expired
            if (file_exists($cacheFile)) {
                $content = file_get_contents($cacheFile);
                return $content !== false ? $this->parseProxyList($content) : [];
            }
            return [];
        }

        // Save to cache
        @file_put_contents($cacheFile, $content);

        return $this->parseProxyList($content);
    }

    /**
     * Parse a proxy list (one ip:port per line).
     */
    private function parseProxyList($content)
    {
        $lines = explode("\n", trim($content));
        $proxies = [];

        foreach ($lines as $line) {
            $line = trim($line);
            // Validate ip:port format
            if (preg_match('/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}:\d{2,5}$/', $line)) {
                $proxies[] = $line;
            }
        }

        return $proxies;
    }
}
