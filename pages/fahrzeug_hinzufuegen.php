<?php
// eintrag_hinzufuegen.php
session_start();
include '../includes/db_connect.php';
include '../includes/header.php';
include '../includes/functions.php';
?>

<form action="fahrzeug_speichern.php" method="post" enctype="multipart/form-data">
    <input type="text" name="marke" placeholder="Marke" required>
    <input type="text" name="modell" placeholder="Modell" required>
    <!-- Weitere Felder -->
    <input type="file" name="bild">
    <button type="submit">Fahrzeug speichern</button>
</form>


<?php include '../includes/footer.php'; ?>