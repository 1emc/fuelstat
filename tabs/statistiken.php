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

$minVerbrauch = $verbrauchswerte ? min($verbrauchswerte) : 0;
$durchschnittVerbrauch = $verbrauchswerte ? array_sum($verbrauchswerte) / count($verbrauchswerte) : 0;
$maxVerbrauch = $verbrauchswerte ? max($verbrauchswerte) : 0;

ksort($verbrauchProMonat);
ksort($kostenProMonat);
$verbrauchProMonat = array_filter($verbrauchProMonat, fn($v) => $v > 0);
$verbrauchProMonatFormatted = formatLabels($verbrauchProMonat);
$kostenProMonatFormatted = formatLabels($kostenProMonat);

$bestKey = $worstKey = $teuersterKey = '-';
if ($verbrauchProMonatFormatted) {
    $bestKey = array_keys($verbrauchProMonatFormatted, min($verbrauchProMonatFormatted))[0];
    $worstKey = array_keys($verbrauchProMonatFormatted, max($verbrauchProMonatFormatted))[0];
}
if ($kostenProMonatFormatted) {
    $teuersterKey = array_keys($kostenProMonatFormatted, max($kostenProMonatFormatted))[0];
}

$besterVerbrauch = !empty($verbrauchProMonatFormatted) ? min($verbrauchProMonatFormatted) : null;
$schlechtesterVerbrauch = !empty($verbrauchProMonatFormatted) ? max($verbrauchProMonatFormatted) : null;
$teuersterMonatWert = !empty($kostenProMonatFormatted) ? max($kostenProMonatFormatted) : null;
?>

<div class="mt-4">
<div class="row mb-4">
    <div class="col-md-4 mb-2">
        <div class="card p-2 text-center">
            <strong title="Basis: Full-to-Full">Bester Monatsverbrauch</strong><br>
            <span><?= $besterVerbrauch !== null ? $bestKey . ' (' . number_format($besterVerbrauch, 2, ',', '.') . ' ' . $einheitKurz . '/100km)' : '–' ?></span>
        </div>
    </div>
    <div class="col-md-4 mb-2">
        <div class="card p-2 text-center">
            <strong>Schlechtester Monatsverbrauch</strong><br>
            <span><?= $schlechtesterVerbrauch !== null ? $worstKey . ' (' . number_format($schlechtesterVerbrauch, 2, ',', '.') . ' ' . $einheitKurz . '/100km)' : '–' ?></span>
        </div>
    </div>
    <div class="col-md-4 mb-2">
        <div class="card p-2 text-center">
            <strong>Teuerster Monat</strong><br>
            <span><?= $teuersterMonatWert !== null ? $teuersterKey . ' (' . number_format($teuersterMonatWert, 2, ',', '.') . ' €)' : '–' ?></span>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-6 mb-2">
        <div class="card p-2 text-center">
            <strong>Kraftstoffkosten pro km</strong><br>
            <span><?= $kraftstoffKostenProKm !== null ? number_format($kraftstoffKostenProKm, 3, ',', '.') . ' €/km' : '–' ?></span>
        </div>
    </div>
    <div class="col-md-6 mb-2">
        <div class="card p-2 text-center">
            <strong>Ø Kraftstoffpreis</strong><br>
            <span><?= $durchschnittPreis !== null ? number_format($durchschnittPreis, 3, ',', '.') . ' €/' . $einheitKurz : '–' ?></span>
        </div>
    </div>
    <?php if ($co2Faktor !== null): ?>
    <div class="col-md-6 mb-2">
        <div class="card p-2 text-center" title="<?= ($fahrzeug['fuel_type'] ?? '') === 'electric' ? 'Berechnet mit dem deutschen Strommix (0,363 kg CO₂/kWh)' : 'Direkte Emission bei der Verbrennung (' . number_format($co2Faktor, 2, ',', '.') . ' kg CO₂/' . $einheitKurz . ')' ?>">
            <strong>CO₂-Ausstoß gesamt</strong><br>
            <span><?= $co2Gesamt !== null ? number_format($co2Gesamt / 1000, 2, ',', '.') . ' t' : '–' ?></span>
        </div>
    </div>
    <div class="col-md-6 mb-2">
        <div class="card p-2 text-center">
            <strong>Ø CO₂ pro km</strong><br>
            <span><?= $co2ProKm !== null ? number_format($co2ProKm, 0, ',', '.') . ' g/km' : '–' ?></span>
        </div>
    </div>
    <?php endif; ?>
</div>

<div class="row gy-4">
    <div class="col-lg-6">
        <h5>Kraftstoffverbrauch pro Tankfüllung</h5>
        <canvas id="verbrauchChart" style="max-height:250px;"></canvas>
    </div>
    <div class="col-lg-6">
        <h5>Monatlicher Durchschnittsverbrauch</h5>
        <canvas id="verbrauchMonatChart" style="max-height:250px;"></canvas>
    </div>
    <div class="col-lg-6">
        <h5>Monatliche Ausgaben</h5>
        <canvas id="kostenMonatChart" style="max-height:250px;"></canvas>
    </div>
    <div class="col-lg-6">
        <h5>Jahresübersicht</h5>
        <div style="max-height:250px; overflow:auto;">
            <table class="table table-sm table-bordered mb-0">
                <thead>
                    <tr><th>Jahr</th><th># Stops</th><th><?= $einheitKurz ?> gekauft</th><th>€ gekauft</th><th><?= $einheitKurz ?> verbraucht</th><th><?= $einheitKurz ?>/100km</th><?php if ($co2Faktor !== null): ?><th>CO₂</th><?php endif; ?></tr>
                </thead>
                <tbody>
                    <?php foreach ($jahresdaten as $jahr => $daten): ?>
                    <tr>
                        <td><?= $jahr ?></td>
                        <td><?= $daten['anzahl_tankstops'] ?></td>
                        <td><?= number_format($daten['gekauft_menge'], 2, ',', '.') ?></td>
                        <td><?= number_format($daten['gekauft_ausgaben'], 2, ',', '.') ?></td>
                        <td><?= number_format($daten['verbrauch_menge'], 2, ',', '.') ?></td>
                        <td><?= $daten['verbrauch_l100km'] > 0 ? number_format($daten['verbrauch_l100km'], 2, ',', '.') . ' (' . number_format($daten['verbrauch_km'], 0, ',', '.') . ' km)' : '–' ?></td>
                        <?php if ($co2Faktor !== null): ?><td><?= number_format($daten['gekauft_menge'] * $co2Faktor, 0, ',', '.') ?> kg</td><?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
var opts = { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } };
new Chart(document.getElementById('verbrauchChart').getContext('2d'), {
    type: 'line',
    data: { labels: <?= json_encode(array_keys($verbrauchswerte)) ?>, datasets: [{ data: <?= json_encode(array_values($verbrauchswerte)) ?> }] },
    options: opts
});
new Chart(document.getElementById('verbrauchMonatChart').getContext('2d'), {
    type: 'bar',
    data: { labels: <?= json_encode(array_keys($verbrauchProMonatFormatted)) ?>, datasets: [{ data: <?= json_encode(array_values($verbrauchProMonatFormatted)) ?> }] },
    options: opts
});
new Chart(document.getElementById('kostenMonatChart').getContext('2d'), {
    type: 'bar',
    data: { labels: <?= json_encode(array_keys($kostenProMonatFormatted)) ?>, datasets: [{ data: <?= json_encode(array_values($kostenProMonatFormatted)) ?> }] },
    options: opts
});
</script>
