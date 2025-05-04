<?php
// pages/fahrzeug_update.php
// Session-Handling
include '../includes/session.php';
// Andere Includes
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
    $marke       = trim($_POST['marke']);
    $modell      = trim($_POST['modell']);
    $baujahr     = isset($_POST['baujahr']) ? intval($_POST['baujahr']) : null;
    $tankgroesse = isset($_POST['tankgroesse']) ? floatval($_POST['tankgroesse']) : null;
    $tachostand  = isset($_POST['tachostand']) ? intval($_POST['tachostand']) : null;
    $kraftstoff  = isset($_POST['kraftstoff']) ? $_POST['kraftstoff'] : 'diesel';

    // Validierung der erforderlichen Felder
    if (empty($marke) || empty($modell)) {
        die("Marke und Modell sind erforderlich.");
    }
    if (!in_array($kraftstoff, ['diesel','e5','e10','lpg','cng','electric','hybrid','hydrogen','other'])) {
        die('Ungültiger Kraftstofftyp.');
    }

    // Initialisiere die Variable für den Bildnamen
    $bildname = null;

    // Überprüfe, ob ein Bild hochgeladen wurde
    if (isset($_FILES['bild']) && $_FILES['bild']['error'] != UPLOAD_ERR_NO_FILE) {
        $bild = $_FILES['bild'];

        // Upload-Fehlerprüfung
        if ($bild['error'] !== UPLOAD_ERR_OK) {
            die("Fehler beim Hochladen des Bildes.");
        }
        // Dateigrößenbegrenzung (max. 2 MB)
        if ($bild['size'] > 2 * 1024 * 1024) {
            die("Maximale Dateigröße von 2 MB überschritten.");
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
            die("Fehler beim Speichern des Bildes: " . $zielpfad);
        }

        // Bild komprimieren / skalieren
        list($origWidth, $origHeight, $format) = getimagesize($zielpfad);
        $maxWidth  = 1200;
        $maxHeight = 800;
        $ratio = min($maxWidth / $origWidth, $maxHeight / $origHeight, 1);
        $newW = (int) ($origWidth * $ratio);
        $newH = (int) ($origHeight * $ratio);

        switch ($format) {
            case IMAGETYPE_JPEG:
                $src = imagecreatefromjpeg($zielpfad);
                break;
            case IMAGETYPE_PNG:
                $src = imagecreatefrompng($zielpfad);
                break;
            case IMAGETYPE_GIF:
                $src = imagecreatefromgif($zielpfad);
                break;
            default:
                $src = null;
        }
        if ($src) {
            $dst = imagecreatetruecolor($newW, $newH);
            // Erhalte Transparenz für PNG/GIF
            if (in_array($format, [IMAGETYPE_PNG, IMAGETYPE_GIF])) {
                imagecolortransparent($dst, imagecolorallocatealpha($dst, 0, 0, 0, 127));
                imagealphablending($dst, false);
                imagesavealpha($dst, true);
            }
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $origWidth, $origHeight);
            // Speichere komprimiert
            if ($format === IMAGETYPE_JPEG) {
                imagejpeg($dst, $zielpfad, 85);
            } elseif ($format === IMAGETYPE_PNG) {
                imagepng($dst, $zielpfad, 6);
            } elseif ($format === IMAGETYPE_GIF) {
                imagegif($dst, $zielpfad);
            }
            imagedestroy($src);
            imagedestroy($dst);
        }
    }

    // Bereite die SQL-Abfrage vor
    if ($bildname) {
        // Mit neuem Bild
        $sql = "
            UPDATE fahrzeuge
               SET marke       = ?,
                   modell      = ?,
                   baujahr     = ?,
                   tankgroesse = ?,
                   tachostand  = ?,
                   kraftstoff  = ?,
                   bild        = ?
             WHERE id = ?
        ";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            die("Prepare-Fehler: " . $conn->error);
        }
        $stmt->bind_param(
            "ssidsssi",
            $marke,
            $modell,
            $baujahr,
            $tankgroesse,
            $tachostand,
            $kraftstoff,
            $bildname,
            $fahrzeug_id
        );
    } else {
        // Ohne Bildaktualisierung
        $sql = "
            UPDATE fahrzeuge
               SET marke       = ?,
                   modell      = ?,
                   baujahr     = ?,
                   tankgroesse = ?,
                   tachostand  = ?,
                   kraftstoff  = ?
             WHERE id = ?
        ";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            die("Prepare-Fehler: " . $conn->error);
        }
        $stmt->bind_param(
            "ssidssi",
            $marke,
            $modell,
            $baujahr,
            $tankgroesse,
            $tachostand,
            $kraftstoff,
            $fahrzeug_id
        );
    }

    // Führe die Abfrage aus und prüfe auf Fehler
    if (!$stmt->execute()) {
        die("Execute-Fehler: " . $stmt->error);
    }

    $_SESSION['success_message'] = "Fahrzeugdaten erfolgreich aktualisiert.";

    $stmt->close();
    $conn->close();

    // Weiterleitung zur Fahrzeugdetailseite
    header("Location: fahrzeug_detail.php?id=" . $fahrzeug_id);
    exit();
} else {
    die("Ungültige Anfrage.");
}
?>
