<?php
// eintrag_speichern.php
session_start();
include '../includes/db_connect.php';

// Daten aus dem Formular abrufen
$fahrzeug_id = intval($_POST['fahrzeug_id']);
$kategorie = $_POST['kategorie'];
$datum = $_POST['datum'];
$tachostand = !empty($_POST['tachostand']) ? intval($_POST['tachostand']) : null;
$standort = !empty($_POST['standort']) ? $_POST['standort'] : null;

// Unterscheidung zwischen Tankfüllung und anderen Ausgaben
if ($kategorie == 'Tankfüllung') {
    $gesamtpreis = floatval($_POST['gesamtpreis']);
    $preis_pro_einheit = floatval($_POST['preis_pro_einheit']);
    $menge = floatval($_POST['menge']);
    $vollgetankt = isset($_POST['vollgetankt']) ? 1 : 0;
	$skip_previous = isset($_POST['skip_previous']) ? 1 : 0;
    $kosten = $gesamtpreis; // Gesamtkosten sind hier der Gesamtpreis

    // SQL-Abfrage vorbereiten
	$stmt = $conn->prepare("INSERT INTO eintraege (fahrzeug_id, kategorie, datum, tachostand, standort, kosten, preis_pro_einheit, menge, vollgetankt, skip_previous) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
	$stmt->bind_param("issisdddii", $fahrzeug_id, $kategorie, $datum, $tachostand, $standort, $kosten, $preis_pro_einheit, $menge, $vollgetankt, $skip_previous);

} else {
    $kosten = floatval($_POST['kosten']);
    $beschreibung = !empty($_POST['beschreibung']) ? $_POST['beschreibung'] : null;

    // SQL-Abfrage vorbereiten
    $stmt = $conn->prepare("INSERT INTO Eintraege (fahrzeug_id, kategorie, datum, tachostand, standort, kosten, beschreibung) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issisds", $fahrzeug_id, $kategorie, $datum, $tachostand, $standort, $kosten, $beschreibung);
}

// Ausführung der Abfrage
if ($stmt->execute()) {
    // Erfolgreich gespeichert, Weiterleitung zur Detailseite
    header("Location: fahrzeug_detail.php?id=" . $fahrzeug_id);
    exit();
} else {
    // Fehlermeldung
    echo "Fehler beim Speichern des Eintrags: " . $stmt->error;
}

$stmt->close();
$conn->close();
?>
