# Fuelstat

Fuelstat ist eine Web-App, mit der du Tankfüllungen, Ladevorgänge und alle anderen Kosten deiner Fahrzeuge erfasst und auswertest: Verbrauch, Kosten pro Kilometer, CO₂-Ausstoß und Erinnerungen an TÜV, Wartung, Versicherung und Steuer. Sie funktioniert für Benzin, Diesel, Autogas, Erdgas, Elektro, Hybrid, Plug-in-Hybrid und Wasserstoff.

## Funktionen

**Fahrzeuge**
- Mehrere Fahrzeuge mit Name, Kraftstoff, Marke, Modell, Baujahr, Tachostand bei Anschaffung, Tank- bzw. Akkugröße und Foto; dazu optional Kennzeichen, FIN, Erstzulassung, Kauf und Verkauf, Wartungs- und Ölwechselintervall, Versicherung und Kfz-Steuer mit Fälligkeitsmonat, Reifengrößen
- Anlegen als Assistent in vier Schritten mit Live-Vorschau der Fahrzeugkarte; Bearbeiten mit aufklappbaren Abschnitten
- Reihenfolge in der Übersicht frei sortierbar
- Hinweise in der Übersicht, wenn TÜV, Wartung, Versicherung oder Steuer bald fällig oder überfällig sind

**Einträge**
- Erfassen in wenigen Sekunden: Art als große Kacheln (Tanken, Laden, Ausgabe, Fahrt, km notieren), Tachostand zuerst, Datum steht auf heute, zuletzt genutzte Orte als Chips, Speichern-Knopf unten fest
- App-Kürzel (lange auf das App-Icon tippen): „Tanken oder Laden“ und „Kilometerstand notieren“; bei mehreren Fahrzeugen steht das zuletzt genutzte oben
- „km notieren“: nur den Tachostand festhalten (ohne Kosten), z. B. beim Parken; hilft beim Schätzen, bei der Plausibilitätsprüfung und bei der Fahrleistung
- Tankfüllungen bzw. Ladevorgänge mit Menge (l, kWh oder kg je nach Kraftstoff), Preis, Tankstelle, voll/teilweise getankt; alle Texte passen sich der Antriebsart an (Tankstelle/Ladestation, vollgetankt/vollgeladen …)
- Warnung bei unplausiblem Tachostand (kleiner als der vorige oder ungewöhnlich großer Sprung)
- Tachostand vergessen? Tank- und Ladevorgänge lassen sich ohne Tachostand (oder mit einer Schätzung aus Menge und Ø Verbrauch) speichern und später nachtragen – auch nach 14 Tagen. Hinweis in der Übersicht und in der Eintragsliste; der Verbrauch bleibt korrekt
- Menge, Preis pro Einheit und Gesamtpreis: aus zwei Angaben wird die dritte berechnet und gespeichert; Info-Knöpfe zeigen, was eingetragen und was berechnet ist
- Plug-in-Hybride: Tanken und Laden im selben Fahrzeug, Verbrauch getrennt in l/100 km und kWh/100 km, gemeinsame Energiekosten und Stromanteil; Tank und Akku als eigene Angaben
- Tankstellen in der Nähe samt aktuellem Preis (Tankerkönig), beim Laden Ladestationen mit Leistung und Steckertypen (Open Charge Map); der zuletzt bezahlte kWh-Preis an einer Station wird vorgeschlagen
- Andere Ausgaben (Versicherung, Werkstatt, Inspektion, Steuer, Reparatur, Reifen, TÜV, Wartung, Dekor) und Fahrten
- Eintragsliste je Monat mit Summe, Filter nach Art (Tanken, Laden, Ausgaben, Fahrten, km-Notizen), Suche nach Ort oder Beschreibung, Aktionen im Menü je Eintrag
- Alle Einträge bearbeiten und löschen (Tankfüllungen innerhalb von 14 Tagen)

