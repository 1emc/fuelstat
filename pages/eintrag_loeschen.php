<?php
include '../includes/session.php';
include '../includes/db_connect.php';
require_once __DIR__ . '/../includes/api_helpers.php';
$api = getApiClient();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Ungültige Anfrage.');
}
if (!isset($_POST['id'], $_POST['fahrzeug_id'])) {
    die('Eintrag-ID oder Fahrzeug-ID fehlt.');
}
$eintrag_id = trim($_POST['id']);
$fahrzeug_id = trim($_POST['fahrzeug_id']);
$type = $_POST['type'] ?? 'entry';

if ($type === 'fillup') {
    try {
        $api->deleteFillup($eintrag_id);
        $_SESSION['success_message'] = 'Tankvorgang gelöscht.';
    } catch (Throwable $e) {
        $_SESSION['error_message'] = $e->getMessage();
    }
} else {
    // API (OpenAPI 1.0.0) bietet kein Löschen von Entries – nur Fillups
    $_SESSION['warning_message'] = 'Löschen von Ausgabe-Einträgen ist über die API derzeit nicht möglich.';
}
header('Location: fahrzeug_detail.php?id=' . rawurlencode($fahrzeug_id));
exit;
