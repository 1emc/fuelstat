<?php
// Fahrzeug-Einstellungen
$kraftstoffTexte = getKraftstoffOptionen() + ['petrol' => 'Benzin'];
$fuelTypeWert = $fahrzeug['fuel_type'] ?? '';
$zeilen = [
    'Name' => $fahrzeug['name'] ?? '',
    'Antriebsart' => $kraftstoffTexte[$fuelTypeWert] ?? $fuelTypeWert,
    'Marke' => $fahrzeug['brand'] ?? '',
    'Modell' => $fahrzeug['model'] ?? '',
    'Baujahr' => $fahrzeug['year'] ?? '',
    'Tachostand bei Anschaffung' => isset($fahrzeug['odometer_km']) ? number_format((int)$fahrzeug['odometer_km'], 0, ',', '.') . ' km' : '',
    ($fuelTypeWert === 'phev' ? 'Tank' : $begriffe['speicher']) . 'größe' => !empty($fahrzeug['tank_capacity']) ? number_format((float)$fahrzeug['tank_capacity'], 1, ',', '.') . ' ' . $einheitKurz : '',
];
// Plug-in-Hybrid: Akku zusätzlich zum Tank
if ($fuelTypeWert === 'phev') {
    $zeilen['Akkugröße'] = !empty($fahrzeug['battery_capacity']) ? number_format((float)$fahrzeug['battery_capacity'], 1, ',', '.') . ' kWh' : '';
}
?>
<div class="mt-4">
    <h3>Einstellungen</h3>
    <table class="table table-sm w-auto">
        <tbody>
        <?php foreach ($zeilen as $label => $wert): ?>
            <tr>
                <th class="pe-4"><?= htmlspecialchars($label) ?></th>
                <td><?= $wert !== '' && $wert !== null ? htmlspecialchars((string)$wert) : '<span class="text-muted">–</span>' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <a href="../pages/fahrzeug_bearbeiten.php?id=<?= htmlspecialchars(urlencode($fahrzeug_id)) ?>" class="btn btn-primary">
        <i class="fas fa-edit"></i> Fahrzeug bearbeiten
    </a>
</div>