**Auswertungen**
- Ø Verbrauch über alle Abschnitte in Übersicht und Fahrzeugseite, dazu der letzte Abschnitt als Trend
- Verbrauch pro Tankfüllung und Monat, bester/schlechtester Monat, Trend
- Jahresübersicht mit Menge, Kosten, Verbrauch und CO₂
- Ausgaben nach Kategorie und Kostengruppe, Kosten pro Monat und km, Wartungskosten je 10.000 km, Jahresvergleich
- CO₂-Ausstoß gesamt und pro km
- Fälligkeiten: TÜV alle 24 Monate (ohne Eintrag 36 Monate nach Erstzulassung), Wartung nach dem Intervall des Fahrzeugs (Standard 12 Monate oder 15.000 km), Versicherung und Steuer jährlich bzw. im hinterlegten Fälligkeitsmonat
- Export als CSV (für Excel) und als Bericht zum Drucken bzw. Speichern als PDF
- Import aus CSV (z. B. Excel-Listen, andere Apps oder ein Fuelstat-Export) mit Spaltenzuordnung, Vorschau, Duplikaterkennung und Vorlage passend zur Antriebsart

**E-Mail-Benachrichtigungen**
- Ohne eigene Angaben verschickt Fuelstat über den Mailserver des Betreibers (SMTP-Zugang des Admins) an die Konto-Adresse
- Optional hinterlegt jeder Benutzer seinen eigenen Mailserver (SMTP) in den Einstellungen
- Erinnerung, wenn TÜV, Wartung, Versicherung oder Steuer bald fällig sind (Vorlaufzeit einstellbar) und noch einmal, wenn sie überfällig sind
- Erinnerung „Kilometerstand nachtragen“ einige Stunden nach einem Tank- oder Ladevorgang ohne Tachostand (genau einmal)
- Monatsbericht mit Kosten, Strecke, Verbrauch und anstehenden Terminen je Fahrzeug

**Konto**
- Registrierung und Login, optional mit Zwei-Faktor-Authentifizierung (Authenticator-App)
- Profil mit Benutzername und Profilbild, Passwort ändern
- Abmelden auf allen Geräten, Konto löschen
- Benutzerverwaltung für Admins: Fahrzeuge je Benutzer, zuletzt aktiv, letzter Login, letzter Eintrag, Beta-Tester-Kennzeichen, Tarif (Free/Plus/Flotte, optional befristet) und Feedback-Eingang
- Tarif-Grundgerüst für ein späteres Premium-Modell; Grenzen erst mit `PLAN_LIMITS_ENABLED=true` aktiv, bis dahin haben alle den vollen Umfang
- „Feedback senden“ für angemeldete Benutzer (Fehler, Idee, Lob, Sonstiges): wird gespeichert und per Mail an den Betreiber geschickt
- Hell- und Dunkelmodus, als App installierbar (PWA), auf dem Handy mit App-Navigation (untere Leiste, Profil- und Fahrzeugauswahl von unten); auf der Fahrzeugseite wechselt Wischen nach links bzw. rechts zwischen den Reitern

## Aufbau

```
Browser ──> PHP-Frontend (Wurzelverzeichnis) ──> Node-API (backend/) ──> PostgreSQL
```

