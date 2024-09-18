<?php
// tabs/allgemein.php

// Fahrzeugdetails werden von fahrzeug_detail.php bereitgestellt (via $fahrzeug)
$initial_tachostand = $fahrzeug['tachostand']; // Initialer Tachostand aus dem Fahrzeugobjekt
$aktueller_tachostand = getAktuellenTachostand($fahrzeug_id);

// Berechne die Fahrleistung (aktuelle km - initiale km)
$fahrleistung = berechneFahrleistung($fahrzeug_id, $initial_tachostand);
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
            <p><strong>Tachostand:</strong> <?php echo ($aktueller_tachostand !== null) ? number_format($aktueller_tachostand, 0, ',', '.') : 'Keine Daten'; ?> km</p>
            <p><strong>Fahrleistung:</strong> <?php echo ($fahrleistung !== null) ? number_format($fahrleistung, 0, ',', '.') : 'Keine Daten'; ?> km</p>
            
            <!-- Kraftstoffverbrauch -->
            <h3>Kraftstoffverbrauch</h3>
            <p><strong>Gesamt:</strong> <?php echo number_format(berechneGesamtverbrauch($fahrzeug_id), 2, ',', '.'); ?> l/100km</p>
            <p><strong>Aktuell:</strong> <?php echo number_format(berechneAktuellenVerbrauch($fahrzeug_id), 2, ',', '.'); ?> l/100km <?php echo zeigeVerbrauchstendenz($fahrzeug_id); ?></p>
            
            <!-- Kosten per Kilometer -->
            <h3>Kosten per Kilometer</h3>
            <p><strong>Gesamt:</strong> <?php echo number_format(berechneKostenProKmGesamt($fahrzeug_id), 2, ',', '.'); ?> €/km</p>
            <p><strong>Tanken:</strong> <?php echo number_format(berechneKraftstoffkostenProKm($fahrzeug_id), 2, ',', '.'); ?> €/km</p>
        </div>
    </div>
    <!-- Bearbeiten-Button -->
    <a href="fahrzeug_bearbeiten.php?id=<?php echo $fahrzeug_id; ?>" class="btn btn-primary mt-3"><i class="fas fa-edit"></i> Bearbeiten</a>
</div>


