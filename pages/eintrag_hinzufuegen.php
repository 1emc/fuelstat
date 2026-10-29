<?php
include '../includes/session.php';
include '../includes/db_connect.php';
include '../includes/functions.php';
include '../includes/header.php';

if (!isset($_GET['fahrzeug_id'])) {
    die("Fahrzeug-ID fehlt.");
}
$fahrzeug_id = trim($_GET['fahrzeug_id']);

$aktuellerTacho = null;
$letztePreise = [];
$fahrzeugKraftstoff = 'diesel';
$einheit = 'l';
$standortOptionen = [];
try {
    $vehicle = $api->getVehicle($fahrzeug_id);
    $fahrzeugKraftstoff = $vehicle['fuel_type'] ?? 'diesel';
    $einheit = getEinheitFuerKraftstoff($fahrzeugKraftstoff);
    if ($fahrzeugKraftstoff === 'petrol') $fahrzeugKraftstoff = 'e10';
    $stats = $api->getVehicleStats($fahrzeug_id);
    $aktuellerTacho = $stats['currentOdo'] ?? null;
    // Bisherige Orte (neueste zuerst) als Vorschläge, beim E-Auto mit dem zuletzt bezahlten kWh-Preis je Ort
    $fillups = $api->getAllVehicleFillups($fahrzeug_id);
    usort($fillups, fn($a, $b) => strcmp((string)($b['filled_at'] ?? ''), (string)($a['filled_at'] ?? '')));
    foreach ($fillups as $f) {
        $ort = trim((string)($f['station'] ?? ''));
        if ($ort === '' || isset($letztePreise[$ort])) {
            continue;
        }
        if (count($standortOptionen) < 15) {
            $standortOptionen[] = htmlspecialchars($ort);
        }
        // Plug-in-Hybrid: nur Strompreise merken, Spritpreise ändern sich täglich
        if ($fahrzeugKraftstoff === 'phev' && ($f['unit'] ?? 'l') !== 'kwh') {
            continue;
        }
        $preis = (float)($f['price_per_unit'] ?? 0) ?: ((float)($f['amount'] ?? 0) > 0 ? (float)($f['total_cost_eur'] ?? 0) / (float)$f['amount'] : 0);
        $letztePreise[$ort] = $preis > 0 ? round($preis, 3) : null;
    }
} catch (Throwable $e) {
}
// Begriffe passend zum Fahrzeug (Tankfüllung/Ladevorgang, Tankstelle/Ladestation …)
$begriffe = verbrauchsBegriffe($vehicle['fuel_type'] ?? 'other');
// Stationssuche: Tankerkönig für Kraftstoff und Gas, Open Charge Map für E-Autos
$stationsSuche = true;
$stationsArt = $begriffe['art'] === 'strom' ? 'lade' : 'tank';
// Plug-in-Hybrid: vor dem Erfassen wählen, ob getankt oder geladen wurde; Texte wechseln mit
$istPhev = $fahrzeugKraftstoff === 'phev';
$texteJeEinheit = [];
foreach (einheitenFuerKraftstoff($fahrzeugKraftstoff) as $u) {
    $b = begriffeFuerEinheit($fahrzeugKraftstoff, $u);
    $texteJeEinheit[$u] = [
        'menge' => 'Menge (' . einheitLang($u) . ')',
        'preis' => 'Preis pro ' . einheitLang($u) . ' (€)',
        'preisHinweis' => 'Preis pro ' . einheitLang($u) . ' automatisch berechnet.',
        'voll' => $b['voll'],
        'luecke' => $b['luecke'],
        'beispiel' => $b['beispiel_ort'],
        'stationen' => $b['stationen'] . ' in der Nähe suchen',
        'stationsArt' => $u === 'kwh' ? 'lade' : 'tank',
    ];
}
$startEinheit = $istPhev && ($_GET['einheit'] ?? '') === 'kwh' ? 'kwh' : $einheit;
if ($istPhev) {
    $stationsArt = $texteJeEinheit[$startEinheit]['stationsArt'];
    $begriffeStart = begriffeFuerEinheit($fahrzeugKraftstoff, $startEinheit);
    $begriffe['voll'] = $begriffeStart['voll'];
    $begriffe['luecke'] = $begriffeStart['luecke'];
    $begriffe['beispiel_ort'] = $begriffeStart['beispiel_ort'];
    $begriffe['stationen'] = $begriffeStart['stationen'];
    $einheit = $startEinheit;
}