- **Frontend**: PHP 8 mit Bootstrap 5.3 und Chart.js. Es speichert selbst keine Daten, sondern ruft die API auf (`includes/fuelstat_api.php`). Der Login-Token liegt in der PHP-Session und zusätzlich in einem HttpOnly-Cookie (`fuelstat_auth`, 7 Tage), damit man angemeldet bleibt.
- **API**: Node.js 24 (LTS) mit Express (`backend/src/server.js`), Authentifizierung per JWT. Doku: `docs/api.md` und `docs/openapi.1.0.0.yaml`.
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
mkdir -p ../uploads && sudo chown 1000:1000 ../uploads   # die API läuft als Benutzer "node" (UID 1000)
docker compose up -d --build
```

Die API läuft danach auf `127.0.0.1:3000`, Adminer auf `127.0.0.1:8080` (beides nur lokal erreichbar). Die API läuft im `prod`-Container: Code und Pakete stecken im Image, ohne Root-Rechte. Beim allerersten Start legt Postgres das komplette Schema aus `backend/database/postgres_init.sql` automatisch an.

Wird `JWT_SECRET` später ohne eigenen `MAIL_ENCRYPTION_KEY` geändert, lassen sich gespeicherte SMTP-Passwörter und 2FA-Schlüssel nicht mehr entschlüsseln: Benutzer müssen ihr SMTP-Passwort neu eingeben, und 2FA muss für betroffene Konten zurückgesetzt werden. Deshalb `MAIL_ENCRYPTION_KEY` am besten von Anfang an fest setzen.

### 2. Frontend (PHP)

- Das Repository-Wurzelverzeichnis ist der Webroot des Frontends.
- Eine Datenbankverbindung braucht das Frontend nicht: Es liest und schreibt ausschließlich über die API. Eine alte `includes/db_connect.php` aus der MySQL-Zeit kann gelöscht werden.
- API-Adresse: Ohne Angabe nutzt das Frontend `https://tanken.1emc.de/api/v1`. Für eine eigene Installation im PHP-FPM-Pool setzen, z. B. `env[FUELSTAT_API_BASE_URL] = https://deine-domain.de/api/v1` (oder direkt `http://127.0.0.1:3000/api/v1`).
- Tankstellensuche: Tankerkönig-API-Key (kostenlos auf creativecommons.tankerkoenig.de) in `api/key.key` ablegen.
- Ladestationssuche für E-Autos und Plug-in-Hybride: Open-Charge-Map-API-Key (kostenlos, openchargemap.org → Mein Profil → API-Schlüssel) in `api/ocm.key` eintragen. Die leere Datei ist versioniert. Nach dem Eintragen `git update-index --skip-worktree api/ocm.key` ausführen, damit der Schlüssel nie committet wird.
- Betreiberangaben für Impressum und Datenschutzerklärung in `includes/betreiber.php` eintragen.
- Bootstrap, Chart.js, QRCode.js und Font Awesome liegen in `vendor/` bzw. `css/` und `webfonts/`. Es werden keine CDNs geladen (siehe `vendor/README.md`).

### 3. Webserver (nginx)

Eine vollständige Konfiguration liegt in `infra/nginx/fuelstat.conf.example` (HTTPS-Weiterleitung, Sicherheits-Header, `/api/v1/` → Node-API auf `127.0.0.1:3000`, PHP-FPM, Caching). Domain, Webroot und FPM-Socket anpassen:

