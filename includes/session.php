<?php

if (!function_exists('isHttpsRequest')) {
    function isHttpsRequest(): bool
    {
        return (
            (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
        );
    }
}

if (!function_exists('getSessionCookieOptions')) {
    function getSessionCookieOptions(): array
    {
        return [
            'lifetime' => 60 * 60 * 24 * 28, // 28 Tage
            'path' => '/',
            'secure' => isHttpsRequest(),
            'httponly' => true,
            'samesite' => 'Lax',
        ];
    }
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params(getSessionCookieOptions());
    session_start();
}

// CSRF-Schutz: jedes POST-Formular muss das Token dieser Session mitschicken.
// Das Token wird per JavaScript (includes/footer.php) in alle POST-Formulare eingefügt.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
if (!function_exists('csrfToken')) {
    function csrfToken(): string
    {
        return $_SESSION['csrf_token'];
    }
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
    && !hash_equals($_SESSION['csrf_token'], (string)($_POST['csrf_token'] ?? ''))) {
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    exit('<p>Die Anfrage wurde aus Sicherheitsgründen abgelehnt (Sitzung abgelaufen oder ungültiges Formular). '
        . '<a href="javascript:history.back()">Zurück</a>, Seite neu laden und erneut versuchen.</p>');
}

// Dynamische Seiten nicht cachen (verhindert veraltete Anzeige bei F5 / alte MySQL-Daten)
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

?>
