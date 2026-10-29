<?php

require_once __DIR__ . '/api_config.php';

/** API-Fehler: Nachricht ist der Fehlercode der API, $detail die optionale Zusatzinfo. */
class FuelstatApiException extends RuntimeException
{
    public ?string $detail;
    /** Vollständige Fehlerantwort der API (z. B. mfaToken bei mfa_required) */
    public array $data;

    public function __construct(string $message, int $code, ?string $detail = null, array $data = [])
    {
        parent::__construct($message, $code);
        $this->detail = $detail;
        $this->data = $data;
    }
}

class FuelstatApi
{
    private string $baseUrl;
    private ?string $token;
    /** GET-Antworten innerhalb eines Seitenaufrufs; wird bei jeder Änderung geleert */
    private array $getCache = [];

    public function __construct(?string $token = null, ?string $baseUrl = null)
    {
        $this->baseUrl = rtrim($baseUrl ?? getApiBaseUrl(), '/');
        $this->token = $token ?? getApiToken();
    }

    public function postAuthLogin(string $email, string $password, ?string $code = null): array
    {
        $payload = ['email' => $email, 'password' => $password];
        if ($code !== null) {
            $payload['code'] = $code;
        }
        return $this->request('POST', '/auth/login', $payload);
    }

    public function postAuthLoginMfa(string $mfaToken, string $code): array
    {
        return $this->request('POST', '/auth/login/mfa', ['mfaToken' => $mfaToken, 'code' => $code]);
    }

    public function postAuthPassword(string $currentPassword, string $newPassword): array
    {
        return $this->request('POST', '/auth/password', ['currentPassword' => $currentPassword, 'newPassword' => $newPassword]);
    }