$vorgegebeneKategorie = isset($_GET['kategorie']) ? $_GET['kategorie'] : null;
$allCategories = [
    'Versicherung', 'Werkstatt', 'Inspektion', 'Steuer',
    'Reparatur', 'Reifen', 'TUV', 'Wartung', 'Dekor'
];
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
    <form method="post" action="eintrag_speichern.php" class="mt-4">
        <input type="hidden" name="fahrzeug_id" value="<?= htmlspecialchars($fahrzeug_id) ?>">

        <!-- Typ-Auswahl -->
        <div class="mb-3">
            <label for="entryType" class="form-label">Eintragstyp</label>
            <select id="entryType" name="eintragstyp" class="form-select" required>
                <option value="" disabled>Bitte auswählen</option>
                <option value="Tankfuellung" <?php echo $vorgegebeneKategorie ? '' : 'selected'; ?>><?= $begriffe['vorgang'] ?></option>
                <option value="Andere Ausgabe" <?php echo $vorgegebeneKategorie ? 'selected' : ''; ?>>Andere Ausgabe</option>
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
            <?php if ($istPhev): ?>
            <!-- Plug-in-Hybrid: getankt oder geladen? -->
            <div class="mb-3">
                <div class="btn-group w-100" role="group" aria-label="Getankt oder geladen">
                    <input type="radio" class="btn-check" name="einheit" id="einheit_l" value="l" <?= $startEinheit === 'l' ? 'checked' : '' ?>>
                    <label class="btn btn-outline-primary" for="einheit_l"><i class="fas fa-gas-pump me-1"></i> Tanken</label>
                    <input type="radio" class="btn-check" name="einheit" id="einheit_kwh" value="kwh" <?= $startEinheit === 'kwh' ? 'checked' : '' ?>>
                    <label class="btn btn-outline-success" for="einheit_kwh"><i class="fas fa-charging-station me-1"></i> Laden</label>
                </div>
            </div>
            <div class="mb-3" id="kraftstoffSorte" <?= $startEinheit === 'kwh' ? 'hidden' : '' ?>>
                <label class="form-label" for="kraftstoffAuswahl">Kraftstoff</label>
                <select name="kraftstoff" id="kraftstoffAuswahl" class="form-select">
                    <option value="e5">Benzin (E5)</option>
                    <option value="e10">Benzin (E10)</option>
                </select>
            </div>
            <?php elseif ($begriffe['art'] !== 'fluessig'): ?>
            <!-- Strom bzw. Gas: keine Sortenauswahl nötig -->
            <input type="hidden" name="kraftstoff" value="<?= htmlspecialchars($fahrzeugKraftstoff) ?>">
            <?php else: ?>
            <div class="mb-3">
                <label class="form-label"><?= $begriffe['energie'] ?></label>
                <select name="kraftstoff" class="form-select" required>
                    <option value="diesel"   <?php if($fahrzeugKraftstoff==='diesel')   echo 'selected'; ?>>Diesel</option>
                    <option value="e5"      <?php if($fahrzeugKraftstoff==='e5')      echo 'selected'; ?>>Benzin (E5)</option>
                    <option value="e10"     <?php if($fahrzeugKraftstoff==='e10')     echo 'selected'; ?>>Benzin (E10)</option>
                    <option value="lpg"     <?php if($fahrzeugKraftstoff==='lpg')     echo 'selected'; ?>>Autogas (LPG)</option>
                    <option value="cng"     <?php if($fahrzeugKraftstoff==='cng')     echo 'selected'; ?>>Erdgas (CNG)</option>
                    <option value="electric"<?php if($fahrzeugKraftstoff==='electric')echo 'selected'; ?>>Elektro</option>
                    <option value="hybrid"  <?php if($fahrzeugKraftstoff==='hybrid')  echo 'selected'; ?>>Hybrid</option>
                    <option value="hydrogen"<?php if($fahrzeugKraftstoff==='hydrogen')echo 'selected'; ?>>Wasserstoff</option>
                    <option value="other"   <?php if($fahrzeugKraftstoff==='other')   echo 'selected'; ?>>Andere</option>
                </select>
            </div>
            <?php endif; ?>
            <div class="mb-3">
                <label for="standort" class="form-label">Standort</label>
                <div class="input-group">
                    <input type="text" list="stationen" placeholder="<?= htmlspecialchars($begriffe['beispiel_ort']) ?>" autocomplete="address-level2" autocapitalize="words" enterkeyhint="done" id="standort" name="standort" class="form-control">
                    <?php if ($stationsSuche): ?>
                    <button type="button" class="btn btn-outline-primary" id="btnTankstellenSuche" title="<?= $begriffe['stationen'] ?> in der Nähe suchen" aria-label="<?= $begriffe['stationen'] ?> in der Nähe suchen">
                        <i class="fas fa-map-marker-alt"></i>
                    </button>
                    <?php endif; ?>
                </div>
                <datalist id="stationen">
                <?php foreach ($standortOptionen as $opt): ?>
                    <option value="<?= $opt ?>">
                <?php endforeach; ?>
                </datalist>
                <div id="tankstellenVorschlaege" class="mt-2"></div>
            </div>
			<!-- Menge (Liter/kWh/kg) -->
			<div class="mb-3">
			  <label for="menge" class="form-label" id="mengeLabel">Menge (<?= einheitLang($einheit) ?>)</label>
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

			<!-- Preis pro Einheit (€) -->
			<div class="mb-3">
			  <label for="preis_pro_einheit" class="form-label" id="preisLabel">Preis pro <?= einheitLang($einheit) ?> (€)</label>
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
                <label for="vollgetankt" class="form-check-label" id="vollLabel"><?= $begriffe['voll'] ?></label>
            </div>
            <div class="mb-3 form-check">
                <input type="checkbox" id="skip_previous" name="skip_previous" class="form-check-input" value="1">
                <label for="skip_previous" class="form-check-label" id="lueckeLabel"><?= $begriffe['luecke'] ?></label>
            </div>
        </div>

        <!-- Andere Ausgabe -->
        <div id="fields_other" style="display:none;">
            <div class="mb-3">
                <label for="kategorie" class="form-label">Kategorie</label>
                <select id="kategorie" name="kategorie" class="form-select">
                    <option value="">Bitte auswählen</option>
                    <?php foreach ($allCategories as $cat): ?>
                        <option value="<?php echo $cat; ?>" <?php echo ($vorgegebeneKategorie === $cat) ? 'selected' : ''; ?>><?php echo getKategorieName($cat); ?></option>
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
        <a href="fahrzeug_detail.php?id=<?= htmlspecialchars(urlencode($fahrzeug_id)) ?>" class="btn btn-secondary ms-2">Abbrechen</a>
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

