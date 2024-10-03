<?php
// tabs/eintraege.php

include_once '../includes/functions.php'; // Sicherstellen, dass Funktionen eingebunden sind

// Optional: Jahr-Filter verarbeiten
$jahr = isset($_GET['jahr']) ? intval($_GET['jahr']) : null;

// Einträge für das Fahrzeug abrufen
if ($jahr) {
    $stmt = $conn->prepare("SELECT * FROM eintraege WHERE fahrzeug_id = ? AND YEAR(datum) = ? ORDER BY datum DESC");
    $stmt->bind_param("ii", $fahrzeug_id, $jahr);
} else {
    $stmt = $conn->prepare("SELECT * FROM eintraege WHERE fahrzeug_id = ? ORDER BY datum DESC");
    $stmt->bind_param("i", $fahrzeug_id);
}
$stmt->execute();
$result = $stmt->get_result();

// Einträge nach Monat gruppieren
$eintraege = [];
while ($row = $result->fetch_assoc()) {
    $monat = date('Y-m', strtotime($row['datum']));
    $eintraege[$monat][] = $row;
}
?>
<div class="mt-4">
    <!-- Button zum Hinzufügen neuer Einträge -->
    <a href="eintrag_hinzufuegen.php?fahrzeug_id=<?php echo $fahrzeug_id; ?>" class="btn btn-success mb-3">
        <i class="fas fa-plus"></i> Neuer Eintrag
    </a>

    <?php if (!empty($eintraege)): ?>
        <?php foreach ($eintraege as $monat => $eintraege_im_monat): ?>
            <h4><?php echo date('F Y', strtotime($monat . '-01')); ?></h4>
            <div class="accordion" id="accordion-<?php echo $monat; ?>">
                <?php foreach ($eintraege_im_monat as $index => $eintrag): ?>
                <?php
                // Initialisierung der Variablen
                $verbrauch = null;
                $gefahrene_km = null;
                $vorheriger_verbrauch = null;
                $trend = 'neutral';

                // Abhängig vom Eintragstyp Berechnungen durchführen
                if ($eintrag['kategorie'] == 'Tankfüllung') {
                    // Berechnete Werte
                    $verbrauch = berechneVerbrauchEintrag($eintrag, $fahrzeug_id);
                    $gefahrene_km = berechneGefahreneKm($eintrag, $fahrzeug_id);

                    $vorheriger_verbrauch = getPreviousVerbrauch($eintrag, $fahrzeug_id);

                    // Verbrauchstrend bestimmen
                    if ($vorheriger_verbrauch !== null && $verbrauch !== null) {
                        if ($verbrauch > $vorheriger_verbrauch) {
                            $trend = 'up';
                        } elseif ($verbrauch < $vorheriger_verbrauch) {
                            $trend = 'down';
                        }
                    }
                }

                // Start der Anzeige des Eintrags
                ?>
                    <div class="card mb-3">
                        <div class="card-body">
                            <div class="row">
                                <!-- Spalte 1: Kategorie-Icon -->
                                <div class="col-2 text-center">
                                    <i class="fas <?php echo getCategoryIcon($eintrag['kategorie']); ?> fa-2x"></i>
                                </div>
                                <!-- Spalte 2: Daten -->
                                <div class="col-10">
                                    <!-- Erste Zeile: Kategorie-Name und Gesamtkosten -->
                                    <div class="d-flex justify-content-between">
                                        <div class="fw-bold"><?php echo htmlspecialchars($eintrag['kategorie']); ?></div>
                                        <div class="text-end">
                                            <?php if (isset($eintrag['kosten']) && $eintrag['kosten'] > 0): ?>
                                                <?php echo number_format($eintrag['kosten'], 2, ',', '.'); ?> €
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <!-- Zweite Zeile: Tachostand und Datum -->
                                    <div class="d-flex justify-content-between">
                                        <div class="text-muted small">
                                            <?php if (isset($eintrag['tachostand'])): ?>
                                                <?php echo number_format($eintrag['tachostand'], 0, ',', '.'); ?> km
                                            <?php endif; ?>
                                            &#183; <?php echo date('d.m.y', strtotime($eintrag['datum'])); ?>
                                        </div>
                                        <div class="text-end small">
                                            <?php if (isset($eintrag['preis_pro_einheit']) && $eintrag['kategorie'] == 'Tankfüllung'): ?>
                                                <?php echo number_format($eintrag['preis_pro_einheit'], 3, ',', '.'); ?> €/l
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <!-- Anzeige je nach Kategorie -->
                                    <?php if ($eintrag['kategorie'] == 'Tankfüllung'): ?>
                                        <!-- Dritte Zeile: Standort und gefahrene Kilometer -->
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <?php if (!empty($eintrag['standort'])): ?>
                                                    <i class="fas fa-map-marker-alt text-muted me-1"></i>
                                                    <?php echo htmlspecialchars($eintrag['standort']); ?>
                                                <?php endif; ?>
                                            </div>
                                            <div class="text-end">
                                                <i class="fas fa-ruler-combined text-muted me-1"></i>
                                                <?php echo (is_numeric($gefahrene_km)) ? number_format($gefahrene_km, 0, ',', '.') . ' km' : 'N/A'; ?>
                                            </div>
                                        </div>
                                        <!-- Vierte Zeile: Verbrauchstrend und getankte Liter -->
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <?php if ($trend === 'up' && $verbrauch != 'zwischen'): ?>
                                                    <i class="fas fa-arrow-trend-up text-danger me-1"></i>
                                                <?php elseif ($verbrauch === 'zwischen'): ?>
                                                    <i class="fas fa-circle-half-stroke text-warning me-1"></i>
                                                <?php elseif ($trend === 'down'): ?>
                                                    <i class="fas fa-arrow-trend-down text-success me-1"></i>
                                                <?php else: ?>
                                                    <i class="fas fa-minus text-muted me-1"></i>
                                                <?php endif; ?>
                                                <?php if (is_numeric($verbrauch)):?>
                                                    <?php echo number_format($verbrauch, 2, ',', '.') . ' l/100km';?>
                                                <?php elseif ($verbrauch === 'zwischen'): ?>
                                                    <i>nicht vollgetankt</i>
                                                <?php else: ?>
                                                    N/A
                                                <?php endif; ?>
                                            </div>
                                            <div class="text-end">
                                                <i class="fas fa-tint text-muted me-1"></i>
                                                <?php echo number_format($eintrag['menge'], 2, ',', '.') . ' l'; ?>
                                            </div>
                                        </div>
                                    <?php elseif ($eintrag['kategorie'] == 'Andere Ausgabe'): ?>
                                        <!-- Anzeige für Andere Ausgaben -->
                                        <div class="mt-2">
                                            <div>
                                                <?php if (!empty($eintrag['beschreibung'])): ?>
                                                    <?php echo htmlspecialchars($eintrag['beschreibung']); ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php elseif ($eintrag['kategorie'] == 'Fahrt'): ?>
                                        <!-- Anzeige für Fahrt -->
                                        <div class="mt-2">
                                            <div><strong>Startort:</strong> <?php echo htmlspecialchars($eintrag['startort']); ?></div>
                                            <div><strong>Zielort:</strong> <?php echo htmlspecialchars($eintrag['zielort']); ?></div>
                                            <div><strong>Zweck:</strong> <?php echo htmlspecialchars($eintrag['zweck']); ?></div>
                                            <div><strong>Gefahrene Kilometer:</strong> <?php echo number_format($eintrag['gefahrene_km'], 1, ',', '.'); ?> km</div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="alert alert-warning">Keine Einträge vorhanden.</div>
    <?php endif; ?>
</div>

<!-- Optional: Bootstrap Tooltips Initialisierung -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    })
});
</script>
