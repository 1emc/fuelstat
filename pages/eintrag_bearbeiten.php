<?php
// pages/eintrag_bearbeiten.php
session_start();
include '../includes/db_connect.php';
include '../includes/functions.php';
include '../includes/header.php';

// Parameter prüfen
if (!isset($_GET['id'], $_GET['fahrzeug_id'])) {
    die("Eintrag-ID oder Fahrzeug-ID fehlt.");
}
$eintrag_id    = intval($_GET['id']);
$fahrzeug_id   = intval($_GET['fahrzeug_id']);

// Eintrag laden
$stmt = $conn->prepare("SELECT * FROM eintraege WHERE id = ? AND fahrzeug_id = ?");
$stmt->bind_param("ii", $eintrag_id, $fahrzeug_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    die("Eintrag nicht gefunden.");
}
$eintrag = $result->fetch_assoc();
$stmt->close();
?>

<div class="container mt-5">
    <h2>Eintrag bearbeiten</h2>
    <form method="post" action="eintrag_update.php" class="mt-4">
        <input type="hidden" name="id" value="<?php echo $eintrag_id; ?>">
        <input type="hidden" name="fahrzeug_id" value="<?php echo $fahrzeug_id; ?>">
        <!-- Kategorie (readonly) -->
        <div class="mb-3">
            <label for="entryType" class="form-label">Eintragstyp</label>
            <input type="text" class="form-control" name="kategorie" value="<?php echo htmlspecialchars($eintrag['kategorie']); ?>" readonly>
        </div>

        <!-- Gemeinsame Felder -->
        <div class="mb-3">
            <label class="form-label">Datum</label>
            <input type="date" class="form-control" name="datum" value="<?php echo $eintrag['datum']; ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Tachostand (km)</label>
            <input type="number" class="form-control" name="tachostand" value="<?php echo $eintrag['tachostand']; ?>" required>
        </div>

        <?php if ($eintrag['kategorie'] === 'Tankfuellung'): ?>
            <!-- Tankfüllungs-Felder -->
            <div class="mb-3">
                <label class="form-label">Kraftstoff</label>
                <select name="kraftstoff" class="form-select" required>
                    <option value="diesel"   <?php if($eintrag['kraftstoff']==='diesel')   echo 'selected'; ?>>Diesel</option>
                    <option value="e5"      <?php if($eintrag['kraftstoff']==='e5')      echo 'selected'; ?>>Benzin (E5)</option>
                    <option value="e10"     <?php if($eintrag['kraftstoff']==='e10')     echo 'selected'; ?>>Benzin (E10)</option>
                    <option value="lpg"     <?php if($eintrag['kraftstoff']==='lpg')     echo 'selected'; ?>>Autogas (LPG)</option>
                    <option value="cng"     <?php if($eintrag['kraftstoff']==='cng')     echo 'selected'; ?>>Erdgas (CNG)</option>
                    <option value="electric"<?php if($eintrag['kraftstoff']==='electric')echo 'selected'; ?>>Elektro</option>
                    <option value="hybrid"  <?php if($eintrag['kraftstoff']==='hybrid')  echo 'selected'; ?>>Hybrid</option>
                    <option value="hydrogen"<?php if($eintrag['kraftstoff']==='hydrogen')echo 'selected'; ?>>Wasserstoff</option>
                    <option value="other"   <?php if($eintrag['kraftstoff']==='other')   echo 'selected'; ?>>Andere</option>
                </select>
            </div>
			<!-- Menge (Liter) -->
			<div class="mb-3">
			  <label for="menge" class="form-label">Menge (Liter)</label>
			  <div class="input-group">
				  <input type="number"
                         value="<?php echo $eintrag['menge']; ?>"
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
                         value="<?php echo $eintrag['preis_pro_einheit']; ?>"
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
                         value="<?php echo $eintrag['kosten']; ?>"
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
            <div class="form-check mb-3">
                <input type="checkbox" class="form-check-input" name="vollgetankt" value="1" <?php echo $eintrag['vollgetankt'] ? 'checked' : ''; ?>>
                <label class="form-check-label">Vollgetankt</label>
            </div>
            <div class="mb-3 form-check">
                <input type="checkbox" id="skip_previous" name="skip_previous" class="form-check-input" value="1">
                <label for="skip_previous" class="form-check-label">Vorherige Tankfüllungen ignorieren</label>
            </div>
        <?php elseif ($eintrag['kategorie'] === 'Fahrt'): ?>
            <!-- Fahrt-Felder -->
            <div class="mb-3">
                <label class="form-label">Startort</label>
                <input type="text" class="form-control" name="startort" value="<?php echo htmlspecialchars($eintrag['startort']); ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Zielort</label>
                <input type="text" class="form-control" name="zielort" value="<?php echo htmlspecialchars($eintrag['zielort']); ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Zweck</label>
                <input type="text" class="form-control" name="zweck" value="<?php echo htmlspecialchars($eintrag['zweck']); ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">Gefahrene Kilometer</label>
                <input type="number" step="0.1" class="form-control" name="gefahrene_km" value="<?php echo $eintrag['gefahrene_km']; ?>" required>
            </div>
        <?php else: ?>
            <!-- Alle anderen Kategorien: Kosten und Beschreibung -->
            <div class="mb-3">
                <label class="form-label">Kategorie</label>
                <input type="text" class="form-control" value="<?php echo htmlspecialchars($eintrag['kategorie']); ?>" readonly>
            </div>
            <div class="mb-3">
                <label class="form-label">Kosten (€)</label>
                <input type="number" step="0.01" class="form-control" name="kosten" value="<?php echo $eintrag['kosten']; ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Beschreibung</label>
                <textarea class="form-control" name="beschreibung"><?php echo htmlspecialchars($eintrag['beschreibung']); ?></textarea>
            </div>
        <?php endif; ?>

        <div class="d-flex mt-4">
            <button type="submit" class="btn btn-primary me-2">Speichern</button>
            <a href="fahrzeug_detail.php?id=<?php echo $fahrzeug_id; ?>" class="btn btn-secondary me-auto">Abbrechen</a>
        </div>
    </form>

    <!-- Lösch-Formular mit Bestätigung -->
    <form method="post" action="eintrag_loeschen.php" onsubmit="return confirm('Möchtest du diesen Eintrag wirklich löschen?');" class="mt-3">
        <input type="hidden" name="id" value="<?php echo $eintrag_id; ?>">
        <input type="hidden" name="fahrzeug_id" value="<?php echo $fahrzeug_id; ?>">
        <button type="submit" class="btn btn-danger">Eintrag löschen</button>
    </form>
</div>

<script>
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

// Heute-Button
document.getElementById('btnHeute').addEventListener('click', () => {
    const heute = new Date().toISOString().split('T')[0]; // YYYY-MM-DD
    document.getElementById('datum').value = heute;
});
</script>

<?php include '../includes/footer.php'; ?>
