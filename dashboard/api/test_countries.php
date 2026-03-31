<?php
/**
 * Quick test: Enrich countries for a few ads via Google LookupService.
 * Usage: /dashboard/api/test_countries.php?token=ads-intelligent-2024&limit=3
 */

$basePath = dirname(dirname(__DIR__));
$config = require $basePath . '/config/config.php';
require_once $basePath . '/src/Database.php';

header('Content-Type: application/json');

$token = $_GET['token'] ?? '';
if ($token !== 'ads-intelligent-2024') {
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

$limit = min((int)($_GET['limit'] ?? 3), 10);

try {
    $db = Database::getInstance($config['db']);

    // Override: just do a small batch
    $ads = $db->fetchAll(
        "SELECT a.creative_id, a.advertiser_id,
                (SELECT COUNT(DISTINCT t.country) FROM ad_targeting t WHERE t.creative_id = a.creative_id) as country_count
         FROM ads a
         WHERE a.status = 'active'
         HAVING country_count <= 1
         ORDER BY a.last_seen DESC
         LIMIT ?",
        [$limit]
    );

    if (empty($ads)) {
        echo json_encode(['success' => true, 'message' => 'No ads need country enrichment']);
        exit;
    }

    $geoMap = [
        2004=>'AF',2008=>'AL',2012=>'DZ',2016=>'AS',2020=>'AD',2024=>'AO',2010=>'AQ',
        2028=>'AG',2032=>'AR',2051=>'AM',2036=>'AU',2040=>'AT',2031=>'AZ',2048=>'BH',
        2050=>'BD',2052=>'BB',2056=>'BE',2068=>'BO',2070=>'BA',2076=>'BR',2096=>'BN',
        2100=>'BG',2116=>'KH',2120=>'CM',2124=>'CA',2144=>'LK',2152=>'CL',2156=>'CN',
        2158=>'TW',2170=>'CO',2188=>'CR',2191=>'HR',2196=>'CY',2203=>'CZ',2208=>'DK',
        2214=>'DO',2218=>'EC',2818=>'EG',2222=>'SV',2231=>'ET',2233=>'EE',2242=>'FJ',
        2246=>'FI',2250=>'FR',2268=>'GE',2276=>'DE',2288=>'GH',2300=>'GR',2308=>'GD',
        2320=>'GT',2332=>'HT',2340=>'HN',2344=>'HK',2348=>'HU',2352=>'IS',2356=>'IN',
        2360=>'ID',2364=>'IR',2368=>'IQ',2372=>'IE',2376=>'IL',2380=>'IT',2384=>'CI',
        2388=>'JM',2392=>'JP',2398=>'KZ',2400=>'JO',2404=>'KE',2410=>'KR',2414=>'KW',
        2417=>'KG',2418=>'LA',2422=>'LB',2428=>'LV',2434=>'LY',2440=>'LT',2442=>'LU',
        2446=>'MO',2450=>'MG',2458=>'MY',2462=>'MV',2466=>'ML',2470=>'MT',2484=>'MX',
        2496=>'MN',2498=>'MD',2504=>'MA',2508=>'MZ',2512=>'MM',2516=>'NA',2524=>'NP',
        2528=>'NL',2540=>'NC',2554=>'NZ',2558=>'NI',2566=>'NG',2578=>'NO',2586=>'PK',
        2591=>'PA',2598=>'PG',2600=>'PY',2604=>'PE',2608=>'PH',2616=>'PL',2620=>'PT',
        2630=>'PR',2634=>'QA',2638=>'RE',2642=>'RO',2643=>'RU',2646=>'RW',2682=>'SA',
        2686=>'SN',2688=>'RS',2702=>'SG',2703=>'SK',2704=>'VN',2705=>'SI',2710=>'ZA',
        2724=>'ES',2740=>'SR',2752=>'SE',2756=>'CH',2760=>'SY',2764=>'TH',2780=>'TT',
        2784=>'AE',2788=>'TN',2792=>'TR',2800=>'UG',2804=>'UA',2807=>'MK',2826=>'GB',
        2834=>'TZ',2840=>'US',2854=>'BF',2858=>'UY',2860=>'UZ',2862=>'VE',2887=>'YE',
        2894=>'ZM',2716=>'ZW',2312=>'GP',2254=>'GF',2474=>'MQ',
    ];

    // Init Google session
    $cookieFile = tempnam(sys_get_temp_dir(), 'gads_');
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://adstransparency.google.com/?region=anywhere',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
    ]);
    $sessionResp = curl_exec($ch);
    $sessionCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    sleep(1);

    $results = [];

    foreach ($ads as $ad) {
        $creativeId = $ad['creative_id'];
        $advertiserId = $ad['advertiser_id'];

        $reqData = json_encode(['1' => $advertiserId, '2' => $creativeId, '5' => ['1' => 1]]);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://adstransparency.google.com/anji/_/rpc/LookupService/GetCreativeById?authuser=0',
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => 'f.req=' . urlencode($reqData),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_COOKIEFILE => $cookieFile,
            CURLOPT_COOKIEJAR => $cookieFile,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
                'Origin: https://adstransparency.google.com',
                'Referer: https://adstransparency.google.com/',
            ],
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
        ]);

        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($resp, true);
        $creative = $data['1'] ?? [];
        $countryEntries = $creative['17'] ?? [];

        $countries = [];
        foreach ($countryEntries as $entry) {
            $geoId = (int)($entry['1'] ?? 0);
            if ($geoId > 0 && isset($geoMap[$geoId])) {
                $countries[] = $geoMap[$geoId];
            }
        }

        // Save to DB
        $saved = 0;
        foreach ($countries as $country) {
            $exists = $db->fetchOne("SELECT id FROM ad_targeting WHERE creative_id = ? AND country = ?", [$creativeId, $country]);
            if (!$exists) {
                $db->insert('ad_targeting', ['creative_id' => $creativeId, 'country' => $country, 'platform' => 'Google Ads']);
                $saved++;
            }
        }

        $results[] = [
            'creative_id' => $creativeId,
            'http_code' => $httpCode,
            'geo_ids_found' => count($countryEntries),
            'countries' => $countries,
            'new_saved' => $saved,
        ];

        sleep(2);
    }

    @unlink($cookieFile);

    echo json_encode([
        'success' => true,
        'session_code' => $sessionCode,
        'ads_checked' => count($ads),
        'results' => $results,
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
