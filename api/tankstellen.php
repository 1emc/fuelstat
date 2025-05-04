<?php
// api/tankstellen.php
require_once '../includes/functions.php';
header('Content-Type: application/json');

if (!isset($_GET['lat'], $_GET['lng'], $_GET['radius'], $_GET['type'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Fehlende Parameter'], JSON_UNESCAPED_UNICODE);
    exit;
}
$lat = floatval($_GET['lat']);
$lng = floatval($_GET['lng']);
$radius = intval($_GET['radius']);
$type = $_GET['type'];

// API-Key nur hier im Backend!
$apikey = '7169ad93-5669-9fb0-47da-f146cbb835b6';

$stations = getNearbyStationsTankerkoenig($lat, $lng, $radius, $type, $apikey);

if ($stations === null) {
    // Versuche, mehr Details zu liefern
    $url = 'https://creativecommons.tankerkoenig.de/json/list.php'
        . '?lat=' . urlencode($lat)
        . '&lng=' . urlencode($lng)
        . '&rad=' . urlencode($radius)
        . '&type=' . urlencode($type)
        . '&apikey=' . urlencode($apikey);
    $context = stream_context_create(['http' => ['ignore_errors' => true]]);
    $json = @file_get_contents($url, false, $context);
    $httpStatus = isset($http_response_header[0]) ? $http_response_header[0] : 'unbekannt';
    $apiError = null;
    if ($json !== false) {
        $data = json_decode($json, true);
        if (isset($data['message'])) {
            $apiError = $data['message'];
        } elseif (isset($data['note'])) {
            $apiError = $data['note'];
        } elseif (isset($data['error'])) {
            $apiError = $data['error'];
        }
    }
    http_response_code(500);
    echo json_encode([
        'error' => 'Tankerkönig-API-Fehler',
        'http_status' => $httpStatus,
        'api_error' => $apiError,
        'url' => $url
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['stations' => $stations], JSON_UNESCAPED_UNICODE); 

?>