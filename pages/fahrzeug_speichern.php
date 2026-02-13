<?php
include '../includes/session.php';
require_once __DIR__ . '/../includes/api_config.php';
include '../includes/db_connect.php';
require_once __DIR__ . '/../includes/api_helpers.php';

if (!isApiAuthenticated()) {
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
    $api = getApiClient();
    $api->postVehicle($name, $fuelType);
    $_SESSION['success_message'] = 'Fahrzeug erfolgreich angelegt.';
    header('Location: onboarding.php');
    exit;
} catch (Throwable $e) {
    $_SESSION['error_message'] = $e->getMessage();
    header('Location: fahrzeug_hinzufuegen.php');
    exit;
}
