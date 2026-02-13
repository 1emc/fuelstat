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
$basisKraftstoffKm = null;
$kraftstoffKostenProKm = null;
$durchschnittPreis = null;
try {
    $stats = $api->getVehicleStats($fahrzeug_id);
    $durchschnittPreis = $stats['avgPricePerLiter'] ?? null;
} catch (Throwable $e) {
}

$minVerbrauch = $verbrauchswerte ? min($verbrauchswerte) : 0;
$durchschnittVerbrauch = $verbrauchswerte ? array_sum($verbrauchswerte) / count($verbrauchswerte) : 0;
$maxVerbrauch = $verbrauchswerte ? max($verbrauchswerte) : 0;

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
            <strong>Bester Monatsverbrauch</strong><br>
            <small>Basis: Full-to-Full</small><br>
            <span><?= $besterVerbrauch !== null ? $bestKey . ' (' . number_format($besterVerbrauch, 2, ',', '.') . ' l/100km)' : '–' ?></span>
        </div>
    </div>
    <div class="col-md-4 mb-2">
        <div class="card p-2 text-center">
            <strong>Schlechtester Monatsverbrauch</strong><br>
            <span><?= $schlechtesterVerbrauch !== null ? $worstKey . ' (' . number_format($schlechtesterVerbrauch, 2, ',', '.') . ' l/100km)' : '–' ?></span>
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
            <span><?= $kraftstoffKostenProKm !== null ? number_format($kraftstoffKostenProKm, 2, ',', '.') . ' €' : '–' ?></span>
        </div>
    </div>
    <div class="col-md-6 mb-2">
        <div class="card p-2 text-center">
            <strong>Ø Kraftstoffpreis</strong><br>
            <span><?= $durchschnittPreis !== null ? number_format($durchschnittPreis, 3, ',', '.') . ' €/l' : '–' ?></span>
        </div>
    </div>
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
                    <tr><th>Jahr</th><th># Stops</th><th>l gekauft</th><th>€ gekauft</th><th>l verbraucht</th><th>l/100km</th></tr>
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
