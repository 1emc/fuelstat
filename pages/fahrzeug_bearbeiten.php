<?php
include '../includes/session.php';
include '../includes/db_connect.php';
include '../includes/header.php';

if (!isset($_GET['id'])) {
    die("Fahrzeug-ID fehlt.");
}
$fahrzeug_id = trim($_GET['id']);

try {
    $fahrzeug = $api->getVehicle($fahrzeug_id);
} catch (Throwable $e) {
    die("Fahrzeug nicht gefunden.");
}

$fuelType = $fahrzeug['fuel_type'] ?? 'diesel';
if ($fuelType === 'petrol') $fuelType = 'e10';
?>

<div class="container mt-5">
    <h2>Fahrzeug bearbeiten</h2>
    <form method="post" action="fahrzeug_update.php">
        <input type="hidden" name="fahrzeug_id" value="<?= htmlspecialchars($fahrzeug_id) ?>">
        <div class="mb-3">
            <label for="name" class="form-label">Name / Bezeichnung</label>
            <input type="text" id="name" name="name" class="form-control"
                   value="<?= htmlspecialchars($fahrzeug['name'] ?? '') ?>" required>
        </div>
        <div class="mb-3">
            <label for="fuelType" class="form-label">Kraftstoffart</label>
            <select id="fuelType" name="fuelType" class="form-select" required>
                <option value="diesel"   <?= $fuelType === 'diesel'   ? 'selected' : '' ?>>Diesel</option>
                <option value="e5"  <?= $fuelType === 'petrol' || $fuelType === 'e5' || $fuelType === 'e10' ? 'selected' : '' ?>>Benzin</option>
                <option value="electric"<?= $fuelType === 'electric' ? 'selected' : '' ?>>Elektro</option>
                <option value="hybrid"  <?= $fuelType === 'hybrid'   ? 'selected' : '' ?>>Hybrid</option>
                <option value="cng"     <?= $fuelType === 'cng'      ? 'selected' : '' ?>>Erdgas (CNG)</option>
                <option value="lpg"     <?= $fuelType === 'lpg'      ? 'selected' : '' ?>>Autogas (LPG)</option>
                <option value="other"   <?= $fuelType === 'other'   ? 'selected' : '' ?>>Andere</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Speichern</button>
        <a href="fahrzeug_detail.php?id=<?= htmlspecialchars(urlencode($fahrzeug_id)) ?>" class="btn btn-secondary ms-2">Abbrechen</a>
    </form>
</div>

<?php include '../includes/footer.php'; ?>
