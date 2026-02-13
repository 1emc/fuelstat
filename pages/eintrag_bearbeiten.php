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
    $_SESSION['warning_message'] = 'Bearbeiten von Ausgabe-Einträgen ist über die API derzeit nicht möglich.';
    header('Location: fahrzeug_detail.php?id=' . rawurlencode($fahrzeug_id));
    exit;
}

try {
    $eintrag = $api->getFillup($eintrag_id);
} catch (Throwable $e) {
    die("Eintrag nicht gefunden.");
}

$datum = isset($eintrag['filled_at']) ? date('Y-m-d', strtotime($eintrag['filled_at'])) : '';
$tachostand = $eintrag['odometer_km'] ?? 0;
$menge = $eintrag['amount'] ?? 0;
$kosten = $eintrag['total_cost_eur'] ?? 0;
$preis_pro_einheit = $eintrag['price_per_unit'] ?? ($menge > 0 ? $kosten / $menge : 0);
$vollgetankt = !empty($eintrag['is_full']);
$skip_previous = !empty($eintrag['skip_previous']);
$standort = $eintrag['station'] ?? '';
?>

<div class="container mt-5">
    <h2>Tankvorgang bearbeiten</h2>
    <form method="post" action="eintrag_update.php" class="mt-4">
        <input type="hidden" name="id" value="<?= htmlspecialchars($eintrag_id) ?>">
        <input type="hidden" name="fahrzeug_id" value="<?= htmlspecialchars($fahrzeug_id) ?>">
        <input type="hidden" name="type" value="fillup">
        <div class="mb-3">
            <label class="form-label">Datum</label>
            <input type="date" class="form-control" name="datum" value="<?= htmlspecialchars($datum) ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Tachostand (km)</label>
            <input type="number" class="form-control" name="tachostand" value="<?= (int)$tachostand ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Tankstelle</label>
            <input type="text" class="form-control" name="standort" value="<?= htmlspecialchars($standort) ?>">
        </div>
        <div class="mb-3">
            <label class="form-label">Menge (Liter)</label>
            <input type="number" step="0.01" id="menge" name="menge" class="form-control" value="<?= (float)$menge ?>" min="0">
        </div>
        <div class="mb-3">
            <label class="form-label">Preis pro Liter (€)</label>
            <input type="number" step="0.001" id="preis_pro_einheit" name="preis_pro_einheit" class="form-control" value="<?= (float)$preis_pro_einheit ?>" min="0">
        </div>
        <div class="mb-3">
            <label class="form-label">Gesamtpreis (€)</label>
            <input type="number" step="0.01" id="kosten" name="kosten" class="form-control" value="<?= (float)$kosten ?>" min="0">
        </div>
        <div class="form-check mb-3">
            <input type="checkbox" class="form-check-input" name="vollgetankt" value="1" <?= $vollgetankt ? 'checked' : '' ?>>
            <label class="form-check-label">Vollgetankt</label>
        </div>
        <div class="mb-3 form-check">
            <input type="checkbox" id="skip_previous" name="skip_previous" class="form-check-input" value="1" <?= $skip_previous ? 'checked' : '' ?>>
            <label for="skip_previous" class="form-check-label">Vorherige Tankfüllung ignorieren</label>
        </div>
        <div class="d-flex mt-4">
            <button type="submit" class="btn btn-primary me-2">Speichern</button>
            <a href="fahrzeug_detail.php?id=<?= htmlspecialchars(urlencode($fahrzeug_id)) ?>" class="btn btn-secondary me-auto">Abbrechen</a>
        </div>
    </form>
    <form method="post" action="eintrag_loeschen.php" onsubmit="return confirm('Möchtest du diesen Tankvorgang wirklich löschen?');" class="mt-3">
        <input type="hidden" name="id" value="<?= htmlspecialchars($eintrag_id) ?>">
        <input type="hidden" name="fahrzeug_id" value="<?= htmlspecialchars($fahrzeug_id) ?>">
        <input type="hidden" name="type" value="fillup">
        <button type="submit" class="btn btn-danger">Tankvorgang löschen</button>
    </form>
</div>

<?php include '../includes/footer.php'; ?>
