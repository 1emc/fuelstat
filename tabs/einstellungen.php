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
// Zusatzangaben (#45)
$datum = fn($d) => !empty($d) ? date('d.m.Y', strtotime($d)) : '';
$euro = fn($v) => $v !== null && $v !== '' ? number_format((float)$v, 2, ',', '.') . ' €' : '';
$monatsName = fn($m) => $m ? [1 => 'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'][(int)$m] : '';
$intervall = function ($monate, $km) {
    $teile = array_filter([$monate ? $monate . ' Monate' : '', $km ? number_format((int)$km, 0, ',', '.') . ' km' : '']);
    return implode(' oder ', $teile);
};
$zeilen += [
    'Kennzeichen' => $fahrzeug['license_plate'] ?? '',
    'FIN' => $fahrzeug['vin'] ?? '',
    'Erstzulassung' => $datum($fahrzeug['first_registration'] ?? null),
    'Kauf' => implode(' · ', array_filter([$datum($fahrzeug['purchase_date'] ?? null), $euro($fahrzeug['purchase_price'] ?? null)])),
    'Verkauf' => implode(' · ', array_filter([$datum($fahrzeug['sale_date'] ?? null), $euro($fahrzeug['sale_price'] ?? null)])),
    'Wartung' => $intervall($fahrzeug['service_interval_months'] ?? null, $fahrzeug['service_interval_km'] ?? null) ?: 'Standard: 12 Monate oder 15.000 km',
    'Ölwechsel' => $intervall($fahrzeug['oil_interval_months'] ?? null, $fahrzeug['oil_interval_km'] ?? null),
    'Versicherung' => implode(' · ', array_filter([$fahrzeug['insurance_company'] ?? '', $euro($fahrzeug['insurance_premium'] ?? null), ($m = $monatsName($fahrzeug['insurance_due_month'] ?? null)) ? 'fällig im ' . $m : ''])),
    'Kfz-Steuer' => implode(' · ', array_filter([$euro($fahrzeug['tax_amount'] ?? null), ($m = $monatsName($fahrzeug['tax_due_month'] ?? null)) ? 'fällig im ' . $m : ''])),
    'Reifen' => implode(' · ', array_filter([!empty($fahrzeug['tire_size_summer']) ? 'Sommer ' . $fahrzeug['tire_size_summer'] : '', !empty($fahrzeug['tire_size_winter']) ? 'Winter ' . $fahrzeug['tire_size_winter'] : ''])),
];
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
