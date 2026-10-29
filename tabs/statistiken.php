<?php
include_once __DIR__ . '/../includes/db_connect.php';
include_once __DIR__ . '/../includes/functions.php';
include_once __DIR__ . '/../includes/api_helpers.php';

if (!isset($fahrzeug_id) && isset($_GET['id'])) {
    $fahrzeug_id = $_GET['id'];
}
if (!isset($fahrzeug_id)) {
    die('Fahrzeug-ID fehlt.');
}

$d = getStatistikDatenFromApi($api, $fahrzeug_id);
$verbrauchswerte = $d['verbrauchswerte'];
$verbrauchProMonat = $d['verbrauchProMonat'];
$kostenProMonat = $d['kostenProMonat'];
$jahresdaten = $d['jahresdaten'];
// Kraftstoffkosten pro km: Kosten aller Tankfüllungen nach der ersten / gefahrene km dazwischen
$kraftstoffKostenProKm = null;
$tankRows = [];
foreach (getMergedEintraegeFromApi($api, $fahrzeug_id)['eintraege'] as $monatsEintraege) {
    foreach ($monatsEintraege as $e) {
        if (($e['kategorie'] ?? '') === 'Tankfuellung') {
            $tankRows[] = $e;
        }
    }
}
usort($tankRows, fn($a, $b) => [(int)$a['tachostand'], $a['datum']] <=> [(int)$b['tachostand'], $b['datum']]);
if (count($tankRows) >= 2) {
    $kmSpanne = (int)end($tankRows)['tachostand'] - (int)$tankRows[0]['tachostand'];
    $kostenNachErster = array_sum(array_map(fn($e) => (float)$e['kosten'], array_slice($tankRows, 1)));
    if ($kmSpanne > 0) {
        $kraftstoffKostenProKm = $kostenNachErster / $kmSpanne;
    }
}

// CO₂: getankte Menge × Emissionsfaktor; Ø pro km aus dem Gesamtverbrauch
$co2Faktor = getCo2Faktor($fahrzeug['fuel_type'] ?? null);
$co2Gesamt = null;
$co2ProKm = null;
if ($co2Faktor !== null) {
    $co2Gesamt = array_sum(array_map(fn($e) => (float)$e['menge'], $tankRows)) * $co2Faktor;
    $summeMenge = array_sum(array_column($jahresdaten, 'verbrauch_menge'));
    $summeKm = array_sum(array_column($jahresdaten, 'verbrauch_km'));
    if ($summeKm > 0) {
        $co2ProKm = $summeMenge / $summeKm * $co2Faktor * 1000; // g/km
    }
}
$durchschnittPreis = null;
try {
    $stats = $api->getVehicleStats($fahrzeug_id);
    $durchschnittPreis = $stats['avgPricePerLiter'] ?? null;
} catch (Throwable $e) {
}

$minVerbrauch = $verbrauchswerte ? min($verbrauchswerte) : null;
$maxVerbrauch = $verbrauchswerte ? max($verbrauchswerte) : null;

// Gewichteter Durchschnitt über alle Full-to-Full-Abschnitte, Summen über alle Jahre
$summeMenge = array_sum(array_column($jahresdaten, 'verbrauch_menge'));
$summeKm = array_sum(array_column($jahresdaten, 'verbrauch_km'));
$durchschnittVerbrauch = $summeKm > 0 ? $summeMenge / $summeKm * 100 : null;
$gekauftMenge = array_sum(array_column($jahresdaten, 'gekauft_menge'));
$gekauftKosten = array_sum(array_column($jahresdaten, 'gekauft_ausgaben'));

ksort($verbrauchProMonat);
ksort($kostenProMonat);
$verbrauchProMonat = array_filter($verbrauchProMonat, fn($v) => $v > 0);
$verbrauchProMonatFormatted = formatLabels($verbrauchProMonat);
$kostenProMonatFormatted = formatLabels($kostenProMonat);

// Bester, schlechtester und teuerster Monat
$highlights = [];
if ($verbrauchProMonatFormatted) {
    $wert = min($verbrauchProMonatFormatted);
    $highlights[] = ['fa-arrow-trend-down', 'success', 'Sparsamster Monat', array_search($wert, $verbrauchProMonatFormatted), number_format($wert, 2, ',', '.') . ' ' . $einheitKurz . '/100 km'];
    $wert = max($verbrauchProMonatFormatted);
    $highlights[] = ['fa-arrow-trend-up', 'danger', 'Durstigster Monat', array_search($wert, $verbrauchProMonatFormatted), number_format($wert, 2, ',', '.') . ' ' . $einheitKurz . '/100 km'];
}
if ($kostenProMonatFormatted) {
    $wert = max($kostenProMonatFormatted);
    $highlights[] = ['fa-euro-sign', 'warning', 'Teuerster Monat', array_search($wert, $kostenProMonatFormatted), number_format($wert, 2, ',', '.') . ' €'];
}

