<?php
// tabs/ausgaben.php

// Daten für das Kuchendiagramm vorbereiten
$ausgabenDaten = holeAusgabenDaten($fahrzeug_id);

// Aufbereitung der Daten für das Diagramm
$kategorien = array_keys($ausgabenDaten);
$beträge = array_values($ausgabenDaten);

// Gesamtkosten berechnen
$gesamtKosten = array_sum($beträge);

// Detailtabelle vorbereiten
$detailDaten = holeDetailAusgabenDaten($fahrzeug_id);
?>

<div class="mt-4">
    <h3>Ausgabenanalyse</h3>
    <!-- Kuchendiagramm anzeigen -->
    <canvas id="ausgabenChart"></canvas>

    <!-- Filter nach Jahr (optional) -->
    <form method="get" action="">
        <input type="hidden" name="id" value="<?php echo $fahrzeug_id; ?>">
        <label for="jahr">Jahr:</label>
        <select name="jahr" onchange="this.form.submit()">
            <option value="">Alle Jahre</option>
            <?php
            $jahre = holeVerfügbareJahre($fahrzeug_id);
            foreach ($jahre as $jahrOption) {
                echo '<option value="' . $jahrOption . '">' . $jahrOption . '</option>';
            }
            ?>
        </select>
    </form>

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
                <td><?php echo number_format(($daten['summe'] / $gesamtKosten) * 100, 2, ',', '.'); ?>%</td>
                <td><?php echo number_format($daten['summe'], 2, ',', '.'); ?> €</td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
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
            backgroundColor: [
                'rgba(255, 99, 132, 0.7)',
                'rgba(54, 162, 235, 0.7)',
                // Weitere Farben hinzufügen
            ]
        }]
    },
    options: {
        responsive: true
    }
});
</script>

<?php
function holeAusgabenDaten($fahrzeug_id) {
    // Implementiere die Logik zum Abrufen der Ausgaben pro Kategorie
    // Rückgabeformat: ['Kategorie' => Summe]
    return [
        'Tankfüllung' => 800,
        'Versicherung' => 400,
        // Weitere Kategorien
    ];
}

function holeDetailAusgabenDaten($fahrzeug_id) {
    // Implementiere die Logik zum Abrufen der Detaildaten pro Kategorie
    // Rückgabeformat: ['Kategorie' => ['anzahl' => ..., 'summe' => ...]]
    return [
        'Tankfüllung' => ['anzahl' => 12, 'summe' => 800],
        'Versicherung' => ['anzahl' => 1, 'summe' => 400],
        // Weitere Kategorien
    ];
}

function holeVerfügbareJahre($fahrzeug_id) {
    // Implementiere die Logik zum Abrufen der verfügbaren Jahre
    return ['2023', '2022']; // Beispielwerte
}
?>
