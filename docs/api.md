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
    "liters": "47.200",
    "price_total_eur": "86.30",
    "station": "Aral",
    "filled_at": "2025-12-23T09:16:08.998Z",
    "created_at": "2025-12-23T09:16:08.998Z"
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

