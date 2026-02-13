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
    }
}

if (!function_exists('getApiToken')) {
    function getApiToken(): ?string
    {
        $token = $_SESSION['api_token'] ?? null;
        return (is_string($token) && trim($token) !== '') ? $token : null;
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
    }
}
