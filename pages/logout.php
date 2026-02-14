<?php
ini_set('display_errors', 0);
error_reporting(0);

include '../includes/session.php';
require_once __DIR__ . '/../includes/api_config.php';

if (isset($_POST['csrf_token']) && isset($_SESSION['csrf_token'])) {
    if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        error_log("Möglicher CSRF-Angriff erkannt - IP: " . ($_SERVER['REMOTE_ADDR'] ?? ''));
        die("Ungültige Anfrage");
    }
}

$_SESSION = array();
clearApiToken();

$sessionCookieOptions = getSessionCookieOptions();

if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', [
        'expires' => time() - 3600,
        'path' => $sessionCookieOptions['path'],
        'secure' => $sessionCookieOptions['secure'],
        'httponly' => $sessionCookieOptions['httponly'],
        'samesite' => $sessionCookieOptions['samesite'],
    ]);
}

foreach (['remember_me', 'auth_token'] as $cookie) {
    if (isset($_COOKIE[$cookie])) {
        setcookie($cookie, '', [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => $sessionCookieOptions['secure'],
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}

session_destroy();

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");

header('Location: login.php');
exit;
