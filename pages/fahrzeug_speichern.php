<?php
include '../includes/session.php';
require_once __DIR__ . '/../includes/api_config.php';
include '../includes/db_connect.php';
require_once __DIR__ . '/../includes/api_helpers.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isApiAuthenticated()) {
    die('Benutzer nicht eingeloggt.');
}

$daten = fahrzeugDatenAusPost($_POST);
if ($daten['name'] === '' || !array_key_exists($daten['fuelType'], getKraftstoffOptionen())) {
    $_SESSION['error_message'] = 'Name und gültige Kraftstoffart sind erforderlich.';
    header('Location: fahrzeug_hinzufuegen.php');
    exit;
}

try {
    $api = getApiClient();
    $fahrzeug = $api->postVehicle($daten);
    $bildFehler = speichereFahrzeugBild($api, $fahrzeug['id'], $_FILES, $_POST);
    if ($bildFehler !== null) {
        $_SESSION['warning_message'] = 'Fahrzeug angelegt, aber: ' . $bildFehler;
    } else {
        $_SESSION['success_message'] = 'Fahrzeug erfolgreich angelegt.';
    }
    header('Location: onboarding.php');
    exit;
} catch (Throwable $e) {
    $_SESSION['error_message'] = $e->getMessage();
    header('Location: fahrzeug_hinzufuegen.php');
    exit;
}
