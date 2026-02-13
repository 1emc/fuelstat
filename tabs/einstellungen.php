<?php
// Fahrzeug-Einstellungen (API: name, fuelType)
?>
<div class="mt-4">
    <h3>Einstellungen</h3>
    <p>Name: <strong><?= htmlspecialchars($fahrzeug['name'] ?? trim($fahrzeug['marke'] . ' ' . $fahrzeug['modell'])) ?></strong></p>
    <p>Kraftstoff: <strong><?= htmlspecialchars($fahrzeug['fuel_type'] ?? $fahrzeug['kraftstoff'] ?? '') ?></strong></p>
    <a href="../pages/fahrzeug_bearbeiten.php?id=<?= htmlspecialchars(urlencode($fahrzeug_id)) ?>" class="btn btn-primary">
        <i class="fas fa-edit"></i> Fahrzeug bearbeiten
    </a>
</div>
