<?php
// Debug-Einstellungen
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// Jahr aus GET-Parameter holen
$selectedYear = isset($_GET['jahr']) && $_GET['jahr'] !== '' ? intval($_GET['jahr']) : null;

// Daten für das Kuchendiagramm vorbereiten
// Daten holen und Kategorien mit 0 € für das Diagramm ausblenden
$ausgabenDaten = holeAusgabenDaten($fahrzeug_id, $selectedYear);
$ausgabenDaten = array_filter($ausgabenDaten, fn($sum) => $sum > 0);

// Aufbereitung der Daten für das Diagramm
$kategorien = array_keys($ausgabenDaten);
$beträge   = array_values($ausgabenDaten);

// Gesamtkosten berechnen
$gesamtKosten = array_sum($beträge);

// Detailtabelle vorbereiten
$detailDaten = holeDetailAusgabenDaten($fahrzeug_id, $selectedYear);

// Zusätzliche Kennzahlen
$anzahlMonate = holeAnzahlMonate($fahrzeug_id, $selectedYear);
$durchschnittMonat = $anzahlMonate > 0 ? $gesamtKosten / $anzahlMonate : 0;
$topKategorie = '-';
$topSumme = 0;
foreach ($detailDaten as $k => $daten) {
    if ($daten['summe'] > $topSumme) {
        $topKategorie = $k;
        $topSumme = $daten['summe'];
    }
}

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
        <input type="hidden" name="id" value="<?php echo $fahrzeug_id; ?>">
        <div class="row align-items-center">
            <div class="col-auto">
                <label for="jahr" class="form-label">Jahr:</label>
            </div>
            <div class="col-auto">
                <select name="jahr" id="jahr" class="form-select" onchange="this.form.submit()">
                    <option value="">Alle Jahre</option>
                    <?php
                    $jahre = holeVerfügbareJahre($fahrzeug_id);
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
                    <?php
                    // Aktuellen Tachostand und initialen Tachostand holen
                    $aktTachostand = getAktuellenTachostand($fahrzeug_id);
                    $initialTachostand = getInitialTachostand($fahrzeug_id) ?? 0;
                    $gefahreneKm = $aktTachostand - $initialTachostand;
                    $kostenProKm = $gefahreneKm > 0 ? $gesamtKosten / $gefahreneKm : 0;
                    ?>
                    <div class="text-center">
                        <h3 class="mb-0"><?php echo number_format($kostenProKm, 2, ',', '.'); ?> €/km</h3>
                        <p class="text-muted mb-0">bei <?php echo number_format($gefahreneKm, 0, ',', '.'); ?> km</p>
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
                    $wartungskostenPro10k = $gefahreneKm > 0 ? ($wartungskosten / $gefahreneKm) * 10000 : 0;
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

<?php
function holeAusgabenDaten($fahrzeug_id, $jahr = null) {
    global $conn;
    
    $sql = "SELECT kategorie, SUM(kosten) as summe 
            FROM eintraege 
            WHERE fahrzeug_id = ?";
    
    if ($jahr !== null) {
        $sql .= " AND YEAR(datum) = ?";
    }
    
    $sql .= " GROUP BY kategorie";
            
    $stmt = $conn->prepare($sql);
    
    if ($jahr !== null) {
        $stmt->bind_param('ii', $fahrzeug_id, $jahr);
    } else {
        $stmt->bind_param('i', $fahrzeug_id);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $ausgaben = [];
    while ($row = $result->fetch_assoc()) {
        $ausgaben[$row['kategorie']] = $row['summe'];
    }
    
    return $ausgaben;
}

function holeDetailAusgabenDaten($fahrzeug_id, $jahr = null) {
    global $conn;
    
    $sql = "SELECT kategorie, 
                   COUNT(*) as anzahl, 
                   SUM(kosten) as summe 
            FROM eintraege 
            WHERE fahrzeug_id = ?";
    
    if ($jahr !== null) {
        $sql .= " AND YEAR(datum) = ?";
    }
    
    $sql .= " GROUP BY kategorie";
            
    $stmt = $conn->prepare($sql);
    
    if ($jahr !== null) {
        $stmt->bind_param('ii', $fahrzeug_id, $jahr);
    } else {
        $stmt->bind_param('i', $fahrzeug_id);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $details = [];
    while ($row = $result->fetch_assoc()) {
        $details[$row['kategorie']] = [
            'anzahl' => $row['anzahl'],
            'summe' => $row['summe']
        ];
    }
    
    return $details;
}

function holeVerfügbareJahre($fahrzeug_id) {
    global $conn;
    
    $sql = "SELECT DISTINCT YEAR(datum) as jahr 
            FROM eintraege 
            WHERE fahrzeug_id = ? 
            ORDER BY jahr DESC";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $fahrzeug_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $jahre = [];
    while ($row = $result->fetch_assoc()) {
        $jahre[] = $row['jahr'];
    }
    
    return $jahre;
}

function holeAnzahlMonate($fahrzeug_id, $jahr = null) {
    global $conn;

    $sql = "SELECT COUNT(DISTINCT DATE_FORMAT(datum, '%Y-%m')) AS monate FROM eintraege WHERE fahrzeug_id = ?";
    if ($jahr !== null) {
        $sql .= " AND YEAR(datum) = ?";
    }

    $stmt = $conn->prepare($sql);
    if ($jahr !== null) {
        $stmt->bind_param('ii', $fahrzeug_id, $jahr);
    } else {
        $stmt->bind_param('i', $fahrzeug_id);
    }
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int)($row['monate'] ?? 0);
}
?>
