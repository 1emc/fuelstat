<?php
$servername = "localhost";
$db_username = "d0417106";
$db_password = "oNKLFiex4AqwizHt9msE";
$dbname = "d0417106";

// Verbindung herstellen
$conn = new mysqli($servername, $db_username, $db_password, $dbname);

// Verbindung prüfen
if ($conn->connect_error) {
    die("Verbindung fehlgeschlagen: " . $conn->connect_error);
}

//Läuft
?>
