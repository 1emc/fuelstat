<?php
// Debug-Ausgabe aktivieren (temporär für Fehlersuche)
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
// Fatale Fehler sichtbar machen
register_shutdown_function(function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        if (!headers_sent()) {
            header('Content-Type: text/plain; charset=utf-8');
            http_response_code(500);
        }
        echo "Fatal error: {$e['message']} in {$e['file']} on line {$e['line']}\n";
    }
});
// pages/eintrag_speichern.php
// Session-Handling
include '../includes/session.php';
// Andere Includes
include '../includes/db_connect.php';

// Daten aus dem Formular abrufen
$fahrzeug_id = intval($_POST['fahrzeug_id']);
$eintragstyp = $_POST['eintragstyp'] ?? '';
$datum       = $_POST['datum'] ?? null;
$tachostand  = isset($_POST['tachostand']) ? intval($_POST['tachostand']) : null;

// Gemeinsame Felder validieren
if (!$datum || $tachostand === null) {
    die('Datum und Tachostand sind erforderlich.');
}

// Daten-Array vorbereiten
$data = [
    'fahrzeug_id' => $fahrzeug_id,
    'kategorie'    => '',
    'datum'        => $datum,
    'tachostand'   => $tachostand,
    'standort'     => '',
    'kosten'       => 0,
    'preis_pro_einheit' => 0,
    'menge'        => 0,
    'vollgetankt'  => 0,
    'skip_previous'=> 0,
    'beschreibung' => '',
    // Schema verlangt NOT NULL für standort_bezeichnung
    'standort_bezeichnung' => ''
];

// Verarbeitung nach Typ
switch ($eintragstyp) {
    case 'Tankfuellung':
        $data['kategorie']    = 'Tankfuellung';
        $data['kraftstoff']   = $_POST['kraftstoff'] ?? null;
        $data['standort']     = $_POST['standort'] ?? '';
        $data['menge']        = floatval($_POST['menge'] ?? 0);
        $data['kosten']       = floatval($_POST['kosten'] ?? 0);
        $data['preis_pro_einheit'] = floatval($_POST['preis_pro_einheit'] ?? 0);
        $data['vollgetankt']  = isset($_POST['vollgetankt']) ? 1 : 0;
        $data['skip_previous']= isset($_POST['skip_previous']) ? 1 : 0;
        break;

    case 'Andere Ausgabe':
        $data['kategorie']    = $_POST['kategorie'] ?? '';
        $data['kosten']       = floatval($_POST['kosten'] ?? 0);
        $data['beschreibung'] = trim($_POST['beschreibung'] ?? '');
        break;

    case 'Fahrt':
        // Hinweis: Die aktuelle Tabellenstruktur enthält keine Spalten für Fahrt-Details.
        // Wir speichern daher nur eine Ausgabe unter Kategorie 'Wartung' mit Beschreibung.
        $data['kategorie']    = 'Wartung';
        $data['beschreibung'] = trim($_POST['zweck'] ?? 'Fahrt');
        $data['kosten']       = 0;
        break;

    default:
        die('Ungültiger Eintragstyp.');
}

// Dynamisch Spalten und Platzhalter
$columns = array_keys($data);
$placeholders = implode(', ', array_fill(0, count($columns), '?'));
$sql = "INSERT INTO eintraege (" . implode(', ', $columns) . ") VALUES ($placeholders)";

// Param-String: s für strings, d für doubles, i für integers
$types = '';
$params = [];
foreach ($data as $key => $value) {
    if (is_int($value)) {
        $types .= 'i';
    } elseif (is_float($value)) {
        $types .= 'd';
    } else {
        $types .= 's';
    }
    $params[] = $value;
}

$stmt = $conn->prepare($sql);
if (!$stmt) {
    error_log('eintrag_speichern PREPARE-ERROR: ' . $conn->error . ' | SQL=' . $sql);
    die('Prepare-Fehler: ' . $conn->error);
}
$stmt->bind_param($types, ...$params);

if ($stmt->execute()) {
    header("Location: fahrzeug_detail.php?id=$fahrzeug_id");
    exit();
} else {
    error_log('eintrag_speichern EXEC-ERROR: ' . $stmt->error . ' | SQL=' . $sql . ' | TYPES=' . $types . ' | PARAMS=' . json_encode($params));
    die('Fehler beim Speichern des Eintrags: ' . $stmt->error);
}
?>