    public function postMfaSetup(): array { return $this->request('POST', '/auth/mfa/setup', []); }
    public function postMfaEnable(string $code): array { return $this->request('POST', '/auth/mfa/enable', ['code' => $code]); }
    public function postMfaDisable(string $password, string $code): array { return $this->request('POST', '/auth/mfa/disable', ['password' => $password, 'code' => $code]); }
    public function revokeToken(string $token): array { return $this->request('POST', '/auth/tokens/revoke', ['token' => $token, 'reason' => 'logout']); }
    public function revokeAllTokens(string $userId): array { return $this->request('POST', '/auth/users/' . rawurlencode($userId) . '/revoke-all', ['reason' => 'logout_all']); }
    public function getUsers(): array { return $this->request('GET', '/auth/users'); }
    public function deleteUser(string $userId): void { $this->request('DELETE', '/auth/users/' . rawurlencode($userId)); }
    public function uploadUserMedia(string $filePath, string $mimeType, string $fileName): array
    {
        return $this->request('POST', '/media/upload', null, ['file' => new CURLFile($filePath, $mimeType, $fileName)]);
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
    public function postVehicle(array $data): array { return $this->request('POST', '/vehicles', $data); }
    public function getVehicleMedia(string $vehicleId): array { return $this->request('GET', '/vehicles/' . rawurlencode($vehicleId) . '/media'); }
    public function deleteVehicleMedia(string $vehicleId, string $mediaId): void { $this->request('DELETE', '/vehicles/' . rawurlencode($vehicleId) . '/media/' . rawurlencode($mediaId)); }
    public function uploadVehicleMedia(string $vehicleId, string $filePath, string $mimeType, string $fileName): array
    {
        return $this->request('POST', '/vehicles/' . rawurlencode($vehicleId) . '/media/upload', null, [
            'file' => new CURLFile($filePath, $mimeType, $fileName),
        ]);
    }
    public function patchVehicle(string $vehicleId, array $data): array { return $this->request('PATCH', '/vehicles/' . rawurlencode($vehicleId), $data); }
    public function deleteVehicle(string $vehicleId): void { $this->request('DELETE', '/vehicles/' . rawurlencode($vehicleId)); }
    public function getVehicleFillups(string $vehicleId, int $limit = 50): array { return $this->request('GET', '/vehicles/' . rawurlencode($vehicleId) . '/fillups?limit=' . $limit); }
    public function getFillup(string $fillupId): array { return $this->request('GET', '/fillups/' . rawurlencode($fillupId)); }
    public function postFillup(array $data): array { return $this->request('POST', '/fillups', $data); }
    public function patchFillup(string $fillupId, array $data): array { return $this->request('PATCH', '/fillups/' . rawurlencode($fillupId), $data); }
    public function deleteFillup(string $fillupId): array { return $this->request('DELETE', '/fillups/' . rawurlencode($fillupId)); }
    public function getVehicleStats(string $vehicleId): array { return $this->request('GET', '/vehicles/' . rawurlencode($vehicleId) . '/stats'); }
    public function getVehicleEntries(string $vehicleId, int $limit = 50): array { return $this->request('GET', '/vehicles/' . rawurlencode($vehicleId) . '/entries?limit=' . $limit); }

    /** Alle Tankfüllungen eines Fahrzeugs (seitenweise geladen, ohne 200er-Grenze). */
    public function getAllVehicleFillups(string $vehicleId): array { return $this->getAllPages('/vehicles/' . rawurlencode($vehicleId) . '/fillups'); }

    /** Alle Ausgaben eines Fahrzeugs (seitenweise geladen, ohne 200er-Grenze). */
    public function getAllVehicleEntries(string $vehicleId): array { return $this->getAllPages('/vehicles/' . rawurlencode($vehicleId) . '/entries'); }

    private function getAllPages(string $path): array
    {
        $alle = [];
        $offset = 0;
        do {
            $seite = $this->request('GET', $path . '?limit=200&offset=' . $offset);
            $alle = array_merge($alle, $seite);
            $offset += 200;
        } while (count($seite) === 200);
        return $alle;
    }
    public function postEntry(array $data): array { return $this->request('POST', '/entries', $data); }
    public function postVehicleImport(string $vehicleId, array $fillups, array $entries): array { return $this->request('POST', '/vehicles/' . rawurlencode($vehicleId) . '/import', ['fillups' => $fillups, 'entries' => $entries]); }
    public function getEntry(string $entryId): array { return $this->request('GET', '/entries/' . rawurlencode($entryId)); }
    public function patchEntry(string $entryId, array $data): array { return $this->request('PATCH', '/entries/' . rawurlencode($entryId), $data); }
    public function deleteEntry(string $entryId): void { $this->request('DELETE', '/entries/' . rawurlencode($entryId)); }
    public function getUser(string $userId): array { return $this->request('GET', '/auth/users/' . rawurlencode($userId)); }
    public function patchUser(string $userId, array $data): array { return $this->request('PATCH', '/auth/users/' . rawurlencode($userId), $data); }
    public function getWhoAmI(): array { return $this->request('GET', '/whoami'); }
    public function getHealth(): array { return $this->request('GET', '/health'); }
    public function getVehicleReminders(string $vehicleId): array { return $this->request('GET', '/vehicles/' . rawurlencode($vehicleId) . '/reminders'); }
    public function getMailSettings(): array { return $this->request('GET', '/auth/mail-settings'); }
    public function putMailSettings(array $data): array { return $this->request('PUT', '/auth/mail-settings', $data); }
    public function postMailTest(): array { return $this->request('POST', '/auth/mail-settings/test', []); }
    public function postMailSendNow(string $type): array { return $this->request('POST', '/auth/mail-settings/send-now', ['type' => $type]); }

    private function request(string $method, string $path, ?array $payload = null, ?array $multipart = null): array
    {
        $url = $this->baseUrl . '/' . ltrim($path, '/');
        if ($method === 'GET' && array_key_exists($url, $this->getCache)) {
            return $this->getCache[$url];
        }
        if ($method !== 'GET') {
            $this->getCache = [];
        }
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
        if ($multipart !== null) {
            // curl setzt Content-Type multipart/form-data inkl. Boundary selbst
            curl_setopt($ch, CURLOPT_POSTFIELDS, $multipart);
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
            throw new FuelstatApiException((string)$message, $httpCode, isset($data['detail']) ? (string)$data['detail'] : null, $data);
        }

        if ($method === 'GET') {
            $this->getCache[$url] = $data;
        }
        return $data;
    }
}