// Diagramm-Beschriftung: Datum kurz (25.11.24)
$verbrauchLabels = array_map(fn($datum) => date('d.m.y', strtotime(substr($datum, 0, 10))), array_keys($verbrauchswerte));

$co2Hinweis = ($fahrzeug['fuel_type'] ?? '') === 'electric'
    ? 'Berechnet mit dem deutschen Strommix (0,363 kg CO₂/kWh)'
    : 'Direkte Emission bei der Verbrennung (' . number_format((float)$co2Faktor, 2, ',', '.') . ' kg CO₂/' . $einheitKurz . ')';
$zahl = fn($wert, int $stellen = 2) => number_format((float)$wert, $stellen, ',', '.');
// Jahresübersicht: Jahre ohne Tankfüllung (nur andere Ausgaben) weglassen
$tankJahre = array_filter($jahresdaten, fn($d) => $d['anzahl_tankstops'] > 0 || $d['gekauft_menge'] > 0);
?>

<div class="mt-3 mt-lg-4">
    <?php if (!$jahresdaten): ?>
        <div class="card border-0 shadow-sm text-center">
            <div class="card-body p-5">
                <i class="fas fa-chart-line fa-2x mb-3"></i>
                <p class="mb-0 text-body-secondary">Noch keine Tankfüllungen erfasst. Ab zwei vollen Tankfüllungen gibt es hier Verbrauch, Kosten und Diagramme.</p>
            </div>
        </div>
    <?php else: ?>

    <!-- Kennzahlen -->
    <div class="row g-2 g-md-3 mb-3">
        <div class="col-6 col-lg-3">
            <div class="kennzahl">
                <div class="small text-body-secondary">Ø Verbrauch</div>
                <div class="kennzahl-wert"><?= $durchschnittVerbrauch !== null ? $zahl($durchschnittVerbrauch) . ' <small class="fw-normal">' . $einheitKurz . '/100 km</small>' : '—' ?></div>
                <?php if ($minVerbrauch !== null): ?><div class="small text-body-secondary"><?= $zahl($minVerbrauch, 1) ?> bis <?= $zahl($maxVerbrauch, 1) ?></div><?php endif; ?>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="kennzahl">
                <div class="small text-body-secondary">Kraftstoffkosten</div>
                <div class="kennzahl-wert"><?= $kraftstoffKostenProKm !== null ? $zahl($kraftstoffKostenProKm * 100) . ' <small class="fw-normal">€/100 km</small>' : '—' ?></div>
                <?php if ($durchschnittPreis !== null): ?><div class="small text-body-secondary">Ø <?= $zahl($durchschnittPreis, 3) ?> €/<?= $einheitKurz ?></div><?php endif; ?>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="kennzahl">
                <div class="small text-body-secondary">Getankt gesamt</div>
                <div class="kennzahl-wert"><?= $zahl($gekauftMenge, 0) ?> <small class="fw-normal"><?= $einheitKurz ?></small></div>
                <div class="small text-body-secondary">für <?= $zahl($gekauftKosten) ?> €</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="kennzahl" title="<?= htmlspecialchars($co2Hinweis) ?>">
                <div class="small text-body-secondary">CO₂ gesamt</div>
                <div class="kennzahl-wert"><?= $co2Gesamt !== null ? $zahl($co2Gesamt / 1000) . ' <small class="fw-normal">t</small>' : '—' ?></div>
                <?php if ($co2ProKm !== null): ?><div class="small text-body-secondary">Ø <?= $zahl($co2ProKm, 0) ?> g/km</div><?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($highlights): ?>
    <div class="row g-2 g-md-3 mb-3">
        <?php foreach ($highlights as [$icon, $farbe, $titel, $monat, $wert]): ?>
        <div class="col-md-4">
            <div class="kennzahl d-flex align-items-center gap-3">
                <span class="statistik-icon text-<?= $farbe ?> bg-<?= $farbe ?>-subtle"><i class="fas <?= $icon ?>"></i></span>
                <div class="min-w-0">
                    <div class="small text-body-secondary"><?= $titel ?> · <?= htmlspecialchars($monat) ?></div>
                    <div class="fw-semibold"><?= $wert ?></div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Diagramme -->
    <div class="row g-3 mb-3">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h3 class="h6 mb-3">Verbrauch pro Tankfüllung <small class="text-body-secondary fw-normal"><?= $einheitKurz ?>/100 km</small></h3>
                    <?php if ($verbrauchswerte): ?>
                        <div class="diagramm"><canvas id="verbrauchChart"></canvas></div>
                    <?php else: ?>
                        <p class="text-body-secondary small mb-0">Ab zwei vollen Tankfüllungen verfügbar.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h3 class="h6 mb-3">Verbrauch pro Monat <small class="text-body-secondary fw-normal"><?= $einheitKurz ?>/100 km</small></h3>
                    <?php if ($verbrauchProMonatFormatted): ?>
                        <div class="diagramm"><canvas id="verbrauchMonatChart"></canvas></div>
                    <?php else: ?>
                        <p class="text-body-secondary small mb-0">Ab zwei vollen Tankfüllungen verfügbar.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h3 class="h6 mb-3">Ausgaben pro Monat <small class="text-body-secondary fw-normal">€, alle Kategorien</small></h3>
                    <div class="diagramm"><canvas id="kostenMonatChart"></canvas></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Jahresübersicht: PC als Tabelle, Handy als Liste -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent py-3">
            <h3 class="h6 mb-0">Jahresübersicht</h3>
        </div>
        <div class="table-responsive d-none d-md-block">
            <table class="table table-hover align-middle mb-0 jahrestabelle">
                <thead>
                    <tr>
                        <th>Jahr</th>
                        <th class="text-end">Tankstopps</th>
                        <th class="text-end">Getankt</th>
                        <th class="text-end">Kosten</th>
                        <th class="text-end">Ø Preis</th>
                        <th class="text-end">Strecke</th>
                        <th class="text-end">Verbrauch</th>
                        <?php if ($co2Faktor !== null): ?><th class="text-end">CO₂</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tankJahre as $jahr => $daten):
                        $preis = $daten['gekauft_menge'] > 0 ? $daten['gekauft_ausgaben'] / $daten['gekauft_menge'] : null; ?>
                    <tr>
                        <th scope="row"><?= $jahr ?></th>
                        <td class="text-end"><?= (int)$daten['anzahl_tankstops'] ?></td>
                        <td class="text-end"><?= $zahl($daten['gekauft_menge']) ?> <?= $einheitKurz ?></td>
                        <td class="text-end"><?= $zahl($daten['gekauft_ausgaben']) ?> €</td>
                        <td class="text-end"><?= $preis !== null ? $zahl($preis, 3) . ' €/' . $einheitKurz : '—' ?></td>
                        <td class="text-end"><?= $daten['verbrauch_km'] > 0 ? $zahl($daten['verbrauch_km'], 0) . ' km' : '—' ?></td>
                        <td class="text-end fw-semibold"><?= $daten['verbrauch_l100km'] > 0 ? $zahl($daten['verbrauch_l100km']) . ' ' . $einheitKurz . '/100 km' : '—' ?></td>
                        <?php if ($co2Faktor !== null): ?><td class="text-end"><?= $zahl($daten['gekauft_menge'] * $co2Faktor, 0) ?> kg</td><?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="list-group list-group-flush d-md-none">
            <?php $nr = 0; foreach ($tankJahre as $jahr => $daten):
                $preis = $daten['gekauft_menge'] > 0 ? $daten['gekauft_ausgaben'] / $daten['gekauft_menge'] : null; ?>
            <?php if ($nr++ === 3): ?>
            <div class="collapse" id="weitereJahre">
            <?php endif; ?>
            <div class="list-group-item py-3">
                <div class="d-flex justify-content-between align-items-baseline mb-2">
                    <span class="fw-semibold fs-5"><?= $jahr ?></span>
                    <span class="fw-semibold"><?= $daten['verbrauch_l100km'] > 0 ? $zahl($daten['verbrauch_l100km']) . ' ' . $einheitKurz . '/100 km' : '—' ?></span>
                </div>
                <div class="row row-cols-3 g-2 small">
                    <div><div class="text-body-secondary">Tankstopps</div><?= (int)$daten['anzahl_tankstops'] ?></div>
                    <div><div class="text-body-secondary">Getankt</div><?= $zahl($daten['gekauft_menge'], 1) ?> <?= $einheitKurz ?></div>
                    <div><div class="text-body-secondary">Kosten</div><?= $zahl($daten['gekauft_ausgaben']) ?> €</div>
                    <div><div class="text-body-secondary">Strecke</div><?= $daten['verbrauch_km'] > 0 ? $zahl($daten['verbrauch_km'], 0) . ' km' : '—' ?></div>
                    <div><div class="text-body-secondary">Ø Preis</div><?= $preis !== null ? $zahl($preis, 3) . ' €' : '—' ?></div>
                    <?php if ($co2Faktor !== null): ?><div><div class="text-body-secondary">CO₂</div><?= $zahl($daten['gekauft_menge'] * $co2Faktor, 0) ?> kg</div><?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if (count($tankJahre) > 3): ?>
            </div>
            <button class="list-group-item list-group-item-action text-primary text-center" type="button" id="weitereJahreKnopf" data-bs-toggle="collapse" data-bs-target="#weitereJahre" aria-expanded="false">
                Alle <?= count($tankJahre) ?> Jahre anzeigen
            </button>
            <script>
                (function () {
                    var bereich = document.getElementById('weitereJahre');
                    var knopf = document.getElementById('weitereJahreKnopf');
                    var text = knopf.textContent.trim();
                    bereich.addEventListener('show.bs.collapse', function () { knopf.textContent = 'Weniger anzeigen'; });
                    bereich.addEventListener('hide.bs.collapse', function () { knopf.textContent = text; });
                })();
            </script>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<script src="<?= htmlspecialchars(assetUrl($baseUrl, 'vendor/chartjs/chart.umd.min.js')) ?>"></script>
