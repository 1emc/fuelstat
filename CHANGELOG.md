# Changelog

Alle nennenswerten Änderungen an Fuelstat. Format nach [Keep a Changelog](https://keepachangelog.com/de/1.1.0/), Versionsnummern nach [Semantic Versioning](https://semver.org/lang/de/): `MAJOR.MINOR.PATCH`. Bis zum Ende der Beta bleibt die Hauptversion 0.

Kategorien je Version: **Neu**, **Verbessert**, **Behoben**, **Sicherheit**, **Technik** und, wenn nötig, **Hinweise zum Update** (Migrationen, Befehle, Einstellungen). Die App zeigt diese Datei unter „Neuigkeiten“ an.

## [Unreleased]

## [0.9.0] - 2026-11-06

Erste Version mit Versionsnummer: alles, was Fuelstat bis zu diesem Stand kann.

### Neu
- Fahrzeuge für alle Antriebsarten (Benzin, Diesel, Autogas, Erdgas, Elektro, Hybrid, Plug-in-Hybrid, Wasserstoff) mit Foto, Kennzeichen, FIN, Kauf, Intervallen, Versicherung, Steuer und Reifengrößen; Anlegen als Assistent in vier Schritten
- Tanken, Laden, Ausgaben, Fahrten und reine Kilometerstände erfassen, mit Tankstellen- und Ladestationssuche samt aktuellem Preis
- Plug-in-Hybride: Tanken und Laden im selben Fahrzeug, Verbrauch getrennt, gemeinsame Energiekosten
- Statistiken: Verbrauch je Abschnitt, Monat und Jahr, Kosten pro km und Monat, CO₂, Jahresvergleich, Ausgaben nach Kategorie und Gruppe
- Fälligkeiten für TÜV, Wartung, Versicherung und Steuer mit E-Mail-Erinnerung; Monatsbericht per E-Mail
- Kategorien aus der API, dazu Premium-Kategorien (Parken, Maut, Pflege, Leasing, Zubehör) und bis zu 10 eigene Kategorien mit Symbol, Farbe und Gruppe
- Import aus CSV mit Spaltenzuordnung und Vorschau, Export als CSV und Bericht zum Drucken
- Konto mit Zwei-Faktor-Anmeldung, Profilbild, Abmelden auf allen Geräten, Konto löschen
- Benutzerverwaltung und Feedback-Eingang für Admins, Tarif-Grundgerüst (Free, Plus, Flotte)
- Als App auf dem Handy installierbar, mit unterer Navigationsleiste und Wischen zwischen den Reitern
- „Neuigkeiten“: diese Übersicht mit der installierten Version

### Verbessert
- Deutlich schnellere Seiten: Daten werden parallel und direkt von der API geladen, Ladebalken beim Seitenwechsel, „Zurück“ ohne Neuladen
- Bilder werden beim Hochladen verkleinert und als WebP gespeichert (aus mehreren MB werden einige hundert KB)
- Angemeldet bleiben bei aktiver Nutzung (bis zu 90 Tage)
- Einheitliches Design für Übersicht, Fahrzeugseite, Einträge und alle Eintragsformulare

### Behoben
- Der Preis pro Liter der gewählten Tankstelle wird nicht mehr von der automatischen Berechnung überschrieben
- Ort-Vorschläge verschwinden, sobald ein Ort eingetragen ist
- Kopfzeile in der iPhone-App wird nicht mehr weichgezeichnet

### Sicherheit
- Hochgeladene Bilder ohne Metadaten (kein GPS-Ort) und nur über signierte, ablaufende Adressen abrufbar
- 2FA-Schlüssel und SMTP-Passwörter verschlüsselt gespeichert, CORS standardmäßig aus, Rate Limiting, CSRF-Schutz, CodeQL

### Technik
- API: Node.js 24 mit Express, PostgreSQL 15, Betrieb im `prod`-Container; PHP-Frontend greift nur über die API zu
- Automatische Tests (PHP und API) und Prüfung der YAML-Dateien bei jedem Pull Request
- Datensicherung mit Verschlüsselung und Wiederherstellungsprobe

### Hinweise zum Update
- Datenbank auf Stand Migration 014 bringen (`docs/betrieb.md`)
- Einmalig vorhandene Bilder umstellen: `sudo docker compose exec api node scripts/bilder-optimieren.js`
- nginx: `/uploads/` sperren, `FUELSTAT_API_BASE_URL` direkt auf `127.0.0.1:3000` setzen (`docs/installation.md`)

[Unreleased]: https://github.com/1emc/fuelstat/compare/v0.9.0...HEAD
[0.9.0]: https://github.com/1emc/fuelstat/releases/tag/v0.9.0
