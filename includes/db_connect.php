<?php
$servername = "localhost";
$username = "d0417106";
$password = "oNKLFiex4AqwizHt9msE";
$dbname = "d0417106";

// Verbindung herstellen
$conn = new mysqli($servername, $username, $password, $dbname);

// Verbindung prüfen
if ($conn->connect_error) {
    die("Verbindung fehlgeschlagen: " . $conn->connect_error);
}
?>
