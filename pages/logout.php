<?php
// Debug-Einstellungen
ini_set('display_errors', 0);
error_reporting(0);

// Session starten und beenden
session_start();
include '../includes/db_connect.php'; // DB-Verbindung sicherstellen

// CSRF-Token überprüfen, falls vorhanden
if (isset($_POST['csrf_token']) && isset($_SESSION['csrf_token'])) {
    if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        // Ungültiger Token - möglicher CSRF-Angriff
        error_log("Möglicher CSRF-Angriff erkannt - IP: " . $_SERVER['REMOTE_ADDR']);
        die("Ungültige Anfrage");
    }
}

// Alle Session-Variablen löschen
$_SESSION = array();

// Session-Cookie löschen
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/', '', true, true); // secure und httponly Flag
}

// Zusätzliche Sicherheits-Cookies löschen
$cookies = array('remember_me', 'auth_token');
foreach ($cookies as $cookie) {
    if (isset($_COOKIE[$cookie])) {
        setcookie($cookie, '', time() - 3600, '/', '', true, true);
    }
}

$sessionId = session_id();
$stmt = $conn->prepare("DELETE FROM benutzer_sessions WHERE session_id = ?");
$stmt->bind_param("s", $sessionId);
$stmt->execute();

// Session zerstören
session_destroy();

// Cache-Header setzen
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Sicherheits-Header setzen
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");

// Zur Login-Seite weiterleiten
header('Location: login.php');
exit;
?>
