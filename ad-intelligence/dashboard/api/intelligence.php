<?php

/**
 * API: AI Intelligence
 * Returns hook detection, sentiment analysis, performance scores, and fingerprints.
 */

header('Content-Type: application/json');

$basePath = dirname(dirname(__DIR__));
$config = require $basePath . '/config/config.php';

require_once $basePath . '/src/Database.php';
require_once $basePath . '/src/AIIntelligence.php';
require_once $basePath . '/src/CreativeFingerprint.php';

try {
    $db = Database::getInstance($config['db']);
    $ai = new AIIntelligence($db);
    $fingerprints = new CreativeFingerprint($db);

    $advertiserId = isset($_GET['advertiser_id']) ? trim($_GET['advertiser_id']) : null;
    $creativeId = isset($_GET['creative_id']) ? trim($_GET['creative_id']) : null;

    $result = ['success' => true];

    if ($creativeId) {
        // Single ad analysis
        $analysis = $db->fetchOne("SELECT * FROM ai_ad_analysis WHERE creative_id = ?", [$creativeId]);
        $performance = $ai->estimatePerformance($creativeId);

        $result['analysis'] = $analysis ? [
            'hooks'      => json_decode($analysis['hooks_detected'] ?? '{}', true),
            'sentiment'  => $analysis['sentiment'],
            'score'      => $analysis['sentiment_score'],
            'keywords'   => json_decode($analysis['keywords'] ?? '[]', true),
            'persuasion' => json_decode($analysis['persuasion_techniques'] ?? '[]', true),
        ] : null;
        $result['performance'] = $performance;
    } else {
        // Summary view
        $result['summary'] = $ai->getAnalysisSummary($advertiserId);
        $result['ab_tests'] = $fingerprints->getAbTests($advertiserId);

        if ($advertiserId) {
            $result['clusters'] = $fingerprints->getClusters($advertiserId);
        }
    }

    echo json_encode($result);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
