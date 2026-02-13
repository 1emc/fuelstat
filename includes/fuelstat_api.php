<?php

require_once __DIR__ . '/api_config.php';

class FuelstatApi
{
    private string $baseUrl;
    private ?string $token;

    public function __construct(?string $token = null, ?string $baseUrl = null)
    {
        $this->baseUrl = rtrim($baseUrl ?? getApiBaseUrl(), '/');
        $this->token = $token ?? getApiToken();
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

    public function getVehicles(): array { return $this->request('GET', '/vehicles'); }
    public function getVehicle(string $vehicleId): array { return $this->request('GET', '/vehicles/' . rawurlencode($vehicleId)); }
    public function postVehicle(string $name, string $fuelType): array { return $this->request('POST', '/vehicles', ['name' => $name, 'fuelType' => $fuelType]); }
    public function patchVehicle(string $vehicleId, array $data): array { return $this->request('PATCH', '/vehicles/' . rawurlencode($vehicleId), $data); }
    public function deleteVehicle(string $vehicleId): void { $this->request('DELETE', '/vehicles/' . rawurlencode($vehicleId)); }
    public function getVehicleFillups(string $vehicleId, int $limit = 50): array { return $this->request('GET', '/vehicles/' . rawurlencode($vehicleId) . '/fillups?limit=' . $limit); }
    public function getFillup(string $fillupId): array { return $this->request('GET', '/fillups/' . rawurlencode($fillupId)); }
    public function postFillup(array $data): array { return $this->request('POST', '/fillups', $data); }
    public function patchFillup(string $fillupId, array $data): array { return $this->request('PATCH', '/fillups/' . rawurlencode($fillupId), $data); }
    public function deleteFillup(string $fillupId): array { return $this->request('DELETE', '/fillups/' . rawurlencode($fillupId)); }
    public function getVehicleStats(string $vehicleId): array { return $this->request('GET', '/vehicles/' . rawurlencode($vehicleId) . '/stats'); }
    public function getVehicleEntries(string $vehicleId, int $limit = 50): array { return $this->request('GET', '/vehicles/' . rawurlencode($vehicleId) . '/entries?limit=' . $limit); }
    public function postEntry(array $data): array { return $this->request('POST', '/entries', $data); }
    public function getUser(string $userId): array { return $this->request('GET', '/auth/users/' . rawurlencode($userId)); }
    public function patchUser(string $userId, array $data): array { return $this->request('PATCH', '/auth/users/' . rawurlencode($userId), $data); }
    public function getWhoAmI(): array { return $this->request('GET', '/whoami'); }

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
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
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
            error_log('[FuelstatApi] ' . $method . ' ' . $url . ' => ' . $httpCode . ' ' . (string)$message);
            throw new RuntimeException((string)$message, $httpCode);
        }

        return $data;
    }
}