```bash
sudo cp infra/nginx-sicherheit.conf.example /etc/nginx/snippets/fuelstat-sicherheit.conf
sudo cp infra/nginx/fuelstat.conf.example /etc/nginx/sites-available/fuelstat.conf   # danach Platzhalter ersetzen
sudo ln -s /etc/nginx/sites-available/fuelstat.conf /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

- Wer eine eigene Konfiguration hat, bindet nur das Snippet **ganz oben** im `server`-Block ein: `include snippets/fuelstat-sicherheit.conf;`. Es sperrt interne Verzeichnisse (`backend/`, `infra/`, `tests/`, `docs/` …), versteckte Dateien wie `.env` und `.git`, alle `*.key`-Dateien, Doku-/Konfigurationsdateien (`*.md`, `*.yml`, `*.sql`, `*.sh` …), liefert aus `uploads/` nur Bilder aus und `/.well-known/security.txt` als Text. **Ohne diese Regeln sind `.env`-Dateien und API-Schlüssel abrufbar.**
- Nach Updates prüfen, ob sich das Snippet geändert hat (`git diff` auf `infra/nginx-sicherheit.conf.example`), und es erneut kopieren.
- Schnelltest: `curl -I https://<domain>/readme.md` und `curl -I https://<domain>/api/ocm.key` müssen `404` liefern.
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
cd infra && docker compose up -d --build   # API: neues Image mit aktuellem Code und aktuellen Paketen
sudo systemctl reload php8.4-fpm            # Frontend
```

**Einmalig beim Umstieg auf den `prod`-Container (November 2026):** Bis dahin lief die API im Entwicklungs-Container mit eingebundenem Code und eigenem `node_modules`-Volume. Dadurch kamen Paket-Updates nicht an. Umstellen:

```bash
cd infra
sudo chown -R 1000:1000 ../uploads          # Uploads gehören jetzt dem Benutzer "node"
sudo docker compose up -d --build api
sudo docker volume rm infra_api_node_modules # altes Paket-Volume wird nicht mehr gebraucht
```

Neue Datenbank-Migrationen (siehe unten) **vor** dem Neustart der API einspielen, z. B.:

```bash
cd infra
docker compose exec -T db sh -c 'psql -U "$POSTGRES_USER" -d "$POSTGRES_DB"' < ../backend/database/postgres_013_vehicle_details.sql
```

### Datensicherung

`infra/backup/` enthält Sicherung und Wiederherstellung (Datenbank per `pg_dump` und hochgeladene Bilder):

1. `cp infra/backup/backup.env.example infra/backup/backup.env` und Pfade eintragen
2. Verschlüsselung (empfohlen): `age-keygen -o fuelstat-backup-key.txt`. Den **privaten** Schlüssel offline aufbewahren (z. B. im Passwortmanager), auf dem Server nur den öffentlichen Schlüssel als `BACKUP_AGE_RECIPIENT` eintragen.
3. Optional externe Kopie per `rsync`, z. B. auf eine Hetzner Storage Box (`BACKUP_REMOTE`)
4. Täglich automatisch:

   ```bash
   sudo cp infra/backup/fuelstat-backup.service infra/backup/fuelstat-backup.timer /etc/systemd/system/
   sudo systemctl daemon-reload && sudo systemctl enable --now fuelstat-backup.timer
   ```

   Aufbewahrung: 14 tägliche und 12 wöchentliche Sicherungen (einstellbar). Danach sind gelöschte Daten auch aus den Sicherungen verschwunden; die Datenschutzerklärung nennt dafür 90 Tage (`backup_tage` in `includes/betreiber.php`).
5. **Wiederherstellung regelmäßig testen** (spielt in eine eigene Testdatenbank ein und zählt Zeilen, ohne die echten Daten anzufassen):

   ```bash
   sudo AGE_IDENTITY=/pfad/zu/fuelstat-backup-key.txt infra/backup/fuelstat-restore.sh --test /var/backups/fuelstat/daily/fuelstat-db-….dump.age
   ```

   Im Ernstfall `--echt` statt `--test` (überschreibt die Datenbank nach Rückfrage, optional mit dem Uploads-Archiv als drittem Argument).

### Datenbank-Migrationen

Neue Installationen brauchen keine Migrationen: `postgres_init.sql` enthält bereits alles bis einschließlich 013. Bestehende Installationen spielen alle noch fehlenden Migrationen der Reihe nach ein:

| Datei | Inhalt |
|---|---|
| `postgres_002` bis `postgres_005` | Ausgaben, Einheiten, Token-Sperrlisten, Fahrzeug- und Profildetails, Bilder (nur für Installationen vor Dezember 2025) |
| `postgres_006_fix_fillup_units.sql` | Einheit bestehender Tank- und Ladevorgänge aus der Antriebsart korrigieren |
| `postgres_007_mail_settings.sql` | SMTP-Einstellungen je Benutzer und Versandprotokoll |
| `postgres_008_beta_feedback.sql` | Beta-Tester-Kennzeichen und Feedback |
| `postgres_009_user_activity.sql` | Letzter Login und zuletzt aktiv |
| `postgres_010_plugin_hybrid.sql` | Antriebsart Plug-in-Hybrid (`phev`) und Akkugröße |
| `postgres_011_plans.sql` | Tarif je Konto (`plan`, `plan_until`), siehe `PLAN_LIMITS_ENABLED` |
| `postgres_012_missing_odometer.sql` | Tank-/Ladevorgänge ohne Tachostand, Markierung „geschätzt“, Erinnerung „Kilometerstand nachtragen“ |
| `postgres_013_vehicle_details.sql` | Fahrzeugangaben: Kennzeichen, FIN, Erstzulassung, Kauf/Verkauf, Intervalle, Versicherung, Steuer, Reifen |

Alle Migrationen laufen in einer Transaktion und lassen sich gefahrlos ein zweites Mal ausführen.

### Sicherheit

- Sicherheitslücken bitte vertraulich melden, siehe [`SECURITY.md`](SECURITY.md). Die Kontaktdaten stehen auch in `/.well-known/security.txt` (RFC 9116); deren `Expires`-Datum spätestens nach einem Jahr verlängern.
- Uploads werden anhand ihres Inhalts geprüft (nur JPEG, PNG, GIF, WebP) und unter einem zufälligen Namen mit passender Endung gespeichert.
- Rate Limiting in der API: angemeldet 600 Anfragen pro Minute je Konto, ohne Anmeldung 300 je IP, Uploads 30 und Importe 60 je 10 Minuten (Antwort `429 too_many_requests`). Die API vertraut `X-Forwarded-For` nur von lokalen bzw. privaten Adressen (nginx, Docker-Netz).
- Code-Scanning mit CodeQL über `.github/workflows/codeql.yml` (erweiterte Einrichtung, ohne `vendor/`); in den Repository-Einstellungen muss dafür die Standard-Einrichtung von CodeQL aus sein.
- Login, 2FA-Code und Passwortänderung werden nach 10 bzw. 5 Fehlversuchen für 15 Minuten gesperrt (je E-Mail-Adresse bzw. Benutzer, im Speicher der API).
- Alle Formulare sind per CSRF-Token geschützt; die Session-Cookies sind `HttpOnly` und `SameSite=Lax`.
- Öffentliche Registrierung abschalten: `REGISTRATION_ENABLED=false` in der Backend-`.env` (Admins legen Konten dann z. B. über die Datenbank an).

## Entwicklung

```bash
cd backend && npm install && npm run dev   # API mit Auto-Reload (braucht DATABASE_URL und JWT_SECRET)
# oder per Docker mit eingebundenem Code und nodemon (nur lokal, nie auf dem Server):
# cd infra && docker compose -f docker-compose.yml -f docker-compose.dev.yml up -d --build
php -S 127.0.0.1:8088                      # Frontend, dazu FUELSTAT_API_BASE_URL=http://127.0.0.1:3000/api/v1 setzen
for t in tests/*.php; do php -d zend.assertions=1 $t; done   # PHP-Tests
```

### Tests

Bei jedem PR und jedem Push auf `main` laufen automatisch (`.github/workflows/tests.yml`):
- PHP: Syntax aller Dateien und die Tests in `tests/`
- API: `node --check` und die Tests in `backend/test/` gegen eine frische Postgres-Datenbank
- YAML: OpenAPI-Datei, Docker-Compose-Dateien und Workflows

Die API-Tests (Node-Testrunner, ohne zusätzliche Pakete) brauchen eine leere Datenbank mit dem Schema aus `postgres_init.sql`. Jeder Lauf legt eigene Testkonten an, die Datenbank muss danach nicht geleert werden. **Nie gegen die echte Datenbank laufen lassen.**

```bash
createdb fuelstat_test && psql -d fuelstat_test -f backend/database/postgres_init.sql
cd backend && DATABASE_URL=postgresql://localhost/fuelstat_test npm test
```

Ohne `DATABASE_URL` laufen nur die Tests ohne Datenbank (z. B. die Tachostand-Prüfung).

API-Doku: `docs/api.md` (Übersicht aller Endpunkte) und `docs/openapi.1.0.0.yaml`.

## Kontakt

Mario Caraggiu – [GitHub @1emc](https://github.com/1emc)
