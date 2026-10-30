# Fuelstat

Fuelstat ist eine Web-App, mit der du Tankfüllungen, Ladevorgänge und alle anderen Kosten deiner Fahrzeuge erfasst und auswertest: Verbrauch, Kosten pro Kilometer, CO₂-Ausstoß und Erinnerungen an TÜV, Wartung, Versicherung und Steuer. Sie funktioniert für Benzin, Diesel, Autogas, Erdgas, Elektro, Hybrid, Plug-in-Hybrid und Wasserstoff.

## Funktionen

**Fahrzeuge**
- Mehrere Fahrzeuge mit Name, Kraftstoff, Marke, Modell, Baujahr, Tachostand bei Anschaffung, Tank- bzw. Akkugröße und Foto
- Reihenfolge in der Übersicht frei sortierbar
- Hinweise in der Übersicht, wenn TÜV, Wartung, Versicherung oder Steuer bald fällig oder überfällig sind

**Einträge**
- Tankfüllungen bzw. Ladevorgänge mit Menge (l, kWh oder kg je nach Kraftstoff), Preis, Tankstelle, voll/teilweise getankt; alle Texte passen sich der Antriebsart an (Tankstelle/Ladestation, vollgetankt/vollgeladen …)
- Warnung bei unplausiblem Tachostand (kleiner als der vorige oder ungewöhnlich großer Sprung)
- Plug-in-Hybride: Tanken und Laden im selben Fahrzeug, Verbrauch getrennt in l/100 km und kWh/100 km, gemeinsame Energiekosten und Stromanteil; Tank und Akku als eigene Angaben
- Tankstellen in der Nähe samt aktuellem Preis (Tankerkönig), beim Laden Ladestationen mit Leistung und Steckertypen (Open Charge Map); der zuletzt bezahlte kWh-Preis an einer Station wird vorgeschlagen
- Andere Ausgaben (Versicherung, Werkstatt, Inspektion, Steuer, Reparatur, Reifen, TÜV, Wartung, Dekor) und Fahrten
- Alle Einträge bearbeiten und löschen (Tankfüllungen innerhalb von 14 Tagen)

**Auswertungen**
- Verbrauch pro Tankfüllung und Monat, bester/schlechtester Monat, Trend
- Jahresübersicht mit Menge, Kosten, Verbrauch und CO₂
- Ausgaben nach Kategorie und Kostengruppe, Kosten pro Monat und km, Wartungskosten je 10.000 km, Jahresvergleich
- CO₂-Ausstoß gesamt und pro km
- Fälligkeiten: TÜV alle 24 Monate, Wartung alle 12 Monate oder 15.000 km, Versicherung und Steuer jährlich – jeweils ab dem letzten erfassten Eintrag
- Export als CSV (für Excel) und als Bericht zum Drucken bzw. Speichern als PDF
- Import aus CSV (z. B. Excel-Listen, andere Apps oder ein Fuelstat-Export) mit Spaltenzuordnung, Vorschau, Duplikaterkennung und Vorlage passend zur Antriebsart

**E-Mail-Benachrichtigungen**
- Ohne eigene Angaben verschickt Fuelstat über den Mailserver des Betreibers (SMTP-Zugang des Admins) an die Konto-Adresse
- Optional hinterlegt jeder Benutzer seinen eigenen Mailserver (SMTP) in den Einstellungen
- Erinnerung, wenn TÜV, Wartung, Versicherung oder Steuer bald fällig sind (Vorlaufzeit einstellbar) und noch einmal, wenn sie überfällig sind
- Monatsbericht mit Kosten, Strecke, Verbrauch und anstehenden Terminen je Fahrzeug

**Konto**
- Registrierung und Login, optional mit Zwei-Faktor-Authentifizierung (Authenticator-App)
- Profil mit Benutzername und Profilbild, Passwort ändern
- Abmelden auf allen Geräten, Konto löschen
- Benutzerverwaltung für Admins: Fahrzeuge je Benutzer, zuletzt aktiv, letzter Login, letzter Eintrag, Beta-Tester-Kennzeichen und Feedback-Eingang
- „Feedback senden“ für angemeldete Benutzer (Fehler, Idee, Lob, Sonstiges): wird gespeichert und per Mail an den Betreiber geschickt
- Hell- und Dunkelmodus, als App installierbar (PWA), auf dem Handy mit App-Navigation (untere Leiste, Profil- und Fahrzeugauswahl von unten)

