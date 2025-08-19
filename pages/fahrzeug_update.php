<?php
// — Sofort sichtbare Fehler einschalten —
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

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

    // Aktuelle Fahrzeugdaten laden (für Defaults, wenn Form-Felder fehlen)
    $curStmt = $conn->prepare("SELECT marke, modell, baujahr, tankgroesse, tachostand, kraftstoff, bild FROM fahrzeuge WHERE id = ?");
    $curStmt->bind_param("i", $fahrzeug_id);
    $curStmt->execute();
    $curRes = $curStmt->get_result();
    if ($curRes->num_rows === 0) {
        $_SESSION['error_message'] = "Fahrzeug nicht gefunden.";
        header("Location: fahrzeug_bearbeiten.php?id=" . $fahrzeug_id);
        exit();
    }
    $current = $curRes->fetch_assoc();
    $curStmt->close();

    // Felder aus dem Formular (fehlende Felder behalten den vorhandenen DB-Wert)
    $post_marke       = isset($_POST['marke']) ? trim($_POST['marke']) : null;
    $post_modell      = isset($_POST['modell']) ? trim($_POST['modell']) : null;
    $post_baujahr_raw = isset($_POST['baujahr']) ? trim((string)$_POST['baujahr']) : null;
    $post_tank_raw    = isset($_POST['tankgroesse']) ? trim((string)$_POST['tankgroesse']) : null;
    $post_tacho_raw   = isset($_POST['tachostand']) ? trim((string)$_POST['tachostand']) : null;
    $post_kraftstoff  = isset($_POST['kraftstoff']) ? $_POST['kraftstoff'] : null;

    $marke       = ($post_marke !== null && $post_marke !== '') ? $post_marke : (string)$current['marke'];
    $modell      = ($post_modell !== null && $post_modell !== '') ? $post_modell : (string)$current['modell'];
    $baujahr     = ($post_baujahr_raw !== null && $post_baujahr_raw !== '') ? intval($post_baujahr_raw) : (isset($current['baujahr']) ? (int)$current['baujahr'] : 0);
    $tankgroesse = ($post_tank_raw !== null && $post_tank_raw !== '') ? floatval(str_replace(',', '.', $post_tank_raw)) : (isset($current['tankgroesse']) ? (float)$current['tankgroesse'] : 0.0);
    $tachostand  = ($post_tacho_raw !== null && $post_tacho_raw !== '') ? intval($post_tacho_raw) : (isset($current['tachostand']) ? (int)$current['tachostand'] : 0);
    $kraftstoff  = ($post_kraftstoff !== null) ? $post_kraftstoff : (string)$current['kraftstoff'];

    // Validierung der erforderlichen Felder
    if (empty($marke) || empty($modell)) {
        die("Marke und Modell sind erforderlich.");
    }
    if (!in_array($kraftstoff, ['diesel','e5','e10','lpg','cng','electric','hybrid','hydrogen','other'])) {
        $kraftstoff = (string)$current['kraftstoff'];
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
        // Dateigrößenbegrenzung (max. 20 MB)
        if ($bild['size'] > 20 * 1024 * 1024) {
            die("Maximale Dateigröße von 20 MB überschritten.");
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

        // Bild komprimieren / skalieren (nur wenn GD-Extension verfügbar)
        if (isGdExtensionAvailable()) {
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
        } else {
            // GD-Extension nicht verfügbar - Bild wird unverändert gespeichert
            // Optional: Warnung in Session speichern
            if (!isset($_SESSION['gd_warning_shown'])) {
                $_SESSION['gd_warning_shown'] = true;
                $_SESSION['warning_message'] = "Hinweis: Bildkomprimierung nicht verfügbar (GD-Extension fehlt). Bilder werden in Originalgröße gespeichert.";
            }
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
            "ssidissi",
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
            "ssidisi",
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
    try {
        $ok = $stmt->execute();
        if (!$ok) {
            $_SESSION['error_message'] = 'Aktualisierung fehlgeschlagen: ' . $stmt->error;
            header('Location: fahrzeug_bearbeiten.php?id=' . $fahrzeug_id);
            exit();
        }
    } catch (mysqli_sql_exception $ex) {
        $_SESSION['error_message'] = 'Aktualisierung fehlgeschlagen: ' . $ex->getMessage();
        header('Location: fahrzeug_bearbeiten.php?id=' . $fahrzeug_id);
        exit();
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
