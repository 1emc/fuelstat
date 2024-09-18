<?php
// fahrzeug_update.php
session_start();
include '../includes/db_connect.php';
include '../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // Fahrzeug-ID
    if (isset($_POST['fahrzeug_id'])) {
        $fahrzeug_id = intval($_POST['fahrzeug_id']);
    } else {
        die("Fahrzeug-ID fehlt.");
    }

    // Felder aus dem Formular
    $marke = trim($_POST['marke']);
    $modell = trim($_POST['modell']);
    $tankgroesse = isset($_POST['tankgroesse']) ? floatval($_POST['tankgroesse']) : null;

    // Validierung der erforderlichen Felder
    if (empty($marke) || empty($modell)) {
        die("Marke und Modell sind erforderlich.");
    }

    // Initialisiere die Variable für den Bildnamen
    $bildname = null;

    // Überprüfe, ob ein Bild hochgeladen wurde
    if (isset($_FILES['bild']) && $_FILES['bild']['error'] != UPLOAD_ERR_NO_FILE) {
        $bild = $_FILES['bild'];

        // Überprüfe auf Upload-Fehler
        if ($bild['error'] !== UPLOAD_ERR_OK) {
            die("Fehler beim Hochladen des Bildes.");
        }

        // Überprüfe den Dateityp (nur Bilder erlauben)
        $erlaubte_typen = ['image/jpeg', 'image/png', 'image/gif'];
        if (!in_array($bild['type'], $erlaubte_typen)) {
            die("Nur JPEG, PNG und GIF Bilder sind erlaubt.");
        }

        // Generiere einen eindeutigen Dateinamen
        $bildname = uniqid() . '_' . basename($bild['name']);
        $zielpfad = '../images/' . $bildname;

        // Bewege die hochgeladene Datei an den Zielort
        if (!move_uploaded_file($bild['tmp_name'], $zielpfad)) {
            die("Fehler beim Speichern des Bildes: ".$zielpfad);
        }

        // Optional: Altes Bild löschen
        // Hier könntest du das alte Bild aus der Datenbank holen und löschen
    }

    // Bereite die SQL-Abfrage vor
    if ($bildname) {
        // Wenn ein neues Bild hochgeladen wurde
        $sql = "UPDATE Fahrzeuge SET marke = ?, modell = ?, tankgroesse = ?, bild = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssdsi", $marke, $modell, $tankgroesse, $bildname, $fahrzeug_id);
    } else {
        // Ohne Bildaktualisierung
        $sql = "UPDATE Fahrzeuge SET marke = ?, modell = ?, tankgroesse = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssdi", $marke, $modell, $tankgroesse, $fahrzeug_id);
    }

    // Führe die Abfrage aus und prüfe auf Fehler
    if ($stmt->execute()) {
        // Erfolgreich aktualisiert
        $_SESSION['success_message'] = "Fahrzeugdaten erfolgreich aktualisiert.";
    } else {
        die("Fehler beim Aktualisieren der Fahrzeugdaten: " . $stmt->error);
    }

    $stmt->close();
    $conn->close();

    // Weiterleitung zur Fahrzeugdetailseite
    header("Location: fahrzeug_detail.php?id=" . $fahrzeug_id);
    exit();
} else {
    die("Ungültige Anfrage.");
}
?>
