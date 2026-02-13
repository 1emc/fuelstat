<?php
/**
 * PHP-Client für die Fuelstat-API (OpenAPI 1.0.0).
 * Alle Aufrufe nutzen Bearer-Auth, sofern ein Token gesetzt ist.
 */

require_once __DIR__ . '/api_config.php';

class FuelstatApi
{
    private string $baseUrl;
    private ?string $token;

    public function __construct(?string $token = null)
    {
        $this->baseUrl = rtrim(FUELSTAT_API_BASE_URL, '/');
        $this->token = $token ?? getApiToken();
    }

    /**
     * HTTP-Request ausführen.
     * @param string $method GET|POST|PATCH|DELETE
     * @param string $path z.B. /api/v1/vehicles
     * @param array|null $body Bei POST/PATCH als JSON gesendet
     * @return array Dekodierte JSON-Response
     * @throws RuntimeException bei HTTP-Fehler oder ungültiger JSON-Response
     */
    public function request(string $method, string $path, ?array $body = null): array
    {
        $url = $this->baseUrl . $path;
        $ch = curl_init($url);

        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
            'User-Agent: Fuelstat-PHP/1.0',
        ];
        if ($this->token !== null && $this->token !== '') {
            $headers[] = 'Authorization: Bearer ' . $this->token;
        }

        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
        ]);

        if ($body !== null && in_array($method, ['POST', 'PATCH', 'PUT'], true)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }

        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err !== '') {
            throw new RuntimeException('API-Verbindungsfehler: ' . $err);
        }

        $decoded = $response === '' ? [] : json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE && $response !== '') {
            throw new RuntimeException('Ungültige API-Antwort: ' . json_last_error_msg());
        }

        if ($httpCode >= 400) {
            $msg = isset($decoded['error']) ? $decoded['error'] : 'HTTP ' . $httpCode;
            if (!empty($decoded['detail'])) {
                $msg .= ' – ' . $decoded['detail'];
            }
            error_log('[FuelstatApi] ' . $method . ' ' . $url . ' → ' . $httpCode . ' ' . $msg . ' | body: ' . substr($response, 0, 500));
            throw new RuntimeException($msg, $httpCode);
        }

        return is_array($decoded) ? $decoded : [];
    }

    // --- Health ---
    public function getHealth(): array
    {
        return $this->request('GET', '/api/v1/health');
    }

    public function getDbPing(): array
    {
        return $this->request('GET', '/api/v1/db-ping');
    }

    public function getWhoAmI(): array
    {
        return $this->request('GET', '/api/v1/whoami');
    }

    // --- Auth ---
    public function postAuthRegister(string $email, string $password): array
    {
        return $this->request('POST', '/api/v1/auth/register', [
            'email' => $email,
            'password' => $password,
        ]);
    }

    public function postAuthLogin(string $email, string $password): array
    {
        return $this->request('POST', '/api/v1/auth/login', [
            'email' => $email,
            'password' => $password,
        ]);
    }

    // --- Vehicles ---
    public function getVehicles(): array
    {
        return $this->request('GET', '/api/v1/vehicles');
    }

    public function getVehicle(string $vehicleId): array
    {
        return $this->request('GET', '/api/v1/vehicles/' . rawurlencode($vehicleId));
    }

    public function postVehicle(string $name, string $fuelType): array
    {
        return $this->request('POST', '/api/v1/vehicles', [
            'name' => $name,
            'fuelType' => $fuelType,
        ]);
    }

    public function patchVehicle(string $vehicleId, array $data): array
    {
        return $this->request('PATCH', '/api/v1/vehicles/' . rawurlencode($vehicleId), $data);
    }

    public function deleteVehicle(string $vehicleId): void
    {
        $this->request('DELETE', '/api/v1/vehicles/' . rawurlencode($vehicleId));
    }

    // --- Fillups ---
    public function getVehicleFillups(string $vehicleId, int $limit = 50): array
    {
        return $this->request('GET', '/api/v1/vehicles/' . rawurlencode($vehicleId) . '/fillups?limit=' . $limit);
    }

    public function getFillup(string $fillupId): array
    {
        return $this->request('GET', '/api/v1/fillups/' . rawurlencode($fillupId));
    }

    public function postFillup(array $data): array
    {
        return $this->request('POST', '/api/v1/fillups', $data);
    }

    public function patchFillup(string $fillupId, array $data): array
    {
        return $this->request('PATCH', '/api/v1/fillups/' . rawurlencode($fillupId), $data);
    }

    public function deleteFillup(string $fillupId): array
    {
        return $this->request('DELETE', '/api/v1/fillups/' . rawurlencode($fillupId));
    }

    // --- Stats ---
    public function getVehicleStats(string $vehicleId): array
    {
        return $this->request('GET', '/api/v1/vehicles/' . rawurlencode($vehicleId) . '/stats');
    }

    // --- Entries ---
    public function getVehicleEntries(string $vehicleId, int $limit = 50): array
    {
        return $this->request('GET', '/api/v1/vehicles/' . rawurlencode($vehicleId) . '/entries?limit=' . $limit);
    }

    public function postEntry(array $data): array
    {
        return $this->request('POST', '/api/v1/entries', $data);
    }

    // --- Users (für Einstellungen/Profil, falls API das anbietet) ---
    public function getUser(string $userId): array
    {
        return $this->request('GET', '/api/v1/auth/users/' . rawurlencode($userId));
    }

    public function patchUser(string $userId, array $data): array
    {
        return $this->request('PATCH', '/api/v1/auth/users/' . rawurlencode($userId), $data);
    }
}
