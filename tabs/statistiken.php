<?php
// tabs/statistiken.php

include_once __DIR__ . '/../includes/db_connect.php';
include_once __DIR__ . '/../includes/functions.php';

// Sicherstellen, dass Fahrzeug-ID definiert ist
if (!isset($fahrzeug_id) && isset($_GET['id'])) {
    $fahrzeug_id = intval($_GET['id']);
}
if (!isset($fahrzeug_id)) {
    die('Fahrzeug-ID fehlt.');
}

// Daten abrufen
$verbrauchswerte              = holeVerbrauchswerte($fahrzeug_id) ?: [];
$verbrauchProMonat             = holeVerbrauchProMonat($fahrzeug_id) ?: [];
$kostenProMonat                = holeKostenProMonat($fahrzeug_id) ?: [];
$jahresdaten                   = holeJahresdaten($fahrzeug_id) ?: [];
$kraftstoffKostenProKm         = berechneKraftstoffkostenProKm($fahrzeug_id);
$durchschnittPreis             = berechneDurchschnittPreisProLiter($fahrzeug_id);

// Kennzahlen berechnen
$minVerbrauch                  = $verbrauchswerte ? min($verbrauchswerte) : 0;
$durchschnittVerbrauch         = $verbrauchswerte ? array_sum($verbrauchswerte) / count($verbrauchswerte) : 0;
$maxVerbrauch                  = $verbrauchswerte ? max($verbrauchswerte) : 0;

// Labels formatieren
$verbrauchProMonatFormatted    = formatLabels($verbrauchProMonat);
$kostenProMonatFormatted       = formatLabels($kostenProMonat);

// Zusammenfassung ermitteln mit Guards
if ($verbrauchProMonatFormatted) {
    $bestKey    = array_keys($verbrauchProMonatFormatted, max($verbrauchProMonatFormatted))[0];
    $worstKey   = array_keys($verbrauchProMonatFormatted, min($verbrauchProMonatFormatted))[0];
} else {
    $bestKey = $worstKey = '-';
}

if ($kostenProMonatFormatted) {
    $teuersterKey = array_keys($kostenProMonatFormatted, max($kostenProMonatFormatted))[0];
} else {
    $teuersterKey = '-';
}

$besterVerbrauch     = !empty($verbrauchProMonatFormatted) ? min($verbrauchProMonatFormatted)  : null;
$schlechtesterVerbrauch = !empty($verbrauchProMonatFormatted) ? max($verbrauchProMonatFormatted)  : null;
$teuersterMonatWert  = !empty($kostenProMonatFormatted)    ? max($kostenProMonatFormatted)     : null;
?>

<div class="mt-4">
<!-- Zusammenfassung -->
<div class="row mb-4">
    <!-- Bester Verbrauch -->
    <div class="col-md-4 mb-2">
        <div class="card p-2 text-center">
            <strong>Bester Verbrauch</strong><br>
            <span>
                <?php
                    echo $besterVerbrauch !== null
                         ? $bestKey.' ('.number_format($besterVerbrauch, 2, ',', '.').' l/100km)'
                         : '–';
                ?>
            </span>
        </div>
    </div>

    <!-- Schlechtester Verbrauch -->
    <div class="col-md-4 mb-2">
        <div class="card p-2 text-center">
            <strong>Schlechtester Verbrauch</strong><br>
            <span>
                <?php
                    echo $schlechtesterVerbrauch !== null
                         ? $worstKey.' ('.number_format($schlechtesterVerbrauch, 2, ',', '.').' l/100km)'
                         : '–';
                ?>
            </span>
        </div>
    </div>

    <!-- Teuerster Monat -->
    <div class="col-md-4 mb-2">
        <div class="card p-2 text-center">
            <strong>Teuerster Monat</strong><br>
            <span>
                <?php
                    echo $teuersterMonatWert !== null
                         ? $teuersterKey.' ('.number_format($teuersterMonatWert, 2, ',', '.').' €)'
                         : '–';
                ?>
            </span>
        </div>
    </div>
</div>

<!-- Zusätzliche Kennzahlen -->
<div class="row mb-4">
    <div class="col-md-6 mb-2">
        <div class="card p-2 text-center">
            <strong>Kraftstoffkosten pro km</strong><br>
            <span>
                <?php
                    echo $kraftstoffKostenProKm !== null
                         ? number_format($kraftstoffKostenProKm, 2, ',', '.').' €'
                         : '–';
                ?>
            </span>
        </div>
    </div>
    <div class="col-md-6 mb-2">
        <div class="card p-2 text-center">
            <strong>Ø Kraftstoffpreis</strong><br>
            <span>
                <?php
                    echo $durchschnittPreis !== null
                         ? number_format($durchschnittPreis, 3, ',', '.').' €/l'
                         : '–';
                ?>
            </span>
        </div>
    </div>
</div>


    <!-- Charts und Tabelle -->
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
                        <tr>
                            <th>Jahr</th>
                            <th># Stops</th>
                            <th>l gesamt</th>
                            <th>l/100km</th>
                            <th>€ gesamt</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($jahresdaten as $jahr => $daten): ?>
                        <tr>
                            <td><?php echo $jahr; ?></td>
                            <td><?php echo $daten['anzahl_tankstops']; ?></td>
                            <td><?php echo number_format($daten['gesamt_menge'],2,',','.'); ?></td>
                            <td><?php echo number_format($daten['durchschnitt_verbrauch'],2,',','.'); ?></td>
                            <td><?php echo number_format($daten['gesamt_ausgaben'],2,',','.'); ?></td>
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
    data: { labels: <?= json_encode(array_keys($verbrauchswerte)); ?>, datasets: [{ data: <?= json_encode(array_values($verbrauchswerte)); ?> }] },
    options: opts
});

new Chart(document.getElementById('verbrauchMonatChart').getContext('2d'), {
    type: 'bar',
    data: { labels: <?= json_encode(array_keys($verbrauchProMonatFormatted)); ?>, datasets: [{ data: <?= json_encode(array_values($verbrauchProMonatFormatted)); ?> }] },
    options: opts
});

new Chart(document.getElementById('kostenMonatChart').getContext('2d'), {
    type: 'bar',
    data: { labels: <?= json_encode(array_keys($kostenProMonatFormatted)); ?>, datasets: [{ data: <?= json_encode(array_values($kostenProMonatFormatted)); ?> }] },
    options: opts
});
</script>

<?php
// (Die Daten-Funktionen und Helper wurden in includes/functions.php ausgelagert.)
?>
