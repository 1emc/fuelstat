<?php
// eintrag_speichern.php
session_start();
include '../includes/db_connect.php';

// Daten aus dem Formular abrufen
$fahrzeug_id = intval($_POST['fahrzeug_id']);
$eintragstyp = $_POST['eintragstyp'];
$datum = $_POST['datum'];
$tachostand = $_POST['tachostand'];

// Gemeinsame Felder für alle Einträge
$data = [
    'fahrzeug_id' => $fahrzeug_id,
    'datum' => $datum,
    'tachostand' => $tachostand,
];

// Unterschiedliche Verarbeitung basierend auf dem Eintragstyp
if ($eintragstyp == 'Tankfüllung') {
    $data['kategorie'] = 'Tankfüllung';
    $data['menge'] = $_POST['menge'];
    $data['kosten'] = $_POST['kosten'];
    $data['preis_pro_einheit'] = $_POST['preis_pro_einheit'];
    $data['vollgetankt'] = isset($_POST['vollgetankt']) ? 1 : 0;
    // Weitere Verarbeitung und Validierung
} elseif ($eintragstyp == 'Andere Ausgabe') {
    $data['kategorie'] = $_POST['kategorie'];
    $data['kosten'] = $_POST['kosten'];
    $data['beschreibung'] = $_POST['beschreibung'];
    // Weitere Verarbeitung und Validierung
} elseif ($eintragstyp == 'Fahrt') {
    $data['kategorie'] = 'Fahrt';
    $data['startort'] = $_POST['startort'];
    $data['zielort'] = $_POST['zielort'];
    $data['zweck'] = $_POST['zweck'];
    $data['gefahrene_km'] = $_POST['gefahrene_km'];
    // Weitere Verarbeitung und Validierung
} else {
    // Fehlerbehandlung
    die("Ungültiger Eintragstyp.");
}

// Daten in die Datenbank einfügen
$spalten = implode(", ", array_keys($data));
$werte = implode(", ", array_fill(0, count($data), '?'));

$sql = "INSERT INTO eintraege ($spalten) VALUES ($werte)";
$stmt = $conn->prepare($sql);
$stmt->bind_param(str_repeat('s', count($data)), ...array_values($data));

if ($stmt->execute()) {
    // Erfolgreich gespeichert
    header("Location: fahrzeug_detail.php?id=$fahrzeug_id");
} else {
    // Fehlerbehandlung
    echo "Fehler beim Speichern des Eintrags: " . $stmt->error;
}
?>
