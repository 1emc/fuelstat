<?php

if (!function_exists('getApiBaseUrl')) {
    function getApiBaseUrl(): string
    {
        $envBase = getenv('FUELSTAT_API_BASE_URL');
        if (is_string($envBase) && trim($envBase) !== '') {
            return rtrim(trim($envBase), '/');
        }

        return 'https://tanken.1emc.de/api/v1';
    }
}

// Das API-Token liegt in der Session und zusätzlich in einem eigenen Cookie. PHP räumt Session-Dateien
// nach session.gc_maxlifetime auf (Debian/Ubuntu per Cronjob, Standard 24 Minuten); ohne das Cookie
// wäre man danach ausgeloggt, obwohl das Token noch gültig ist.
const FUELSTAT_AUTH_COOKIE = 'fuelstat_auth';

if (!function_exists('apiTokenPayload')) {
    /** Payload eines JWT (ohne Signaturprüfung – die macht die API bei jeder Anfrage) */
    function apiTokenPayload(string $token): ?array
    {
        $teile = explode('.', $token);
        if (count($teile) !== 3) {
            return null;
        }
        $json = base64_decode(strtr($teile[1], '-_', '+/'), true);
        $daten = $json !== false ? json_decode($json, true) : null;
        return is_array($daten) ? $daten : null;
    }
}

if (!function_exists('setAuthCookie')) {
    function setAuthCookie(string $wert, int $ablauf): void
    {
        if (headers_sent()) {
            return;
        }
        $https = function_exists('isHttpsRequest') ? isHttpsRequest() : false;
        setcookie(FUELSTAT_AUTH_COOKIE, $wert, [
            'expires' => $ablauf,
            'path' => '/',
            'secure' => $https,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}

if (!function_exists('setApiToken')) {
    function setApiToken(string $token, array $user = []): void
    {
        $_SESSION['api_token'] = $token;
        if (isset($user['id']) && $user['id'] !== null && $user['id'] !== '') {
            $_SESSION['user_id'] = (string)$user['id'];
        }
        if (isset($user['email']) && $user['email'] !== '') {
            $_SESSION['user_email'] = (string)$user['email'];
        }
        // Cookie läuft zusammen mit dem Token ab
        $ablauf = (int)(apiTokenPayload($token)['exp'] ?? 0) ?: time() + 7 * 24 * 3600;
        setAuthCookie($token, $ablauf);
    }
}

if (!function_exists('getApiToken')) {
    function getApiToken(): ?string
    {
        $token = $_SESSION['api_token'] ?? null;
        if (is_string($token) && trim($token) !== '') {
            return $token;
        }
        // Session abgelaufen: aus dem Cookie wiederherstellen, solange das Token nicht abgelaufen ist.
        // Ob es widerrufen wurde, prüft die API (401 → header.php meldet ab und löscht das Cookie).
        $cookie = $_COOKIE[FUELSTAT_AUTH_COOKIE] ?? null;
        if (!is_string($cookie) || !preg_match('/^[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+$/', $cookie)) {
            return null;
        }
        $payload = apiTokenPayload($cookie);
        if ($payload === null || (int)($payload['exp'] ?? 0) <= time()) {
            setAuthCookie('', time() - 3600);
            return null;
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['api_token'] = $cookie;
            if (!empty($payload['sub'])) {
                $_SESSION['user_id'] = (string)$payload['sub'];
            }
            if (!empty($payload['email'])) {
                $_SESSION['user_email'] = (string)$payload['email'];
            }
        }
        return $cookie;
    }
}

if (!function_exists('isApiAuthenticated')) {
    function isApiAuthenticated(): bool
    {
        return getApiToken() !== null;
    }
}

if (!function_exists('clearApiToken')) {
    function clearApiToken(): void
    {
        unset($_SESSION['api_token'], $_SESSION['user_id'], $_SESSION['user_email']);
        unset($_COOKIE[FUELSTAT_AUTH_COOKIE]);
        setAuthCookie('', time() - 3600);
    }
}
