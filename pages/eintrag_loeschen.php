<?php
// pages/eintrag_loeschen.php
// Session-Handling
include '../includes/session.php';
// Andere Includes
include '../includes/db_connect.php';

// Nur POST erlauben
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Ungültige Anfrage.');
}

// IDs prüfen
if (!isset($_POST['id'], $_POST['fahrzeug_id'])) {
    die('Eintrag-ID oder Fahrzeug-ID fehlt.');
}
$eintrag_id   = intval($_POST['id']);
$fahrzeug_id  = intval($_POST['fahrzeug_id']);

// Löschen ausführen
$stmt = $conn->prepare("DELETE FROM eintraege WHERE id = ? AND fahrzeug_id = ?");
if (!$stmt) {
    die('Prepare-Fehler: ' . $conn->error);
}
$stmt->bind_param('ii', $eintrag_id, $fahrzeug_id);

if ($stmt->execute()) {
    // Erfolg: zurück zur Fahrzeugdetailseite
    header("Location: fahrzeug_detail.php?id={$fahrzeug_id}");
    exit();
} else {
    die('Fehler beim Löschen des Eintrags: ' . $stmt->error);
}
?>
