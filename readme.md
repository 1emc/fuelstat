# Fahrzeug-Tracker-Webanwendung

Willkommen zum **Fahrzeug-Tracker-Projekt**! Diese Webanwendung ermöglicht es Benutzern, Informationen zu mehreren Fahrzeugen zu speichern, Ausgaben zu verfolgen und detaillierte Statistiken über den Verbrauch und die Kosten ihrer Fahrzeuge zu erhalten. Die Anwendung eignet sich für Besitzer von Benzin-, Diesel-, Gas-, Elektro- und Hybridfahrzeugen.

## Inhaltsverzeichnis

- [Funktionen](#funktionen)
- [Projektstruktur](#projektstruktur)
- [Installation](#installation)
- [Verwendung](#verwendung)
- [Technologien](#technologien)
- [Zukünftige Erweiterungen](#zukünftige-erweiterungen)
- [Mitwirkende](#mitwirkende)
- [Lizenz](#lizenz)

## Funktionen

### 1. Fahrzeugverwaltung

- **Übersicht "Meine Fahrzeuge"**: Startseite mit einer Liste aller hinzugefügten Fahrzeuge.
- **Fahrzeug hinzufügen**: Möglichkeit, neue Fahrzeuge über ein "+" Symbol hinzuzufügen.
- **Fahrzeugdetails**: Detailansicht jedes Fahrzeugs mit fünf Reitern für verschiedene Informationen und Funktionen.

### 2. Fahrzeuginformationen (Reiter 1)

- **Allgemeine Informationen**: Anzeige von Bild, Tachostand und Fahrleistung.
- **Bearbeitungsfunktion**: Über einen Stift-Button können Fahrzeugdetails bearbeitet werden.
- **Kraftstoffverbrauch**:
  - **Gesamtverbrauch**: Durchschnittlicher Verbrauch über die gesamte Fahrzeugnutzung.
  - **Aktueller Verbrauch**: Verbrauch basierend auf den neuesten Daten, mit Tendenz (steigend, sinkend, gleichbleibend).
- **Kosten pro Kilometer**:
  - **Gesamtkosten**: Durchschnittliche Kosten pro Kilometer über die gesamte Nutzung.
  - **Kraftstoffkosten**: Spezifische Kosten für Benzin/Diesel/Gas/Strom pro Kilometer.

### 3. Ausgabenverwaltung (Reiter 2)

- **Einträge für Tankfüllungen und andere Kosten**:
  - Anzeige aller Ausgaben in Form von Karten, gruppiert nach Monaten.
  - Jede Karte enthält Informationen wie Kategorie, Tachostand, Datum, Standort, Verbrauch, Kosten und weitere Details.
- **Neue Einträge hinzufügen**:
  - Über ein "+" Symbol können neue Ausgaben hinzugefügt werden.
  - Bei Tankfüllungen werden spezifische Daten abgefragt, z. B. Tachostand, Datum, Gesamtpreis, Preis pro Liter/kWh, Tankmenge und ob vollgetankt wurde.
- **Kategorien**:
  - **Tankfüllung**, **Versicherung**, **Werkstatt**, **Inspektion**, **Steuer**, **Reparatur**, **Reifen**, **TÜV**, **Wartung**, **Dekor**.

### 4. Statistiken (Reiter 3)

- **Verbrauchsanalyse**:
  - Liniendiagramm des Kraftstoffverbrauchs über die Zeit.
  - Anzeige von Minimal-, Durchschnitts- und Maximalverbrauchswerten.
- **Jahresübersicht**:
  - Tabelle mit jährlichen Daten wie Anzahl der Tankstops, Gesamtmenge getankter Liter/kWh, durchschnittlicher Verbrauch und Gesamtausgaben.

### 5. Ausgabenanalyse (Reiter 4)

- **Kuchendiagramm der Ausgaben**:
  - Visualisierung der Ausgaben nach Kategorien.
  - Möglichkeit, nach Jahr zu filtern oder alle historischen Daten anzuzeigen.
- **Detailtabelle**:
  - Aufschlüsselung der Kategorien mit Anzahl der Einträge, prozentualem Anteil und Gesamtkosten.

### 6. Neue Einträge hinzufügen (Reiter 5)

- **Auswahl des Eintragstyps**:
  - Frage, ob es sich um eine Tankfüllung oder eine andere Ausgabe handelt.
- **Spezifische Dateneingabe**:
  - Bei Tankfüllungen werden Daten wie Tachostand, Datum, Gesamtpreis, Preis pro Einheit, Menge und Volltankstatus abgefragt.
  - Bei anderen Ausgaben werden entsprechende Details erfasst.

## Projektstruktur

Die Anwendung ist in mehrere Module unterteilt, um eine klare Struktur und Wartbarkeit zu gewährleisten:

- **Frontend**:
  - **HTML/CSS/JavaScript**: Für die Benutzeroberfläche und Interaktivität.
  - **Templates**: Wiederverwendbare Komponenten für Header, Footer und Navigation.
- **Backend**:
  - **PHP**: Serverseitige Skripte für die Verarbeitung von Formularen und Datenbankinteraktionen.
- **Datenbank**:
  - **MySQL**: Speicherung aller Daten zu Benutzern, Fahrzeugen, Ausgaben und Statistiken.
- **Verzeichnisse**:
  - `css/`: Stylesheets.
  - `js/`: JavaScript-Dateien.
  - `images/`: Bilder der Fahrzeuge und Benutzer.
  - `includes/`: Wiederverwendbare PHP-Dateien wie Header, Footer und Datenbankverbindung.
  - `pages/`: Individuelle Seiten der Anwendung.

## Installation

### Voraussetzungen

- **Webserver**: Apache, Nginx oder ein anderer Server mit PHP-Unterstützung.
- **PHP**: Version 7.0 oder höher.
- **MySQL-Datenbank**: Für die Speicherung der Anwendungsdaten.
- **Git**: Zum Klonen des Repositorys (optional).

### Schritte

1. **Repository klonen**:

   ```bash
   git clone https://github.com/dein-benutzername/fahrzeug-tracker.git
   ```

2. **Dateien auf den Webserver kopieren**:

   Kopiere alle Dateien in das Dokumentenverzeichnis deines Webservers, z. B. in einen Unterordner `fahrzeug-tracker`.

3. **Datenbank einrichten**:

   - **Datenbank erstellen**:

     Logge dich in deine MySQL-Datenbank ein und erstelle eine neue Datenbank:

     ```sql
     CREATE DATABASE fahrzeugtracker_db;
     ```

   - **Tabellen anlegen**:

     Führe das bereitgestellte SQL-Skript `database/schema.sql` aus, um die erforderlichen Tabellen anzulegen:

     ```bash
     mysql -u benutzername -p fahrzeugtracker_db < database/schema.sql
     ```

4. **Datenbankverbindung konfigurieren**:

   - Bearbeite die Datei `includes/db_connect.php` und trage deine Datenbankzugangsdaten ein:

     ```php
     <?php
     $servername = "localhost";
     $username = "dein_db_benutzername";
     $password = "dein_db_passwort";
     $dbname = "fahrzeugtracker_db";

     // Verbindung herstellen
     $conn = new mysqli($servername, $username, $password, $dbname);

     // Verbindung prüfen
     if ($conn->connect_error) {
         die("Verbindung fehlgeschlagen: " . $conn->connect_error);
     }
     ?>
     ```

5. **Berechtigungen setzen**:

   - Stelle sicher, dass der Webserver Schreibrechte für die Verzeichnisse hat, in denen Bilder oder Dateien hochgeladen werden (`images/`).

6. **Abhängigkeiten installieren** (falls erforderlich):

   - Wenn du Composer oder andere Paketmanager verwendest, führe die entsprechenden Installationsbefehle aus.

## Verwendung

### Startseite - "Meine Fahrzeuge"

- **Übersicht**: Zeigt alle Fahrzeuge des Benutzers in einer übersichtlichen Darstellung.
- **Fahrzeug hinzufügen**: Über das "+" Symbol kann ein neues Fahrzeug hinzugefügt werden.
- **Navigation**: Durch Klicken auf ein Fahrzeug gelangt man zur Detailansicht mit den fünf Reitern.

### Fahrzeugdetailansicht

- **Reiter 1: Allgemeine Informationen**
  - Anzeige von Fahrzeugdetails wie Bild, Marke, Modell, Tachostand und Fahrleistung.
  - Bearbeitungsmöglichkeit über den Stift-Button.

- **Reiter 2: Tankfüllungen & Kosten**
  - Liste aller Ausgaben als Karten, gruppiert nach Monaten.
  - Informationen zu jeder Ausgabe, einschließlich Kategorie, Datum, Kosten und Verbrauch.
  - Möglichkeit, neue Einträge hinzuzufügen.

- **Reiter 3: Statistiken**
  - Liniendiagramm des Kraftstoffverbrauchs über die Zeit.
  - Anzeige von Minimal-, Durchschnitts- und Maximalwerten.
  - Jahresübersicht in Tabellenform.

- **Reiter 4: Ausgabenanalyse**
  - Kuchendiagramm der Ausgaben nach Kategorien.
  - Filteroptionen nach Jahr.
  - Detailtabelle mit Aufschlüsselung der Kategorien.

- **Reiter 5: Einstellungen**
  - Anpassung spezifischer Fahrzeugparameter wie Tankgröße oder Energiequelle.
  - Verwaltung von Fahrzeugbildern und anderen Einstellungen.

### Neue Einträge hinzufügen

- **Auswahl des Eintragstyps**:
  - Bei Klick auf das "+" Symbol wird gefragt, ob eine **Tankfüllung** oder eine **andere Ausgabe** hinzugefügt werden soll.

- **Tankfüllung hinzufügen**:
  - Eingabe von:
    - **Tachostand**
    - **Datum**
    - **Gesamtpreis**
    - **Preis pro Liter/kWh**
    - **Tankmenge in Liter/kWh**
    - **Vollgetankt** (Ja/Nein)
    - **Standort**
  - Automatische Berechnung des Verbrauchs und der Kosten pro Kilometer.

- **Andere Ausgabe hinzufügen**:
  - Auswahl der **Kategorie** (z. B. Versicherung, Werkstatt).
  - Eingabe von:
    - **Datum**
    - **Kosten**
    - **Beschreibung**
    - **Standort** (optional)

## Technologien

- **Frontend**:
  - **HTML5 & CSS3**: Strukturierung und Gestaltung der Seiten.
  - **JavaScript**: Interaktive Elemente und dynamische Funktionen.
  - **Bootstrap**: Responsives Design und vorgefertigte Komponenten.
  - **Chart.js**: Erstellung von Diagrammen und grafischen Darstellungen.

- **Backend**:
  - **PHP**: Serverseitige Logik, Verarbeitung von Formularen und Kommunikation mit der Datenbank.

- **Datenbank**:
  - **MySQL**: Speicherung aller persistenten Daten, einschließlich Benutzerinformationen, Fahrzeuge und Ausgaben.

- **Weitere Bibliotheken**:
  - **jQuery**: Vereinfachung von DOM-Manipulationen und AJAX-Anfragen (optional).
  - **FontAwesome**: Icons für Buttons und Symbole.

## Zukünftige Erweiterungen

- **Benutzerauthentifizierung**:
  - Implementierung eines Login-Systems zur Unterstützung mehrerer Benutzer.
  - Passwortverschlüsselung und Sitzungssicherheit.

- **Exporte und Berichte**:
  - Möglichkeit, Daten als CSV oder PDF zu exportieren.
  - Automatisierte Berichte per E-Mail.

- **Mobile Optimierung**:
  - Verbesserung der Benutzererfahrung auf mobilen Geräten.
  - Entwicklung einer eigenständigen mobilen Anwendung.

- **Mehrsprachigkeit**:
  - Unterstützung für mehrere Sprachen durch Internationalisierung (i18n).

- **Integration externer Dienste**:
  - Anbindung an APIs von Tankstellen für aktuelle Kraftstoffpreise.
  - Integration von GPS-Tracking für automatische Fahrleistungsaktualisierung.

- **Erweiterte Statistiken**:
  - CO₂-Emissionen basierend auf dem Kraftstoffverbrauch.
  - Vergleich mit Durchschnittswerten ähnlicher Fahrzeuge.

- **Benachrichtigungen**:
  - Erinnerungen für Wartungen, TÜV-Termine oder Versicherungsabläufe.

## Mitwirkende

- **Projektleiter**: Dein Name
- **Entwickler**: Liste der Entwickler
- **Designer**: Liste der Designer
- **Tester**: Liste der Tester

Beiträge sind herzlich willkommen! Bitte lese die [CONTRIBUTING.md](CONTRIBUTING.md), um zu erfahren, wie du zum Projekt beitragen kannst.

## Lizenz

Dieses Projekt steht unter der **MIT-Lizenz**. Siehe die [LICENSE](LICENSE)-Datei für weitere Details.

---

**Hinweis**: Dieses Projekt befindet sich in der aktiven Entwicklung. Feedback, Fehlerberichte und Vorschläge sind sehr willkommen!

---

### Kontakt

Für Fragen oder Anregungen wende dich bitte an:

- **E-Mail**: mario@caraggiu.com
- **GitHub**: [1emc](https://github.com/1emc)
