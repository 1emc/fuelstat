<?php
include '../includes/session.php';
include '../includes/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Ungültige Anfrage.');
}
if (!isset($_POST['fahrzeug_id'])) {
    die('Fahrzeug-ID fehlt.');
}
$fahrzeug_id = trim($_POST['fahrzeug_id']);

try {
    $api->deleteVehicle($fahrzeug_id);
    $_SESSION['success_message'] = 'Fahrzeug erfolgreich gelöscht.';
} catch (Throwable $e) {
    $_SESSION['error_message'] = $e->getMessage();
}
header('Location: ../index.php');
exit;
