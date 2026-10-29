<?php
include 'includes/session.php';
include 'includes/db_connect.php';
include 'includes/functions.php';
include 'includes/header.php';

// Nicht angemeldet: Willkommensseite mit Registrierung/Login
if (!isApiAuthenticated()) {
    echo '<div class="container mt-4">';
    include 'includes/willkommen.php';
    echo '</div>';
    include 'includes/footer.php';
    exit;
}

$vehicles = [];
$ladeFehler = false;
try {
    $vehicles = $api->getVehicles();
} catch (Throwable $e) {
    $ladeFehler = true;
}
?>

<style>
    .card { transition: transform .25s ease, box-shadow .25s ease; }
    .card:hover { transform: translateY(-4px); box-shadow: 0 .5rem 1rem rgba(0,0,0,.15); }
    .card .zoom-img { transition: transform .4s ease; }
    .card:hover .zoom-img { transform: scale(1.05); }
    .km-badge { font-size: .75rem; font-weight: 500; padding: .25em .5em; border-radius: .25rem; }
</style>

<div class="container mt-4">
    <h1 class="mb-4">Fahrzeugübersicht</h1>
    <?php if ($ladeFehler): ?>
        <div class="alert alert-danger">Die Fahrzeuge konnten gerade nicht geladen werden. Bitte versuche es gleich noch einmal.</div>
    <?php elseif (count($vehicles) > 0): ?>
        <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-4">
            <?php foreach (array_values($vehicles) as $index => $row):
                $vehicleId = $row['id'];
                $displayName = $row['name'] ?? 'Fahrzeug';
                $bildUrl = fahrzeugBildUrl($row, $baseUrl);

                $currentOdo = null;
                $verbrauch = null;
                try {
                    $stats = $api->getVehicleStats($vehicleId);
                    $currentOdo = $stats['currentOdo'] ?? null;
                    $trend = $stats['trend'] ?? null;
                    $verbrauch = $trend['current'] ?? null;
                } catch (Throwable $e) {
                }
                $faellig = array_filter(
                    getFaelligkeitenFromApi($api, $vehicleId),
                    fn($f) => in_array($f['status'], ['ueberfaellig', 'bald'], true)
                );
                $tooltip = $verbrauch !== null
                    ? 'Ø Verbrauch: ' . number_format($verbrauch, 2, ',', '.') . ' ' . einheitKurz(getEinheitFuerKraftstoff($row['fuel_type'] ?? null)) . '/100 km'
                    : 'Noch keine Verbrauchsdaten';
            ?>
            <div class="col">
                <div class="card h-100 shadow-sm border-0"
                     data-bs-toggle="tooltip" data-bs-placement="top" title="<?= htmlspecialchars($tooltip) ?>">
                    <div class="position-relative overflow-hidden">
                        <div class="ratio ratio-16x9">
                            <img src="<?= htmlspecialchars($bildUrl) ?>" class="w-100 h-100 object-fit-cover zoom-img" alt="<?= htmlspecialchars($displayName) ?>" loading="lazy">
                        </div>
                        <?php if ($faellig): ?>
                        <span class="position-absolute top-0 start-0 m-2 d-flex flex-column gap-1">
                            <?php foreach ($faellig as $f): ?>
                            <span class="badge km-badge text-bg-<?= $f['status'] === 'ueberfaellig' ? 'danger' : 'warning' ?>">
                                <i class="fas fa-bell me-1"></i><?= htmlspecialchars($f['titel']) ?> <?= $f['status'] === 'ueberfaellig' ? 'überfällig' : 'bald fällig' ?>
                            </span>
                            <?php endforeach; ?>
                        </span>
                        <?php endif; ?>
                        <?php if ($currentOdo !== null): ?>
                        <span class="badge bg-secondary km-badge position-absolute top-0 end-0 m-2">
                            <?= number_format($currentOdo, 0, ',', '.') ?> km
                        </span>
                        <?php endif; ?>
                    </div>
                    <div class="card-body pb-0">
                        <div class="d-flex justify-content-between align-items-start">
                            <h5 class="card-title mb-2 fw-semibold"><?= htmlspecialchars($displayName) ?></h5>
                            <?php if (count($vehicles) > 1): ?>
                            <form method="post" action="includes/fahrzeug_sortieren.php" class="btn-group btn-group-sm ms-2" aria-label="Reihenfolge ändern">
                                <input type="hidden" name="id" value="<?= htmlspecialchars($vehicleId) ?>">
                                <button type="submit" name="richtung" value="hoch" class="btn btn-outline-secondary" title="Nach vorne" <?= $index === 0 ? 'disabled' : '' ?>><i class="fas fa-arrow-left"></i></button>
                                <button type="submit" name="richtung" value="runter" class="btn btn-outline-secondary" title="Nach hinten" <?= $index === count($vehicles) - 1 ? 'disabled' : '' ?>><i class="fas fa-arrow-right"></i></button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card-footer bg-body border-0 pt-0 pb-3">
                        <div class="btn-group w-100">
                            <a href="pages/fahrzeug_detail.php?id=<?= htmlspecialchars(urlencode($vehicleId)) ?>" class="btn btn-outline-primary">
                                <i class="fas fa-info-circle me-1"></i> Details
                            </a>
                            <a href="pages/eintrag_hinzufuegen.php?fahrzeug_id=<?= htmlspecialchars(urlencode($vehicleId)) ?>" class="btn btn-primary">
                                <i class="fas fa-plus"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="card border-0 shadow-sm text-center mx-auto" style="max-width: 36rem;">
            <div class="card-body p-5">
                <i class="fas fa-car-side fa-3x mb-3"></i>
                <h2 class="h4">Noch kein Fahrzeug angelegt</h2>
                <p class="text-body-secondary mb-4">Lege dein erstes Fahrzeug an, danach kannst du Tankfüllungen, Ladevorgänge und Ausgaben erfassen.</p>
                <a href="pages/fahrzeug_hinzufuegen.php" class="btn btn-primary btn-lg px-4">
                    <i class="fas fa-plus me-2"></i>Fahrzeug hinzufügen
                </a>
                <p class="small text-body-secondary mt-4 mb-0">
                    <i class="fas fa-file-import me-1"></i>Daten aus einer anderen App oder Excel? Nach dem Anlegen unter „Einträge“ → „Import“ übernehmen.
                </p>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>

<script>
document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));
</script>
