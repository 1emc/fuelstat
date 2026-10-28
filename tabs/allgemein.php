<?php
include_once __DIR__ . '/../includes/db_connect.php';
include_once __DIR__ . '/../includes/functions.php';
include_once __DIR__ . '/../includes/api_helpers.php';

$stats = getVehicleStatsForDisplay($api, $fahrzeug_id);
$currentOdo = $stats['currentOdo'] ?? null;
$trend = $stats['trend'] ?? null;
$avgPrice = $stats['avgPricePerLiter'] ?? null;
$fahrleistung = getFahrleistungFromApi($api, $fahrzeug_id, $fahrzeug, $currentOdo);
$faelligkeiten = getFaelligkeitenFromApi($api, $fahrzeug_id);

$letzterReifen = getLetzterEintragApi($api, $fahrzeug_id, 'Reifen');
$letzteWartung = getLetzterEintragApi($api, $fahrzeug_id, 'Wartung');
$letzterTUV = getLetzterEintragApi($api, $fahrzeug_id, 'TUV');

$fuel_type = $fahrzeug['fuel_type'] ?? $fahrzeug['kraftstoff'] ?? '';
$kraftstoffTexte = getKraftstoffOptionen() + ['petrol' => 'Benzin'];

// Marke, Modell, Baujahr und Tankgröße als eine Zeile
$fahrzeugDetails = array_filter([
    trim(($fahrzeug['brand'] ?? '') . ' ' . ($fahrzeug['model'] ?? '')),
    !empty($fahrzeug['year']) ? 'Baujahr ' . (int)$fahrzeug['year'] : '',
    !empty($fahrzeug['tank_capacity']) ? 'Tank ' . number_format((float)$fahrzeug['tank_capacity'], 0, ',', '.') . ' ' . $einheitKurz : '',
]);
?>

