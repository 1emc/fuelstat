<?php
include '../includes/session.php';
include '../includes/db_connect.php';
require_once __DIR__ . '/../includes/api_helpers.php';
$api = getApiClient();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['id'], $_POST['fahrzeug_id'])) {
    header('Location: ../index.php');
    exit;
}
$eintrag_id = trim($_POST['id']);
$fahrzeug_id = trim($_POST['fahrzeug_id']);
$type = $_POST['type'] ?? 'fillup';

if ($type !== 'fillup') {
    $_SESSION['warning_message'] = 'Nur Tankvorgänge können bearbeitet werden.';
    header('Location: fahrzeug_detail.php?id=' . rawurlencode($fahrzeug_id));
    exit;
}

$datum = $_POST['datum'] ?? '';
$tachostand = (int)($_POST['tachostand'] ?? 0);
$menge = (float)($_POST['menge'] ?? 0);
$kosten = (float)($_POST['kosten'] ?? 0);
$standort = trim($_POST['standort'] ?? '');
$vollgetankt = isset($_POST['vollgetankt']);
$skipPrevious = isset($_POST['skip_previous']);
$filledAt = $datum ? $datum . 'T12:00:00.000Z' : null;

try {
    $api->patchFillup($eintrag_id, [
        'odometerKm' => $tachostand,
        'unit' => 'l',
        'amount' => $menge,
        'totalCostEur' => $kosten,
        'station' => $standort !== '' ? $standort : null,
        'filledAt' => $filledAt,
        'isFull' => $vollgetankt,
        'skipPrevious' => $skipPrevious,
    ]);
    $_SESSION['success_message'] = 'Tankvorgang aktualisiert.';
} catch (Throwable $e) {
    $_SESSION['error_message'] = $e->getMessage();
}
header('Location: fahrzeug_detail.php?id=' . rawurlencode($fahrzeug_id));
exit;
