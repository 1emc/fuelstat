# Fuelstat – Hinweise für Claude und andere Agents

Web-App (PHP-Frontend), API (Node.js/Express) und Datenbank (PostgreSQL) von Fuelstat.
Die zentrale Planung (Milestones) liegt in diesem Repository, auch für die iOS-App
([FuelStat/fuelstat-ios](https://github.com/FuelStat/fuelstat-ios)).

## Release-Prozess

Ausführlich in [`docs/betrieb.md`](docs/betrieb.md#versionen-und-releases). Kurzfassung, damit
nichts vergessen wird:

**Versionsnummer:** Semantic Versioning, während der Beta `0.x.y`. Einzige Quelle ist die Datei
`VERSION`. PATCH für Fehlerbehebungen, MINOR für neue Funktionen, MAJOR für Brüche (z. B.
API-Änderungen, die Apps anpassen müssen). Die geplanten Versionen sind die Milestones.

1. **Laufend:** Jeder PR mit sichtbarer Änderung trägt sie in `CHANGELOG.md` unter
   `## [Unreleased]` ein (Kategorien: Neu, Verbessert, Behoben, Sicherheit, Technik; dazu
   „Hinweise zum Update“, wenn beim Ausrollen etwas zu tun ist).
2. **Release vorbereiten** in einem eigenen PR:

   ```bash
   node backend/scripts/neue-version.js X.Y.Z
   ```

   Das setzt `VERSION`, `backend/package.json` und `backend/package-lock.json` und schließt
   `[Unreleased]` als `## [X.Y.Z] - JJJJ-MM-TT` ab. Vor dem PR `php tests/VersionTest.php`.
3. **PR mergen.**
4. **Tag setzen** auf dem gemergten `main` – das löst `.github/workflows/release.yml` aus, der
   Tag, `VERSION`, `package.json` und CHANGELOG prüft und das GitHub-Release mit dem Text aus dem
   CHANGELOG anlegt (0.x als „Pre-release“):

   ```bash
   git tag -a vX.Y.Z -m "Fuelstat X.Y.Z"
   git push origin vX.Y.Z
   ```

5. **Ausrollen** auf dem Server, wie unter „Aktualisieren“ in `docs/betrieb.md`:

   ```bash
   git pull
   cd infra && sudo docker compose up -d --build
   sudo systemctl reload php8.4-fpm
   ```

   Ohne `--build` meldet die API weiter die alte Version (`/api/v1/health`), und unter
   „Neuigkeiten“ warnt die Seite Admins, dass API und Frontend nicht zusammenpassen. Danach die
   „Hinweise zum Update“ der Version abarbeiten (Migrationen, `.env`, nginx).

**Erinnern:** Der Maintainer möchte an diesen Ablauf erinnert werden. Weise am Ende einer Aufgabe
darauf hin, wenn

- unter `## [Unreleased]` Einträge stehen, die seit dem letzten Tag nicht veröffentlicht sind –
  mit Vorschlag für die nächste Versionsnummer;
- ein Release-PR gemergt ist, aber das Tag `vX.Y.Z` fehlt (`git tag`, `git ls-remote --tags origin`);
- ein Tag existiert, der Server aber noch nicht ausgerollt ist (Schritt 5).

Tags setzt und pusht Claude nur, wenn der Maintainer ausdrücklich darum bittet.

## Regeln

- **API-first:** Fachliche Berechnungen gehören in die API, nicht ins PHP-Frontend (#82). Clients
  stellen nur dar.
- **Externe Dienste nur über die API** (Tankerkönig, Open Charge Map, später weitere): kein
  direkter Aufruf aus PHP oder den Apps; API-Keys nur in der Server-Konfiguration (#127).
- Änderungen an der API nur abwärtskompatibel unter `/api/v1`; Brüche nur über `/api/v2`.
  Doku in `docs/api.md` und `docs/openapi.1.0.0.yaml` mit anpassen.
- Tarifregeln setzt ausschließlich die API durch (#102).
- Keine Secrets committen (`.env`, `api/key.key`, `api/ocm.key`, Tokens).
- Oberfläche, Doku und Commit-Texte auf Deutsch; PRs folgen `.github/pull_request_template.md`.
