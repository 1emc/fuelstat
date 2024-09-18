<?php
// tabs/statistiken.php

// Daten für das Liniendiagramm vorbereiten
$verbrauchswerte = holeVerbrauchswerte($fahrzeug_id);

$minVerbrauch = min($verbrauchswerte);
$durchschnittVerbrauch = array_sum($verbrauchswerte) / count($verbrauchswerte);
$maxVerbrauch = max($verbrauchswerte);
?>

<div class="mt-4">
    <h3>Kraftstoffverbrauch</h3>
    <!-- Liniendiagramm anzeigen -->
    <canvas id="verbrauchChart"></canvas>

    <!-- Anzeige der Minimal-, Durchschnitts- und Maximalwerte -->
    <div class="mt-3">
        <p><strong>Minimum Verbrauch:</strong> <?php echo number_format($minVerbrauch, 2, ',', '.'); ?> l/100km</p>
        <p><strong>Durchschnittlicher Verbrauch:</strong> <?php echo number_format($durchschnittVerbrauch, 2, ',', '.'); ?> l/100km</p>
        <p><strong>Maximum Verbrauch:</strong> <?php echo number_format($maxVerbrauch, 2, ',', '.'); ?> l/100km</p>
    </div>

    <!-- Tabelle nach Jahren -->
    <h3 class="mt-4">Jahresübersicht</h3>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Jahr</th>
                <th>Anzahl Tankstops</th>
                <th>Gesamt getankt (l)</th>
                <th>Durchschnittlicher Verbrauch (l/100km)</th>
                <th>Gesamtausgaben (€)</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $jahresdaten = holeJahresdaten($fahrzeug_id);
            foreach ($jahresdaten as $jahr => $daten):
            ?>
            <tr>
                <td><?php echo $jahr; ?></td>
                <td><?php echo $daten['anzahl_tankstops']; ?></td>
                <td><?php echo number_format($daten['gesamt_menge'], 2, ',', '.'); ?> l</td>
                <td><?php echo number_format($daten['durchschnitt_verbrauch'], 2, ',', '.'); ?> l/100km</td>
                <td><?php echo number_format($daten['gesamt_ausgaben'], 2, ',', '.'); ?> €</td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Chart.js einbinden -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Daten für das Diagramm vorbereiten
var ctx = document.getElementById('verbrauchChart').getContext('2d');
var chart = new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode(array_keys($verbrauchswerte)); ?>,
        datasets: [{
            label: 'Verbrauch (l/100km)',
            data: <?php echo json_encode(array_values($verbrauchswerte)); ?>,
            borderColor: 'rgba(75, 192, 192, 1)',
            fill: false
        }]
    },
    options: {
        scales: {
            x: { display: true },
            y: { display: true }
        }
    }
});
</script>

<?php
function holeVerbrauchswerte($fahrzeug_id) {
    // Implementiere die Logik zum Abrufen der Verbrauchswerte
    // Rückgabeformat: ['Datum' => Verbrauchswert]
    return [
        '2023-01-01' => 6.5,
        '2023-02-01' => 6.7,
        // Weitere Werte
    ];
}

function holeJahresdaten($fahrzeug_id) {
    // Implementiere die Logik zum Abrufen der Jahresdaten
    // Rückgabeformat: ['Jahr' => ['anzahl_tankstops' => ..., 'gesamt_menge' => ..., ...]]
    return [
        '2023' => [
            'anzahl_tankstops' => 12,
            'gesamt_menge' => 600,
            'durchschnitt_verbrauch' => 6.5,
            'gesamt_ausgaben' => 800,
        ],
        // Weitere Jahre
    ];
}
?>
