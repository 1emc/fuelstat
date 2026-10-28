<?php
// api/tankstellen.php – Tankstellen in der Nähe über Tankerkönig (nur für angemeldete Nutzer)
include __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/api_config.php';
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json');

$antwort = function (int $status, array $daten) {
    http_response_code($status);
    echo json_encode($daten, JSON_UNESCAPED_UNICODE);
    exit;
};

// Ohne Login würde jeder Besucher das Kontingent des API-Keys nutzen können
if (!isApiAuthenticated()) {
    $antwort(401, ['error' => 'Bitte anmelden.']);
}
// Session nicht länger sperren als nötig (der Tankerkönig-Aufruf kann dauern)
session_write_close();

$lat = filter_var($_GET['lat'] ?? null, FILTER_VALIDATE_FLOAT);
$lng = filter_var($_GET['lng'] ?? null, FILTER_VALIDATE_FLOAT);
$radius = filter_var($_GET['radius'] ?? null, FILTER_VALIDATE_INT);
$type = $_GET['type'] ?? '';

if ($lat === false || $lng === false || $lat === null || $lng === null || abs($lat) > 90 || abs($lng) > 180) {
    $antwort(400, ['error' => 'Ungültige Koordinaten']);
}
if ($radius === false || $radius === null || $radius < 1 || $radius > 25) {
    $antwort(400, ['error' => 'Radius muss zwischen 1 und 25 km liegen']);
}
// Tankerkönig kennt nur diese Sorten; alles andere (Elektro, Gas …) sucht alle Tankstellen
if (!in_array($type, ['e5', 'e10', 'diesel', 'all'], true)) {
    $type = 'all';
}

// API-Key nur hier im Backend – wird nie an den Browser ausgegeben
$keyfile = __DIR__ . '/key.key';
$apikey = is_readable($keyfile) ? trim((string)file_get_contents($keyfile)) : '';
if ($apikey === '') {
    error_log('[Tankstellen] API-Key-Datei api/key.key fehlt oder ist leer');
    $antwort(500, ['error' => 'Die Tankstellensuche ist nicht eingerichtet.']);
}

$stations = getNearbyStationsTankerkoenig($lat, $lng, $radius, $type, $apikey);
if ($stations === null) {
    error_log('[Tankstellen] Tankerkönig-Anfrage fehlgeschlagen');
    $antwort(502, ['error' => 'Tankerkönig-API-Fehler']);
}

$antwort(200, ['stations' => $stations]);
