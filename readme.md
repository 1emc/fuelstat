# Fuelstat

Fuelstat ist eine Web-App, mit der du Tankfüllungen, Ladevorgänge und alle anderen Kosten deiner Fahrzeuge erfasst und auswertest: Verbrauch, Kosten pro Kilometer, CO₂-Ausstoß und Erinnerungen an TÜV, Wartung, Versicherung und Steuer. Sie funktioniert für Benzin, Diesel, Autogas, Erdgas, Elektro, Hybrid und Wasserstoff.

## Funktionen

**Fahrzeuge**
- Mehrere Fahrzeuge mit Name, Kraftstoff, Marke, Modell, Baujahr, Tachostand bei Anschaffung, Tank- bzw. Akkugröße und Foto
- Reihenfolge in der Übersicht frei sortierbar
- Hinweise in der Übersicht, wenn TÜV, Wartung, Versicherung oder Steuer bald fällig oder überfällig sind

**Einträge**
- Tankfüllungen bzw. Ladevorgänge mit Menge (l, kWh oder kg je nach Kraftstoff), Preis, Tankstelle, voll/teilweise getankt
- Tankstellen in der Nähe samt aktuellem Preis (Tankerkönig)
- Andere Ausgaben (Versicherung, Werkstatt, Inspektion, Steuer, Reparatur, Reifen, TÜV, Wartung, Dekor) und Fahrten
- Alle Einträge bearbeiten und löschen (Tankfüllungen innerhalb von 14 Tagen)

**Auswertungen**
- Verbrauch pro Tankfüllung und Monat, bester/schlechtester Monat, Trend
- Jahresübersicht mit Menge, Kosten, Verbrauch und CO₂
- Ausgaben nach Kategorie, Fix- und variable Kosten, Kosten pro km
- CO₂-Ausstoß gesamt und pro km
- Fälligkeiten: TÜV alle 24 Monate, Wartung alle 12 Monate oder 15.000 km, Versicherung und Steuer jährlich – jeweils ab dem letzten erfassten Eintrag
- Export als CSV (für Excel) und als Bericht zum Drucken bzw. Speichern als PDF

**Konto**
- Registrierung und Login, optional mit Zwei-Faktor-Authentifizierung (Authenticator-App)
- Profil mit Benutzername und Profilbild, Passwort ändern
- Abmelden auf allen Geräten, Konto löschen
- Benutzerverwaltung für Admins
- Hell- und Dunkelmodus, als App installierbar (PWA)

## Aufbau

```
Browser ──> PHP-Frontend (Wurzelverzeichnis) ──> Node-API (backend/) ──> PostgreSQL
```

- **Frontend**: PHP 8 mit Bootstrap 5.3 und Chart.js. Es speichert selbst keine Daten, sondern ruft die API auf (`includes/fuelstat_api.php`). Der Login-Token liegt in der PHP-Session.
- **API**: Node.js 20 mit Express (`backend/src/server.js`), Authentifizierung per JWT. Doku: `docs/api.md` und `docs/openapi.1.0.0.yaml`.
- **Datenbank**: PostgreSQL 15, Schema in `backend/database/`.
- **Infrastruktur**: `infra/docker-compose.yml` startet API, Datenbank und Adminer.

## Installation

### 1. API und Datenbank (Docker)

Lege `infra/.env` an:

```ini
POSTGRES_USER=fuelstat
POSTGRES_PASSWORD=ein-starkes-passwort
POSTGRES_DB=fuelstat
JWT_SECRET=mindestens-32-zufaellige-zeichen
# Optional: kommagetrennte User-IDs mit Admin-Rechten (Benutzerverwaltung)
ADMIN_USER_IDS=
# Optional: Ablage und URL-Pfad für hochgeladene Bilder
# UPLOAD_DIR=../uploads
# MEDIA_BASE_URL=/uploads
```

Dann starten:

```bash
cd infra && docker compose up -d
```

Die API läuft danach auf `127.0.0.1:3000` (nur lokal erreichbar).

### 2. Datenbankschema

Neue Installation: `backend/database/postgres_init.sql` einspielen, danach `postgres_006_fix_fillup_units.sql`.

Bestehende Installation: alle noch nicht eingespielten `postgres_00X_*.sql` der Reihe nach ausführen, z. B. über Adminer oder:

```bash
docker compose exec -T db psql -U fuelstat -d fuelstat < ../backend/database/postgres_006_fix_fillup_units.sql
```

### 3. Frontend

- PHP 8 mit den Erweiterungen `curl` und `fileinfo`
- `includes/db_connect.php` wird noch eingebunden, kann aber leer sein (`<?php`)
- API-Adresse: Umgebungsvariable `FUELSTAT_API_BASE_URL` setzen, sonst wird `https://tanken.1emc.de/api/v1` verwendet (`includes/api_config.php`)
- Für die Tankstellensuche einen Tankerkönig-API-Key in `api/key.key` ablegen

### 4. Webserver

- `/api/` an die Node-API weiterleiten, `/uploads/` aus dem Upload-Verzeichnis ausliefern.
- **Wichtig:** Nur das Frontend gehört ins öffentliche Verzeichnis. Liegt das ganze Repository im Webroot, müssen mindestens `backend/`, `infra/`, `database/`, `tests/`, `.git/` und `api/key.key` gesperrt werden, sonst sind `.env`-Dateien und Schlüssel abrufbar.
- Bei OPcache mit `opcache.validate_timestamps=0` nach jedem Deploy PHP-FPM neu laden (z. B. `systemctl reload php8.4-fpm`).

## Entwicklung

```bash
cd backend && npm install && npm run dev   # API mit Auto-Reload
php -S 127.0.0.1:8088                      # Frontend
php tests/VerbrauchProMonatTest.php        # PHP-Test
```

## Kontakt

Mario Caraggiu – [GitHub @1emc](https://github.com/1emc)
