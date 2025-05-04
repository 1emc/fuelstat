<?php
ini_set('display_errors', 1);          // Fehler direkt im Browser ausgeben
ini_set('display_startup_errors', 1);  // auch Startup-Errors zeigen
error_reporting(E_ALL);                // wirklich alle Fehlertypen melden

// optional, wenn du MySQLi nutzt:
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
// pages/fahrzeug_speichern.php
session_start();
include '../includes/db_connect.php';
include '../includes/functions.php';   // falls du hier später gemeinsame Helfer nutzt

/* ------------------------------------------------------------------
   1)  Formulareingaben auslesen & validieren
   ------------------------------------------------------------------ */
$benutzer_id = 1;                                   // TODO: später $_SESSION['user_id']
$marke        = trim($_POST['marke']);
$modell       = trim($_POST['modell']);
$baujahr      = isset($_POST['baujahr'])      ? intval($_POST['baujahr'])      : null;
$tankgroesse  = isset($_POST['tankgroesse'])  ? floatval($_POST['tankgroesse']) : null;
$tachostand   = isset($_POST['tachostand'])   ? intval($_POST['tachostand'])   : null;
$kraftstoff   = isset($_POST['kraftstoff'])   ? $_POST['kraftstoff'] : 'diesel';

// Minimal-Validierung
if ($marke === '' || $modell === '' || $tachostand === null) {
    die('Marke, Modell und Tachostand sind Pflichtfelder.');
}
if (!in_array($kraftstoff, ['diesel','e5','e10','lpg','cng','electric','hybrid','hydrogen','other'])) {
    die('Ungültiger Kraftstofftyp.');
}

/* ------------------------------------------------------------------
   2)  Bild-Upload behandeln (optional)
   ------------------------------------------------------------------ */
$bildname = null;

if (isset($_FILES['bild']) && $_FILES['bild']['error'] !== UPLOAD_ERR_NO_FILE) {

    $upload = $_FILES['bild'];

    // a) Basis-Checks
    if ($upload['error'] !== UPLOAD_ERR_OK)          die('Fehler beim Hochladen des Bildes.');
    if ($upload['size']  > 2 * 1024 * 1024)          die('Maximale Dateigröße von 2 MB überschritten.');

    $erlaubteTypen = ['image/jpeg', 'image/png', 'image/gif'];
    if (!in_array($upload['type'], $erlaubteTypen))  die('Nur JPEG, PNG oder GIF erlaubt.');

    // b) Eindeutigen Dateinamen erstellen
    $bildname = uniqid() . '_' . basename($upload['name']);
    $zielpfad = '../images/' . $bildname;

    if (!move_uploaded_file($upload['tmp_name'], $zielpfad)) {
        die('Fehler beim Speichern des Bildes.');
    }

    // c) Optionales Resize / Komprimieren (wie in fahrzeug_update.php)
    list($origW, $origH, $format) = getimagesize($zielpfad);
    $maxW = 1200;  $maxH = 800;
    $ratio = min($maxW / $origW, $maxH / $origH, 1);
    $newW  = (int)($origW * $ratio);
    $newH  = (int)($origH * $ratio);

    switch ($format) {
        case IMAGETYPE_JPEG: $src = imagecreatefromjpeg($zielpfad); break;
        case IMAGETYPE_PNG:  $src = imagecreatefrompng($zielpfad);  break;
        case IMAGETYPE_GIF:  $src = imagecreatefromgif($zielpfad);  break;
        default:             $src = null;
    }
    if ($src) {
        $dst = imagecreatetruecolor($newW, $newH);
        if (in_array($format, [IMAGETYPE_PNG, IMAGETYPE_GIF])) {   // Transparenz erhalten
            imagecolortransparent($dst, imagecolorallocatealpha($dst, 0,0,0,127));
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
        }
        imagecopyresampled($dst, $src, 0,0,0,0, $newW,$newH, $origW,$origH);
        if ($format === IMAGETYPE_JPEG)      imagejpeg($dst, $zielpfad, 85);
        elseif ($format === IMAGETYPE_PNG)   imagepng($dst, $zielpfad, 6);
        elseif ($format === IMAGETYPE_GIF)   imagegif($dst, $zielpfad);
        imagedestroy($src); imagedestroy($dst);
    }
	
}

if ($bildname === null) {
	$bildname = '';                 // <-- leeren String verwenden
}

/* ------------------------------------------------------------------
   3)  Datensatz anlegen
   ------------------------------------------------------------------ */
$sql = "
    INSERT INTO fahrzeuge
        (benutzer_id, marke, modell, baujahr, tankgroesse, tachostand, bild, kraftstoff)
    VALUES
        (?,            ?,     ?,      ?,       ?,           ?,          ?,    ?)
";
$stmt = $conn->prepare($sql);
if (!$stmt) { die('Prepare-Fehler: ' . $conn->error); }

$stmt->bind_param(
    'issidiss',
    $benutzer_id,
    $marke,
    $modell,
    $baujahr,
    $tankgroesse,
    $tachostand,
    $bildname,
    $kraftstoff
);

if ($stmt->execute()) {
    $_SESSION['success_message'] = 'Fahrzeug erfolgreich angelegt.';
    header('Location: ../index.php');
    exit();
}
die('Fehler: ' . $stmt->error);
?>