// PHP-Variable für JS bereitstellen
const fahrzeugKraftstoff = <?php echo json_encode($fahrzeugKraftstoff); ?>;
let stationsArt = <?= json_encode($stationsArt) ?>;
// Zuletzt bezahlter Strompreis je Ort (E-Auto und Plug-in-Hybrid: ändert sich selten, an der Wallbox praktisch nie)
const letztePreise = <?= json_encode($stationsArt === 'lade' || $istPhev ? $letztePreise : new stdClass(), JSON_UNESCAPED_UNICODE) ?>;
const texteJeEinheit = <?= json_encode($texteJeEinheit, JSON_UNESCAPED_UNICODE) ?>;

// Wenn eine Kategorie vorgegeben ist, zeige direkt die "Andere Ausgabe" Felder
<?php if ($vorgegebeneKategorie): ?>
window.addEventListener('DOMContentLoaded', () => {
    toggleFields();
    document.getElementById('fields_common').style.display = 'block';
    document.getElementById('fields_other').style.display = 'block';
    enableSection(document.getElementById('fields_common'));
    enableSection(document.getElementById('fields_other'));
});
<?php else: ?>
window.addEventListener('DOMContentLoaded', () => toggleFields());
<?php endif; ?>

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
    preis  : {input:'preis_pro_einheit', btn:'btnUsePreis', step:0.001, hint:<?= json_encode('Preis pro ' . einheitLang($einheit) . ' automatisch berechnet.', JSON_UNESCAPED_UNICODE) ?>},
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

