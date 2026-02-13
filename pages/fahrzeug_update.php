<?php
include '../includes/session.php';
include '../includes/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['fahrzeug_id'])) {
    header('Location: ../index.php');
    exit;
}
$fahrzeug_id = trim($_POST['fahrzeug_id']);
$name = trim($_POST['name'] ?? '');
$fuelType = $_POST['fuelType'] ?? 'diesel';

if ($name === '') {
    $_SESSION['error_message'] = 'Name ist erforderlich.';
    header('Location: fahrzeug_bearbeiten.php?id=' . rawurlencode($fahrzeug_id));
    exit;
}
$allowed = ['diesel', 'petrol', 'electric', 'hybrid', 'cng', 'lpg', 'other'];
if (!in_array($fuelType, $allowed, true)) {
    $fuelType = 'diesel';
}

try {
    $api->patchVehicle($fahrzeug_id, ['name' => $name, 'fuelType' => $fuelType]);
    $_SESSION['success_message'] = 'Fahrzeug aktualisiert.';
    header('Location: fahrzeug_detail.php?id=' . rawurlencode($fahrzeug_id));
    exit;
} catch (Throwable $e) {
    $_SESSION['error_message'] = $e->getMessage();
    header('Location: fahrzeug_bearbeiten.php?id=' . rawurlencode($fahrzeug_id));
    exit;
}
