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

// Dynamische Seiten nicht cachen (verhindert veraltete Anzeige bei F5 / alte MySQL-Daten)
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

?>
