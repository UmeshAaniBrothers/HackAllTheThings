<?php

/**
 * LandingPageAnalyzer - Funnel & Landing Page Intelligence
 *
 * Scrapes and analyzes landing pages to detect funnel types,
 * app info, pricing, and page changes over time.
 */
class LandingPageAnalyzer
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Analyze all unscraped landing pages from ad_details.
     */
    public function analyzeAll(): int
    {
        $urls = $this->db->fetchAll(
            "SELECT DISTINCT d.landing_url
             FROM ad_details d
             WHERE d.landing_url IS NOT NULL AND d.landing_url != ''
             AND SHA2(d.landing_url, 256) NOT IN (SELECT url_hash FROM landing_pages)"
        );

        $analyzed = 0;
        foreach ($urls as $row) {
            if ($this->analyzePage($row['landing_url'])) {
                $analyzed++;
            }
        }

        // Check for changes on existing pages
        $this->detectChanges();

        $this->log("Analyzed {$analyzed} new landing pages");
        return $analyzed;
    }

    /**
     * Analyze a single landing page.
     */
    public function analyzePage(string $url): bool
    {
        $urlHash = hash('sha256', $url);
        $domain = $this->extractDomain($url);

        $html = $this->fetchPage($url);
        if ($html === null) return false;

        $analysis = $this->parsePageContent($html, $url);

        $existing = $this->db->fetchOne("SELECT id FROM landing_pages WHERE url_hash = ?", [$urlHash]);

        if ($existing) {
            $this->db->update('landing_pages', [
                'page_title'       => $analysis['title'],
                'meta_description' => $analysis['meta_description'],
                'funnel_type'      => $analysis['funnel_type'],
                'app_name'         => $analysis['app_name'],
                'app_category'     => $analysis['app_category'],
                'pricing_detected' => $analysis['pricing'],
                'has_form'         => $analysis['has_form'] ? 1 : 0,
                'has_pricing'      => $analysis['has_pricing'] ? 1 : 0,
                'has_app_download' => $analysis['has_app_download'] ? 1 : 0,
                'technologies'     => json_encode($analysis['technologies']),
                'last_scraped_at'  => date('Y-m-d H:i:s'),
            ], 'url_hash = ?', [$urlHash]);
        } else {
            $this->db->insert('landing_pages', [
                'url_hash'         => $urlHash,
                'url'              => $url,
                'domain'           => $domain,
                'funnel_type'      => $analysis['funnel_type'],
                'page_title'       => $analysis['title'],
                'meta_description' => $analysis['meta_description'],
                'app_name'         => $analysis['app_name'],
                'app_category'     => $analysis['app_category'],
                'pricing_detected' => $analysis['pricing'],
                'has_form'         => $analysis['has_form'] ? 1 : 0,
                'has_pricing'      => $analysis['has_pricing'] ? 1 : 0,
                'has_app_download' => $analysis['has_app_download'] ? 1 : 0,
                'technologies'     => json_encode($analysis['technologies']),
                'last_scraped_at'  => date('Y-m-d H:i:s'),
            ]);
        }

        return true;
    }

    /**
     * Parse page HTML content and extract intelligence.
     */
    private function parsePageContent(string $html, string $url): array
    {
        $result = [
            'title'            => null,
            'meta_description' => null,
            'funnel_type'      => 'other',
            'app_name'         => null,
            'app_category'     => null,
            'pricing'          => null,
            'has_form'         => false,
            'has_pricing'      => false,
            'has_app_download' => false,
            'technologies'     => [],
        ];

        $htmlLower = strtolower($html);

        // Title
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m)) {
            $result['title'] = trim(html_entity_decode(strip_tags($m[1])));
        }

        // Meta description
        if (preg_match('/<meta\s+name=["\']description["\']\s+content=["\'](.*?)["\']/is', $html, $m)) {
            $result['meta_description'] = trim(html_entity_decode($m[1]));
        }

        // Form detection
        $result['has_form'] = (bool) preg_match('/<form[^>]*>/i', $html);

        // Pricing detection
        $pricingPatterns = ['/\$\d+[\.,]?\d*/', '/€\d+/', '/£\d+/', '/price/i', '/pricing/i', '/per\s+month/i', '/\/mo\b/i'];
        foreach ($pricingPatterns as $pattern) {
            if (preg_match($pattern, $html)) {
                $result['has_pricing'] = true;
                if (preg_match('/\$[\d,]+\.?\d*(?:\s*\/\s*\w+)?/', $html, $pm)) {
                    $result['pricing'] = trim($pm[0]);
                }
                break;
            }
        }

        // App detection
        $appStorePatterns = [
            'play.google.com' => 'Google Play',
            'apps.apple.com'  => 'App Store',
            'itunes.apple.com' => 'App Store',
        ];

        foreach ($appStorePatterns as $pattern => $store) {
            if (stripos($html, $pattern) !== false) {
                $result['has_app_download'] = true;
                break;
            }
        }

        // Also check URL itself
        if (stripos($url, 'play.google.com') !== false || stripos($url, 'apps.apple.com') !== false) {
            $result['has_app_download'] = true;
        }

        // App name from store pages
        if ($result['has_app_download'] && $result['title']) {
            $result['app_name'] = preg_replace('/\s*[-–—].*$/', '', $result['title']);
        }

        // Technology detection
        $techSignatures = [
            'Shopify'    => ['shopify', 'cdn.shopify.com'],
            'WordPress'  => ['wp-content', 'wp-includes'],
            'React'      => ['react', '__NEXT_DATA__', '_next/'],
            'Vue.js'     => ['vue.js', '__vue__'],
            'Angular'    => ['ng-app', 'angular'],
            'Bootstrap'  => ['bootstrap'],
            'jQuery'     => ['jquery'],
            'Stripe'     => ['stripe.com', 'stripe.js'],
            'Intercom'   => ['intercom'],
            'HubSpot'    => ['hubspot', 'hs-scripts'],
            'Google Tag' => ['googletagmanager', 'gtag'],
            'Facebook Pixel' => ['fbevents', 'facebook.com/tr'],
        ];

        foreach ($techSignatures as $tech => $signatures) {
            foreach ($signatures as $sig) {
                if (stripos($html, $sig) !== false) {
                    $result['technologies'][] = $tech;
                    break;
                }
            }
        }

        // Funnel type detection
        $result['funnel_type'] = $this->detectFunnelType($html, $url, $result);

        return $result;
    }

    /**
     * Detect funnel type from page content.
     */
    private function detectFunnelType(string $html, string $url, array $analysis): string
    {
        $htmlLower = strtolower($html);

        // App install
        if ($analysis['has_app_download']) {
            return 'app_install';
        }

        // E-commerce
        $ecomSignals = ['add to cart', 'buy now', 'shop now', 'add to bag', 'checkout', 'shopify'];
        foreach ($ecomSignals as $signal) {
            if (stripos($htmlLower, $signal) !== false) {
                return 'ecommerce';
            }
        }

        // SaaS
        $saasSignals = ['free trial', 'start free', 'sign up free', 'get started', 'per month', '/mo', 'saas'];
        foreach ($saasSignals as $signal) {
            if (stripos($htmlLower, $signal) !== false) {
                return 'saas';
            }
        }

        // Lead generation
        if ($analysis['has_form']) {
            $leadSignals = ['contact us', 'get a quote', 'request demo', 'schedule', 'book a call', 'free consultation'];
            foreach ($leadSignals as $signal) {
                if (stripos($htmlLower, $signal) !== false) {
                    return 'lead_gen';
                }
            }
        }

        // Content
        $contentSignals = ['blog', 'article', 'read more', 'published', 'author'];
        $contentCount = 0;
        foreach ($contentSignals as $signal) {
            if (stripos($htmlLower, $signal) !== false) $contentCount++;
        }
        if ($contentCount >= 2) return 'content';

        return 'other';
    }

    /**
     * Detect changes in previously scraped landing pages.
     */
    private function detectChanges(): int
    {
        $pages = $this->db->fetchAll(
            "SELECT * FROM landing_pages
             WHERE last_scraped_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)
             ORDER BY last_scraped_at ASC
             LIMIT 50"
        );

        $changes = 0;
        foreach ($pages as $page) {
            $html = $this->fetchPage($page['url']);
            if ($html === null) continue;

            $newAnalysis = $this->parsePageContent($html, $page['url']);

            // Compare key fields
            $fieldsToCheck = ['page_title', 'funnel_type', 'pricing_detected', 'app_name'];
            $fieldMap = ['page_title' => 'title', 'funnel_type' => 'funnel_type', 'pricing_detected' => 'pricing', 'app_name' => 'app_name'];

            foreach ($fieldsToCheck as $dbField) {
                $newField = $fieldMap[$dbField];
                $oldValue = $page[$dbField] ?? null;
                $newValue = $newAnalysis[$newField] ?? null;

                if ($oldValue !== $newValue && ($oldValue !== null || $newValue !== null)) {
                    $this->db->insert('landing_page_changes', [
                        'landing_page_id' => $page['id'],
                        'field_changed'   => $dbField,
                        'old_value'       => $oldValue,
                        'new_value'       => $newValue,
                    ]);
                    $changes++;
                }
            }

            // Update the page
            $this->db->update('landing_pages', [
                'page_title'       => $newAnalysis['title'],
                'meta_description' => $newAnalysis['meta_description'],
                'funnel_type'      => $newAnalysis['funnel_type'],
                'pricing_detected' => $newAnalysis['pricing'],
                'has_form'         => $newAnalysis['has_form'] ? 1 : 0,
                'has_pricing'      => $newAnalysis['has_pricing'] ? 1 : 0,
                'has_app_download' => $newAnalysis['has_app_download'] ? 1 : 0,
                'technologies'     => json_encode($newAnalysis['technologies']),
                'last_scraped_at'  => date('Y-m-d H:i:s'),
            ], 'id = ?', [$page['id']]);
        }

        return $changes;
    }

    /**
     * Get landing page intelligence for an advertiser.
     */
    public function getAdvertiserLandingPages(string $advertiserId): array
    {
        return $this->db->fetchAll(
            "SELECT DISTINCT lp.*
             FROM landing_pages lp
             INNER JOIN ad_details d ON SHA2(d.landing_url, 256) = lp.url_hash
             INNER JOIN ads a ON d.creative_id = a.creative_id
             WHERE a.advertiser_id = ?
             ORDER BY lp.last_scraped_at DESC",
            [$advertiserId]
        );
    }

    /**
     * Get funnel type distribution.
     */
    public function getFunnelDistribution(?string $advertiserId = null): array
    {
        if ($advertiserId) {
            return $this->db->fetchAll(
                "SELECT lp.funnel_type, COUNT(*) as count
                 FROM landing_pages lp
                 INNER JOIN ad_details d ON SHA2(d.landing_url, 256) = lp.url_hash
                 INNER JOIN ads a ON d.creative_id = a.creative_id
                 WHERE a.advertiser_id = ?
                 GROUP BY lp.funnel_type",
                [$advertiserId]
            );
        }

        return $this->db->fetchAll(
            "SELECT funnel_type, COUNT(*) as count FROM landing_pages GROUP BY funnel_type"
        );
    }

    private function extractDomain(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST);
        return $host ? preg_replace('/^www\./', '', $host) : '';
    }

    private function fetchPage(string $url): ?string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ($response !== false && $code >= 200 && $code < 400) ? $response : null;
    }

    private function log(string $message): void
    {
        echo "[" . date('Y-m-d H:i:s') . "] LANDING: {$message}\n";
    }
}
