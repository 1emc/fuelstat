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
- Import aus CSV (z. B. Excel-Listen, andere Apps oder ein Fuelstat-Export) mit Spaltenzuordnung, Vorschau und Duplikaterkennung

**E-Mail-Benachrichtigungen**
- Jeder Benutzer hinterlegt seinen eigenen Mailserver (SMTP) in den Einstellungen
- Erinnerung, wenn TÜV, Wartung, Versicherung oder Steuer bald fällig sind (Vorlaufzeit einstellbar) und noch einmal, wenn sie überfällig sind
- Monatsbericht mit Kosten, Strecke, Verbrauch und anstehenden Terminen je Fahrzeug

**Konto**
- Registrierung und Login, optional mit Zwei-Faktor-Authentifizierung (Authenticator-App)
- Profil mit Benutzername und Profilbild, Passwort ändern
- Abmelden auf allen Geräten, Konto löschen
- Benutzerverwaltung für Admins
- Hell- und Dunkelmodus, als App installierbar (PWA), auf dem Handy mit App-Navigation (untere Leiste, Profil- und Fahrzeugauswahl von unten)

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
# Optional: E-Mail-Benachrichtigungen
# MAIL_ENCRYPTION_KEY=eigener-schluessel-fuer-smtp-passwoerter   (Standard: aus JWT_SECRET abgeleitet)
# ALLOW_PRIVATE_SMTP=true      Mailserver mit interner Adresse erlauben (z. B. im selben Netz)
# NOTIFICATIONS_ENABLED=false  automatischen Versand abschalten
# Optional: öffentliche Registrierung abschalten
# REGISTRATION_ENABLED=false
```

Wird `JWT_SECRET` ohne eigenen `MAIL_ENCRYPTION_KEY` geändert, müssen die Benutzer ihr SMTP-Passwort neu eingeben.

Dann starten:

```bash
cd infra && docker compose up -d
```

Die API läuft danach auf `127.0.0.1:3000` (nur lokal erreichbar).

### 2. Datenbankschema

Neue Installation: `backend/database/postgres_init.sql` einspielen, danach `postgres_006_fix_fillup_units.sql` und `postgres_007_mail_settings.sql`.

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
- Die Regeln aus `infra/nginx-sicherheit.conf.example` in die nginx-Konfiguration übernehmen: Sie sperren interne Verzeichnisse, versteckte Dateien und den API-Key und verhindern, dass im Upload-Ordner jemals PHP ausgeführt wird.

### Sicherheit

- Uploads werden anhand ihres Inhalts geprüft (nur JPEG, PNG, GIF, WebP) und unter einem zufälligen Namen mit passender Endung gespeichert.
- Login, 2FA-Code und Passwortänderung werden nach 10 bzw. 5 Fehlversuchen für 15 Minuten gesperrt (je E-Mail-Adresse bzw. Benutzer, im Speicher der API).
- Alle Formulare sind per CSRF-Token geschützt; die Session-Cookies sind `HttpOnly` und `SameSite=Lax`.
- Öffentliche Registrierung abschalten: `REGISTRATION_ENABLED=false` in der Backend-`.env` (Admins legen Konten dann z. B. über die Datenbank an).

## Entwicklung

```bash
cd backend && npm install && npm run dev   # API mit Auto-Reload
php -S 127.0.0.1:8088                      # Frontend
php tests/VerbrauchProMonatTest.php        # PHP-Tests
php tests/CsvImportTest.php
```

## Kontakt

Mario Caraggiu – [GitHub @1emc](https://github.com/1emc)