// Tankstellen-Suche (nur bei Tankfuellung sichtbar)
function toggleTankstellenButton() {
    const show = typeSelect.value === 'Tankfuellung';
    const btn = document.getElementById('btnTankstellenSuche');
    const vorschlaege = document.getElementById('tankstellenVorschlaege');
    if (btn) btn.style.display = show ? '' : 'none';
    if (vorschlaege) vorschlaege.style.display = show ? '' : 'none';
}
typeSelect.addEventListener('change', toggleTankstellenButton);
window.addEventListener('DOMContentLoaded', toggleTankstellenButton);

// Hilfsfunktion: aktuellen Kraftstofftyp auslesen
function getSelectedKraftstoff() {
    const select = document.querySelector('select[name="kraftstoff"]');
    return select ? select.value : fahrzeugKraftstoff;
}

// Hilfsfunktion: Erlaubte Typen für Preisabfrage
function isPreisTyp(type) {
    return ['e5', 'e10', 'diesel'].includes(type);
}

// Stationssuche: Standort per GPS, sonst PLZ/Ort über OpenStreetMap; Treffer als Liste zum Anklicken.
// Alle Texte (auch die von Tankerkönig und Open Charge Map) werden mit textContent gesetzt, nie als HTML.
const btnSuche = document.getElementById('btnTankstellenSuche');
const vorschlaege = document.getElementById('tankstellenVorschlaege');

function zeigeHinweis(art, text, zusatz) {
    vorschlaege.replaceChildren();
    const box = document.createElement('div');
    box.className = 'alert alert-' + art;
    box.textContent = text;
    if (zusatz) {
        box.append(document.createElement('br'));
        const klein = document.createElement('small');
        klein.textContent = zusatz;
        box.append(klein);
    }
    vorschlaege.append(box);
}

function zeigeLaden(text) {
    vorschlaege.replaceChildren();
    const spinner = document.createElement('span');
    spinner.className = 'spinner-border spinner-border-sm me-1';
    vorschlaege.append(spinner, document.createTextNode(text));
}

function sucheBeenden() {
    btnSuche.disabled = false;
    btnSuche.innerHTML = '<i class="fas fa-map-marker-alt"></i>';
}

function zeigeTankstellen(stations, mitPreis, ortHinweis) {
    vorschlaege.replaceChildren();
    if (!stations || stations.length === 0) {
        zeigeHinweis('warning', 'Keine Tankstellen gefunden.', ortHinweis);
        return;
    }
    const liste = document.createElement('ul');
    liste.className = 'list-group';
    stations.forEach(station => {
        const name = String(station.name || '').trim();
        const marke = String(station.brand || '').trim();
        const ort = station.place ? ' (' + station.place + ')' : '';
        const eintrag = (marke && marke === name ? marke + ort : (marke + ort + ' ' + name)).trim();
        const preis = mitPreis && typeof station.price === 'number' ? station.price.toFixed(3) : null;

        const li = document.createElement('li');
        li.className = 'list-group-item list-group-item-action';
        li.style.cursor = 'pointer';
        li.textContent = (marke ? marke + ' – ' : '') + name + ' – ' + (preis ? preis + ' €/L' : 'Preis n/a');
        li.addEventListener('click', () => {
            document.getElementById('standort').value = eintrag;
            if (preis) {
                document.getElementById('preis_pro_einheit').value = preis;
            }
            setTimeout(() => vorschlaege.replaceChildren(), 2000);
        });
        liste.append(li);
    });
    vorschlaege.append(liste);
}

