<?php
// pages/eintrag_hinzufuegen.php
session_start();
include '../includes/db_connect.php';
include '../includes/functions.php';
include '../includes/header.php';

if (!isset($_GET['fahrzeug_id'])) {
    die("Fahrzeug-ID fehlt.");
}
$fahrzeug_id = intval($_GET['fahrzeug_id']);

$allCategories = [
    'Versicherung', 'Werkstatt', 'Inspektion', 'Steuer',
    'Reparatur', 'Reifen', 'TUV', 'Wartung', 'Dekor'
];
?>

<div class="container mt-5">
    <h2>Neuen Eintrag hinzufügen</h2>
    <form method="post" action="eintrag_speichern.php">
        <input type="hidden" name="fahrzeug_id" value="<?php echo $fahrzeug_id; ?>">

        <!-- Typ-Auswahl -->
        <div class="mb-3">
            <label for="entryType" class="form-label">Eintragstyp</label>
            <select id="entryType" name="eintragstyp" class="form-select" required>
                <option value="">Bitte auswählen</option>
                <option value="Tankfuellung">Tankfuellung</option>
                <option value="Andere Ausgabe">Andere Ausgabe</option>
                <option value="Fahrt">Fahrt</option>
            </select>
        </div>

        <!-- Gemeinsame Felder -->
        <div id="fields_common" style="display:none;">
            <div class="mb-3">
                <label for="datum" class="form-label">Datum</label>
                <input type="date" id="datum" name="datum" class="form-control" required>
            </div>
            <div class="mb-3">
                <label for="tachostand" class="form-label">Tachostand (km)</label>
                <input type="number" id="tachostand" name="tachostand" class="form-control" min="0" step="1" required>
            </div>
        </div>

        <!-- Tankfuellung -->
        <div id="fields_tank" style="display:none;">
            <div class="mb-3">
                <label for="standort" class="form-label">Standort</label>
                <input type="text" id="standort" name="standort" class="form-control">
            </div>
            <div class="mb-3">
                <label for="menge" class="form-label">Menge (Liter)</label>
                <input type="number" id="menge" name="menge" class="form-control" min="0" step="0.01">
            </div>
            <div class="mb-3">
                <label for="preis_pro_einheit" class="form-label">Preis pro Liter (€)</label>
                <input type="number" id="preis_pro_einheit" name="preis_pro_einheit" class="form-control" min="0" step="0.001">
            </div>
            <div class="mb-3">
                <label for="kosten" class="form-label">Gesamtpreis (€)</label>
                <input type="number" id="kosten" name="kosten" class="form-control" min="0" step="0.01">
            </div>
            <div class="mb-3 form-check">
                <input type="checkbox" id="vollgetankt" name="vollgetankt" class="form-check-input" value="1">
                <label for="vollgetankt" class="form-check-label">Vollgetankt</label>
            </div>
            <div class="mb-3 form-check">
                <input type="checkbox" id="skip_previous" name="skip_previous" class="form-check-input" value="1">
                <label for="skip_previous" class="form-check-label">Vorherige Tankfüllungen ignorieren</label>
            </div>
        </div>

        <!-- Andere Ausgabe -->
        <div id="fields_other" style="display:none;">
            <div class="mb-3">
                <label for="kategorie" class="form-label">Kategorie</label>
                <select id="kategorie" name="kategorie" class="form-select">
                    <option value="">Bitte auswählen</option>
                    <?php foreach ($allCategories as $cat): ?>
                        <option value="<?php echo $cat; ?>"><?php echo $cat; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label for="kosten_other" class="form-label">Kosten (€)</label>
                <input type="number" id="kosten_other" name="kosten" class="form-control" min="0" step="0.01">
            </div>
            <div class="mb-3">
                <label for="beschreibung" class="form-label">Beschreibung</label>
                <textarea id="beschreibung" name="beschreibung" class="form-control"></textarea>
            </div>
        </div>

        <!-- Fahrt -->
        <div id="fields_trip" style="display:none;">
            <div class="mb-3">
                <label for="startort" class="form-label">Startort</label>
                <input type="text" id="startort" name="startort" class="form-control">
            </div>
            <div class="mb-3">
                <label for="zielort" class="form-label">Zielort</label>
                <input type="text" id="zielort" name="zielort" class="form-control">
            </div>
            <div class="mb-3">
                <label for="zweck" class="form-label">Zweck der Fahrt</label>
                <input type="text" id="zweck" name="zweck" class="form-control">
            </div>
            <div class="mb-3">
                <label for="gefahrene_km" class="form-label">Gefahrene Kilometer</label>
                <input type="number" id="gefahrene_km" name="gefahrene_km" class="form-control" min="0" step="0.1">
            </div>
        </div>

        <button type="submit" class="btn btn-primary">Eintrag speichern</button>
        <a href="fahrzeug_detail.php?id=<?php echo $fahrzeug_id; ?>" class="btn btn-secondary ms-2">Abbrechen</a>
    </form>
</div>

<script>
const typeSelect = document.getElementById('entryType');
const commonSection = document.getElementById('fields_common');
const sections = {
    'Tankfuellung': document.getElementById('fields_tank'),
    'Andere Ausgabe': document.getElementById('fields_other'),
    'Fahrt': document.getElementById('fields_trip')
};

function toggleFields() {
    // Verstecke alle Sektionen
    commonSection.style.display = 'none';
    disableSection(commonSection);
    Object.values(sections).forEach(sec => {
        sec.style.display = 'none';
        disableSection(sec);
    });

    const val = typeSelect.value;
    if (!val) return;
    // Zeige gemeinsame Felder
    commonSection.style.display = 'block';
    enableSection(commonSection);
    // Zeige spezifische Felder
    const sec = sections[val];
    if (sec) {
        sec.style.display = 'block';
        enableSection(sec);
    }
}

function disableSection(section) {
    section.querySelectorAll('input, select, textarea').forEach(el => el.disabled = true);
}
function enableSection(section) {
    section.querySelectorAll('input, select, textarea').forEach(el => el.disabled = false);
}

typeSelect.addEventListener('change', toggleFields);
window.addEventListener('DOMContentLoaded', () => toggleFields());
</script>