<div class="mt-4">
    <div class="row">
        <div class="col-md-4">
            <img src="<?= htmlspecialchars(fahrzeugBildUrl($fahrzeug, $baseUrl)) ?>" alt="Fahrzeugbild" class="img-fluid rounded">
        </div>
        <div class="col-md-8">
            <h2><?= htmlspecialchars($fahrzeug['name'] ?? trim($fahrzeug['marke'] . ' ' . $fahrzeug['modell'])) ?></h2>
            <?php if ($fahrzeugDetails): ?>
                <p class="text-muted"><?= htmlspecialchars(implode(' · ', $fahrzeugDetails)) ?></p>
            <?php endif; ?>
            <p><strong>Tachostand:</strong>
                <?= $currentOdo !== null ? number_format($currentOdo, 0, ',', '.') . ' km' : 'Keine Daten' ?>
            </p>
            <p><strong>Fahrleistung:</strong>
                <?php if ($fahrleistung !== null): ?>
                    <?= number_format($fahrleistung['km'], 0, ',', '.') ?> km
                    <span class="text-muted">(<?= htmlspecialchars($fahrleistung['basis']) ?>)</span>
                <?php else: ?>
                    —
                <?php endif; ?>
            </p>

            <h3>Kraftstoffverbrauch</h3>
            <p><strong>Kraftstoff:</strong> <?= isset($kraftstoffTexte[$fuel_type]) ? $kraftstoffTexte[$fuel_type] : ucfirst($fuel_type) ?></p>
            <p><strong>Aktuell:</strong>
                <?php
                $aktuell = $trend['current'] ?? null;
                $diff = $trend['diff'] ?? null;
                if ($aktuell !== null) {
                    echo number_format($aktuell, 2, ',', '.') . ' ' . $einheitKurz . '/100km';
                    if ($diff !== null && abs($diff) >= 0.01) {
                        $sign = $diff > 0 ? '+' : '';
                        echo ' <span class="text-muted">(' . $sign . number_format($diff, 2, ',', '.') . ' ggü. Ø)</span>';
                    }
                } else {
                    echo 'Keine Daten';
                }
                ?>
            </p>
            <?php if ($avgPrice !== null): ?>
            <p><strong>Ø Preis/Einheit:</strong> <?= number_format($avgPrice, 3, ',', '.') ?> €</p>
            <?php endif; ?>

            <h3>Wartungshistorie</h3>
            <div class="d-flex justify-content-between align-items-center mt-2 mb-2">
                <span></span>
                <a href="../pages/eintrag_hinzufuegen.php?fahrzeug_id=<?= htmlspecialchars(urlencode($fahrzeug_id)) ?>&kategorie=Wartung" class="btn btn-outline-primary btn-sm">
                    <i class="fas fa-plus"></i> Eintrag hinzufügen
                </a>
            </div>
            <div class="row">
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title">
                                <i class="fas <?= getCategoryIcon('Reifen') ?> text-primary me-2"></i>
                                <?= getKategorieName('Reifen') ?>
                            </h5>
                            <?php if ($letzterReifen): ?>
                                <p class="card-text">
                                    <strong>Letzter Wechsel:</strong><br>
                                    <span class="text-muted">
                                        <?= date('d.m.Y', strtotime($letzterReifen['datum'])) ?><br>
                                        bei <?= number_format((int)$letzterReifen['tachostand'], 0, ',', '.') ?> km
                                    </span>
                                </p>
                                <?php if (!empty($letzterReifen['beschreibung'])): ?>
                                    <p class="card-text"><small class="text-muted"><?= htmlspecialchars($letzterReifen['beschreibung']) ?></small></p>
                                <?php endif; ?>
                            <?php else: ?>
                                <p class="card-text text-muted">Keine Reifenwechsel erfasst</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title">
                                <i class="fas <?= getCategoryIcon('Wartung') ?> text-primary me-2"></i>
                                Kundendienst
                            </h5>
                            <?php if ($letzteWartung): ?>
                                <p class="card-text">
                                    <strong>Letzte Wartung:</strong><br>
                                    <span class="text-muted">
                                        <?= date('d.m.Y', strtotime($letzteWartung['datum'])) ?><br>
                                        bei <?= number_format((int)$letzteWartung['tachostand'], 0, ',', '.') ?> km
                                    </span>
                                </p>
                                <?php if (!empty($letzteWartung['beschreibung'])): ?>
                                    <p class="card-text"><small class="text-muted"><?= htmlspecialchars($letzteWartung['beschreibung']) ?></small></p>
                                <?php endif; ?>
                            <?php else: ?>
                                <p class="card-text text-muted">Keine Wartungen erfasst</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title">
                                <i class="fas <?= getCategoryIcon('TUV') ?> text-primary me-2"></i>
                                <?= getKategorieName('TUV') ?>
                            </h5>
                            <?php if ($letzterTUV): ?>
                                <p class="card-text">
                                    <strong>Letzter TÜV:</strong><br>
                                    <span class="text-muted">
                                        <?= date('d.m.Y', strtotime($letzterTUV['datum'])) ?><br>
                                        bei <?= number_format((int)$letzterTUV['tachostand'], 0, ',', '.') ?> km
                                    </span>
                                </p>
                                <?php if (!empty($letzterTUV['beschreibung'])): ?>
                                    <p class="card-text"><small class="text-muted"><?= htmlspecialchars($letzterTUV['beschreibung']) ?></small></p>
                                <?php endif; ?>
                            <?php else: ?>
                                <p class="card-text text-muted">Keine TÜV-Einträge erfasst</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <h3 class="mt-4">Fälligkeiten</h3>
            <ul class="list-group mb-2">
                <?php foreach ($faelligkeiten as $f):
                    $farbe = ['ueberfaellig' => 'danger', 'bald' => 'warning', 'ok' => 'success', 'unbekannt' => 'secondary'][$f['status']];
                    $text = ['ueberfaellig' => 'überfällig', 'bald' => 'bald fällig', 'ok' => 'ok', 'unbekannt' => 'kein Eintrag'][$f['status']];
                ?>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <div>
                        <strong><?= htmlspecialchars($f['titel']) ?></strong><br>
                        <small class="text-muted">
                            <?php if ($f['faellig_datum']): ?>
                                fällig am <?= date('d.m.Y', strtotime($f['faellig_datum'])) ?>
                                <?php if ($f['faellig_km']): ?> oder bei <?= number_format($f['faellig_km'], 0, ',', '.') ?> km<?php endif; ?>
                                · zuletzt <?= date('d.m.Y', strtotime($f['letzte'])) ?>
                            <?php else: ?>
                                <a href="../pages/eintrag_hinzufuegen.php?fahrzeug_id=<?= htmlspecialchars(urlencode($fahrzeug_id)) ?>&kategorie=<?= urlencode($f['kategorie']) ?>">Letzten Termin erfassen</a>, um eine Erinnerung zu bekommen
                            <?php endif; ?>
                        </small>
                    </div>
                    <span class="badge text-bg-<?= $farbe ?>"><?= $text ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <div class="d-flex gap-2 mt-3">
        <a href="../pages/fahrzeug_bearbeiten.php?id=<?= htmlspecialchars(urlencode($fahrzeug_id)) ?>" class="btn btn-primary">
            <i class="fas fa-edit"></i> Bearbeiten
        </a>
        <form method="post" action="../pages/fahrzeug_loeschen.php" onsubmit="return confirm('Möchtest du dieses Fahrzeug wirklich löschen? Alle zugehörigen Einträge werden ebenfalls entfernt!');">
            <input type="hidden" name="fahrzeug_id" value="<?= htmlspecialchars($fahrzeug_id) ?>">
            <button type="submit" class="btn btn-danger">
                <i class="fas fa-trash-alt"></i> Löschen
            </button>
        </form>
    </div>
</div>
