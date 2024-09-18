<?php
include '../includes/db_connect.php';

$benutzer_id = 1; // Temporär
$marke = $_POST['marke'];
$modell = $_POST['modell'];
// Weitere Felder

// Bild hochladen
$bild = $_FILES['bild']['name'];
$ziel = "../images/" . basename($bild);
move_uploaded_file($_FILES['bild']['tmp_name'], $ziel);

$sql = "INSERT INTO Fahrzeuge (benutzer_id, marke, modell, bild) VALUES ('$benutzer_id', '$marke', '$modell', '$bild')";
if ($conn->query($sql) === TRUE) {
    header("Location: ../index.php");
} else {
    echo "Fehler: " . $sql . "<br>" . $conn->error;
}
?>