<script>
(function () {
    if (!window.Chart) return;
    var css = getComputedStyle(document.documentElement);
    var rgb = css.getPropertyValue('--bs-primary-rgb').trim();
    var farbe = function (alpha) { return 'rgba(' + rgb + ',' + alpha + ')'; };
    var zahl = function (wert, stellen) { return Number(wert).toLocaleString('de-DE', { minimumFractionDigits: stellen, maximumFractionDigits: stellen }); };
    var verbrauchEinheit = <?= json_encode(' ' . $einheitKurz . '/100 km') ?>;

    // Gemeinsame Optionen: keine schrägen Beschriftungen, Werte mit Einheit im Tooltip
    function optionen(einheit, stellen, extra) {
        var o = {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: false },
                tooltip: { callbacks: { label: function (c) { return (c.dataset.label ? c.dataset.label + ': ' : '') + zahl(c.parsed.y, stellen) + einheit; } } }
            },
            scales: {
                x: { grid: { display: false }, ticks: { maxRotation: 0, autoSkipPadding: 16 } },
                y: { ticks: { callback: function (v) { return zahl(v, 0); } } }
            }
        };
        return Object.assign(o, extra || {});
    }

    var el = document.getElementById('verbrauchChart');
    if (el) {
        var werte = <?= json_encode(array_values(array_map(fn($v) => round($v, 2), $verbrauchswerte))) ?>;
        var schnitt = <?= json_encode($durchschnittVerbrauch !== null ? round($durchschnittVerbrauch, 2) : null) ?>;
        var datasets = [{
            label: 'Tankfüllung', data: werte, borderColor: farbe(1), backgroundColor: farbe(.12),
            fill: true, cubicInterpolationMode: 'monotone', pointRadius: werte.length > 60 ? 0 : 2, pointHoverRadius: 5, borderWidth: 2
        }];
        if (schnitt !== null) {
            datasets.push({ label: 'Ø', data: werte.map(function () { return schnitt; }), borderColor: farbe(.6), borderDash: [6, 4], borderWidth: 1.5, pointRadius: 0, fill: false });
        }
        new Chart(el, { type: 'line', data: { labels: <?= json_encode($verbrauchLabels) ?>, datasets: datasets }, options: optionen(verbrauchEinheit, 2) });
    }

    el = document.getElementById('verbrauchMonatChart');
    if (el) {
        new Chart(el, {
            type: 'bar',
            data: { labels: <?= json_encode(array_keys($verbrauchProMonatFormatted)) ?>, datasets: [{ label: 'Verbrauch', data: <?= json_encode(array_values(array_map(fn($v) => round($v, 2), $verbrauchProMonatFormatted))) ?>, backgroundColor: farbe(.75), borderRadius: 4 }] },
            options: optionen(verbrauchEinheit, 2)
        });
    }

    el = document.getElementById('kostenMonatChart');
    if (el) {
        new Chart(el, {
            type: 'bar',
            data: { labels: <?= json_encode(array_keys($kostenProMonatFormatted)) ?>, datasets: [{ label: 'Ausgaben', data: <?= json_encode(array_values(array_map(fn($v) => round($v, 2), $kostenProMonatFormatted))) ?>, backgroundColor: farbe(.75), borderRadius: 4 }] },
            options: optionen(' €', 2)
        });
    }
})();
</script>
