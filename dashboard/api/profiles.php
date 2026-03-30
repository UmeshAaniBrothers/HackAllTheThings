<?php

/**
 * API: Advertiser Profiles (DNA)
 * Returns advertiser profiling and intelligence data.
 */

header('Content-Type: application/json');

$basePath = dirname(dirname(__DIR__));
$config = require $basePath . '/config/config.php';

require_once $basePath . '/src/Database.php';
require_once $basePath . '/src/AdvertiserProfile.php';

try {
    $db = Database::getInstance($config['db']);
    $profiles = new AdvertiserProfile($db);

    $advertiserId = isset($_GET['advertiser_id']) ? trim($_GET['advertiser_id']) : null;

    if ($advertiserId) {
        $profile = $profiles->getProfile($advertiserId);
        if (!$profile) {
            $profile = $profiles->updateProfile($advertiserId);
        }
        echo json_encode(['success' => true, 'profile' => $profile]);
    } else {
        echo json_encode(['success' => true, 'profiles' => $profiles->getAllProfiles()]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