## Aufbau

```
Browser ──> PHP-Frontend (Wurzelverzeichnis) ──> Node-API (backend/) ──> PostgreSQL
```

- **Frontend**: PHP 8 mit Bootstrap 5.3 und Chart.js. Es speichert selbst keine Daten, sondern ruft die API auf (`includes/fuelstat_api.php`). Der Login-Token liegt in der PHP-Session und zusätzlich in einem HttpOnly-Cookie (`fuelstat_auth`, 7 Tage), damit man angemeldet bleibt.
- **API**: Node.js 20 mit Express (`backend/src/server.js`), Authentifizierung per JWT. Doku: `docs/api.md` und `docs/openapi.1.0.0.yaml`.
- **Datenbank**: PostgreSQL 15. Vollständiges Schema in `backend/database/postgres_init.sql`, Migrationen daneben.
- **Infrastruktur**: `infra/docker-compose.yml` startet API, Datenbank und Adminer.

## Installation

### Voraussetzungen

- Docker mit Compose (für API und Datenbank)
- PHP 8.1 oder neuer (getestet mit 8.4) als PHP-FPM, mit den Erweiterungen `curl`, `mbstring` und `fileinfo`
- nginx (oder ein anderer Webserver mit Reverse Proxy) mit HTTPS

### 1. API und Datenbank (Docker)

```bash
git clone https://github.com/1emc/fuelstat.git && cd fuelstat/infra
cp .env.example .env    # Passwort, JWT_SECRET usw. eintragen, alle Optionen sind dort beschrieben
docker compose up -d
```

Die API läuft danach auf `127.0.0.1:3000`, Adminer auf `127.0.0.1:8080` (beides nur lokal erreichbar). Beim allerersten Start legt Postgres das komplette Schema aus `backend/database/postgres_init.sql` automatisch an.

Wird `JWT_SECRET` später ohne eigenen `MAIL_ENCRYPTION_KEY` geändert, müssen die Benutzer ihr SMTP-Passwort neu eingeben.

### 2. Frontend (PHP)

