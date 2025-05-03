<?php
// pages/eintrag_update.php
session_start();
include '../includes/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Ungültige Anfrage.');
}

// IDs prüfen
if (!isset($_POST['id'], $_POST['fahrzeug_id'])) {
    die('Eintrag-ID oder Fahrzeug-ID fehlt.');
}
$eintrag_id   = intval($_POST['id']);
$fahrzeug_id  = intval($_POST['fahrzeug_id']);

// Gemeinsame Felder
$datum       = $_POST['datum'] ?? null;
$tachostand  = isset($_POST['tachostand']) ? intval($_POST['tachostand']) : null;
$kategorie   = $_POST['kategorie'] ?? '';

// Validierung
if (!$datum || !$tachostand) {
    die('Datum und Tachostand sind erforderlich.');
}

// SQL-Aufbau je nach Kategorie
switch ($kategorie) {
    case 'Tankfuellung':
        $menge            = isset($_POST['menge']) ? floatval($_POST['menge']) : null;
        $kosten           = isset($_POST['kosten']) ? floatval($_POST['kosten']) : null;
        $preis_pro_einheit= isset($_POST['preis_pro_einheit']) ? floatval($_POST['preis_pro_einheit']) : null;
        $vollgetankt      = isset($_POST['vollgetankt']) ? 1 : 0;
        
        $sql = "UPDATE eintraege SET datum = ?, tachostand = ?, menge = ?, kosten = ?, preis_pro_einheit = ?, vollgetankt = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) { die('Prepare-Fehler: ' . $conn->error); }
        $stmt->bind_param('sidddii', $datum, $tachostand, $menge, $kosten, $preis_pro_einheit, $vollgetankt, $eintrag_id);
        break;

    case 'Fahrt':
        $startort      = trim($_POST['startort'] ?? '');
        $zielort       = trim($_POST['zielort'] ?? '');
        $zweck         = trim($_POST['zweck'] ?? '');
        $gefahrene_km  = isset($_POST['gefahrene_km']) ? floatval($_POST['gefahrene_km']) : null;
        
        $sql = "UPDATE eintraege SET datum = ?, tachostand = ?, startort = ?, zielort = ?, zweck = ?, gefahrene_km = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) { die('Prepare-Fehler: ' . $conn->error); }
        $stmt->bind_param('sidssdi', $datum, $tachostand, $startort, $zielort, $zweck, $gefahrene_km, $eintrag_id);
        break;

    // Alle anderen Kategorien wie 'Andere Ausgabe' behandeln
    case 'Andere Ausgabe':
    case 'Versicherung':
    case 'Steuer':
    case 'Inspektion':
    case 'Reparatur':
    case 'Reifen':
    case 'TUV':
    case 'Wartung':
    case 'Dekor':
    case 'Verbrauch':
        $kosten       = isset($_POST['kosten']) ? floatval($_POST['kosten']) : null;
        $beschreibung = trim($_POST['beschreibung'] ?? '');
        
        $sql = "UPDATE eintraege SET datum = ?, tachostand = ?, kosten = ?, beschreibung = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) { die('Prepare-Fehler: ' . $conn->error); }
        $stmt->bind_param('sidsi', $datum, $tachostand, $kosten, $beschreibung, $eintrag_id);
        break;

    default:
        die('Unbekannte Kategorie.');
}

// Ausführen und prüfen
if (!$stmt->execute()) {
    die('Execute-Fehler: ' . $stmt->error);
}
$stmt->close();
$conn->close();

// Weiterleitung zurück zur Detailseite
header("Location: fahrzeug_detail.php?id={$fahrzeug_id}#eintraege");
exit();
?>
