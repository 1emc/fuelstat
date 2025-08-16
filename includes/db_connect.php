<?php
$servername = "localhost";
$db_username = "fuelstat";
$db_password = "viey!hh4zaH0ee.Cu";
$dbname = "fuelstat";

// Verbindung herstellen
$conn = new mysqli($servername, $db_username, $db_password, $dbname);

// Verbindung prüfen
if ($conn->connect_error) {
    die("Verbindung fehlgeschlagen: " . $conn->connect_error);
}

//Läuft
?>
