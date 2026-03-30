<?php

/**
 * AIIntelligence - Hook Detection, Sentiment Analysis, Copy Clustering
 *
 * Provides keyword-based intelligence without external AI APIs:
 * detects persuasion hooks, analyzes selling style,
 * clusters similar ad copies, and estimates performance.
 */
class AIIntelligence
{
    private Database $db;

    // Persuasion hook patterns
    private const HOOKS = [
        'urgency'      => ['limited time', 'hurry', 'act now', 'don\'t miss', 'last chance', 'ends soon', 'today only', 'expiring'],
        'scarcity'     => ['limited offer', 'only .* left', 'few remaining', 'selling fast', 'almost gone', 'exclusive'],
        'social_proof' => ['trusted by', 'join .* users', 'million', 'rated', 'reviewed', '#1', 'best-selling', 'award'],
        'free_offer'   => ['free trial', 'free shipping', 'no cost', 'complimentary', '0%', 'free for', 'try free'],
        'discount'     => ['% off', 'save \$', 'discount', 'sale', 'deal', 'coupon', 'promo', 'clearance'],
        'guarantee'    => ['money back', 'guarantee', 'risk-free', 'no obligation', 'cancel anytime'],
        'authority'    => ['expert', 'certified', 'approved', 'official', 'recommended by', 'doctor', 'professional'],
        'curiosity'    => ['discover', 'secret', 'revealed', 'find out', 'learn how', 'the truth about'],
    ];

    // Aggressive vs soft selling signals
    private const AGGRESSIVE_SIGNALS = ['buy now', 'act now', 'don\'t wait', 'last chance', 'limited', 'hurry', 'today only', 'order now'];
    private const SOFT_SIGNALS = ['learn more', 'discover', 'explore', 'see how', 'find out', 'get started', 'try', 'compare'];

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Analyze all unanalyzed ads.
     */
    public function analyzeAll(): int
    {
        $ads = $this->db->fetchAll(
            "SELECT a.creative_id, d.headline, d.description, d.cta
             FROM ads a
             LEFT JOIN ad_details d ON a.creative_id = d.creative_id
                 AND d.id = (SELECT MAX(id) FROM ad_details WHERE creative_id = a.creative_id)
             WHERE a.creative_id NOT IN (SELECT creative_id FROM ai_ad_analysis)"
        );

        $analyzed = 0;
        foreach ($ads as $ad) {
            $this->analyzeAd($ad);
            $analyzed++;
        }

        $this->log("Analyzed {$analyzed} ads");
        return $analyzed;
    }

    /**
     * Analyze a single ad.
     */
    public function analyzeAd(array $ad): array
    {
        $text = implode(' ', array_filter([
            $ad['headline'] ?? '',
            $ad['description'] ?? '',
            $ad['cta'] ?? '',
        ]));

        $hooks = $this->detectHooks($text);
        $sentiment = $this->analyzeSentiment($text);
        $keywords = $this->extractKeywords($text);
        $persuasion = array_keys($hooks);

        $this->db->insert('ai_ad_analysis', [
            'creative_id'          => $ad['creative_id'],
            'hooks_detected'       => json_encode($hooks),
            'sentiment'            => $sentiment['label'],
            'sentiment_score'      => $sentiment['score'],
            'keywords'             => json_encode($keywords),
            'persuasion_techniques' => json_encode($persuasion),
        ]);

        return [
            'hooks'      => $hooks,
            'sentiment'  => $sentiment,
            'keywords'   => $keywords,
            'persuasion' => $persuasion,
        ];
    }

    /**
     * Detect persuasion hooks in text.
     */
    public function detectHooks(string $text): array
    {
        $textLower = strtolower($text);
        $detected = [];

        foreach (self::HOOKS as $category => $patterns) {
            foreach ($patterns as $pattern) {
                if (strpos($pattern, '.*') !== false) {
                    if (preg_match('/' . $pattern . '/i', $textLower)) {
                        $detected[$category][] = $pattern;
                    }
                } else {
                    if (stripos($textLower, $pattern) !== false) {
                        $detected[$category][] = $pattern;
                    }
                }
            }
        }

        return $detected;
    }

    /**
     * Analyze selling sentiment (aggressive vs soft).
     */
    public function analyzeSentiment(string $text): array
    {
        $textLower = strtolower($text);

        $aggressiveCount = 0;
        foreach (self::AGGRESSIVE_SIGNALS as $signal) {
            if (stripos($textLower, $signal) !== false) $aggressiveCount++;
        }

        $softCount = 0;
        foreach (self::SOFT_SIGNALS as $signal) {
            if (stripos($textLower, $signal) !== false) $softCount++;
        }

        // Exclamation marks boost aggressiveness
        $exclamations = substr_count($text, '!');
        $aggressiveCount += min(3, $exclamations);

        // ALL CAPS words boost aggressiveness
        preg_match_all('/\b[A-Z]{3,}\b/', $text, $capsMatches);
        $aggressiveCount += min(3, count($capsMatches[0]));

        $total = $aggressiveCount + $softCount;
        if ($total === 0) {
            return ['label' => 'neutral', 'score' => 0.5];
        }

        $aggressiveRatio = $aggressiveCount / $total;

        if ($aggressiveRatio > 0.7) {
            return ['label' => 'aggressive', 'score' => round($aggressiveRatio, 2)];
        }
        if ($aggressiveRatio > 0.4) {
            return ['label' => 'moderate', 'score' => round($aggressiveRatio, 2)];
        }

        return ['label' => 'soft', 'score' => round($aggressiveRatio, 2)];
    }

