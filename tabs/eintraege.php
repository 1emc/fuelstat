<?php
// tabs/eintraege.php

include_once '../includes/db_connect.php';
include_once '../includes/functions.php';

// Fahrzeug-ID und optionaler Jahr-Filter
$jahr = isset($_GET['jahr']) ? intval($_GET['jahr']) : null;

// 1) Alle Jahre laden
$yearStmt = $conn->prepare("SELECT DISTINCT YEAR(datum) AS jahr FROM eintraege WHERE fahrzeug_id = ? ORDER BY jahr DESC");
$yearStmt->bind_param("i", $fahrzeug_id);
$yearStmt->execute();
$yearRes = $yearStmt->get_result();
$jahrListe = [];
while ($y = $yearRes->fetch_assoc()) {
    $jahrListe[] = $y['jahr'];
}
$yearStmt->close();

// 2) Einträge abrufen
if ($jahr) {
    $stmt = $conn->prepare("SELECT * FROM eintraege WHERE fahrzeug_id = ? AND YEAR(datum) = ? ORDER BY datum DESC");
    $stmt->bind_param("ii", $fahrzeug_id, $jahr);
} else {
    $stmt = $conn->prepare("SELECT * FROM eintraege WHERE fahrzeug_id = ? ORDER BY datum DESC");
    $stmt->bind_param("i", $fahrzeug_id);
}
$stmt->execute();
$result = $stmt->get_result();

// 3) Gruppieren
$eintraege = [];
while ($row = $result->fetch_assoc()) {
    $monatKey = date('Y-m', strtotime($row['datum']));
    $eintraege[$monatKey][] = $row;
}
$stmt->close();

// Farbcodierung pro Kategorie
$farben = [
    'Tankfuellung' => 'primary',
    'Versicherung'  => 'success',
    'Werkstatt'     => 'warning',
    'Inspektion'    => 'info',
    'Reparatur'     => 'danger',
    'Reifen'        => 'secondary',
    'TUV'           => 'dark',
    'Wartung'       => 'warning',
    'Dekor'         => 'info',
    'Verbrauch'     => 'secondary'
];
?>

<div class="mt-4">
    <!-- Neuer Eintrag + Jahresfilter -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <a href="eintrag_hinzufuegen.php?fahrzeug_id=<?php echo $fahrzeug_id; ?>" class="btn btn-success w-100 w-sm-auto">
            <i class="fas fa-plus"></i> Neuer Eintrag
        </a>
        <form id="jahrFilterForm" class="row g-2 flex-nowrap" method="get" action="">
            <input type="hidden" name="id" value="<?php echo $fahrzeug_id; ?>">
            <div class="col-auto">
                <select name="jahr" class="form-select">
                    <option value="">Alle Jahre</option>
                    <?php foreach ($jahrListe as $y): ?>
                        <option value="<?php echo $y; ?>" <?php if ($jahr === (int)$y) echo 'selected'; ?>><?php echo $y; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" form="jahrFilterForm" class="btn btn-secondary w-100 w-sm-auto">Anzeigen</button>
            </div>
        </form>
    </div>

    <?php if (empty($eintraege)): ?>
        <div class="alert alert-warning">Keine Einträge vorhanden.</div>
    <?php else: ?>
        <?php foreach ($eintraege as $monat => $entries): ?>
            <h5 class="mt-4"><?php echo date('F Y', strtotime($monat . '-01')); ?></h5>
            <div class="row">
                <?php foreach ($entries as $eintrag): ?>
                    <?php
                        // Verbrauchsberechnung
                        if ($eintrag['kategorie'] === 'Tankfuellung') {
                            $verbrauch    = berechneVerbrauchEintrag($eintrag, $fahrzeug_id);
                            $prev         = getPreviousVerbrauch($eintrag, $fahrzeug_id);
                            $trend        = ($prev !== null && $verbrauch !== null)
                                          ? ($verbrauch > $prev ? 'up' : ($verbrauch < $prev ? 'down' : 'neutral'))
                                          : 'neutral';
                        } else {
                            $verbrauch = null;
                            $trend = 'neutral';
                        }
                        $farbe = $farben[$eintrag['kategorie']] ?? 'secondary';
                        $icon  = getCategoryIcon($eintrag['kategorie']);
                    ?>
                    <div class="col-md-6 mb-3">
                        <ul class="list-group">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div class="d-flex align-items-start">
                                    <i class="fas <?php echo $icon; ?> fa-2x text-<?php echo $farbe; ?> me-3"></i>
                                    <div>
                                        <strong><?php echo htmlspecialchars($eintrag['kategorie']); ?></strong><br>
                                        <small class="text-muted">
                                            <?php echo number_format($eintrag['tachostand'],0,',','.'); ?> km ·
                                            <?php echo date('d.m.Y', strtotime($eintrag['datum'])); ?>
                                        </small>
                                        <?php if (!empty($eintrag['standort'])): ?>
                                            <br><small>
                                                <i class="fas fa-map-marker-alt text-<?php echo $farbe; ?> me-1"></i>
                                                <?php echo htmlspecialchars($eintrag['standort']); ?>
                                            </small>
                                        <?php endif; ?>
                                        <?php if ($eintrag['kategorie'] === 'Tankfuellung' && is_numeric($verbrauch)): ?>
                                            <br><small>
                                                <i class="fas fa-tint text-<?php echo $farbe; ?> me-1"></i>
                                                <?php echo number_format($eintrag['menge'],2,',','.'); ?> l ·
                                                <?php echo number_format($verbrauch,2,',','.'); ?> l/100km
                                                <?php if ($trend === 'up'): ?>
                                                    <i class="fas fa-arrow-up text-danger ms-1"></i>
                                                <?php elseif ($trend === 'down'): ?>
                                                    <i class="fas fa-arrow-down text-success ms-1"></i>
                                                <?php endif; ?>
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center">
                                    <a href="eintrag_bearbeiten.php?id=<?php echo $eintrag['id']; ?>&fahrzeug_id=<?php echo $fahrzeug_id; ?>" class="btn btn-sm btn-outline-secondary me-2 w-100 w-sm-auto" title="Eintrag bearbeiten">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <span class="badge bg-<?php echo $farbe; ?> rounded-pill ms-2">
                                        <?php echo number_format($eintrag['kosten'] ?? 0,2,',','.'); ?> €
                                    </span>
                                </div>
                            </li>
                        </ul>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