function sucheTankstellen(lat, lng, radius, ortHinweis) {
    let type = getSelectedKraftstoff();
    const mitPreis = isPreisTyp(type);
    if (!mitPreis) type = 'all';
    zeigeLaden('Tankstellen werden gesucht …');
    const params = new URLSearchParams({ lat, lng, radius, type });
    fetch('../api/tankstellen.php?' + params)
        .then(response => response.json().catch(() => ({})).then(data => {
            if (!response.ok) throw new Error(data.error || ('Status ' + response.status));
            return data;
        }))
        .then(data => zeigeTankstellen(data.stations, mitPreis, ortHinweis))
        .catch(err => zeigeHinweis('danger', 'Fehler bei der Tankstellenabfrage.', err.message))
        .finally(sucheBeenden);
}

// Plug-in-Hybrid: Beschriftungen, Stationssuche und Kraftstoffauswahl je nach Tanken oder Laden
document.querySelectorAll('input[name="einheit"]').forEach(radio => radio.addEventListener('change', () => {
    const t = texteJeEinheit[radio.value];
    if (!radio.checked || !t) return;
    document.getElementById('mengeLabel').textContent = t.menge;
    document.getElementById('preisLabel').textContent = t.preis;
    document.getElementById('vollLabel').textContent = t.voll;
    document.getElementById('lueckeLabel').textContent = t.luecke;
    document.getElementById('standort').placeholder = t.beispiel;
    F.preis.hint = t.preisHinweis;
    stationsArt = t.stationsArt;
    if (btnSuche) { btnSuche.title = t.stationen; btnSuche.setAttribute('aria-label', t.stationen); }
    document.getElementById('kraftstoffSorte').hidden = radio.value === 'kwh';
    vorschlaege.replaceChildren();
}));

// Preis vom letzten Ladevorgang an diesem Ort übernehmen, wenn noch keiner eingetragen ist
function preisAusHistorie(ort) {
    if (stationsArt !== 'lade') return;
    const preis = letztePreise[String(ort || '').trim()];
    const feld = document.getElementById('preis_pro_einheit');
    if (!preis || !feld || feld.value !== '') return;
    feld.value = preis.toFixed(3);
    feld.dispatchEvent(new Event('input', { bubbles: true }));
    const hinweis = document.getElementById('calcHint');
    hinweis.textContent = (hinweis.textContent ? hinweis.textContent + ' ' : '') + 'Preis vom letzten Laden hier übernommen.';
}
document.getElementById('standort').addEventListener('change', e => preisAusHistorie(e.target.value));

