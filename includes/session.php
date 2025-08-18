<?php

// Dynamische Session-Cookie-Parameter (funktioniert in beliebigem Unterordner und mit http/https)
$isHttps = (
    (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] && $_SERVER['HTTPS'] !== 'off')
    || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
    || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
);

// Basis-Pfad der App relativ zum Document-Root ermitteln (z.B. "/fuelstat")
$docRoot   = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
$appRootFs = str_replace('\\', '/', realpath(__DIR__ . '/..'));
$basePath  = rtrim(str_replace($docRoot, '', $appRootFs), '/');
if ($basePath === '' || $basePath[0] !== '/') {
    $basePath = '/' . ltrim($basePath, '/');
}
// Cookie-Pfad soll immer mit Slash enden und mindestens "/"
$cookiePath = ($basePath === '/' ? '/' : $basePath . '/');

session_set_cookie_params([
    'lifetime' => 60 * 60 * 24 * 7,
    'path' => $cookiePath,
    // keine feste Domain -> host-only Cookie, funktioniert auf beliebigen Hosts/Subdomains
    'secure' => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();

?>
