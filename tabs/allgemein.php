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

// Marke, Modell, Baujahr, Kraftstoff und Tank- bzw. Akkugröße als eine Zeile
$fahrzeugDetails = array_filter([
    trim(($fahrzeug['brand'] ?? '') . ' ' . ($fahrzeug['model'] ?? '')),
    !empty($fahrzeug['year']) ? 'Baujahr ' . (int)$fahrzeug['year'] : '',
    $kraftstoffTexte[$fuel_type] ?? ucfirst((string)$fuel_type),
    !empty($fahrzeug['tank_capacity']) ? ($fuel_type === 'phev' ? 'Tank' : $begriffe['speicher']) . ' ' . number_format((float)$fahrzeug['tank_capacity'], fmod((float)$fahrzeug['tank_capacity'], 1) ? 1 : 0, ',', '.') . ' ' . $einheitKurz : '',
    // Plug-in-Hybrid: Akku zusätzlich zum Tank
    !empty($fahrzeug['battery_capacity']) && $fuel_type === 'phev' ? 'Akku ' . number_format((float)$fahrzeug['battery_capacity'], fmod((float)$fahrzeug['battery_capacity'], 1) ? 1 : 0, ',', '.') . ' kWh' : '',
]);
// Plug-in-Hybrid mit Tank- und Ladevorgängen: Verbrauch je Einheit statt einer Kachel für Verbrauch und Preis
$verbrauchJeEinheit = count($stats['units'] ?? []) > 1 ? $stats['units'] : [];
uksort($verbrauchJeEinheit, fn($a, $b) => array_search($a, ['l', 'kwh', 'kg']) <=> array_search($b, ['l', 'kwh', 'kg']));

// Termine: dringendste zuerst, danach Reifen (ohne Fälligkeit)
$statusReihenfolge = ['ueberfaellig' => 0, 'bald' => 1, 'ok' => 2, 'unbekannt' => 3];
usort($faelligkeiten, fn($a, $b) => [$statusReihenfolge[$a['status']] ?? 9, $a['tage'] ?? PHP_INT_MAX] <=> [$statusReihenfolge[$b['status']] ?? 9, $b['tage'] ?? PHP_INT_MAX]);
$letzteEintraege = ['Wartung' => $letzteWartung, 'TUV' => $letzterTUV];
$aktuell = $trend['current'] ?? null;
$diff = $trend['diff'] ?? null;
$eintragUrl = '../pages/eintrag_hinzufuegen.php?fahrzeug_id=' . urlencode($fahrzeug_id);
?>

