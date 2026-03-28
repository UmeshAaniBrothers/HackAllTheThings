<?php

/**
 * AlertManager - Real-time Alerting & Notification System
 *
 * Detects alert conditions and delivers notifications via
 * Email, Telegram bot, and Slack webhook.
 */
class AlertManager
{
    private Database $db;
    private array $config;

    public function __construct(Database $db, array $config)
    {
        $this->db = $db;
        $this->config = $config;
    }

    // =========================================================
    // Alert Detection
    // =========================================================

    /**
     * Run all alert checks and send notifications.
     */
    public function processAlerts(): array
    {
        $results = [
            'new_ads'         => $this->checkNewAds(),
            'ads_stopped'     => $this->checkAdsStopped(),
            'new_countries'   => $this->checkNewCountries(),
            'landing_changes' => $this->checkLandingChanges(),
            'bursts'          => $this->checkBursts(),
        ];

        $this->log("Alerts processed: " . json_encode($results));
        return $results;
    }

    /**
     * Check for new ads and alert if thresholds met.
     */
    private function checkNewAds(): int
    {
        $rules = $this->getActiveRules('new_ad');
        $sent = 0;

        foreach ($rules as $rule) {
            $conditions = json_decode($rule['conditions'] ?? '{}', true);
            $minCount = $conditions['min_count'] ?? 1;
            $advertiserId = $rule['advertiser_id'];

            $where = "created_at >= DATE_SUB(NOW(), INTERVAL 6 HOUR)";
            $params = [];

            if ($advertiserId) {
                $where .= " AND advertiser_id = ?";
                $params[] = $advertiserId;
            }

            $count = (int) $this->db->fetchColumn(
                "SELECT COUNT(*) FROM ads WHERE {$where}", $params
            );

            if ($count >= $minCount) {
                $advLabel = $advertiserId ?? 'All advertisers';
                $this->sendAlert($rule, [
                    'title'   => "New Ads Detected: {$count}",
                    'message' => "{$advLabel} launched {$count} new ads in the last 6 hours.",
                    'count'   => $count,
                ]);
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * Check for ads that stopped running.
     */
    private function checkAdsStopped(): int
    {
        $rules = $this->getActiveRules('ad_stopped');
        $sent = 0;

        foreach ($rules as $rule) {
            $advertiserId = $rule['advertiser_id'];
            $where = "status = 'inactive' AND last_seen >= DATE_SUB(NOW(), INTERVAL 6 HOUR) AND last_seen < DATE_SUB(NOW(), INTERVAL 48 HOUR)";
            $params = [];

            if ($advertiserId) {
                $where .= " AND advertiser_id = ?";
                $params[] = $advertiserId;
            }

            $stopped = $this->db->fetchAll("SELECT creative_id, advertiser_id FROM ads WHERE {$where} LIMIT 50", $params);

            if (!empty($stopped)) {
                $count = count($stopped);
                $advLabel = $advertiserId ?? 'Multiple advertisers';
                $this->sendAlert($rule, [
                    'title'   => "{$count} Ads Stopped Running",
                    'message' => "{$advLabel}: {$count} ads have been inactive for 48+ hours.",
                    'count'   => $count,
                ]);
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * Check for new country targeting.
     */
    private function checkNewCountries(): int
    {
        $rules = $this->getActiveRules('new_country');
        $sent = 0;

        foreach ($rules as $rule) {
            $advertiserId = $rule['advertiser_id'];

            $query = "SELECT t.country, COUNT(DISTINCT t.creative_id) as ad_count
                      FROM ad_targeting t
                      INNER JOIN ads a ON t.creative_id = a.creative_id
                      WHERE t.detected_at >= DATE_SUB(NOW(), INTERVAL 6 HOUR)";
            $params = [];

            if ($advertiserId) {
                $query .= " AND a.advertiser_id = ?";
                $params[] = $advertiserId;
            }

            $query .= " GROUP BY t.country
                        HAVING ad_count >= 1";

            // Check these countries weren't targeted before
            $newCountries = [];
            $results = $this->db->fetchAll($query, $params);

            foreach ($results as $row) {
                $existed = $this->db->fetchColumn(
                    "SELECT COUNT(*) FROM ad_targeting t
                     INNER JOIN ads a ON t.creative_id = a.creative_id
                     WHERE t.country = ? AND t.detected_at < DATE_SUB(NOW(), INTERVAL 6 HOUR)"
                    . ($advertiserId ? " AND a.advertiser_id = ?" : ""),
                    $advertiserId ? [$row['country'], $advertiserId] : [$row['country']]
                );

                if ((int) $existed === 0) {
                    $newCountries[] = $row['country'];
                }
            }

            if (!empty($newCountries)) {
                $list = implode(', ', $newCountries);
                $this->sendAlert($rule, [
                    'title'   => "New Country Targeting Detected",
                    'message' => ($advertiserId ?? 'Advertisers') . " now targeting: {$list}",
                    'countries' => $newCountries,
                ]);
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * Check for landing page changes.
     */
    private function checkLandingChanges(): int
    {
        $rules = $this->getActiveRules('landing_change');
        $sent = 0;

        foreach ($rules as $rule) {
            $changes = $this->db->fetchAll(
                "SELECT lpc.*, lp.url, lp.domain
                 FROM landing_page_changes lpc
                 INNER JOIN landing_pages lp ON lpc.landing_page_id = lp.id
                 WHERE lpc.detected_at >= DATE_SUB(NOW(), INTERVAL 6 HOUR)
                 ORDER BY lpc.detected_at DESC
                 LIMIT 20"
            );

            if (!empty($changes)) {
                $domains = array_unique(array_column($changes, 'domain'));
                $this->sendAlert($rule, [
                    'title'   => count($changes) . " Landing Page Changes Detected",
                    'message' => "Changes on: " . implode(', ', array_slice($domains, 0, 5)),
                    'changes' => count($changes),
                ]);
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * Check for campaign bursts.
     */
    private function checkBursts(): int
    {
        $rules = $this->getActiveRules('burst_detected');
        $sent = 0;

        foreach ($rules as $rule) {
            $bursts = $this->db->fetchAll(
                "SELECT advertiser_id, ads_launched, burst_magnitude
                 FROM trend_snapshots
                 WHERE is_burst = 1 AND snapshot_date = CURDATE()"
            );

            foreach ($bursts as $burst) {
                $this->sendAlert($rule, [
                    'title'   => "Campaign Burst: {$burst['advertiser_id']}",
                    'message' => "{$burst['ads_launched']} ads launched today (magnitude: {$burst['burst_magnitude']}x normal)",
                    'advertiser_id' => $burst['advertiser_id'],
                ]);
                $sent++;
            }
        }

        return $sent;
    }

    // =========================================================
    // Notification Delivery
    // =========================================================

    /**
     * Send alert via configured channels.
     */
    private function sendAlert(array $rule, array $data): void
    {
        $channels = json_decode($rule['channels'] ?? '[]', true);

        foreach ($channels as $channel) {
            $success = match ($channel) {
                'email'    => $this->sendEmail($rule, $data),
                'telegram' => $this->sendTelegram($rule, $data),
                'slack'    => $this->sendSlack($rule, $data),
                default    => false,
            };

            $this->db->insert('alert_log', [
                'rule_id'         => $rule['id'],
                'alert_type'      => $rule['rule_type'],
                'title'           => $data['title'],
                'message'         => $data['message'],
                'channel'         => $channel,
                'advertiser_id'   => $rule['advertiser_id'],
                'metadata'        => json_encode($data),
                'delivery_status' => $success ? 'sent' : 'failed',
            ]);
        }

        $this->db->update('alert_rules', ['last_triggered_at' => date('Y-m-d H:i:s')], 'id = ?', [$rule['id']]);
    }

    /**
     * Send email notification.
     */
    private function sendEmail(array $rule, array $data): bool
    {
        $channelConfig = $this->getChannelConfig($rule['user_id'], 'email');
        if (!$channelConfig) return false;

        $to = $channelConfig['to'] ?? ($this->config['alerts']['email_to'] ?? null);
        if (!$to) return false;

        $subject = "[Ad Intelligence] " . $data['title'];
        $body = $data['message'] . "\n\n---\nAd Intelligence Dashboard";

        $headers = [
            'From: ' . ($this->config['alerts']['email_from'] ?? 'alerts@ad-intelligence.local'),
            'Content-Type: text/plain; charset=UTF-8',
        ];

        return mail($to, $subject, $body, implode("\r\n", $headers));
    }

    /**
     * Send Telegram notification.
     */
    private function sendTelegram(array $rule, array $data): bool
    {
        $channelConfig = $this->getChannelConfig($rule['user_id'], 'telegram');
        if (!$channelConfig) {
            $channelConfig = $this->config['alerts']['telegram'] ?? null;
        }
        if (!$channelConfig || empty($channelConfig['bot_token']) || empty($channelConfig['chat_id'])) {
            return false;
        }

        $text = "*{$data['title']}*\n\n{$data['message']}";

        $url = "https://api.telegram.org/bot{$channelConfig['bot_token']}/sendMessage";
        $params = [
            'chat_id'    => $channelConfig['chat_id'],
            'text'       => $text,
            'parse_mode' => 'Markdown',
        ];

        return $this->httpPost($url, $params);
    }

    /**
     * Send Slack notification.
     */
    private function sendSlack(array $rule, array $data): bool
    {
        $channelConfig = $this->getChannelConfig($rule['user_id'], 'slack');
        if (!$channelConfig) {
            $channelConfig = $this->config['alerts']['slack'] ?? null;
        }
        if (!$channelConfig || empty($channelConfig['webhook_url'])) {
            return false;
        }

        $payload = [
            'text' => "*{$data['title']}*\n{$data['message']}",
            'blocks' => [
                [
                    'type' => 'header',
                    'text' => ['type' => 'plain_text', 'text' => $data['title']],
                ],
                [
                    'type' => 'section',
                    'text' => ['type' => 'mrkdwn', 'text' => $data['message']],
                ],
            ],
        ];

        return $this->httpPost($channelConfig['webhook_url'], $payload, true);
    }

    /**
     * Get notification channel config for a user.
     */
    private function getChannelConfig(?int $userId, string $type): ?array
    {
        if ($userId === null) return null;

        $row = $this->db->fetchOne(
            "SELECT config FROM notification_channels WHERE user_id = ? AND channel_type = ? AND is_active = 1",
            [$userId, $type]
        );

        return $row ? json_decode($row['config'], true) : null;
    }

    /**
     * Get active alert rules of a given type.
     */
    private function getActiveRules(string $type): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM alert_rules WHERE rule_type = ? AND is_active = 1",
            [$type]
        );
    }

    /**
     * Get recent alerts for the notification dashboard.
     */
    public function getRecentAlerts(int $limit = 50): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM alert_log ORDER BY created_at DESC LIMIT ?",
            [$limit]
        );
    }

    /**
     * Get today's alert summary.
     */
    public function getTodaySummary(): array
    {
        return $this->db->fetchAll(
            "SELECT alert_type, COUNT(*) as count, MAX(created_at) as last_sent
             FROM alert_log
             WHERE DATE(created_at) = CURDATE()
             GROUP BY alert_type"
        );
    }

    /**
     * HTTP POST helper for webhooks.
     */
    private function httpPost(string $url, array $data, bool $json = false): bool
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_POSTFIELDS     => $json ? json_encode($data) : http_build_query($data),
            CURLOPT_HTTPHEADER     => $json
                ? ['Content-Type: application/json']
                : ['Content-Type: application/x-www-form-urlencoded'],
        ]);

        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $code >= 200 && $code < 300;
    }

    private function log(string $message): void
    {
        echo "[" . date('Y-m-d H:i:s') . "] ALERTS: {$message}\n";
    }
}
