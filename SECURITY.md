# Sicherheit / Security

## Sicherheitslücke melden

Bitte melde Sicherheitslücken **vertraulich** und **nicht** über öffentliche Issues, Pull Requests oder Diskussionen:

1. **Bevorzugt:** über GitHub unter [„Report a vulnerability“](https://github.com/1emc/fuelstat/security/advisories/new)
2. Alternativ per E-Mail an mario@caraggiu.eu

Hilfreich sind:

- betroffene Seite bzw. API-Endpunkt
- Schritte zum Nachstellen, gern mit Beispiel-Anfrage
- mögliche Auswirkung, z. B. Zugriff auf fremde Daten oder Ausfall

Den Eingang bestätige ich innerhalb von **3 Werktagen**. Ich halte dich über den Stand auf dem Laufenden und nenne dich nach der Behebung gern als Finder, wenn du das möchtest.

## Geltungsbereich

- die Live-Instanz **tanken.1emc.de** (Frontend und API unter `/api/v1`)
- der Code in diesem Repository, jeweils der aktuelle Stand von `main`

Nicht im Geltungsbereich: Dienste Dritter wie Tankerkönig, Open Charge Map oder OpenStreetMap/Nominatim, sowie Meldungen ohne nachvollziehbare Auswirkung (z. B. fehlende Header ohne Angriffsszenario, Ergebnisse automatischer Scanner ohne Prüfung).

## Regeln für Tests

- Teste nur mit **eigenen Konten** und eigenen Daten. Rufe keine Daten anderer Nutzer ab, ändere oder lösche sie nicht. Sollte das versehentlich passieren, brich ab und erwähne es in der Meldung.
- Keine Last- oder Denial-of-Service-Tests, kein Spam über die Mail-Funktionen, kein Social Engineering.
- Gib Details erst weiter, wenn die Lücke behoben ist oder wir uns abgestimmt haben (Coordinated Disclosure, in der Regel innerhalb von 90 Tagen).

Wer sich an diese Regeln hält und in gutem Glauben handelt, muss keine rechtlichen Schritte befürchten.

## Unterstützte Versionen

Fuelstat wird nur in der aktuellen Version betrieben. Sicherheitskorrekturen gibt es nur für den aktuellen Stand von `main`.

---

**English summary:** Please report vulnerabilities privately via [GitHub security advisories](https://github.com/1emc/fuelstat/security/advisories/new) or mario@caraggiu.eu, never in public issues. Acknowledgement within 3 business days. Scope: tanken.1emc.de and this repository's `main` branch. Only test with your own accounts, no DoS, no social engineering; good-faith research following these rules will not face legal action.