<div class="mt-3 mt-lg-4">
    <div class="row g-4">
        <div class="col-md-4">
            <img src="<?= htmlspecialchars(fahrzeugBildUrl($fahrzeug, $baseUrl)) ?>" alt="Fahrzeugbild" class="img-fluid rounded-4 w-100 object-fit-cover" style="aspect-ratio: 16 / 9;">
        </div>
        <div class="col-md-8">
            <?php if ($fahrzeugDetails): ?>
                <p class="text-body-secondary small mb-3"><?= htmlspecialchars(implode(' · ', $fahrzeugDetails)) ?></p>
            <?php endif; ?>

            <div class="row g-2">
                <div class="col-6 col-lg-3">
                    <div class="kennzahl">
                        <div class="small text-body-secondary">Tachostand</div>
                        <div class="kennzahl-wert"><?= $currentOdo !== null ? number_format($currentOdo, 0, ',', '.') . ' km' : '—' ?></div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="kennzahl">
                        <div class="small text-body-secondary">Fahrleistung</div>
                        <div class="kennzahl-wert"><?= $fahrleistung !== null ? number_format($fahrleistung['km'], 0, ',', '.') . ' km' : '—' ?></div>
                        <?php if ($fahrleistung !== null): ?><div class="small text-body-secondary"><?= htmlspecialchars($fahrleistung['basis']) ?></div><?php endif; ?>
                    </div>
                </div>
                <?php foreach ($verbrauchJeEinheit as $u => $w):
                    // Durchschnitt statt letztem Abschnitt: beim Plug-in-Hybrid schwankt der Wert je nach elektrischem Anteil stark
                    $wSchnitt = $w['avgConsumption'] ?? null;
                    $wAktuell = $w['trend']['current'] ?? null;
                    $wZusatz = array_filter([
                        $wAktuell !== null ? 'zuletzt ' . number_format($wAktuell, 2, ',', '.') : '',
                        $w['avgPrice'] !== null ? 'Ø ' . number_format((float)$w['avgPrice'], 3, ',', '.') . ' €/' . einheitKurz($u) : '',
                    ]); ?>
                <div class="col-6 col-lg-3">
                    <div class="kennzahl">
                        <div class="small text-body-secondary">Ø Verbrauch <?= begriffeFuerEinheit($fuel_type, $u)['energie'] ?></div>
                        <div class="kennzahl-wert"><?= $wSchnitt !== null ? number_format($wSchnitt, 2, ',', '.') . ' <small class="fw-normal">' . einheitKurz($u) . '/100 km</small>' : '—' ?></div>
                        <?php if ($wZusatz): ?><div class="small text-body-secondary"><?= implode(' · ', $wZusatz) ?></div><?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if (!$verbrauchJeEinheit): ?>
                <div class="col-6 col-lg-3">
                    <div class="kennzahl">
                        <div class="small text-body-secondary">Verbrauch</div>
                        <div class="kennzahl-wert"><?= $aktuell !== null ? number_format($aktuell, 2, ',', '.') . ' <small class="fw-normal">' . $einheitKurz . '/100 km</small>' : '—' ?></div>
                        <?php if ($aktuell !== null && $diff !== null && abs($diff) >= 0.01): ?>
                            <div class="small text-<?= $diff > 0 ? 'danger' : 'success' ?>"><?= ($diff > 0 ? '+' : '') . number_format($diff, 2, ',', '.') ?> ggü. Ø</div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="kennzahl">
                        <div class="small text-body-secondary">Ø Preis/<?= $einheitKurz ?></div>
                        <div class="kennzahl-wert"><?= $avgPrice !== null ? number_format($avgPrice, 3, ',', '.') . ' €' : '—' ?></div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Termine: Fälligkeiten und letzte Wartung, TÜV und Reifen in einer Karte -->
            <div class="card border-0 shadow-sm mt-4">
                <div class="card-header bg-transparent d-flex justify-content-between align-items-center py-3">
                    <h3 class="h5 mb-0">Termine</h3>
                    <a href="<?= htmlspecialchars($eintragUrl) ?>&kategorie=Wartung" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-plus me-1"></i> Erfassen
                    </a>
                </div>
                <ul class="list-group list-group-flush">
                    <?php foreach ($faelligkeiten as $f):
                        $farbe = ['ueberfaellig' => 'danger', 'bald' => 'warning', 'ok' => 'success', 'unbekannt' => 'secondary'][$f['status']] ?? 'secondary';
                        $text = ['ueberfaellig' => 'überfällig', 'bald' => 'bald fällig', 'ok' => 'ok', 'unbekannt' => 'offen'][$f['status']] ?? $f['status'];
                        $letzter = $letzteEintraege[$f['kategorie']] ?? null;
                    ?>
                    <li class="list-group-item d-flex align-items-center gap-3 py-3">
                        <i class="fas <?= getCategoryIcon($f['kategorie']) ?> fa-fw text-body-secondary"></i>
                        <div class="flex-grow-1 min-w-0">
                            <div class="fw-semibold"><?= htmlspecialchars($f['titel']) ?></div>
                            <div class="small text-body-secondary">
                                <?php if ($f['faellig_datum']): ?>
                                    fällig am <?= date('d.m.Y', strtotime($f['faellig_datum'])) ?><?php if ($f['faellig_km']): ?> oder bei <?= number_format($f['faellig_km'], 0, ',', '.') ?> km<?php endif; ?>
                                    <br>zuletzt <?= date('d.m.Y', strtotime($f['letzte'])) ?><?php if ($letzter && !empty($letzter['tachostand'])): ?> bei <?= number_format((int)$letzter['tachostand'], 0, ',', '.') ?> km<?php endif; ?>
                                    <?php if ($letzter && !empty($letzter['beschreibung'])): ?> · <?= htmlspecialchars($letzter['beschreibung']) ?><?php endif; ?>
                                <?php else: ?>
                                    <a href="<?= htmlspecialchars($eintragUrl) ?>&kategorie=<?= urlencode($f['kategorie']) ?>">Letzten Termin erfassen</a>, um erinnert zu werden
                                <?php endif; ?>
                            </div>
                        </div>
                        <span class="badge text-bg-<?= $farbe ?>"><?= $text ?></span>
                    </li>
                    <?php endforeach; ?>
                    <li class="list-group-item d-flex align-items-center gap-3 py-3">
                        <i class="fas <?= getCategoryIcon('Reifen') ?> fa-fw text-body-secondary"></i>
                        <div class="flex-grow-1 min-w-0">
                            <div class="fw-semibold"><?= getKategorieName('Reifen') ?></div>
                            <div class="small text-body-secondary">
                                <?php if ($letzterReifen): ?>
                                    letzter Wechsel am <?= date('d.m.Y', strtotime($letzterReifen['datum'])) ?><?php if (!empty($letzterReifen['tachostand'])): ?> bei <?= number_format((int)$letzterReifen['tachostand'], 0, ',', '.') ?> km<?php endif; ?>
                                    <?php if (!empty($letzterReifen['beschreibung'])): ?> · <?= htmlspecialchars($letzterReifen['beschreibung']) ?><?php endif; ?>
                                <?php else: ?>
                                    <a href="<?= htmlspecialchars($eintragUrl) ?>&kategorie=Reifen">Reifenwechsel erfassen</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </li>
                </ul>
            </div>

            <div class="d-flex gap-2 mt-4">
                <a href="../pages/fahrzeug_bearbeiten.php?id=<?= htmlspecialchars(urlencode($fahrzeug_id)) ?>" class="btn btn-primary">
                    <i class="fas fa-edit"></i> Bearbeiten
                </a>
                <form method="post" action="../pages/fahrzeug_loeschen.php" onsubmit="return confirm('Möchtest du dieses Fahrzeug wirklich löschen? Alle zugehörigen Einträge werden ebenfalls entfernt!');">
                    <input type="hidden" name="fahrzeug_id" value="<?= htmlspecialchars($fahrzeug_id) ?>">
                    <button type="submit" class="btn btn-outline-danger">
                        <i class="fas fa-trash-alt"></i> Löschen
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
