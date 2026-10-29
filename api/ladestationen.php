<?php
// api/ladestationen.php – Ladestationen in der Nähe über Open Charge Map (nur für angemeldete Nutzer)
include __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/api_config.php';
require_once __DIR__ . '/../includes/ladestationen.php';
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
session_write_close();

$lat = filter_var($_GET['lat'] ?? null, FILTER_VALIDATE_FLOAT);
$lng = filter_var($_GET['lng'] ?? null, FILTER_VALIDATE_FLOAT);
$radius = filter_var($_GET['radius'] ?? null, FILTER_VALIDATE_INT);

if ($lat === false || $lng === false || $lat === null || $lng === null || abs($lat) > 90 || abs($lng) > 180) {
    $antwort(400, ['error' => 'Ungültige Koordinaten']);
}
if ($radius === false || $radius === null || $radius < 1 || $radius > 25) {
    $antwort(400, ['error' => 'Radius muss zwischen 1 und 25 km liegen']);
}

// API-Key nur hier im Backend – wird nie an den Browser ausgegeben (nginx sperrt *.key)
$keyfile = __DIR__ . '/ocm.key';
$apikey = is_readable($keyfile) ? trim((string)file_get_contents($keyfile)) : '';
if ($apikey === '') {
    error_log('[Ladestationen] API-Key-Datei api/ocm.key fehlt oder ist leer');
    $antwort(500, ['error' => 'Die Ladestationssuche ist nicht eingerichtet.']);
}

$stationen = ocmLadestationen((float)$lat, (float)$lng, (int)$radius, $apikey);
if ($stationen === null) {
    $antwort(502, ['error' => 'Open Charge Map ist gerade nicht erreichbar.']);
}

$antwort(200, ['stations' => $stationen]);
