<?php
include '../includes/session.php';
include '../includes/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    die('Benutzer nicht eingeloggt.');
}

$name = trim($_POST['name'] ?? '');
$fuelType = $_POST['fuelType'] ?? 'diesel';

$allowed = ['diesel', 'petrol', 'electric', 'hybrid', 'cng', 'lpg', 'other'];
if ($name === '' || !in_array($fuelType, $allowed, true)) {
    $_SESSION['error_message'] = 'Name und gültige Kraftstoffart sind erforderlich.';
    header('Location: fahrzeug_hinzufuegen.php');
    exit;
}

try {
    $api->postVehicle($name, $fuelType);
    $_SESSION['success_message'] = 'Fahrzeug erfolgreich angelegt.';
    header('Location: onboarding.php');
    exit;
} catch (Throwable $e) {
    $_SESSION['error_message'] = $e->getMessage();
    header('Location: fahrzeug_hinzufuegen.php');
    exit;
}
