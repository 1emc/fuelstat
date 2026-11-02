<?php
include '../includes/session.php';
include '../includes/functions.php';
include '../includes/header.php';

if (!isset($_GET['id'], $_GET['fahrzeug_id'])) {
    die("Eintrag-ID oder Fahrzeug-ID fehlt.");
}
$eintrag_id = trim($_GET['id']);
$fahrzeug_id = trim($_GET['fahrzeug_id']);
$type = $_GET['type'] ?? 'fillup';

if ($type === 'entry') {
    try {
        $eintrag = $api->getEntry($eintrag_id);
    } catch (Throwable $e) {
        die("Eintrag nicht gefunden.");
    }
    $kategorien = ['Versicherung', 'Werkstatt', 'Inspektion', 'Steuer', 'Reparatur', 'Reifen', 'TUV', 'Wartung', 'Dekor', 'Fahrt'];
    $kategorie = $eintrag['category'] ?? '';
    if ($kategorie !== '' && !in_array($kategorie, $kategorien, true)) {
        $kategorien[] = $kategorie;
    }
    $datum = isset($eintrag['occurred_at']) ? date('Y-m-d', strtotime($eintrag['occurred_at'])) : '';
    ?>
<div class="container mt-5">
    <h2>Ausgabe bearbeiten</h2>
    <form method="post" action="eintrag_update.php" class="mt-4">
        <input type="hidden" name="id" value="<?= htmlspecialchars($eintrag_id) ?>">
        <input type="hidden" name="fahrzeug_id" value="<?= htmlspecialchars($fahrzeug_id) ?>">
        <input type="hidden" name="type" value="entry">
        <div class="mb-3">
            <label class="form-label" for="kategorie">Kategorie</label>
            <select class="form-select" id="kategorie" name="kategorie" required>
                <?php foreach ($kategorien as $k): ?>
                    <option value="<?= htmlspecialchars($k) ?>" <?= $k === $kategorie ? 'selected' : '' ?>><?= htmlspecialchars(getKategorieName($k)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label" for="datum">Datum</label>
            <input type="date" class="form-control" id="datum" name="datum" value="<?= htmlspecialchars($datum) ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label" for="tachostand">Tachostand (km)</label>
            <input type="number" class="form-control" id="tachostand" name="tachostand" value="<?= htmlspecialchars((string)($eintrag['odometer_km'] ?? '')) ?>" min="0">
        </div>
        <div class="mb-3">
            <label class="form-label" for="kosten">Kosten (€)</label>
            <input type="number" step="0.01" class="form-control" id="kosten" name="kosten" value="<?= (float)($eintrag['amount_eur'] ?? 0) ?>" min="0" required>
        </div>
        <div class="mb-3">
            <label class="form-label" for="standort">Anbieter / Ort</label>
            <input type="text" class="form-control" id="standort" name="standort" value="<?= htmlspecialchars($eintrag['vendor'] ?? '') ?>">
        </div>
        <div class="mb-3">
            <label class="form-label" for="beschreibung">Beschreibung</label>
            <textarea class="form-control" id="beschreibung" name="beschreibung"><?= htmlspecialchars($eintrag['description'] ?? '') ?></textarea>
        </div>
        <div class="d-flex mt-4">
            <button type="submit" class="btn btn-primary me-2">Speichern</button>
            <a href="fahrzeug_detail.php?id=<?= htmlspecialchars(urlencode($fahrzeug_id)) ?>" class="btn btn-secondary me-auto">Abbrechen</a>
        </div>
    </form>
    <form method="post" action="eintrag_loeschen.php" onsubmit="return confirm('Möchtest du diese Ausgabe wirklich löschen?');" class="mt-3">
        <input type="hidden" name="id" value="<?= htmlspecialchars($eintrag_id) ?>">
        <input type="hidden" name="fahrzeug_id" value="<?= htmlspecialchars($fahrzeug_id) ?>">
        <input type="hidden" name="type" value="entry">
        <button type="submit" class="btn btn-danger">Ausgabe löschen</button>
    </form>
</div>
    <?php
    $tachoAusnahmeId = $eintrag_id;
    include '../includes/tacho_pruefung.php';
    include '../includes/footer.php';
    exit;
}

try {
    $eintrag = $api->getFillup($eintrag_id);
} catch (Throwable $e) {
    die("Eintrag nicht gefunden.");
}

$datum = isset($eintrag['filled_at']) ? date('Y-m-d', strtotime($eintrag['filled_at'])) : '';
try {
    $fuelType = $api->getVehicle($fahrzeug_id)['fuel_type'] ?? 'other';
} catch (Throwable $e) {
    $fuelType = null;
}
// Plug-in-Hybrid: Begriffe nach der Einheit dieses Eintrags (Tankfüllung oder Ladevorgang)
$begriffe = begriffeFuerEinheit($fuelType, $eintrag['unit'] ?? null);
$istPhev = $fuelType === 'phev';
$tachostand = $eintrag['odometer_km'] ?? null; // null = fehlt (#41)
$tachoGeschaetzt = !empty($eintrag['odometer_estimated']);
$menge = $eintrag['amount'] ?? 0;
$kosten = $eintrag['total_cost_eur'] ?? 0;
$preis_pro_einheit = $eintrag['price_per_unit'] ?? ($menge > 0 ? $kosten / $menge : 0);
$vollgetankt = !empty($eintrag['is_full']);
$skip_previous = !empty($eintrag['skip_previous']);
$standort = $eintrag['station'] ?? '';
$einheit = $eintrag['unit'] ?? 'l';
$bearbeitbar = isset($eintrag['filled_at']) && strtotime($eintrag['filled_at']) >= time() - 14 * 24 * 3600;
?>

<div class="container eintrag-formular mt-3 mt-lg-5">
    <h1 class="h3 d-none d-lg-block mb-3" id="titel"><?= $begriffe['vorgang'] ?> bearbeiten</h1>
    <?php if (!$bearbeitbar): ?>
        <div class="alert alert-info">
            Dieser Eintrag ist älter als 14 Tage und kann nicht mehr geändert werden.
            Bei Bedarf kannst du ihn löschen und neu anlegen.
            <?php if ($tachostand === null || $tachoGeschaetzt): ?>
                Den Tachostand kannst du trotzdem <a href="tachostand_nachtragen.php?id=<?= urlencode($eintrag_id) ?>&amp;fahrzeug_id=<?= urlencode($fahrzeug_id) ?>">nachtragen</a>.
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <form method="post" action="eintrag_update.php" id="eintragFormular">
        <input type="hidden" name="id" value="<?= htmlspecialchars($eintrag_id) ?>">
        <input type="hidden" name="fahrzeug_id" value="<?= htmlspecialchars($fahrzeug_id) ?>">
        <input type="hidden" name="type" value="fillup">
        <fieldset <?= $bearbeitbar ? '' : 'disabled' ?>>
        <?php if ($istPhev): ?>
        <!-- Plug-in-Hybrid: falsch zugeordneten Eintrag (Tanken statt Laden) korrigieren; Texte wechseln live -->
        <div class="art-kacheln mb-4" role="radiogroup" aria-label="Getankt oder geladen">
            <?php foreach (['l' => 'Tanken', 'kwh' => 'Laden'] as $u => $name): ?>
                <input type="radio" class="btn-check" name="einheit" id="einheit_<?= $u ?>" value="<?= $u ?>" <?= ($einheit === 'kwh') === ($u === 'kwh') ? 'checked' : '' ?>>
                <label class="art-kachel" for="einheit_<?= $u ?>"><i class="fas <?= begriffeFuerEinheit('phev', $u)['icon'] ?>"></i><span><?= $name ?></span></label>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="row g-3 mb-3">
            <div class="col-12 col-sm-7">
                <label class="form-label fw-semibold" for="tachostand">Tachostand (km)</label>
                <input type="number" class="form-control form-control-lg" id="tachostand" name="tachostand" min="0" step="1" inputmode="numeric" pattern="[0-9]*"
                       value="<?= $tachostand !== null ? (int)$tachostand : '' ?>" placeholder="fehlt – kann leer bleiben">
                <?php if ($tachoGeschaetzt): ?>
                    <!-- Bleibt der Wert unverändert, bleibt er als Schätzung markiert -->
                    <input type="hidden" name="tachostand_geschaetzt_wert" value="<?= (int)$tachostand ?>">
                    <div class="form-text text-warning-emphasis">Geschätzter Wert – bitte durch den echten Tachostand ersetzen.</div>
                <?php endif; ?>
            </div>
            <div class="col-12 col-sm-5">
                <label class="form-label fw-semibold" for="datum">Datum</label>
                <input type="date" class="form-control form-control-lg" id="datum" name="datum" value="<?= htmlspecialchars($datum) ?>" required>
            </div>
        </div>

        <div class="row g-2 mb-3">
            <div class="col-6 col-md-4">
                <label class="form-label" for="menge" id="mengeLabel"><?= einheitLang($einheit) ?></label>
                <div class="input-group">
                    <input type="number" inputmode="decimal" step="0.01" id="menge" name="menge" class="form-control" value="<?= (float)$menge ?>" min="0">
                    <button type="button" class="btn btn-outline-secondary" id="info_menge" aria-label="Herkunft des Werts anzeigen"><i class="fas fa-circle-info"></i></button>
                </div>
            </div>
            <div class="col-6 col-md-4">
                <label class="form-label" for="preis_pro_einheit" id="preisLabel">€/<?= einheitKurz($einheit) ?></label>
                <div class="input-group">
                    <input type="number" inputmode="decimal" step="0.001" id="preis_pro_einheit" name="preis_pro_einheit" class="form-control" value="<?= round((float)$preis_pro_einheit, 3) ?>" min="0">
                    <button type="button" class="btn btn-outline-secondary" id="info_preis_pro_einheit" aria-label="Herkunft des Werts anzeigen"><i class="fas fa-circle-info"></i></button>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label" for="kosten" id="kostenLabel">Gesamt (€)</label>
                <div class="input-group">
                    <input type="number" inputmode="decimal" step="0.01" id="kosten" name="kosten" class="form-control" value="<?= (float)$kosten ?>" min="0">
                    <button type="button" class="btn btn-outline-secondary" id="info_kosten" aria-label="Herkunft des Werts anzeigen"><i class="fas fa-circle-info"></i></button>
                </div>
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label" for="standort" id="standortLabel"><?= $begriffe['station'] ?></label>
            <input type="text" class="form-control" id="standort" name="standort" value="<?= htmlspecialchars($standort) ?>">
        </div>
        <div class="form-check form-switch mb-2">
            <input type="checkbox" class="form-check-input" role="switch" id="vollgetankt" name="vollgetankt" value="1" <?= $vollgetankt ? 'checked' : '' ?>>
            <label class="form-check-label" for="vollgetankt" id="vollLabel"><?= $begriffe['voll'] ?></label>
        </div>
        <div class="form-check form-switch mb-3">
            <input type="checkbox" class="form-check-input" role="switch" id="skip_previous" name="skip_previous" value="1" <?= $skip_previous ? 'checked' : '' ?>>
            <label class="form-check-label" for="skip_previous" id="lueckeLabel"><?= $begriffe['luecke'] ?></label>
        </div>
        </fieldset>

        <div class="formular-aktionen">
            <button type="submit" class="btn btn-primary btn-lg flex-grow-1" <?= $bearbeitbar ? '' : 'disabled' ?>><i class="fas fa-check me-1"></i>Speichern</button>
            <a href="fahrzeug_detail.php?id=<?= htmlspecialchars(urlencode($fahrzeug_id)) ?>#eintraege" class="btn btn-outline-secondary btn-lg">Abbrechen</a>
        </div>
    </form>
    <form method="post" action="eintrag_loeschen.php" onsubmit="return confirm('Möchtest du diesen Eintrag wirklich löschen?');" class="mt-4 mb-4">
        <input type="hidden" name="id" value="<?= htmlspecialchars($eintrag_id) ?>">
        <input type="hidden" name="fahrzeug_id" value="<?= htmlspecialchars($fahrzeug_id) ?>">
        <input type="hidden" name="type" value="fillup">
        <button type="submit" class="btn btn-link text-danger px-0"><i class="fas fa-trash me-1"></i><span id="loeschenText"><?= $begriffe['vorgang'] ?> löschen</span></button>
    </form>
</div>

<script src="<?= htmlspecialchars(assetUrl($baseUrl, 'js/mengen-rechner.js')) ?>"></script>
<script>
// Menge × Preis = Gesamtpreis: beim Bearbeiten gelten alle drei Werte als eingetragen. Ändert man einen,
// wird der am längsten nicht geänderte neu berechnet (zuerst der Preis pro Einheit).
window.addEventListener('DOMContentLoaded', () => {
    // Texte je Einheit (Plug-in-Hybrid: Tanken oder Laden), wie beim Erfassen
    const texte = <?= json_encode(array_map(fn($u) => (function ($b) use ($u) {
        return ['lang' => einheitLang($u), 'kurz' => einheitKurz($u), 'station' => $b['station'], 'voll' => $b['voll'], 'luecke' => $b['luecke'], 'vorgang' => $b['vorgang']];
    })(begriffeFuerEinheit($fuelType, $u)), array_combine(einheitenFuerKraftstoff($fuelType), einheitenFuerKraftstoff($fuelType))), JSON_UNESCAPED_UNICODE) ?>;
    let kurz = <?= json_encode(einheitKurz($einheit)) ?>;
    const rechner = mengenRechner({ einheit: () => kurz, eingetragen: ['kosten', 'menge', 'preis'] });
    document.querySelectorAll('input[name="einheit"]').forEach(radio => radio.addEventListener('change', () => {
        const t = texte[radio.value];
        if (!radio.checked || !t) return;
        kurz = t.kurz;
        document.getElementById('mengeLabel').textContent = t.lang;
        document.getElementById('preisLabel').textContent = '€/' + t.kurz;
        document.getElementById('standortLabel').textContent = t.station;
        document.getElementById('vollLabel').textContent = t.voll;
        document.getElementById('lueckeLabel').textContent = t.luecke;
        document.getElementById('titel').textContent = t.vorgang + ' bearbeiten';
        document.getElementById('loeschenText').textContent = t.vorgang + ' löschen';
        rechner.aktualisiere();
    }));
});
</script>
<?php
$tachoAusnahmeId = $eintrag_id;
include '../includes/tacho_pruefung.php';
include '../includes/footer.php'; ?>
