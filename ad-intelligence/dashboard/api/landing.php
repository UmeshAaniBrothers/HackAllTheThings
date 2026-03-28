<?php

/**
 * API: Landing Page Intelligence
 * Returns funnel analysis, technology detection, and page change data.
 */

header('Content-Type: application/json');

$basePath = dirname(dirname(__DIR__));
$config = require $basePath . '/config/config.php';

require_once $basePath . '/src/Database.php';
require_once $basePath . '/src/LandingPageAnalyzer.php';

try {
    $db = Database::getInstance($config['db']);
    $analyzer = new LandingPageAnalyzer($db);

    $advertiserId = isset($_GET['advertiser_id']) ? trim($_GET['advertiser_id']) : null;

    $funnelDist = $analyzer->getFunnelDistribution($advertiserId);

    $pages = $advertiserId
        ? $analyzer->getAdvertiserLandingPages($advertiserId)
        : $db->fetchAll("SELECT * FROM landing_pages ORDER BY last_scraped_at DESC LIMIT 50");

    $recentChanges = $db->fetchAll(
        "SELECT lpc.*, lp.url, lp.domain
         FROM landing_page_changes lpc
         INNER JOIN landing_pages lp ON lpc.landing_page_id = lp.id
         ORDER BY lpc.detected_at DESC LIMIT 30"
    );

    // Technology distribution
    $techDist = [];
    foreach ($pages as $page) {
        $techs = json_decode($page['technologies'] ?? '[]', true);
        foreach ($techs as $tech) {
            $techDist[$tech] = ($techDist[$tech] ?? 0) + 1;
        }
    }
    arsort($techDist);

    echo json_encode([
        'success'       => true,
        'pages'         => $pages,
        'funnel_distribution' => $funnelDist,
        'recent_changes' => $recentChanges,
        'technologies'  => $techDist,
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
