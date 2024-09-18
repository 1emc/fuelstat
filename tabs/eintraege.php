<?php
// tabs/eintraege.php

// Einträge für das Fahrzeug abrufen
$stmt = $conn->prepare("SELECT * FROM Eintraege WHERE fahrzeug_id = ? ORDER BY datum DESC");
$stmt->bind_param("i", $fahrzeug_id);
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
    <a href="eintrag_hinzufuegen.php?fahrzeug_id=<?php echo $fahrzeug_id; ?>" class="btn btn-success mb-3"><i class="fas fa-plus"></i> Neuer Eintrag</a>

    <?php if (!empty($eintraege)): ?>
        <?php foreach ($eintraege as $monat => $eintraege_im_monat): ?>
            <h3><?php echo date('F Y', strtotime($monat . '-01')); ?></h3>
            <div class="row">
                <?php foreach ($eintraege_im_monat as $eintrag): ?>
                    <div class="col-md-6">
                        <div class="card mb-3">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($eintrag['kategorie']); ?></h5>
                                <p class="card-text">
                                    <strong>Datum:</strong> <?php echo date('d.m.Y', strtotime($eintrag['datum'])); ?><br>
                                    <strong>Tachostand:</strong> <?php echo number_format($eintrag['tachostand'], 0, ',', '.'); ?> km<br>
                                    <strong>Standort:</strong> <?php echo htmlspecialchars($eintrag['standort']); ?><br>
                                    <?php if ($eintrag['kategorie'] == 'Tankfüllung'): ?>
                                        <strong>Verbrauch:</strong> <?php echo berechneVerbrauchEintrag($eintrag); ?> l/100km<br>
                                        <strong>Kosten:</strong> <?php echo number_format($eintrag['kosten'], 2, ',', '.'); ?> €<br>
                                        <strong>Preis pro Liter:</strong> <?php echo number_format($eintrag['preis_pro_einheit'], 2, ',', '.'); ?> €/l<br>
                                        <strong>Gefahrene Kilometer:</strong> <?php echo berechneGefahreneKm($eintrag, $fahrzeug_id); ?> km<br>
                                        <strong>Liter:</strong> <?php echo number_format($eintrag['menge'], 2, ',', '.'); ?> l<br>
                                    <?php else: ?>
                                        <strong>Kosten:</strong> <?php echo number_format($eintrag['kosten'], 2, ',', '.'); ?> €<br>
                                        <strong>Beschreibung:</strong> <?php echo htmlspecialchars($eintrag['beschreibung']); ?><br>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p>Keine Einträge vorhanden.</p>
    <?php endif; ?>
</div>

<?php
function berechneVerbrauchEintrag($eintrag) {
    // Implementiere die Logik zur Berechnung des Verbrauchs für diesen Eintrag
    return '6.5'; // Beispielwert
}

function berechneGefahreneKm($eintrag, $fahrzeug_id) {
    // Implementiere die Logik zur Berechnung der gefahrenen Kilometer seit dem letzten Eintrag
    return '500'; // Beispielwert
}
?>
