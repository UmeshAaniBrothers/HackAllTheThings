<?php

/**
 * ExportManager - CSV, PDF Reports & Scheduled Exports
 *
 * Generates data exports in multiple formats and manages
 * scheduled report delivery.
 */
class ExportManager
{
    private Database $db;
    private string $exportPath;

    public function __construct(Database $db, string $exportPath = null)
    {
        $this->db = $db;
        $this->exportPath = $exportPath ?? dirname(__DIR__) . '/storage/exports/';

        if (!is_dir($this->exportPath)) {
            mkdir($this->exportPath, 0755, true);
        }
    }

    // =========================================================
    // CSV Export
    // =========================================================

    /**
     * Export ads to CSV with filters.
     */
    public function exportAdsCsv(array $filters = []): string
    {
        $ads = $this->getFilteredAds($filters);
        $filename = 'ads_export_' . date('Y-m-d_His') . '.csv';
        $filepath = $this->exportPath . $filename;

        $fp = fopen($filepath, 'w');
        fputcsv($fp, ['Creative ID', 'Advertiser ID', 'Type', 'Status', 'Headline', 'Description', 'CTA', 'Landing URL', 'Countries', 'Platforms', 'First Seen', 'Last Seen']);

        foreach ($ads as $ad) {
            fputcsv($fp, [
                $ad['creative_id'],
                $ad['advertiser_id'],
                $ad['ad_type'],
                $ad['status'],
                $ad['headline'] ?? '',
                $ad['description'] ?? '',
                $ad['cta'] ?? '',
                $ad['landing_url'] ?? '',
                $ad['countries'] ?? '',
                $ad['platforms'] ?? '',
                $ad['first_seen'],
                $ad['last_seen'],
            ]);
        }

        fclose($fp);
        return $filepath;
    }

    /**
     * Export advertiser comparison to CSV.
     */
    public function exportComparisonCsv(array $advertiserIds): string
    {
        $filename = 'comparison_' . date('Y-m-d_His') . '.csv';
        $filepath = $this->exportPath . $filename;

        $fp = fopen($filepath, 'w');
        fputcsv($fp, ['Advertiser ID', 'Total Ads', 'Active Ads', 'Countries', 'Platforms', 'Avg Duration (days)', 'Intelligence Score']);

        foreach ($advertiserIds as $advId) {
            $stats = $this->getAdvertiserStats($advId);
            fputcsv($fp, [
                $advId,
                $stats['total_ads'],
                $stats['active_ads'],
                $stats['country_count'],
                $stats['platform_count'],
                $stats['avg_duration'],
                $stats['score'],
            ]);
        }

        fclose($fp);
        return $filepath;
    }

    // =========================================================
    // PDF Report (HTML-based)
    // =========================================================

    /**
     * Generate an HTML report (can be saved as PDF via browser).
     */
    public function generateHtmlReport(string $type, array $params = []): string
    {
        $filename = "report_{$type}_" . date('Y-m-d_His') . '.html';
        $filepath = $this->exportPath . $filename;

        $content = match ($type) {
            'overview'    => $this->buildOverviewReport($params),
            'advertiser'  => $this->buildAdvertiserReport($params),
            'watchlist'   => $this->buildWatchlistReport($params),
            default       => $this->buildOverviewReport($params),
        };

        file_put_contents($filepath, $content);
        return $filepath;
    }

    private function buildOverviewReport(array $params): string
    {
        $totalAds = (int) $this->db->fetchColumn("SELECT COUNT(*) FROM ads");
        $activeAds = (int) $this->db->fetchColumn("SELECT COUNT(*) FROM ads WHERE status = 'active'");
        $newToday = (int) $this->db->fetchColumn("SELECT COUNT(*) FROM ads WHERE DATE(created_at) = CURDATE()");
        $advertisers = (int) $this->db->fetchColumn("SELECT COUNT(DISTINCT advertiser_id) FROM ads");

        $topAdvertisers = $this->db->fetchAll(
            "SELECT advertiser_id, COUNT(*) as ad_count
             FROM ads GROUP BY advertiser_id ORDER BY ad_count DESC LIMIT 10"
        );

        $html = $this->reportHeader('Ad Intelligence - Overview Report');
        $html .= "<h2>Summary</h2><table border='1' cellpadding='8' cellspacing='0'>";
        $html .= "<tr><td><strong>Total Ads</strong></td><td>{$totalAds}</td></tr>";
        $html .= "<tr><td><strong>Active Ads</strong></td><td>{$activeAds}</td></tr>";
        $html .= "<tr><td><strong>New Today</strong></td><td>{$newToday}</td></tr>";
        $html .= "<tr><td><strong>Advertisers Tracked</strong></td><td>{$advertisers}</td></tr></table>";

        $html .= "<h2>Top Advertisers</h2><table border='1' cellpadding='8' cellspacing='0'>";
        $html .= "<tr><th>Advertiser ID</th><th>Ad Count</th></tr>";
        foreach ($topAdvertisers as $adv) {
            $html .= "<tr><td>{$adv['advertiser_id']}</td><td>{$adv['ad_count']}</td></tr>";
        }
        $html .= "</table>";
        $html .= $this->reportFooter();

        return $html;
    }

