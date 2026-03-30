<?php

/**
 * ProxyManager - Proxy & Anti-Blocking System
 *
 * Manages proxy pool rotation, user-agent rotation,
 * and intelligent retry strategies to avoid detection.
 */
class ProxyManager
{
    private Database $db;
    private array $userAgents;

    public function __construct(Database $db)
    {
        $this->db = $db;

        $this->userAgents = [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:121.0) Gecko/20100101 Firefox/121.0',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15',
            'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Safari/537.36 Edg/119.0.0.0',
            'Mozilla/5.0 (iPhone; CPU iPhone OS 17_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Mobile/15E148 Safari/604.1',
            'Mozilla/5.0 (Linux; Android 14; SM-S918B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36',
        ];
    }

    /**
     * Get a random user agent string.
     */
    public function getRandomUserAgent(): string
    {
        return $this->userAgents[array_rand($this->userAgents)];
    }

    /**
     * Get the best available proxy from the pool.
     * Prioritizes: active, low failure rate, least recently used.
     */
    public function getProxy(): ?array
    {
        $proxy = $this->db->fetchOne(
            "SELECT * FROM proxy_pool
             WHERE is_active = 1
             AND (last_failed_at IS NULL OR last_failed_at < DATE_SUB(NOW(), INTERVAL 10 MINUTE))
             ORDER BY
                 (success_count / GREATEST(success_count + fail_count, 1)) DESC,
                 last_used_at ASC
             LIMIT 1"
        );

        if ($proxy) {
            $this->db->update('proxy_pool', ['last_used_at' => date('Y-m-d H:i:s')], 'id = ?', [$proxy['id']]);
        }

        return $proxy;
    }

    /**
     * Record a successful proxy request.
     */
    public function recordSuccess(int $proxyId, int $responseTimeMs): void
    {
        $this->db->query(
            "UPDATE proxy_pool SET
                success_count = success_count + 1,
                avg_response_ms = COALESCE((avg_response_ms * success_count + ?) / (success_count + 1), ?)
             WHERE id = ?",
            [$responseTimeMs, $responseTimeMs, $proxyId]
        );
    }

    /**
     * Record a failed proxy request.
     */
    public function recordFailure(int $proxyId): void
    {
        $this->db->query(
            "UPDATE proxy_pool SET
                fail_count = fail_count + 1,
                last_failed_at = NOW()
             WHERE id = ?",
            [$proxyId]
        );

        // Auto-disable if failure rate > 50% and at least 10 attempts
        $proxy = $this->db->fetchOne("SELECT * FROM proxy_pool WHERE id = ?", [$proxyId]);
        if ($proxy) {
            $total = $proxy['success_count'] + $proxy['fail_count'];
            if ($total >= 10 && ($proxy['fail_count'] / $total) > 0.5) {
                $this->db->update('proxy_pool', ['is_active' => 0], 'id = ?', [$proxyId]);
            }
        }
    }

    /**
     * Add a proxy to the pool.
     */
    public function addProxy(string $proxyUrl, string $type = 'http', ?string $country = null): string
    {
        return $this->db->insert('proxy_pool', [
            'proxy_url'  => $proxyUrl,
            'proxy_type' => $type,
            'country'    => $country,
        ]);
    }

    /**
     * Remove a proxy from the pool.
     */
    public function removeProxy(int $proxyId): int
    {
        return $this->db->query("DELETE FROM proxy_pool WHERE id = ?", [$proxyId])->rowCount();
    }

    /**
     * Get pool statistics.
     */
    public function getPoolStats(): array
    {
        return [
            'total'    => (int) $this->db->fetchColumn("SELECT COUNT(*) FROM proxy_pool"),
            'active'   => (int) $this->db->fetchColumn("SELECT COUNT(*) FROM proxy_pool WHERE is_active = 1"),
            'inactive' => (int) $this->db->fetchColumn("SELECT COUNT(*) FROM proxy_pool WHERE is_active = 0"),
            'avg_success_rate' => (float) ($this->db->fetchColumn(
                "SELECT AVG(success_count / GREATEST(success_count + fail_count, 1)) FROM proxy_pool WHERE is_active = 1"
            ) ?? 0),
            'proxies' => $this->db->fetchAll(
                "SELECT id, proxy_url, proxy_type, country, is_active, success_count, fail_count,
                        ROUND(success_count / GREATEST(success_count + fail_count, 1) * 100, 1) as success_rate,
                        avg_response_ms, last_used_at, last_failed_at
                 FROM proxy_pool ORDER BY is_active DESC, success_count DESC"
            ),
        ];
    }

    /**
     * Apply proxy settings to a cURL handle.
     */
    public function applyCurlProxy($ch, ?array $proxy = null): ?int
    {
        if ($proxy === null) {
            $proxy = $this->getProxy();
        }

        if ($proxy === null) {
            // No proxy available, use direct connection with random UA
            curl_setopt($ch, CURLOPT_USERAGENT, $this->getRandomUserAgent());
            return null;
        }

        curl_setopt($ch, CURLOPT_PROXY, $proxy['proxy_url']);

        $proxyTypes = ['socks5' => CURLPROXY_SOCKS5, 'https' => CURLPROXY_HTTPS];
        $proxyType = isset($proxyTypes[$proxy['proxy_type']]) ? $proxyTypes[$proxy['proxy_type']] : CURLPROXY_HTTP;
        curl_setopt($ch, CURLOPT_PROXYTYPE, $proxyType);
        curl_setopt($ch, CURLOPT_USERAGENT, $this->getRandomUserAgent());

        return (int) $proxy['id'];
    }

    /**
     * Health check all proxies.
     */
    public function healthCheck(): array
    {
        $proxies = $this->db->fetchAll("SELECT * FROM proxy_pool WHERE is_active = 1");
        $results = ['checked' => 0, 'healthy' => 0, 'disabled' => 0];

        foreach ($proxies as $proxy) {
            $ch = curl_init('https://httpbin.org/ip');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 10,
                CURLOPT_PROXY          => $proxy['proxy_url'],
            ]);

            $start = microtime(true);
            $response = curl_exec($ch);
            $elapsed = (int) ((microtime(true) - $start) * 1000);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $results['checked']++;

            if ($code === 200) {
                $this->recordSuccess($proxy['id'], $elapsed);
                $results['healthy']++;
            } else {
                $this->recordFailure($proxy['id']);
                $results['disabled']++;
            }
        }

        return $results;
    }
}
