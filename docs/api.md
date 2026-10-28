# Fuelstat V2 API

Base URL: https://tanken.1emc.de/api/v1  
Content-Type: application/json  
Auth: Bearer JWT (Authorization Header)

## Auth

### POST /auth/register
Legt einen neuen User an und gibt direkt ein JWT zurück.

**Body**
```json
{
  "email": "mario@test.local",
  "password": "SuperSecret123!"
}
````

**Responses**

* 201

```json
{
  "token": "<jwt>",
  "user": {
    "id": "<uuid>",
    "email": "mario@test.local",
    "created_at": "2025-12-23T08:34:47.135Z"
  }
}
```

* 400: `invalid_email` | `password_too_short`
* 409: `email_already_exists`
* 500: `server_error`

### POST /auth/login

Gibt bei gültigen Credentials ein JWT zurück.

**Body**

```json
{
  "email": "mario@test.local",
  "password": "SuperSecret123!"
}
```

**Responses**

* 200 wie Register (token + user)
* 400: `missing_credentials`
* 401: `invalid_credentials`
* 500: `server_error`

## Benutzer & Admin-Rechte

Die Endpunkte unter `/auth/users` und `/auth/tokens` sind auf den eigenen Account beschränkt:

* `GET /auth/users` (alle User auflisten): nur Admins
* `GET|PATCH|DELETE /auth/users/:userId` und `POST /auth/users/:userId/revoke-all`: nur der eigene User oder Admins
* `POST /auth/tokens/revoke`: eigene Tokens jeder, fremde nur Admins
* `GET /auth/tokens`: Admins sehen alle (optional `?userId=`), alle anderen nur ihre eigenen

Admins werden über die Umgebungsvariable `ADMIN_USER_IDS` (kommagetrennte User-UUIDs) in der `.env` des Backends festgelegt. Ohne diese Variable gibt es keine Admins.

Fehlende Rechte: **403** `{ "error": "forbidden" }`

## Health & Diagnostics

### GET /health

Healthcheck, ohne Auth.

**Response 200**

```json
{
  "status": "healthy",
  "timestamp": "2025-12-23T09:00:00.000Z",
  "service": "fuelstat-api-v2"
}
```

### GET /db-ping

DB Ping, ohne Auth.

**Response 200**

```json
{ "ok": true, "result": { "ok": 1 } }
```

## Vehicles (Auth required)

### POST /vehicles

Legt ein Fahrzeug für den eingeloggten User an.

**Header**
Authorization: Bearer <jwt>

**Body**

```json
{
  "name": "Mein Auto",
  "fuelType": "diesel"
}
```

**fuelType erlaubt**
`diesel`, `petrol`, `electric`, `hybrid`, `cng`, `lpg`, `other`

**Response 201**

```json
{
  "id": "<uuid>",
  "name": "Mein Auto",
  "fuel_type": "diesel",
  "created_at": "2025-12-23T08:38:47.952Z"
}
```

**Errors**

* 400: `missing_name` | `invalid_fuelType`
* 401: `missing_token` | `invalid_token`
* 500: `server_error`

### GET /vehicles

Listet alle Fahrzeuge des Users, absteigend nach created_at.

**Header**
Authorization: Bearer <jwt>

**Response 200**

```json
[
  {
    "id": "<uuid>",
    "name": "Mein Auto",
    "fuel_type": "diesel",
    "created_at": "2025-12-23T08:38:47.952Z"
  }
]
```

## Fillups (Auth required)

### POST /fillups

Legt eine Tankfüllung an. Prüft, ob `vehicleId` dem User gehört.

**Header**
Authorization: Bearer <jwt>

**Body**

```json
{
  "vehicleId": "<vehicle-uuid>",
  "odometerKm": 12845,
  "liters": 47.2,
  "priceTotalEur": 86.3,
  "station": "Aral",
  "filledAt": "2025-12-23T09:16:08.998Z"
}
```

**Notes**

* `station` optional
* `filledAt` optional. Wenn nicht gesetzt, nutzt die API `now()`.

**Response 201**

```json
{
  "id": "<uuid>",
  "vehicle_id": "<vehicle-uuid>",
  "odometer_km": 12845,
  "liters": "47.200",
  "price_total_eur": "86.30",
  "station": "Aral",
  "filled_at": "2025-12-23T09:16:08.998Z",
  "created_at": "2025-12-23T09:16:08.998Z"
}
```

**Errors**

* 400: `missing_vehicleId` | `invalid_odometerKm` | `invalid_liters` | `invalid_priceTotalEur` | `invalid_filledAt`
* 401: `missing_token` | `invalid_token`
* 404: `vehicle_not_found`
* 500: `server_error`

### GET /vehicles/:vehicleId/fillups?limit=50

Listet Fillups zu einem Vehicle (nur wenn es dem User gehört).

**Header**
Authorization: Bearer <jwt>

**Query**

* `limit` optional (default 50, min 1, max 200)

**Response 200**

```json
[
  {
    "id": "<uuid>",
    "odometer_km": 12845,
    "amount": "47.200",
    "unit": "l",
    "total_cost_eur": "86.30",
    "price_per_unit": "1.8284",
    "station": "Aral",
    "filled_at": "2025-12-23T09:16:08.998Z",
    "created_at": "2025-12-23T09:16:08.998Z",
    "is_full": true,
    "skip_previous": false,
    "liters": "47.200",
    "price_total_eur": "86.30",
    "price_per_liter": "1.8284"
  }
]
```

**Errors**

* 401: `missing_token` | `invalid_token`
* 404: `vehicle_not_found`
* 500: `server_error`

## Stats (Auth required)

### GET /vehicles/:vehicleId/stats

Gibt aktuelle Kennzahlen und Full-to-Full Segmente zurück.

**Header**
Authorization: Bearer <jwt>

**Response 200 (Beispiel)**

```json
{
  "currentOdo": 12845,
  "avgPricePerLiter": "1.828",
  "segments": [
    {
      "full_id": "<fillup-uuid>",
      "full_at": "2025-12-23T09:11:23.890Z",
      "km": 300,
      "liters_segment": "47.200",
      "l_per_100km": "15.73"
    }
  ],
  "trend": {
    "current": 15.73,
    "avgPrev": 23.6,
    "diff": -7.87
  }
}
```

**Errors**

* 401: `missing_token` | `invalid_token`
* 404: `vehicle_not_found`
* 500: `server_error`

## Ausgaben (Entries) bearbeiten und löschen (Auth required)

* `GET /entries/:entryId` – einzelne Ausgabe
* `PATCH /entries/:entryId` – Felder `category`, `amountEur`, `odometerKm`, `occurredAt`, `vendor`, `description` (alle optional; `null` löscht optionale Felder)
* `DELETE /entries/:entryId` – 204

Fremde oder ungültige IDs liefern 404 `entry_not_found`.

## Listen seitenweise laden

`GET /vehicles/:vehicleId/fillups` und `GET /vehicles/:vehicleId/entries` akzeptieren neben `limit` (max. 200) auch `offset`. Das PHP-Frontend lädt so alle Einträge.

## Passwort und 2FA (Auth required)

* `POST /auth/password` `{ "currentPassword", "newPassword" }` – widerruft alle bestehenden Tokens und gibt `{ "token" }` zurück. Fehler: 401 `invalid_current_password`, 400 `password_too_short`
* `POST /auth/mfa/setup` – erzeugt ein TOTP-Secret `{ "secret", "otpauthUrl" }` (noch nicht aktiv)
* `POST /auth/mfa/enable` `{ "code" }` – aktiviert 2FA nach korrektem Code
* `POST /auth/mfa/disable` `{ "password", "code" }` – deaktiviert 2FA

Bei aktivem 2FA antwortet `POST /auth/login` ohne `code` mit 401 `mfa_required`, mit falschem Code mit 401 `invalid_mfa_code`. TOTP: 30 Sekunden, 6 Stellen, SHA1 (kompatibel mit Google Authenticator, Aegis, 1Password usw.).

`GET /whoami` liefert zusätzlich `is_admin`.

## Tankfüllungen: vollgetankt und Lücken

`POST /fillups` übernimmt `isFull` (Standard `true`) und `skipPrevious` (Standard `false`). Eine Vollbetankung mit `skipPrevious = true` ist ein neuer Startpunkt für die Verbrauchsberechnung: Über die Lücke davor wird kein Verbrauch berechnet.

## Common error format

```json
{ "error": "some_error_code" }
```

Manchmal zusätzlich:

```json
{ "error": "server_error", "detail": "..." }
```

## Quick curl examples

### Register

```bash
curl -s https://tanken.1emc.de/api/v1/auth/register \
  -H 'Content-Type: application/json' \
  -d '{"email":"mario@test.local","password":"SuperSecret123!"}'
```

### Create vehicle

```bash
curl -s https://tanken.1emc.de/api/v1/vehicles \
  -H "Authorization: Bearer $TOKEN" \
  -H 'Content-Type: application/json' \
  -d '{"name":"Mein Auto","fuelType":"diesel"}'
```

### Create fillup

```bash
curl -s https://tanken.1emc.de/api/v1/fillups \
  -H "Authorization: Bearer $TOKEN" \
  -H 'Content-Type: application/json' \
  -d '{"vehicleId":"<uuid>","odometerKm":12845,"liters":47.2,"priceTotalEur":86.3,"station":"Aral"}'
```

