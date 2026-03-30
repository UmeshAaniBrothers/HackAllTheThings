<?php

/**
 * MetadataFetcher — fetches and caches app/video metadata from external APIs.
 */
class MetadataFetcher
{
    private $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    // ── App Store (iOS) ───────────────────────────────────

    /**
     * Get iOS app metadata. Uses iTunes Lookup API.
     * Caches in app_metadata table for 7 days.
     * $productId = ad_products.id, $storeUrl = the apps.apple.com URL
     */
    public function getAppStoreMetadata($productId, $storeUrl)
    {
        // Check cache (7 day TTL)
        $cached = $this->db->fetchOne(
            "SELECT * FROM app_metadata WHERE product_id = ? AND fetched_at > DATE_SUB(NOW(), INTERVAL 7 DAY)",
            [$productId]
        );
        if ($cached) return $cached;

        // Extract app ID from URL like https://apps.apple.com/app/id6751738934
        if (!preg_match('/id(\d+)/', $storeUrl, $m)) return null;
        $appId = $m[1];

        $url = 'https://itunes.apple.com/lookup?id=' . $appId;
        $resp = $this->curlGet($url);
        if (!$resp) return null;

        $data = json_decode($resp, true);
        if (!$data || empty($data['results'])) return null;
        $app = $data['results'][0];

        $meta = [
            'product_id'     => $productId,
            'store_platform' => 'ios',
            'store_url'      => $storeUrl,
            'bundle_id'      => $app['bundleId'] ?? null,
            'app_name'       => $app['trackName'] ?? null,
            'icon_url'       => $app['artworkUrl512'] ?? ($app['artworkUrl100'] ?? null),
            'developer_name' => $app['artistName'] ?? null,
            'developer_url'  => $app['artistViewUrl'] ?? null,
            'description'    => isset($app['description']) ? mb_substr($app['description'], 0, 2000) : null,
            'category'       => $app['primaryGenreName'] ?? null,
            'rating'         => $app['averageUserRating'] ?? null,
            'rating_count'   => $app['userRatingCount'] ?? 0,
            'price'          => isset($app['formattedPrice']) ? $app['formattedPrice'] : 'Free',
            'release_date'   => isset($app['releaseDate']) ? date('Y-m-d', strtotime($app['releaseDate'])) : null,
            'last_updated'   => isset($app['currentVersionReleaseDate']) ? date('Y-m-d', strtotime($app['currentVersionReleaseDate'])) : null,
            'version'        => $app['version'] ?? null,
            'screenshots'    => isset($app['screenshotUrls']) ? json_encode(array_slice($app['screenshotUrls'], 0, 5)) : null,
            'fetched_at'     => date('Y-m-d H:i:s'),
        ];

        // Upsert
        $existing = $this->db->fetchOne("SELECT id FROM app_metadata WHERE product_id = ?", [$productId]);
        if ($existing) {
            $this->db->update('app_metadata', $meta, 'id = ?', [$existing['id']]);
        } else {
            $this->db->insert('app_metadata', $meta);
        }

        return $meta;
    }

    // ── Play Store ────────────────────────────────────────

    /**
     * Get Play Store app metadata. Scrapes the Play Store page.
     * Caches for 7 days.
     */
    public function getPlayStoreMetadata($productId, $storeUrl)
    {
        $cached = $this->db->fetchOne(
            "SELECT * FROM app_metadata WHERE product_id = ? AND fetched_at > DATE_SUB(NOW(), INTERVAL 7 DAY)",
            [$productId]
        );
        if ($cached) return $cached;

        if (!preg_match('/id=([a-zA-Z0-9._]+)/', $storeUrl, $m)) return null;
        $packageName = $m[1];

        $url = 'https://play.google.com/store/apps/details?id=' . $packageName . '&hl=en';
        $html = $this->curlGet($url);
        if (!$html) return null;

        $appName = null;
        $developer = null;
        $rating = null;
        $downloads = null;
        $icon = null;
        $category = null;
        $description = null;

        // Title
        if (preg_match('/<title>([^<]+?)(?:\s*-\s*Apps on Google Play)?<\/title>/i', $html, $tm)) {
            $appName = trim($tm[1]);
        }

        // Developer name - look for meta itemprop
        if (preg_match('/<div[^>]*class="[^"]*Vbfug[^"]*"[^>]*><a[^>]*>([^<]+)<\/a>/i', $html, $dm)) {
            $developer = trim($dm[1]);
        }
        // Fallback: JSON-LD or script data
        if (!$developer && preg_match('/"developer":\s*\{"name":\s*"([^"]+)"/', $html, $dm2)) {
            $developer = $dm2[1];
        }

        // Rating
        if (preg_match('/itemprop="starRating"[^>]*>.*?itemprop="ratingValue"[^>]*content="([^"]+)"/s', $html, $rm)) {
            $rating = floatval($rm[1]);
        }
        if (!$rating && preg_match('/"aggregateRating":\s*\{[^}]*"ratingValue":\s*"?([0-9.]+)"?/', $html, $rm2)) {
            $rating = floatval($rm2[1]);
        }

        // Downloads - look for "10M+" type text
        if (preg_match('/([0-9,.]+[KMB]\+?)\s*(?:downloads|installs)/i', $html, $dlm)) {
            $downloads = $dlm[1];
        }

        // Icon URL
        if (preg_match('/<img[^>]*itemprop="image"[^>]*src="([^"]+)"/i', $html, $im)) {
            $icon = $im[1];
        }
        if (!$icon && preg_match('/"icon":\s*\{[^}]*"url":\s*"([^"]+)"/', $html, $im2)) {
            $icon = $im2[1];
        }

        $meta = [
            'product_id'     => $productId,
            'store_platform' => 'playstore',
            'store_url'      => $storeUrl,
            'bundle_id'      => $packageName,
            'app_name'       => $appName,
            'icon_url'       => $icon,
            'developer_name' => $developer,
            'developer_url'  => $developer ? 'https://play.google.com/store/apps/developer?id=' . urlencode($developer) : null,
            'description'    => $description,
            'category'       => $category,
            'rating'         => $rating,
            'rating_count'   => 0,
            'price'          => 'Free',
            'release_date'   => null,
            'last_updated'   => null,
            'version'        => null,
            'screenshots'    => null,
            'downloads'      => $downloads,
            'fetched_at'     => date('Y-m-d H:i:s'),
        ];

        $existing = $this->db->fetchOne("SELECT id FROM app_metadata WHERE product_id = ?", [$productId]);
        if ($existing) {
            $this->db->update('app_metadata', $meta, 'id = ?', [$existing['id']]);
        } else {
            $this->db->insert('app_metadata', $meta);
        }

        return $meta;
    }

