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
$aktuellerTacho = getAktuellenTachostand($fahrzeug_id);   // kann null sein

$allCategories = [
    'Versicherung', 'Werkstatt', 'Inspektion', 'Steuer',
    'Reparatur', 'Reifen', 'TUV', 'Wartung', 'Dekor'
];

$standortOptionen = [];

$stmt = $conn->prepare("
    SELECT DISTINCT standort
      FROM eintraege
     WHERE fahrzeug_id = ?
       AND standort <> ''
       AND standort IS NOT NULL
  ORDER BY datum DESC
     LIMIT 5
");
$stmt->bind_param("i", $fahrzeug_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $standortOptionen[] = htmlspecialchars($row['standort']);
}
$stmt->close();
?>
<style>
    /* wirkt nur innerhalb dieser Seite */
    .form-control::placeholder {
        color: #bfc1c4;     /* helles Grau */
        opacity: 1;         /* Safari / Firefox: volles Deckungsmaß */
    }
</style>

<div class="container mt-5">
    <h2>Neuen Eintrag hinzufügen</h2>
    <form method="post" action="eintrag_speichern.php">
        <input type="hidden" name="fahrzeug_id" value="<?php echo $fahrzeug_id; ?>">

        <!-- Typ-Auswahl -->
        <div class="mb-3">
            <label for="entryType" class="form-label">Eintragstyp</label>
            <select id="entryType" name="eintragstyp" class="form-select" required>
                <option value="" disabled>Bitte auswählen</option>
                <option value="Tankfuellung" selected>Tankfuellung</option>
                <option value="Andere Ausgabe">Andere Ausgabe</option>
                <option value="Fahrt">Fahrt</option>
            </select>
        </div>

        <!-- Gemeinsame Felder -->
        <div id="fields_common" style="display:none;">
            <div class="mb-3">
                <label for="datum" class="form-label">Datum</label>
                <div class="input-group">
					<input type="date" id="datum" name="datum" class="form-control" required>
					<button type="button" id="btnHeute" class="btn btn-outline-secondary">Heute</button>
				</div>
            </div>
            <div class="mb-3">
                <label for="tachostand" class="form-label">Tachostand (km)</label>
				<input  type="number"
						id="tachostand"
						name="tachostand"
						class="form-control"
						min="0" step="1" required
						inputmode="numeric" pattern="[0-9]*"
						placeholder="<?= $aktuellerTacho !== null
										 ? number_format($aktuellerTacho, 0, ',', '.')
										 : '' ?>">
            </div>
        </div>

        <!-- Tankfuellung -->
        <div id="fields_tank" style="display:none;">
            <div class="mb-3">
                <label for="standort" class="form-label">Standort</label>
                <input type="text" list="stationen" placeholder="z. B. Aral München Nord" autocomplete="address-level2" autocapitalize="words" enterkeyhint="done" id="standort" name="standort" class="form-control">

				<datalist id="stationen">
				<?php foreach ($standortOptionen as $opt): ?>
					<option value="<?= $opt ?>">
				<?php endforeach; ?>
				</datalist>
				
            </div>
			<!-- Menge (Liter) -->
			<div class="mb-3">
			  <label for="menge" class="form-label">Menge (Liter)</label>
			  <div class="input-group">
				  <input type="number"
						 inputmode="decimal"
						 id="menge"
						 name="menge"
						 class="form-control"
						 min="0" step="0.01">
				  <button type="button"
						  class="btn btn-outline-success"
						  id="btnUseMenge"
						  title="Wert übernehmen">
					  <i class="fas fa-circle-check"></i>
				  </button>
			  </div>
			</div>

			<!-- Preis pro Liter (€) -->
			<div class="mb-3">
			  <label for="preis_pro_einheit" class="form-label">Preis pro Liter (€)</label>
			  <div class="input-group">
				  <input type="number"
						 inputmode="decimal"
						 id="preis_pro_einheit"
						 name="preis_pro_einheit"
						 class="form-control"
						 min="0" step="0.001">
				  <button type="button"
						  class="btn btn-outline-success"
						  id="btnUsePreis"
						  title="Wert übernehmen">
					  <i class="fas fa-circle-check"></i>
				  </button>
			  </div>
			</div>

			<!-- Gesamtpreis (€) -->
			<div class="mb-3">
			  <label for="kosten" class="form-label">Gesamtpreis (€)</label>
			  <div class="input-group">
				  <input type="number"
						 inputmode="decimal"
						 id="kosten"
						 name="kosten"
						 class="form-control"
						 min="0" step="0.01">
				  <button type="button"
						  class="btn btn-outline-success"
						  id="btnUseKosten"
						  title="Wert übernehmen">
					  <i class="fas fa-circle-check"></i>
				  </button>
			  </div>
			  <small class="form-text text-muted" id="calcHint"></small>
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

document.getElementById('btnHeute').addEventListener('click', () => {
    const heute = new Date().toISOString().split('T')[0]; // YYYY-MM-DD
    document.getElementById('datum').value = heute;
});

// Helper – Zahl oder null
const val = id => {
    const n = parseFloat(document.getElementById(id).value.replace(',', '.'));
    return isFinite(n) ? n : null;
};

// Zugriffskarte auf Felder + Buttons
const F = {
    menge  : {input:'menge',  btn:'btnUseMenge',  step:0.01,  hint:'Menge automatisch berechnet.'},
    preis  : {input:'preis_pro_einheit', btn:'btnUsePreis', step:0.001, hint:'Preis pro Liter automatisch berechnet.'},
    kosten : {input:'kosten', btn:'btnUseKosten', step:0.01,  hint:'Gesamtpreis automatisch berechnet.'}
};

// Vorschlag in placeholder setzen und Button aktivieren
function suggest(key, value, hintText) {
    const {input, btn} = F[key];
    const inp = document.getElementById(input);
    inp.placeholder = value;
    document.getElementById(btn).disabled = false;
    document.getElementById('calcHint').textContent = hintText;
}

// Alle Placeholder + Buttons zurücksetzen
function resetSuggestions() {
    Object.values(F).forEach(({input, btn}) => {
        const inp = document.getElementById(input);
        inp.placeholder = '';
        document.getElementById(btn).disabled = true;   // Button sperren
    });
    document.getElementById('calcHint').textContent = '';
}

// Übernahme-Buttons
Object.values(F).forEach(({input, btn}) => {
    const button = document.getElementById(btn);
    button.disabled = true;                               // initial gesperrt
    button.addEventListener('click', () => {
        const inp = document.getElementById(input);
        if (inp.placeholder) {
            inp.value = inp.placeholder;
            resetSuggestions();                           // nach Übernahme alles zurücksetzen
            recalc();                                     // prüfen, ob neue Ableitungen möglich sind
        }
    });
});

// Hauptberechnung
function recalc() {
    resetSuggestions();

    const menge  = val('menge');
    const preis  = val('preis_pro_einheit');
    const kosten = val('kosten');

    // 1) Menge + Preis → Kosten
    if (menge !== null && preis !== null && kosten === null) {
        suggest('kosten', (menge * preis).toFixed(2), F.kosten.hint);
    }
    // 2) Menge + Kosten → Preis
    else if (menge !== null && kosten !== null && preis === null) {
        suggest('preis', (kosten / menge).toFixed(3), F.preis.hint);
    }
    // 3) Preis + Kosten → Menge
    else if (preis !== null && kosten !== null && menge === null) {
        suggest('menge', (kosten / preis).toFixed(2), F.menge.hint);
    }
}

// Listener auf Eingabefelder
['menge','preis_pro_einheit','kosten'].forEach(id =>
    document.getElementById(id).addEventListener('input', recalc)
);



typeSelect.addEventListener('change', toggleFields);
window.addEventListener('DOMContentLoaded', () => toggleFields());
</script>

<?php include '../includes/footer.php'; ?>