    private function buildAdvertiserReport(array $params): string
    {
        $advertiserId = $params['advertiser_id'] ?? '';
        $stats = $this->getAdvertiserStats($advertiserId);

        $html = $this->reportHeader("Advertiser Report: {$advertiserId}");
        $html .= "<h2>Advertiser: {$advertiserId}</h2>";
        $html .= "<table border='1' cellpadding='8' cellspacing='0'>";
        $html .= "<tr><td><strong>Total Ads</strong></td><td>{$stats['total_ads']}</td></tr>";
        $html .= "<tr><td><strong>Active Ads</strong></td><td>{$stats['active_ads']}</td></tr>";
        $html .= "<tr><td><strong>Countries</strong></td><td>{$stats['country_count']}</td></tr>";
        $html .= "<tr><td><strong>Avg Duration</strong></td><td>{$stats['avg_duration']} days</td></tr>";
        $html .= "<tr><td><strong>Intelligence Score</strong></td><td>{$stats['score']}/100</td></tr></table>";
        $html .= $this->reportFooter();

        return $html;
    }

    private function buildWatchlistReport(array $params): string
    {
        $watchlistId = $params['watchlist_id'] ?? 0;
        $watchlist = $this->db->fetchOne("SELECT * FROM watchlists WHERE id = ?", [$watchlistId]);
        $name = $watchlist['name'] ?? 'Unknown';

        $members = $this->db->fetchAll(
            "SELECT wa.*, (SELECT COUNT(*) FROM ads WHERE advertiser_id = wa.advertiser_id AND status = 'active') as active_ads
             FROM watchlist_advertisers wa WHERE wa.watchlist_id = ?",
            [$watchlistId]
        );

        $html = $this->reportHeader("Watchlist Report: {$name}");
        $html .= "<h2>Watchlist: {$name}</h2>";
        $html .= "<table border='1' cellpadding='8' cellspacing='0'>";
        $html .= "<tr><th>Advertiser ID</th><th>Name</th><th>Active Ads</th></tr>";
        foreach ($members as $m) {
            $html .= "<tr><td>{$m['advertiser_id']}</td><td>" . htmlspecialchars($m['advertiser_name'] ?? '') . "</td><td>{$m['active_ads']}</td></tr>";
        }
        $html .= "</table>";
        $html .= $this->reportFooter();

        return $html;
    }