    /**
     * Get app metadata (auto-detect platform).
     */
    public function getAppMetadata($productId, $storeUrl, $storePlatform)
    {
        if ($storePlatform === 'ios') {
            return $this->getAppStoreMetadata($productId, $storeUrl);
        } elseif ($storePlatform === 'playstore') {
            return $this->getPlayStoreMetadata($productId, $storeUrl);
        }
        return null;
    }

    // ── YouTube ───────────────────────────────────────────

    /**
     * Get YouTube video metadata. Uses oEmbed + page scraping.
     * Caches for 6 hours.
     */
    public function getYouTubeMetadata($videoId)
    {
        $cached = $this->db->fetchOne(
            "SELECT * FROM youtube_metadata WHERE video_id = ? AND fetched_at > DATE_SUB(NOW(), INTERVAL 6 HOUR)",
            [$videoId]
        );
        if ($cached) return $cached;

        $meta = [
            'video_id'      => $videoId,
            'title'         => null,
            'description'   => null,
            'channel_name'  => null,
            'channel_id'    => null,
            'channel_url'   => null,
            'view_count'    => 0,
            'like_count'    => 0,
            'comment_count' => 0,
            'publish_date'  => null,
            'duration'      => null,
            'thumbnail_url' => 'https://i.ytimg.com/vi/' . $videoId . '/hqdefault.jpg',
            'fetched_at'    => date('Y-m-d H:i:s'),
        ];

        // Step 1: oEmbed for title and channel
        $oembedUrl = 'https://www.youtube.com/oembed?url=https://www.youtube.com/watch?v=' . $videoId . '&format=json';
        $resp = $this->curlGet($oembedUrl);
        if ($resp) {
            $data = json_decode($resp, true);
            if ($data) {
                $meta['title'] = $data['title'] ?? null;
                $meta['channel_name'] = $data['author_name'] ?? null;
                $meta['channel_url'] = $data['author_url'] ?? null;
                if (isset($data['thumbnail_url'])) {
                    $meta['thumbnail_url'] = $data['thumbnail_url'];
                }
            }
        }

        // Step 2: Watch page scraping for view count, likes, description, publish date, channel ID
        $watchUrl = 'https://www.youtube.com/watch?v=' . $videoId;
        $html = $this->curlGet($watchUrl);
        if ($html) {
            // View count
            if (preg_match('/"viewCount":\s*"(\d+)"/', $html, $vm)) {
                $meta['view_count'] = (int) $vm[1];
            }
            // Like count
            if (preg_match('/"likeCount":\s*(\d+)/', $html, $lm)) {
                $meta['like_count'] = (int) $lm[1];
            }
            // Publish date
            if (preg_match('/"publishDate":\s*"([^"]+)"/', $html, $pm)) {
                $meta['publish_date'] = date('Y-m-d H:i:s', strtotime($pm[1]));
            }
            // Description
            if (preg_match('/"shortDescription":\s*"((?:[^"\\\\]|\\\\.)*)"/', $html, $dm)) {
                $desc = stripcslashes($dm[1]);
                $meta['description'] = mb_substr($desc, 0, 2000);
            }
            // Channel ID
            if (preg_match('/"channelId":\s*"([^"]+)"/', $html, $cm)) {
                $meta['channel_id'] = $cm[1];
            }
            // Duration
            if (preg_match('/"lengthSeconds":\s*"(\d+)"/', $html, $durm)) {
                $seconds = (int) $durm[1];
                $mins = floor($seconds / 60);
                $secs = $seconds % 60;
                $meta['duration'] = sprintf('%d:%02d', $mins, $secs);
            }
        }

        // Upsert
        $existing = $this->db->fetchOne("SELECT id FROM youtube_metadata WHERE video_id = ?", [$videoId]);
        if ($existing) {
            $this->db->update('youtube_metadata', $meta, 'id = ?', [$existing['id']]);
        } else {
            $this->db->insert('youtube_metadata', $meta);
        }

        return $meta;
    }

    // ── HTTP helper ───────────────────────────────────────

    private function curlGet($url)
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
            CURLOPT_ENCODING       => 'gzip, deflate',
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ($resp !== false && $code === 200) ? $resp : null;
    }
}
