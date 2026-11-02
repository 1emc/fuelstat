<?php
include '../includes/session.php';
include '../includes/header.php';
include_once '../includes/functions.php';

$fahrzeug = [];
$formAction = 'fahrzeug_speichern.php';
$abbrechenUrl = '../index.php';
?>

<div class="container mt-3 mt-lg-5" style="max-width: 1040px;">
    <h1 class="h3 d-none d-lg-block mb-3">Fahrzeug hinzufügen</h1>
    <?php include '../includes/form_fahrzeug.php'; ?>
</div>

<?php include '../includes/footer.php'; ?>