    /**
     * Extract meaningful keywords from ad text.
     */
    public function extractKeywords(string $text): array
    {
        $stopWords = ['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for',
            'of', 'with', 'by', 'is', 'are', 'was', 'were', 'be', 'been', 'has', 'have',
            'had', 'do', 'does', 'did', 'will', 'would', 'could', 'should', 'may', 'can',
            'this', 'that', 'these', 'those', 'it', 'its', 'you', 'your', 'we', 'our',
            'they', 'their', 'from', 'up', 'out', 'if', 'about', 'who', 'get', 'which',
            'go', 'me', 'when', 'make', 'like', 'no', 'just', 'over', 'such', 'not'];

        $text = strtolower($text);
        $text = preg_replace('/[^\w\s]/', '', $text);
        $words = preg_split('/\s+/', $text);

        $wordCounts = [];
        foreach ($words as $word) {
            if (strlen($word) >= 3 && !in_array($word, $stopWords)) {
                $wordCounts[$word] = ($wordCounts[$word] ?? 0) + 1;
            }
        }

        arsort($wordCounts);
        return array_slice($wordCounts, 0, 15, true);
    }

    /**
     * Get performance estimation for an ad.
     */
    public function estimatePerformance(string $creativeId): array
    {
        $ad = $this->db->fetchOne("SELECT * FROM ads WHERE creative_id = ?", [$creativeId]);
        if (!$ad) return [];

        // Longevity score (0-33): how long the ad has been running
        $daysRunning = 0;
        if ($ad['first_seen'] && $ad['last_seen']) {
            $daysRunning = (new DateTime($ad['first_seen']))->diff(new DateTime($ad['last_seen']))->days;
        }
        $longevityScore = min(33, $daysRunning * 0.5);

        // Geo expansion score (0-33): how many countries
        $countryCount = (int) $this->db->fetchColumn(
            "SELECT COUNT(DISTINCT country) FROM ad_targeting WHERE creative_id = ?",
            [$creativeId]
        );
        $geoScore = min(33, $countryCount * 3.3);

        // Duplication/variant score (0-34): similar ads in same cluster
        $clusterSize = (int) ($this->db->fetchColumn(
            "SELECT cc.member_count FROM creative_clusters cc
             INNER JOIN creative_fingerprints cf ON cf.cluster_id = cc.id
             WHERE cf.creative_id = ?",
            [$creativeId]
        ) ?? 0);
        $dupScore = min(34, $clusterSize * 5);

        $overall = round($longevityScore + $geoScore + $dupScore, 2);

        // Label
        if ($overall >= 70) $label = 'winner';
        elseif ($overall >= 50) $label = 'strong';
        elseif ($overall >= 30) $label = 'average';
        elseif ($overall >= 15) $label = 'weak';
        else $label = 'testing';

        // Store score
        $existing = $this->db->fetchOne("SELECT id FROM performance_scores WHERE creative_id = ?", [$creativeId]);
        $data = [
            'longevity_score'      => $longevityScore,
            'geo_expansion_score'  => $geoScore,
            'duplication_score'    => $dupScore,
            'overall_score'        => $overall,
            'performance_label'    => $label,
        ];

        if ($existing) {
            $this->db->update('performance_scores', $data, 'creative_id = ?', [$creativeId]);
        } else {
            $data['creative_id'] = $creativeId;
            $this->db->insert('performance_scores', $data);
        }

        return array_merge($data, ['days_running' => $daysRunning, 'countries' => $countryCount, 'cluster_size' => $clusterSize]);
    }

    /**
     * Get AI analysis summary for dashboard.
     */
    public function getAnalysisSummary(?string $advertiserId = null): array
    {
        $where = '';
        $params = [];
        if ($advertiserId) {
            $where = ' AND a.advertiser_id = ?';
            $params = [$advertiserId];
        }

        // Sentiment distribution
        $sentiments = $this->db->fetchAll(
            "SELECT ai.sentiment, COUNT(*) as count
             FROM ai_ad_analysis ai
             INNER JOIN ads a ON ai.creative_id = a.creative_id
             WHERE ai.sentiment IS NOT NULL{$where}
             GROUP BY ai.sentiment",
            $params
        );

        // Top hooks
        $allHooks = $this->db->fetchAll(
            "SELECT ai.hooks_detected
             FROM ai_ad_analysis ai
             INNER JOIN ads a ON ai.creative_id = a.creative_id
             WHERE ai.hooks_detected IS NOT NULL AND ai.hooks_detected != 'null'{$where}
             LIMIT 500",
            $params
        );

        $hookCounts = [];
        foreach ($allHooks as $row) {
            $hooks = json_decode($row['hooks_detected'], true);
            if (!is_array($hooks)) continue;
            foreach (array_keys($hooks) as $hookType) {
                $hookCounts[$hookType] = ($hookCounts[$hookType] ?? 0) + 1;
            }
        }
        arsort($hookCounts);

        // Performance distribution
        $performance = $this->db->fetchAll(
            "SELECT ps.performance_label, COUNT(*) as count
             FROM performance_scores ps
             INNER JOIN ads a ON ps.creative_id = a.creative_id
             WHERE ps.performance_label IS NOT NULL" . str_replace('AND a.', 'AND a.', $where) . "
             GROUP BY ps.performance_label",
            $params
        );

        return [
            'sentiments'   => $sentiments,
            'top_hooks'    => $hookCounts,
            'performance'  => $performance,
        ];
    }

    private function log(string $message): void
    {
        echo "[" . date('Y-m-d H:i:s') . "] AI: {$message}\n";
    }
}
