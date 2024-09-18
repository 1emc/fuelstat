<?php
// tabs/allgemein.php

// Hier werden die allgemeinen Fahrzeuginformationen angezeigt
?>

<div class="mt-4">
    <div class="row">
        <div class="col-md-4">
            <!-- Fahrzeugbild -->
            <?php if (!empty($fahrzeug['bild'])): ?>
                <img src="../images/<?php echo htmlspecialchars($fahrzeug['bild']); ?>" alt="Fahrzeugbild" class="img-fluid">
            <?php else: ?>
                <img src="../images/platzhalter.jpg" alt="Fahrzeugbild" class="img-fluid">
            <?php endif; ?>
        </div>
        <div class="col-md-8">
            <!-- Fahrzeugdetails -->
            <h2><?php echo htmlspecialchars($fahrzeug['marke'] . ' ' . $fahrzeug['modell']); ?></h2>
            <p><strong>Tachostand:</strong> <?php echo number_format($fahrzeug['tachostand'], 0, ',', '.'); ?> km</p>
            <p><strong>Fahrleistung:</strong> <?php echo number_format($fahrzeug['fahrleistung'], 0, ',', '.'); ?> km</p>
            <!-- Weitere Informationen -->
            <!-- Kraftstoffverbrauch -->
            <h3>Kraftstoffverbrauch</h3>
            <p><strong>Gesamt:</strong> <?php echo berechneGesamtverbrauch($fahrzeug_id); ?> l/100km</p>
            <p><strong>Aktuell:</strong> <?php echo berechneAktuellenVerbrauch($fahrzeug_id); ?> l/100km <?php echo zeigeVerbrauchstendenz($fahrzeug_id); ?></p>
            <!-- Kosten per Kilometer -->
            <h3>Kosten per Kilometer</h3>
            <p><strong>Gesamt:</strong> <?php echo berechneKostenProKmGesamt($fahrzeug_id); ?> €/km</p>
            <p><strong>Tanken:</strong> <?php echo berechneKraftstoffkostenProKm($fahrzeug_id); ?> €/km</p>
        </div>
    </div>
    <!-- Bearbeiten-Button -->
    <a href="fahrzeug_bearbeiten.php?id=<?php echo $fahrzeug_id; ?>" class="btn btn-primary mt-3"><i class="fas fa-edit"></i> Bearbeiten</a>
</div>

<?php
// Funktionen zur Berechnung der Werte (können in includes/functions.php ausgelagert werden)
function berechneGesamtverbrauch($fahrzeug_id) {
    // Hier implementierst du die Logik zur Berechnung des Gesamtverbrauchs
    return '6.5'; // Beispielwert
}

function berechneAktuellenVerbrauch($fahrzeug_id) {
    // Hier implementierst du die Logik zur Berechnung des aktuellen Verbrauchs
    return '6.8'; // Beispielwert
}

function zeigeVerbrauchstendenz($fahrzeug_id) {
    // Hier vergleichst du den aktuellen Verbrauch mit dem vorherigen und gibst einen Pfeil zurück
    return '<i class="fas fa-arrow-up text-danger"></i>'; // Beispiel für steigenden Verbrauch
}

function berechneKostenProKmGesamt($fahrzeug_id) {
    // Hier implementierst du die Logik zur Berechnung der Gesamtkosten pro Kilometer
    return '0.12'; // Beispielwert
}

function berechneKraftstoffkostenProKm($fahrzeug_id) {
    // Hier implementierst du die Logik zur Berechnung der Kraftstoffkosten pro Kilometer
    return '0.08'; // Beispielwert
}
?>
