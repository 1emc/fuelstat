<?php
// CSV-Export aller Einträge eines Fahrzeugs (Semikolon + UTF-8-BOM, damit Excel es direkt öffnet)
include '../includes/session.php';
require_once __DIR__ . '/../includes/api_helpers.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isApiAuthenticated()) {
    header('Location: login.php');
    exit;
}
$fahrzeug_id = trim($_GET['id'] ?? '');
$jahr = isset($_GET['jahr']) && $_GET['jahr'] !== '' ? (int)$_GET['jahr'] : null;

$api = getApiClient();
try {
    $fahrzeug = $api->getVehicle($fahrzeug_id);
} catch (Throwable $e) {
    http_response_code(404);
    exit('Fahrzeug nicht gefunden.');
}
$einheit = einheitKurz(getEinheitFuerKraftstoff($fahrzeug['fuel_type'] ?? null));

$zeilen = [];
foreach (getMergedEintraegeFromApi($api, $fahrzeug_id)['eintraege'] as $monatsEintraege) {
    foreach ($monatsEintraege as $e) {
        if ($jahr === null || (int)substr($e['datum'], 0, 4) === $jahr) {
            $zeilen[] = $e;
        }
    }
}
usort($zeilen, fn($a, $b) => strcmp($a['datum'], $b['datum']));

$zahl = fn($wert, int $stellen) => $wert === null || $wert === '' ? '' : number_format((float)$wert, $stellen, ',', '');
$dateiname = preg_replace('/[^A-Za-z0-9_-]+/', '_', $fahrzeug['name'] ?? 'fahrzeug') . ($jahr ? '_' . $jahr : '') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $dateiname . '"');
$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");
fputcsv($out, ['Datum', 'Kategorie', 'Tachostand (km)', 'Menge (' . $einheit . ')', 'Kosten (EUR)', 'Vollgetankt', 'Verbrauch (' . $einheit . '/100km)', 'Ort/Anbieter', 'Beschreibung'], ';');
foreach ($zeilen as $e) {
    $istTank = $e['kategorie'] === 'Tankfuellung';
    fputcsv($out, [
        date('d.m.Y', strtotime($e['datum'])),
        getKategorieName($e['kategorie']),
        $e['tachostand'] ?? '',
        $istTank ? $zahl($e['menge'], 2) : '',
        $zahl($e['kosten'], 2),
        $istTank ? (!empty($e['vollgetankt']) ? 'ja' : 'nein') : '',
        $istTank ? $zahl($e['l_per_100km'] ?? null, 2) : '',
        $e['standort'] ?? '',
        $e['beschreibung'] ?? '',
    ], ';');
}
fclose($out);