function zeigeLadestationen(stations, ortHinweis) {
    vorschlaege.replaceChildren();
    if (!stations || stations.length === 0) {
        zeigeHinweis('warning', 'Keine Ladestationen gefunden.', ortHinweis);
        return;
    }
    const liste = document.createElement('ul');
    liste.className = 'list-group';
    stations.forEach(station => {
        const li = document.createElement('li');
        li.className = 'list-group-item list-group-item-action' + (station.in_betrieb ? '' : ' text-body-secondary');
        li.style.cursor = 'pointer';
        const titel = document.createElement('div');
        titel.className = 'fw-semibold';
        // Betreiber nur nennen, wenn er nicht schon im Namen steckt ("Aldi Süd Bopfingen")
        const betreiber = station.betreiber && !station.name.toLowerCase().includes(station.betreiber.toLowerCase()) ? station.betreiber : '';
        titel.textContent = station.name + (betreiber ? ' – ' + betreiber : '');
        const details = document.createElement('div');
        details.className = 'small text-body-secondary';
        details.textContent = [
            station.entfernung !== null ? String(station.entfernung).replace('.', ',') + ' km' : '',
            station.max_kw ? 'bis ' + String(station.max_kw).replace('.', ',') + ' kW' : '',
            (station.stecker || []).join(', '),
            station.punkte ? station.punkte + ' Ladepunkte' : '',
            station.in_betrieb ? '' : 'außer Betrieb',
        ].filter(Boolean).join(' · ');
        li.append(titel, details);
        if (station.kosten) {
            const kosten = document.createElement('div');
            kosten.className = 'small';
            kosten.textContent = 'Kosten laut Betreiber: ' + station.kosten;
            li.append(kosten);
        }
        li.addEventListener('click', () => {
            const ort = station.ort && !station.name.includes(station.ort) ? station.name + ' (' + station.ort + ')' : station.name;
            document.getElementById('standort').value = ort;
            preisAusHistorie(ort);
            setTimeout(() => vorschlaege.replaceChildren(), 2000);
        });
        liste.append(li);
    });
    const quelle = document.createElement('div');
    quelle.className = 'small text-body-secondary mt-1';
    quelle.textContent = 'Daten: Open Charge Map (CC BY 4.0)';
    vorschlaege.append(liste, quelle);
}

function sucheLadestationen(lat, lng, radius, ortHinweis) {
    zeigeLaden('Ladestationen werden gesucht …');
    const params = new URLSearchParams({ lat, lng, radius });
    fetch('../api/ladestationen.php?' + params)
        .then(response => response.json().catch(() => ({})).then(data => {
            if (!response.ok) throw new Error(data.error || ('Status ' + response.status));
            return data;
        }))
        .then(data => zeigeLadestationen(data.stations, ortHinweis))
        .catch(err => zeigeHinweis('danger', 'Fehler bei der Ladestationssuche.', err.message))
        .finally(sucheBeenden);
}

// Je nach Antriebsart Tankstellen (Tankerkönig) oder Ladestationen (Open Charge Map) suchen
function sucheStationen(lat, lng, radius, ortHinweis) {
    return stationsArt === 'lade' ? sucheLadestationen(lat, lng, radius, ortHinweis) : sucheTankstellen(lat, lng, radius, ortHinweis);
}

function sucheUeberOrt(frage) {
    const ort = prompt(frage);
    if (!ort) {
        sucheBeenden();
        return;
    }
    zeigeLaden('Standort wird gesucht …');
    fetch('https://nominatim.openstreetmap.org/search?format=json&countrycodes=de&addressdetails=1&q=' + encodeURIComponent(ort))
        .then(res => res.json())
        .then(geo => {
            if (!geo.length) {
                zeigeHinweis('warning', 'Ort nicht gefunden.', 'PLZ/Ort: ' + ort);
                sucheBeenden();
                return;
            }
            sucheStationen(geo[0].lat, geo[0].lon, 15, 'PLZ/Ort: ' + ort);
        })
        .catch(() => {
            zeigeHinweis('danger', 'Ort konnte nicht gesucht werden.');
            sucheBeenden();
        });
}

if (btnSuche) btnSuche.addEventListener('click', function () {
    this.disabled = true;
    this.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    if (!navigator.geolocation) {
        sucheUeberOrt('Standortermittlung nicht möglich. Bitte Postleitzahl (PLZ) oder Ort eingeben:');
        return;
    }
    navigator.geolocation.getCurrentPosition(
        position => sucheStationen(position.coords.latitude, position.coords.longitude, 5, null),
        () => sucheUeberOrt('Standort konnte nicht ermittelt werden. Bitte Postleitzahl (PLZ) oder Ort eingeben:')
    );
});
</script>

<?php
$tachoAnschaffung = $vehicle['odometer_km'] ?? null;
include '../includes/tacho_pruefung.php';
include '../includes/footer.php'; ?>