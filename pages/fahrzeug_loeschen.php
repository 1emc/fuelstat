<?php
// pages/fahrzeug_loeschen.php
session_start();
include '../includes/db_connect.php';

// Nur POST-Anfragen erlauben
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Ungültige Anfrage.');
}

// Fahrzeug-ID prüfen
if (!isset($_POST['fahrzeug_id'])) {
    die('Fahrzeug-ID fehlt.');
}
$fahrzeug_id = intval($_POST['fahrzeug_id']);

// 1. Alle Einträge für das Fahrzeug löschen
$stmtEintraege = $conn->prepare("DELETE FROM eintraege WHERE fahrzeug_id = ?");
if (!$stmtEintraege) {
    die('Prepare-Fehler bei Einträge löschen: ' . $conn->error);
}
$stmtEintraege->bind_param('i', $fahrzeug_id);
$stmtEintraege->execute();
$stmtEintraege->close();

// 2. Fahrzeugbild sichern, bevor Fahrzeug gelöscht wird
$stmtBild = $conn->prepare("SELECT bild FROM fahrzeuge WHERE id = ?");
if (!$stmtBild) {
    die('Prepare-Fehler bei Bildabfrage: ' . $conn->error);
}
$stmtBild->bind_param('i', $fahrzeug_id);
$stmtBild->execute();
$resultBild = $stmtBild->get_result();
$bildDatei = null;
if ($row = $resultBild->fetch_assoc()) {
    $bildDatei = $row['bild'];
}
$stmtBild->close();

// 3. Fahrzeug selbst löschen
$stmtFahrzeug = $conn->prepare("DELETE FROM fahrzeuge WHERE id = ?");
if (!$stmtFahrzeug) {
    die('Prepare-Fehler bei Fahrzeug löschen: ' . $conn->error);
}
$stmtFahrzeug->bind_param('i', $fahrzeug_id);

if ($stmtFahrzeug->execute()) {
    // 4. Unbenutzte Bilder prüfen und löschen
    $usedImages = [];
    $query = $conn->query("SELECT bild FROM fahrzeuge WHERE bild IS NOT NULL AND bild != ''");
    while ($row = $query->fetch_assoc()) {
        $usedImages[] = $row['bild'];
    }

    $imageDir = realpath(__DIR__ . '/../images');
    foreach (glob($imageDir . '/*') as $filePath) {
        $fileName = basename($filePath);
        if (!in_array($fileName, $usedImages) && $fileName !== 'platzhalter.jpg') {
            @unlink($filePath);
        }
    }

    $_SESSION['success_message'] = "Fahrzeug erfolgreich gelöscht.";
    header("Location: ../index.php");
    exit();
} else {
    die('Fehler beim Löschen des Fahrzeugs: ' . $stmtFahrzeug->error);
}

?>