<?php
include '../includes/session.php';
include '../includes/db_connect.php';
include '../includes/header.php';
include_once '../includes/functions.php';

if (!isset($_GET['id'])) {
    die("Fahrzeug-ID fehlt.");
}
$fahrzeug_id = trim($_GET['id']);

try {
    $fahrzeug = $api->getVehicle($fahrzeug_id);
} catch (Throwable $e) {
    die("Fahrzeug nicht gefunden.");
}

$formAction = 'fahrzeug_update.php';
$abbrechenUrl = 'fahrzeug_detail.php?id=' . urlencode($fahrzeug_id);
?>

<div class="container mt-5">
    <h2>Fahrzeug bearbeiten</h2>
    <?php include '../includes/form_fahrzeug.php'; ?>
</div>

<?php include '../includes/footer.php'; ?>
