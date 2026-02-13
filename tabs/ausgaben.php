<?php
include_once __DIR__ . '/../includes/db_connect.php';
include_once __DIR__ . '/../includes/functions.php';
include_once __DIR__ . '/../includes/api_helpers.php';

$selectedYear = isset($_GET['jahr']) && $_GET['jahr'] !== '' ? (int)$_GET['jahr'] : null;

if ($selectedYear !== null) {
    $d = getStatistikDatenFromApiForYear($api, $fahrzeug_id, $selectedYear);
    $ausgabenDaten = $d['ausgabenDaten'];
    $detailDaten = $d['detailAusgabenDaten'];
    $gesamtKosten = $d['gesamtKostenForYear'] ?? array_sum($ausgabenDaten);
    $anzahlMonate = $d['anzahlMonateForYear'] ?? 0;
} else {
    $d = getStatistikDatenFromApi($api, $fahrzeug_id);
    $ausgabenDaten = $d['ausgabenDaten'];
    $detailDaten = $d['detailAusgabenDaten'];
    $gesamtKosten = array_sum($ausgabenDaten);
    $anzahlMonate = count($d['kostenProMonat']);
}
$ausgabenDaten = array_filter($ausgabenDaten, fn($sum) => $sum > 0);
$kategorien = array_keys($ausgabenDaten);
$beträge = array_values($ausgabenDaten);
$durchschnittMonat = $anzahlMonate > 0 ? $gesamtKosten / $anzahlMonate : 0;
$topKategorie = '-';
$topSumme = 0;
foreach ($detailDaten as $k => $daten) {
    if ($daten['summe'] > $topSumme) {
        $topKategorie = $k;
        $topSumme = $daten['summe'];
    }
}
$jahre = $d['verfuegbareJahre'] ?? [];
$currentOdo = null;
try {
    $stats = $api->getVehicleStats($fahrzeug_id);
    $currentOdo = $stats['currentOdo'] ?? null;
} catch (Throwable $e) {
}
$gefahreneKm = $currentOdo !== null ? $currentOdo : 0;
$kostenProKm = $gefahreneKm > 0 ? $gesamtKosten / $gefahreneKm : 0;

// Farben für Kategorien definieren
function getCategoryColor($kategorie) {
    $colors = [
        'Tankfuellung' => 'rgba(54, 162, 235, 0.7)',    // Blau
        'Versicherung' => 'rgba(255, 99, 132, 0.7)',    // Rot
        'Werkstatt'    => 'rgba(255, 206, 86, 0.7)',    // Gelb
        'Inspektion'   => 'rgba(75, 192, 192, 0.7)',    // Türkis
        'Steuer'       => 'rgba(153, 102, 255, 0.7)',   // Lila
        'Reparatur'    => 'rgba(255, 159, 64, 0.7)',    // Orange
        'Reifen'       => 'rgba(201, 203, 207, 0.7)',   // Grau
        'TUV'          => 'rgba(255, 99, 71, 0.7)',     // Tomate
        'Wartung'      => 'rgba(60, 179, 113, 0.7)',    // Grün
        'Dekor'        => 'rgba(238, 130, 238, 0.7)',   // Violett
        'Verbrauch'    => 'rgba(106, 90, 205, 0.7)',    // Slate Blue
        'Fahrt'        => 'rgba(30, 144, 255, 0.7)'     // DodgerBlue
    ];
    return $colors[$kategorie] ?? 'rgba(169, 169, 169, 0.7)'; // Standard: Dunkelgrau
}

// Farben für das Diagramm vorbereiten
$farben = array_map('getCategoryColor', $kategorien);
?>

