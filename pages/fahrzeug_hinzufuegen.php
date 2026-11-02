<?php
include '../includes/session.php';
include '../includes/header.php';
include_once '../includes/functions.php';

$fahrzeug = [];
$formAction = 'fahrzeug_speichern.php';
$abbrechenUrl = '../index.php';
?>

<div class="container mt-5">
    <h2>Fahrzeug hinzufügen</h2>
    <?php include '../includes/form_fahrzeug.php'; ?>
</div>

<?php include '../includes/footer.php'; ?>