- Das Repository-Wurzelverzeichnis ist der Webroot des Frontends.
- `includes/db_connect.php` anlegen. Die Datei wird noch eingebunden, kann aber leer sein: `echo '<?php' > includes/db_connect.php` (Aufräumen siehe [#39](https://github.com/1emc/fuelstat/issues/39)).
- API-Adresse: Ohne Angabe nutzt das Frontend `https://tanken.1emc.de/api/v1`. Für eine eigene Installation im PHP-FPM-Pool setzen, z. B. `env[FUELSTAT_API_BASE_URL] = https://deine-domain.de/api/v1` (oder direkt `http://127.0.0.1:3000/api/v1`).
- Tankstellensuche: Tankerkönig-API-Key (kostenlos auf creativecommons.tankerkoenig.de) in `api/key.key` ablegen.
- Ladestationssuche für E-Autos und Plug-in-Hybride: Open-Charge-Map-API-Key (kostenlos, openchargemap.org → Mein Profil → API-Schlüssel) in `api/ocm.key` eintragen. Die leere Datei ist versioniert. Nach dem Eintragen `git update-index --skip-worktree api/ocm.key` ausführen, damit der Schlüssel nie committet wird.
- Betreiberangaben für Impressum und Datenschutzerklärung in `includes/betreiber.php` eintragen.
- Bootstrap, Chart.js, QRCode.js und Font Awesome liegen in `vendor/` bzw. `css/` und `webfonts/`. Es werden keine CDNs geladen (siehe `vendor/README.md`).

### 3. Webserver (nginx)

- `/api/` an die Node-API weiterleiten (`proxy_pass http://127.0.0.1:3000;`), `/uploads/` aus dem Upload-Verzeichnis ausliefern.
- Die Regeln aus `infra/nginx-sicherheit.conf.example` übernehmen. Sie sperren interne Verzeichnisse (`backend/`, `infra/`, `tests/`, `docs/` …), versteckte Dateien wie `.env` und `.git`, alle `*.key`-Dateien und verhindern, dass im Upload-Ordner jemals PHP ausgeführt wird. **Ohne diese Regeln sind `.env`-Dateien und API-Schlüssel abrufbar.**
- Bei OPcache mit `opcache.validate_timestamps=0` nach jedem Deploy PHP-FPM neu laden (z. B. `systemctl reload php8.4-fpm`).

### 4. Erstes Konto und Admin

1. Auf der Seite registrieren.
2. Die eigene User-ID nachsehen und in `infra/.env` bei `ADMIN_USER_IDS` eintragen:

   ```bash
   docker compose exec db sh -c 'psql -U "$POSTGRES_USER" -d "$POSTGRES_DB" -c "select id, email from users"'
   ```

3. Die API neu starten: `docker compose up -d`. Danach erscheinen Benutzerverwaltung und Feedback-Eingang im Profilmenü. Der SMTP-Zugang des ersten Admins dient als Standard-Mailserver für alle Benutzer ohne eigenen.

### Aktualisieren

```bash
git pull
cd infra && docker compose up -d --build   # API (startet im Dev-Container bei Codeänderungen auch selbst neu)
sudo systemctl reload php8.4-fpm            # Frontend
```

Neue Datenbank-Migrationen (siehe unten) **vor** dem Neustart der API einspielen, z. B.:

```bash
cd infra
docker compose exec -T db sh -c 'psql -U "$POSTGRES_USER" -d "$POSTGRES_DB"' < ../backend/database/postgres_010_plugin_hybrid.sql
```

### Datenbank-Migrationen

Neue Installationen brauchen keine Migrationen: `postgres_init.sql` enthält bereits alles bis einschließlich 010. Bestehende Installationen spielen alle noch fehlenden Migrationen der Reihe nach ein:

| Datei | Inhalt |
|---|---|
| `postgres_002` bis `postgres_005` | Ausgaben, Einheiten, Token-Sperrlisten, Fahrzeug- und Profildetails, Bilder (nur für Installationen vor Dezember 2025) |
| `postgres_006_fix_fillup_units.sql` | Einheit bestehender Tank- und Ladevorgänge aus der Antriebsart korrigieren |
| `postgres_007_mail_settings.sql` | SMTP-Einstellungen je Benutzer und Versandprotokoll |
| `postgres_008_beta_feedback.sql` | Beta-Tester-Kennzeichen und Feedback |
| `postgres_009_user_activity.sql` | Letzter Login und zuletzt aktiv |
| `postgres_010_plugin_hybrid.sql` | Antriebsart Plug-in-Hybrid (`phev`) und Akkugröße |

Alle Migrationen laufen in einer Transaktion und lassen sich gefahrlos ein zweites Mal ausführen.

### Sicherheit

- Sicherheitslücken bitte vertraulich melden, siehe [`SECURITY.md`](SECURITY.md). Die Kontaktdaten stehen auch in `/.well-known/security.txt` (RFC 9116); deren `Expires`-Datum spätestens nach einem Jahr verlängern.
- Uploads werden anhand ihres Inhalts geprüft (nur JPEG, PNG, GIF, WebP) und unter einem zufälligen Namen mit passender Endung gespeichert.
- Login, 2FA-Code und Passwortänderung werden nach 10 bzw. 5 Fehlversuchen für 15 Minuten gesperrt (je E-Mail-Adresse bzw. Benutzer, im Speicher der API).
- Alle Formulare sind per CSRF-Token geschützt; die Session-Cookies sind `HttpOnly` und `SameSite=Lax`.
- Öffentliche Registrierung abschalten: `REGISTRATION_ENABLED=false` in der Backend-`.env` (Admins legen Konten dann z. B. über die Datenbank an).

## Entwicklung

```bash
cd backend && npm install && npm run dev   # API mit Auto-Reload (braucht DATABASE_URL und JWT_SECRET)
php -S 127.0.0.1:8088                      # Frontend, dazu FUELSTAT_API_BASE_URL=http://127.0.0.1:3000/api/v1 setzen
for t in tests/*.php; do php -d zend.assertions=1 $t; done   # PHP-Tests
```

API-Doku: `docs/api.md` (Übersicht aller Endpunkte) und `docs/openapi.1.0.0.yaml`.

## Kontakt

Mario Caraggiu – [GitHub @1emc](https://github.com/1emc)
