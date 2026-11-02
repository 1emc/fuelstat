<?php
// Reiter "Einstellungen" (#48): Fahrzeugangaben als Karten, gruppiert wie das Bearbeiten-Formular.
// "Bearbeiten" springt direkt in den passenden Abschnitt; Löschen steht abgesetzt in der Gefahrenzone.
$kraftstoffTexte = getKraftstoffOptionen() + ['petrol' => 'Benzin'];
$fuelTypeWert = $fahrzeug['fuel_type'] ?? '';
$datum = fn($d) => !empty($d) ? date('d.m.Y', strtotime($d)) : '';
$euro = fn($v) => $v !== null && $v !== '' ? number_format((float)$v, 2, ',', '.') . ' €' : '';
$zahl = fn($v, $einheit) => !empty($v) ? number_format((float)$v, fmod((float)$v, 1) ? 1 : 0, ',', '.') . ' ' . $einheit : '';
$monatsName = fn($m) => $m ? [1 => 'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'][(int)$m] : '';
$intervall = function ($monate, $km) {
    return implode(' oder ', array_filter([$monate ? $monate . ' Monate' : '', $km ? number_format((int)$km, 0, ',', '.') . ' km' : '']));
};
$mitMonat = fn(array $teile, $m) => implode(' · ', array_filter(array_merge($teile, [($n = $monatsName($m)) ? 'fällig im ' . $n : ''])));

$gruppen = [
    'grunddaten' => ['Grunddaten', 'fa-car-side', [
        'Name' => $fahrzeug['name'] ?? '',
        'Kennzeichen' => $fahrzeug['license_plate'] ?? '',
        'Marke und Modell' => trim(($fahrzeug['brand'] ?? '') . ' ' . ($fahrzeug['model'] ?? '')),
        'Baujahr' => $fahrzeug['year'] ?? '',
        'Erstzulassung' => $datum($fahrzeug['first_registration'] ?? null),
        'FIN' => $fahrzeug['vin'] ?? '',
    ]],
    'technik' => ['Technik', 'fa-gears', array_merge([
        'Antriebsart' => $kraftstoffTexte[$fuelTypeWert] ?? $fuelTypeWert,
        ($fuelTypeWert === 'phev' ? 'Tank' : $begriffe['speicher']) . 'größe' => $zahl($fahrzeug['tank_capacity'] ?? null, $einheitKurz),
    ], $fuelTypeWert === 'phev' ? ['Akkugröße' => $zahl($fahrzeug['battery_capacity'] ?? null, 'kWh')] : [], [
        'Tachostand bei Anschaffung' => isset($fahrzeug['odometer_km']) ? number_format((int)$fahrzeug['odometer_km'], 0, ',', '.') . ' km' : '',
        'Reifen Sommer' => $fahrzeug['tire_size_summer'] ?? '',
        'Reifen Winter' => $fahrzeug['tire_size_winter'] ?? '',
    ])],
    'termine' => ['Kauf und Termine', 'fa-calendar-check', [
        'Kauf' => implode(' · ', array_filter([$datum($fahrzeug['purchase_date'] ?? null), $euro($fahrzeug['purchase_price'] ?? null)])),
        'Verkauf' => implode(' · ', array_filter([$datum($fahrzeug['sale_date'] ?? null), $euro($fahrzeug['sale_price'] ?? null)])),
        'Wartung' => $intervall($fahrzeug['service_interval_months'] ?? null, $fahrzeug['service_interval_km'] ?? null) ?: 'Standard: 12 Monate oder 15.000 km',
        'Ölwechsel' => $intervall($fahrzeug['oil_interval_months'] ?? null, $fahrzeug['oil_interval_km'] ?? null),
        'Versicherung' => $mitMonat([$fahrzeug['insurance_company'] ?? '', $euro($fahrzeug['insurance_premium'] ?? null)], $fahrzeug['insurance_due_month'] ?? null),
        'Kfz-Steuer' => $mitMonat([$euro($fahrzeug['tax_amount'] ?? null)], $fahrzeug['tax_due_month'] ?? null),
    ]],
];
$bearbeitenUrl = '../pages/fahrzeug_bearbeiten.php?id=' . urlencode($fahrzeug_id);
?>
<div class="mt-3 mt-lg-4">
    <div class="row g-3">
        <?php foreach ($gruppen as $abschnitt => [$titel, $icon, $zeilen]):
            $leer = count(array_filter($zeilen, fn($w) => $w === '' || $w === null)); ?>
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100 angaben-karte">
                <div class="card-header bg-transparent d-flex justify-content-between align-items-center py-3">
                    <h3 class="h6 mb-0"><i class="fas <?= $icon ?> me-2 text-primary"></i><?= $titel ?></h3>
                    <a href="<?= htmlspecialchars($bearbeitenUrl . '#' . $abschnitt) ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-pen me-1"></i>Bearbeiten</a>
                </div>
                <div class="card-body pb-2">
                    <dl class="row mb-0">
                        <?php foreach ($zeilen as $label => $w): ?>
                            <dt class="col-5"><?= htmlspecialchars($label) ?></dt>
                            <dd class="col-7"><?= $w !== '' && $w !== null ? htmlspecialchars((string)$w) : '<span class="text-body-tertiary">–</span>' ?></dd>
                        <?php endforeach; ?>
                    </dl>
                    <?php if ($leer >= 3): ?>
                        <p class="small text-body-secondary mb-2"><a href="<?= htmlspecialchars($bearbeitenUrl . '#' . $abschnitt) ?>">Fehlende Angaben ergänzen</a> – sie verbessern Termine und Auswertungen.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100 angaben-karte">
                <div class="card-header bg-transparent d-flex justify-content-between align-items-center py-3">
                    <h3 class="h6 mb-0"><i class="fas fa-image me-2 text-primary"></i>Bild</h3>
                    <a href="<?= htmlspecialchars($bearbeitenUrl . '#bild') ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-pen me-1"></i>Ändern</a>
                </div>
                <div class="card-body">
                    <img src="<?= htmlspecialchars(fahrzeugBildUrl($fahrzeug, $baseUrl)) ?>" alt="Fahrzeugbild" class="rounded-3 w-100 object-fit-cover" style="max-height: 180px;">
                </div>
            </div>
        </div>
    </div>

    <!-- Gefahrenzone: klar abgesetzt -->
    <div class="card border-danger-subtle mt-4 mb-4">
        <div class="card-body d-flex flex-wrap align-items-center gap-3">
            <div class="flex-grow-1">
                <h3 class="h6 text-danger mb-1"><i class="fas fa-triangle-exclamation me-2"></i>Fahrzeug löschen</h3>
                <p class="small text-body-secondary mb-0">Löscht das Fahrzeug mit allen <?= $begriffe['vorgaengen'] ?>, Ausgaben und Bildern endgültig.</p>
            </div>
            <form method="post" action="../pages/fahrzeug_loeschen.php" class="m-0"
                  onsubmit="return confirm(<?= htmlspecialchars(json_encode('„' . ($fahrzeug['name'] ?? 'Fahrzeug') . '“ mit allen Einträgen endgültig löschen?'), ENT_QUOTES) ?>);">
                <input type="hidden" name="fahrzeug_id" value="<?= htmlspecialchars($fahrzeug_id) ?>">
                <button type="submit" class="btn btn-outline-danger"><i class="fas fa-trash-alt me-1"></i>Löschen</button>
            </form>
        </div>
    </div>
</div>
