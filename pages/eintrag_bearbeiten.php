<?php
include '../includes/session.php';
include '../includes/db_connect.php';
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
$tachostand = $eintrag['odometer_km'] ?? 0;
$menge = $eintrag['amount'] ?? 0;
$kosten = $eintrag['total_cost_eur'] ?? 0;
$preis_pro_einheit = $eintrag['price_per_unit'] ?? ($menge > 0 ? $kosten / $menge : 0);
$vollgetankt = !empty($eintrag['is_full']);
$skip_previous = !empty($eintrag['skip_previous']);
$standort = $eintrag['station'] ?? '';
$einheit = $eintrag['unit'] ?? 'l';
$bearbeitbar = isset($eintrag['filled_at']) && strtotime($eintrag['filled_at']) >= time() - 14 * 24 * 3600;
?>

<div class="container mt-5">
    <h2><?= $begriffe['vorgang'] ?> bearbeiten</h2>
    <?php if (!$bearbeitbar): ?>
        <div class="alert alert-info mt-3">
            Dieser Eintrag ist älter als 14 Tage und kann nicht mehr geändert werden.
            Bei Bedarf kannst du ihn löschen und neu anlegen.
        </div>
    <?php endif; ?>
    <form method="post" action="eintrag_update.php" class="mt-4">
        <input type="hidden" name="id" value="<?= htmlspecialchars($eintrag_id) ?>">
        <input type="hidden" name="fahrzeug_id" value="<?= htmlspecialchars($fahrzeug_id) ?>">
        <input type="hidden" name="type" value="fillup">
        <?php if ($istPhev): ?>
        <!-- Plug-in-Hybrid: falsch zugeordneten Eintrag (Tanken statt Laden) korrigieren -->
        <div class="mb-3">
            <div class="btn-group w-100" role="group" aria-label="Getankt oder geladen">
                <input type="radio" class="btn-check" name="einheit" id="einheit_l" value="l" <?= $einheit !== 'kwh' ? 'checked' : '' ?>>
                <label class="btn btn-outline-primary" for="einheit_l"><i class="fas fa-gas-pump me-1"></i> Getankt (Liter)</label>
                <input type="radio" class="btn-check" name="einheit" id="einheit_kwh" value="kwh" <?= $einheit === 'kwh' ? 'checked' : '' ?>>
                <label class="btn btn-outline-success" for="einheit_kwh"><i class="fas fa-charging-station me-1"></i> Geladen (kWh)</label>
            </div>
        </div>
        <?php endif; ?>
        <div class="mb-3">
            <label class="form-label">Datum</label>
            <input type="date" class="form-control" name="datum" value="<?= htmlspecialchars($datum) ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Tachostand (km)</label>
            <input type="number" class="form-control" name="tachostand" value="<?= (int)$tachostand ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label"><?= $begriffe['station'] ?></label>
            <input type="text" class="form-control" name="standort" value="<?= htmlspecialchars($standort) ?>">
        </div>
        <div class="mb-3">
            <label class="form-label">Menge (<?= einheitLang($einheit) ?>)</label>
            <input type="number" step="0.01" id="menge" name="menge" class="form-control" value="<?= (float)$menge ?>" min="0">
        </div>
        <div class="mb-3">
            <label class="form-label">Preis pro <?= einheitLang($einheit) ?> (€)</label>
            <input type="number" step="0.001" id="preis_pro_einheit" name="preis_pro_einheit" class="form-control" value="<?= (float)$preis_pro_einheit ?>" min="0">
        </div>
        <div class="mb-3">
            <label class="form-label">Gesamtpreis (€)</label>
            <input type="number" step="0.01" id="kosten" name="kosten" class="form-control" value="<?= (float)$kosten ?>" min="0">
        </div>
        <div class="form-check mb-3">
            <input type="checkbox" class="form-check-input" name="vollgetankt" value="1" <?= $vollgetankt ? 'checked' : '' ?>>
            <label class="form-check-label"><?= $begriffe['voll'] ?></label>
        </div>
        <div class="mb-3 form-check">
            <input type="checkbox" id="skip_previous" name="skip_previous" class="form-check-input" value="1" <?= $skip_previous ? 'checked' : '' ?>>
            <label for="skip_previous" class="form-check-label"><?= $begriffe['luecke'] ?></label>
        </div>
        <div class="d-flex mt-4">
            <button type="submit" class="btn btn-primary me-2" <?= $bearbeitbar ? '' : 'disabled' ?>>Speichern</button>
            <a href="fahrzeug_detail.php?id=<?= htmlspecialchars(urlencode($fahrzeug_id)) ?>" class="btn btn-secondary me-auto">Abbrechen</a>
        </div>
    </form>
    <form method="post" action="eintrag_loeschen.php" onsubmit="return confirm('Möchtest du diesen Eintrag wirklich löschen?');" class="mt-3">
        <input type="hidden" name="id" value="<?= htmlspecialchars($eintrag_id) ?>">
        <input type="hidden" name="fahrzeug_id" value="<?= htmlspecialchars($fahrzeug_id) ?>">
        <input type="hidden" name="type" value="fillup">
        <button type="submit" class="btn btn-danger"><?= $begriffe['vorgang'] ?> löschen</button>
    </form>
</div>

<?php
$tachoAusnahmeId = $eintrag_id;
include '../includes/tacho_pruefung.php';
include '../includes/footer.php'; ?>
