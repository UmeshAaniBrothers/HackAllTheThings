<?php

/**
 * API: Creative Analytics
 * Returns creative intelligence data: headlines, CTAs, formats, domains.
 */

header('Content-Type: application/json');

$basePath = dirname(dirname(__DIR__));
$config = require $basePath . '/config/config.php';

require_once $basePath . '/src/Database.php';
require_once $basePath . '/src/Analytics.php';

try {
    $db = Database::getInstance($config['db']);
    $analytics = new Analytics($db);

    $advertiserId = isset($_GET['advertiser_id']) ? trim($_GET['advertiser_id']) : null;

    $creative = $analytics->getCreativeAnalytics($advertiserId);

    // Intelligence score per advertiser
    $scores = [];
    if ($advertiserId) {
        $scores[] = array_merge(
            ['advertiser_id' => $advertiserId],
            $analytics->getIntelligenceScore($advertiserId)
        );
    } else {
        $advertisers = $analytics->getAdvertiserList();
        foreach (array_slice($advertisers, 0, 20) as $adv) {
            $scores[] = array_merge(
                ['advertiser_id' => $adv['advertiser_id']],
                $analytics->getIntelligenceScore($adv['advertiser_id'])
            );
        }
    }

    echo json_encode([
        'success'   => true,
        'creative'  => $creative,
        'scores'    => $scores,
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
