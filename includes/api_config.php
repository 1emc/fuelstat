<?php

if (!function_exists('getApiBaseUrl')) {
    function getApiBaseUrl(): string
    {
        $envBase = getenv('FUELSTAT_API_BASE_URL');
        if (is_string($envBase) && trim($envBase) !== '') {
            return rtrim(trim($envBase), '/');
        }

        $isHttps = (
            (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
        );
        $scheme = $isHttps ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

        return $scheme . '://' . $host . '/api/v1';
    }
}

if (!function_exists('setApiToken')) {
    function setApiToken(string $token, array $user = []): void
    {
        $_SESSION['api_token'] = $token;
        if (isset($user['id'])) {
            $_SESSION['user_id'] = (string)$user['id'];
        }
        if (isset($user['email'])) {
            $_SESSION['user_email'] = (string)$user['email'];
        }
    }
}

if (!function_exists('getApiToken')) {
    function getApiToken(): ?string
    {
        $token = $_SESSION['api_token'] ?? null;
        return (is_string($token) && $token !== '') ? $token : null;
    }
}

if (!function_exists('clearApiToken')) {
    function clearApiToken(): void
    {
        unset($_SESSION['api_token'], $_SESSION['user_id'], $_SESSION['user_email']);
    }
}

if (!class_exists('FuelstatApi')) {
    class FuelstatApi
    {
        private string $baseUrl;
        private ?string $token;

        public function __construct(?string $token = null, ?string $baseUrl = null)
        {
            $this->baseUrl = rtrim($baseUrl ?? getApiBaseUrl(), '/');
            $this->token = $token;
        }

        public function postAuthLogin(string $email, string $password): array
        {
            return $this->request('POST', '/auth/login', [
                'email' => $email,
                'password' => $password,
            ]);
        }

        public function postAuthRegister(string $email, string $password): array
        {
            return $this->request('POST', '/auth/register', [
                'email' => $email,
                'password' => $password,
            ]);
        }

        public function getVehicles(): array
        {
            return $this->request('GET', '/vehicles');
        }

        public function getWhoAmI(): array
        {
            return $this->request('GET', '/whoami');
        }

        private function request(string $method, string $path, ?array $payload = null): array
        {
            $url = $this->baseUrl . '/' . ltrim($path, '/');
            $ch = curl_init($url);
            if ($ch === false) {
                throw new RuntimeException('curl_init failed');
            }

            $headers = ['Accept: application/json'];
            if ($payload !== null) {
                $json = json_encode($payload);
                if ($json === false) {
                    throw new RuntimeException('json_encode failed');
                }
                $headers[] = 'Content-Type: application/json';
                curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
            }

            if ($this->token !== null && $this->token !== '') {
                $headers[] = 'Authorization: Bearer ' . $this->token;
            }

            curl_setopt_array($ch, [
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => 20,
            ]);

            $body = curl_exec($ch);
            if ($body === false) {
                $err = curl_error($ch);
                curl_close($ch);
                throw new RuntimeException('API request failed: ' . $err);
            }

            $httpCode = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);

            $data = [];
            if ($body !== '') {
                $decoded = json_decode($body, true);
                if (is_array($decoded)) {
                    $data = $decoded;
                }
            }

            if ($httpCode >= 400) {
                $message = $data['error'] ?? $data['message'] ?? ('HTTP ' . $httpCode);
                throw new RuntimeException((string)$message, $httpCode);
            }

            return $data;
        }
    }
}

$api = new FuelstatApi(getApiToken());