<div class="mt-4">
    <h3>Ausgabenanalyse</h3>

    <div class="row mb-4">
        <div class="col-md-4 mb-2">
            <div class="card p-2 text-center">
                <strong>Gesamtausgaben</strong><br>
                <span><?php echo number_format($gesamtKosten, 2, ',', '.'); ?> €</span>
            </div>
        </div>
        <div class="col-md-4 mb-2">
            <div class="card p-2 text-center">
                <strong>Ø Ausgaben pro Monat</strong><br>
                <span><?php echo number_format($durchschnittMonat, 2, ',', '.'); ?> €</span>
            </div>
        </div>
        <div class="col-md-4 mb-2">
            <div class="card p-2 text-center">
                <strong>Größte Kostenkategorie</strong><br>
                <span><?php echo htmlspecialchars(getKategorieName($topKategorie)); ?></span>
            </div>
        </div>
    </div>
    
    <!-- Filter nach Jahr -->
    <form method="get" action="" class="mb-3">
        <input type="hidden" name="id" value="<?= htmlspecialchars($fahrzeug_id) ?>">
        <div class="row align-items-center">
            <div class="col-auto">
                <label for="jahr" class="form-label">Jahr:</label>
            </div>
            <div class="col-auto">
                <select name="jahr" id="jahr" class="form-select" onchange="this.form.submit()">
                    <option value="">Alle Jahre</option>
                    <?php
                    foreach ($jahre as $jahrOption) {
                        $selected = ($selectedYear == $jahrOption) ? 'selected' : '';
                        echo '<option value="' . $jahrOption . '" ' . $selected . '>' . $jahrOption . '</option>';
                    }
                    ?>
                </select>
            </div>
        </div>
    </form>

    <!-- Container für das Diagramm mit fester Höhe -->
    <div class="chart-container" style="position: relative; height:300px; width:100%;">
        <canvas id="ausgabenChart"></canvas>
    </div>

    <!-- Detailtabelle -->
    <h4 class="mt-4">Aufschlüsselung der Kategorien</h4>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Kategorie</th>
                <th>Anzahl Einträge</th>
                <th>Prozentualer Anteil</th>
                <th>Kosten Summe (€)</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($detailDaten as $kategorie => $daten): ?>
            <tr>
                <td><?php echo htmlspecialchars($kategorie); ?></td>
                <td><?php echo $daten['anzahl']; ?></td>
                <td><?php echo $gesamtKosten > 0 ? number_format(($daten['summe'] / $gesamtKosten) * 100, 2, ',', '.') : '0,00'; ?>%</td>
                <td><?php echo number_format($daten['summe'], 2, ',', '.'); ?> €</td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Zusätzliche Kostenanalysen -->
    <div class="row mt-4">
        <!-- Fixkosten vs. variable Kosten -->
        <div class="col-md-4 mb-4">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title">Kostenstruktur</h5>
                    <?php
                    // Fixkosten (Versicherung, Steuer, TÜV)
                    $fixkosten = 0;
                    $fixkategorien = ['Versicherung', 'Steuer', 'TUV'];
                    foreach ($detailDaten as $kategorie => $daten) {
                        if (in_array($kategorie, $fixkategorien)) {
                            $fixkosten += $daten['summe'];
                        }
                    }
                    $variableKosten = $gesamtKosten - $fixkosten;
                    ?>
                    <canvas id="kostenstrukturChart" style="max-height:200px;"></canvas>
                    <div class="mt-3">
                        <p class="mb-1">Fixkosten: <?php echo number_format($fixkosten, 2, ',', '.'); ?> €</p>
                        <p class="mb-1">Variable Kosten: <?php echo number_format($variableKosten, 2, ',', '.'); ?> €</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kosten pro Kilometer -->
        <div class="col-md-4 mb-4">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title">Kosten pro Kilometer</h5>
                    ?>
                    <div class="text-center">
                        <h3 class="mb-0"><?= number_format($kostenProKm, 2, ',', '.') ?> €/km</h3>
                        <p class="text-muted mb-0">bei <?= number_format($gefahreneKm, 0, ',', '.') ?> km</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Wartungskosten pro 10.000 km -->
        <div class="col-md-4 mb-4">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title">Wartungskosten pro 10.000 km</h5>
                    <?php
                    // Wartungskosten (Wartung, Inspektion, Reparatur)
                    $wartungskosten = 0;
                    $wartungskategorien = ['Wartung', 'Inspektion', 'Reparatur'];
                    foreach ($detailDaten as $kategorie => $daten) {
                        if (in_array($kategorie, $wartungskategorien)) {
                            $wartungskosten += $daten['summe'];
                        }
                    }
                    $wartungskostenPro10k = $gefahreneKm > 0 ? ($wartungskosten / (float)$gefahreneKm) * 10000 : 0;
                    ?>
                    <div class="text-center">
                        <h3 class="mb-0"><?php echo number_format($wartungskostenPro10k, 2, ',', '.'); ?> €</h3>
                        <p class="text-muted mb-0">pro 10.000 km</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js einbinden -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Daten für das Diagramm vorbereiten
var ctx = document.getElementById('ausgabenChart').getContext('2d');
var chart = new Chart(ctx, {
    type: 'pie',
    data: {
        labels: <?php echo json_encode($kategorien); ?>,
        datasets: [{
            data: <?php echo json_encode($beträge); ?>,
            backgroundColor: <?php echo json_encode($farben); ?>
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'right'
            }
        }
    }
});

// Kostenstruktur Chart
new Chart(document.getElementById('kostenstrukturChart').getContext('2d'), {
    type: 'doughnut',
    data: {
        labels: ['Fixkosten', 'Variable Kosten'],
        datasets: [{
            data: [<?php echo $fixkosten; ?>, <?php echo $variableKosten; ?>],
            backgroundColor: [
                'rgba(54, 162, 235, 0.7)',
                'rgba(255, 99, 132, 0.7)'
            ]
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});
</script>