    private function reportHeader(string $title): string
    {
        return "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>{$title}</title>
        <style>body{font-family:Arial,sans-serif;margin:40px}h1{color:#333}table{width:100%;border-collapse:collapse;margin:20px 0}th{background:#4361ee;color:#fff;padding:10px}td{padding:8px;border:1px solid #ddd}</style>
        </head><body><h1>{$title}</h1><p>Generated: " . date('Y-m-d H:i:s') . "</p>";
    }

    private function reportFooter(): string
    {
        return "<hr><p><small>Ad Intelligence Dashboard - Automated Report</small></p></body></html>";
    }

    // =========================================================
    // Scheduled Reports
    // =========================================================

    /**
     * Process due scheduled reports.
     */
    public function processScheduledReports(): int
    {
        $reports = $this->db->fetchAll(
            "SELECT * FROM scheduled_reports WHERE is_active = 1"
        );

        $sent = 0;
        foreach ($reports as $report) {
            if ($this->isDue($report)) {
                $filters = json_decode($report['filters'] ?? '{}', true);
                $filepath = match ($report['format']) {
                    'csv'  => $this->exportAdsCsv($filters),
                    'pdf'  => $this->generateHtmlReport($report['report_type'], $filters),
                    'json' => $this->exportJson($filters),
                    default => null,
                };

                if ($filepath) {
                    $this->db->update('scheduled_reports', ['last_run_at' => date('Y-m-d H:i:s')], 'id = ?', [$report['id']]);
                    $sent++;
                }
            }
        }

        return $sent;
    }

    /**
     * Check if a scheduled report is due.
     */
    private function isDue(array $report): bool
    {
        if (!$report['last_run_at']) return true;

        $lastRun = strtotime($report['last_run_at']);
        $cron = $report['schedule_cron'];

        // Simple cron parsing for common patterns
        $parts = explode(' ', $cron);
        if (count($parts) !== 5) return false;

        $hour = (int) ($parts[1] !== '*' ? $parts[1] : date('H'));
        $dayOfWeek = $parts[4] !== '*' ? (int) $parts[4] : -1;

        // Check if enough time has passed (at least 1 hour)
        if (time() - $lastRun < 3600) return false;

        // Check day of week if specified
        if ($dayOfWeek >= 0 && (int) date('w') !== $dayOfWeek) return false;

        return true;
    }

    /**
     * Export as JSON.
     */
    private function exportJson(array $filters): string
    {
        $ads = $this->getFilteredAds($filters);
        $filename = 'export_' . date('Y-m-d_His') . '.json';
        $filepath = $this->exportPath . $filename;

        file_put_contents($filepath, json_encode($ads, JSON_PRETTY_PRINT));
        return $filepath;
    }

    // =========================================================
    // Data Helpers
    // =========================================================

    private function getFilteredAds(array $filters): array
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['advertiser_id'])) {
            $where[] = 'a.advertiser_id = ?';
            $params[] = $filters['advertiser_id'];
        }
        if (!empty($filters['status'])) {
            $where[] = 'a.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['ad_type'])) {
            $where[] = 'a.ad_type = ?';
            $params[] = $filters['ad_type'];
        }

        $whereClause = implode(' AND ', $where);

        return $this->db->fetchAll(
            "SELECT a.*, d.headline, d.description, d.cta, d.landing_url,
                    (SELECT GROUP_CONCAT(DISTINCT t.country) FROM ad_targeting t WHERE t.creative_id = a.creative_id) as countries,
                    (SELECT GROUP_CONCAT(DISTINCT t.platform) FROM ad_targeting t WHERE t.creative_id = a.creative_id) as platforms
             FROM ads a
             LEFT JOIN ad_details d ON a.creative_id = d.creative_id
                 AND d.id = (SELECT MAX(id) FROM ad_details WHERE creative_id = a.creative_id)
             WHERE {$whereClause}
             ORDER BY a.last_seen DESC",
            $params
        );
    }

    private function getAdvertiserStats(string $advertiserId): array
    {
        return [
            'total_ads'     => (int) $this->db->fetchColumn("SELECT COUNT(*) FROM ads WHERE advertiser_id = ?", [$advertiserId]),
            'active_ads'    => (int) $this->db->fetchColumn("SELECT COUNT(*) FROM ads WHERE advertiser_id = ? AND status = 'active'", [$advertiserId]),
            'country_count' => (int) $this->db->fetchColumn("SELECT COUNT(DISTINCT country) FROM ad_targeting t INNER JOIN ads a ON t.creative_id = a.creative_id WHERE a.advertiser_id = ?", [$advertiserId]),
            'platform_count' => (int) $this->db->fetchColumn("SELECT COUNT(DISTINCT platform) FROM ad_targeting t INNER JOIN ads a ON t.creative_id = a.creative_id WHERE a.advertiser_id = ?", [$advertiserId]),
            'avg_duration'  => round((float) ($this->db->fetchColumn("SELECT AVG(TIMESTAMPDIFF(DAY, first_seen, last_seen)) FROM ads WHERE advertiser_id = ?", [$advertiserId]) ?? 0), 1),
            'score'         => (int) ($this->db->fetchColumn("SELECT intelligence_score FROM advertiser_profiles WHERE advertiser_id = ?", [$advertiserId]) ?? 0),
        ];
    }
}
