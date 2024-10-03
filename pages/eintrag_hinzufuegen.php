<?php
// eintrag_hinzufuegen.php
session_start();
include '../includes/db_connect.php';
include '../includes/header.php';

// Überprüfe, ob die Fahrzeug-ID übergeben wurde
if (isset($_GET['fahrzeug_id'])) {
    $fahrzeug_id = intval($_GET['fahrzeug_id']);
} else {
    // Fehlerbehandlung oder Weiterleitung
    die("Fahrzeug-ID fehlt.");
}
?>

<div class="container mt-5">
    <h2>Neuen Eintrag hinzufügen</h2>
    <form method="post" action="eintrag_speichern.php">
        <!-- Eintragstyp Auswahl -->
        <div class="form-group">
            <label for="eintragstyp">Was möchtest du hinzufügen?</label>
            <select class="form-control" id="eintragstyp" name="eintragstyp" required>
                <option value="">Bitte auswählen</option>
                <option value="Tankfüllung">Tankfüllung</option>
                <option value="Andere Ausgabe">Andere Ausgabe</option>
                <option value="Fahrt">Fahrt</option>
            </select>
        </div>

        <!-- Gemeinsame Felder -->
        <div id="gemeinsame_felder" style="display: none;">
            <div class="form-group mt-3">
                <label for="datum">Datum</label>
                <input type="date" class="form-control" id="datum" name="datum" required>
            </div>
            <div class="form-group mt-3">
                <label for="tachostand">Tachostand (km)</label>
                <input type="number" class="form-control" id="tachostand" name="tachostand" required
                    inputmode="numeric" pattern="[0-9]*" min="0" step="1">
            </div>
        </div>

        <!-- Felder für Tankfüllung -->
        <div id="tankfuellung_felder" style="display: none;">
            <div class="form-group mt-3">
                <label for="menge">Getankte Menge (Liter)</label>
                <input type="number" class="form-control" id="menge" name="menge" required
                    inputmode="decimal" step="0.01" min="0">
            </div>
            <div class="form-group mt-3">
                <label for="kosten">Kosten (€)</label>
                <input type="number" class="form-control" id="kosten" name="kosten" required
                    inputmode="decimal" step="0.01" min="0">
            </div>
            <div class="form-group mt-3">
                <label for="preis_pro_einheit">Preis pro Liter (€)</label>
                <input type="number" class="form-control" id="preis_pro_einheit" name="preis_pro_einheit" required
                    inputmode="decimal" step="0.001" min="0">
            </div>
            <div class="form-check mt-3">
                <input type="checkbox" class="form-check-input" id="vollgetankt" name="vollgetankt" value="1">
                <label class="form-check-label" for="vollgetankt">Vollgetankt</label>
            </div>
        </div>

        <!-- Felder für Andere Ausgabe -->
        <div id="andere_ausgabe_felder" style="display: none;">
            <div class="form-group mt-3">
                <label for="kategorie_ausgabe">Kategorie</label>
                <select class="form-control" id="kategorie_ausgabe" name="kategorie">
                    <option value="Wartung">Wartung</option>
                    <option value="Reparatur">Reparatur</option>
                    <option value="Versicherung">Versicherung</option>
                    <option value="Steuer">Steuer</option>
                    <!-- Weitere Kategorien hinzufügen -->
                </select>
            </div>
            <div class="form-group mt-3">
                <label for="kosten_ausgabe">Kosten (€)</label>
                <input type="number" class="form-control" id="kosten_ausgabe" name="kosten" required
                    inputmode="decimal" step="0.01" min="0">
            </div>
            <div class="form-group mt-3">
                <label for="beschreibung">Beschreibung</label>
                <textarea class="form-control" id="beschreibung" name="beschreibung"></textarea>
            </div>
        </div>

        <!-- Felder für Fahrt -->
        <div id="fahrt_felder" style="display: none;">
            <div class="form-group mt-3">
                <label for="startort">Startort</label>
                <input type="text" class="form-control" id="startort" name="startort" required>
            </div>
            <div class="form-group mt-3">
                <label for="zielort">Zielort</label>
                <input type="text" class="form-control" id="zielort" name="zielort" required>
            </div>
            <div class="form-group mt-3">
                <label for="zweck">Zweck der Fahrt</label>
                <input type="text" class="form-control" id="zweck" name="zweck">
            </div>
            <div class="form-group mt-3">
                <label for="gefahrene_km">Gefahrene Kilometer</label>
                <input type="number" class="form-control" id="gefahrene_km" name="gefahrene_km" required
                    inputmode="decimal" step="0.1" min="0">
            </div>
        </div>

        <!-- Fahrzeug-ID als verstecktes Feld -->
        <input type="hidden" name="fahrzeug_id" value="<?php echo $fahrzeug_id; ?>">

        <!-- Absenden-Button -->
        <button type="submit" class="btn btn-primary mt-4">Eintrag speichern</button>
    </form>
</div>

<!-- JavaScript zum dynamischen Anzeigen der Felder -->
<script>
document.getElementById('eintragstyp').addEventListener('change', function() {
    var typ = this.value;

    // Alle spezifischen Felder ausblenden und deaktivieren
    var allFieldsets = ['tankfuellung_felder', 'andere_ausgabe_felder', 'fahrt_felder'];
    allFieldsets.forEach(function(fieldsetId) {
        var fieldset = document.getElementById(fieldsetId);
        if (fieldset) {
            fieldset.style.display = 'none';
            // Deaktiviere alle Eingabefelder in diesem Abschnitt
            Array.from(fieldset.querySelectorAll('input, select, textarea')).forEach(function(element) {
                element.disabled = true;
            });
        }
    });

    // Gemeinsame Felder ausblenden und deaktivieren
    var gemeinsameFelder = document.getElementById('gemeinsame_felder');
    if (gemeinsameFelder) {
        gemeinsameFelder.style.display = 'none';
        Array.from(gemeinsameFelder.querySelectorAll('input, select, textarea')).forEach(function(element) {
            element.disabled = true;
        });
    }

    // Spezifische Felder basierend auf der Auswahl anzeigen und aktivieren
    if (typ) {
        // Gemeinsame Felder anzeigen und aktivieren
        gemeinsameFelder.style.display = 'block';
        Array.from(gemeinsameFelder.querySelectorAll('input, select, textarea')).forEach(function(element) {
            element.disabled = false;
        });

        var selectedFieldsetId = '';
        if (typ === 'Tankfüllung') {
            selectedFieldsetId = 'tankfuellung_felder';
        } else if (typ === 'Andere Ausgabe') {
            selectedFieldsetId = 'andere_ausgabe_felder';
        } else if (typ === 'Fahrt') {
            selectedFieldsetId = 'fahrt_felder';
        }
        var selectedFieldset = document.getElementById(selectedFieldsetId);
        if (selectedFieldset) {
            selectedFieldset.style.display = 'block';
            // Aktiviere alle Eingabefelder in diesem Abschnitt
            Array.from(selectedFieldset.querySelectorAll('input, select, textarea')).forEach(function(element) {
                element.disabled = false;
            });
        }
    }
});

// Formular beim Laden initialisieren
document.addEventListener('DOMContentLoaded', function() {
    var event = new Event('change');
    document.getElementById('eintragstyp').dispatchEvent(event);
});

</script>
