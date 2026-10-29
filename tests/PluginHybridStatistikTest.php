<?php
// Plug-in-Hybrid: Liter und kWh werden getrennt von voller Füllung zu voller Füllung gerechnet
require __DIR__ . '/../includes/api_helpers.php';
require __DIR__ . '/../includes/functions.php';

class TestApi
{
    public function __construct(private array $fillups) {}
    public function getAllVehicleFillups($id) { return $this->fillups; }
    public function getAllVehicleEntries($id) { return []; }
    public function getVehicleStats($id) { return []; }
}

$f = fn($id, $datum, $km, $menge, $unit, $kosten, $voll = true) => ['id' => $id, 'filled_at' => $datum . 'T12:00:00Z', 'odometer_km' => $km, 'amount' => $menge, 'unit' => $unit, 'total_cost_eur' => $kosten, 'is_full' => $voll];
// Abends tanken und an der Wallbox laden, auch am selben Tag beim selben Tachostand
$api = new TestApi([
    $f('7', '2026-01-20', 2000, 12.0, 'kwh', 3.36),
    $f('6', '2026-01-20', 2000, 40.0, 'l', 70.0),
    $f('5', '2026-01-10', 1400, 10.0, 'kwh', 2.80, false),
    $f('4', '2026-01-05', 1200, 15.0, 'kwh', 4.20),
    $f('3', '2026-01-02', 1050, 14.0, 'kwh', 3.92),
    $f('2', '2026-01-01', 1000, 30.0, 'l', 52.5),
    $f('1', '2026-01-01', 1000, 14.0, 'kwh', 3.92),
]);

$d = getStatistikDatenFromApi($api, 'x');
assert(array_keys($d['einheiten']) === ['l', 'kwh'], 'Kraftstoff zuerst, dann Strom');

$l = $d['einheiten']['l'];
assert(count($l['verbrauchswerte']) === 1, 'ein Abschnitt Benzin');
assert(abs($l['jahresdaten'][2026]['verbrauch_l100km'] - 40.0 / 1000 * 100) < 0.01, '4 l/100 km über 1.000 km');
assert($l['jahresdaten'][2026]['anzahl_tankstops'] === 2);

$kwh = $d['einheiten']['kwh'];
// 1000 → 1050: 14 kWh; 1050 → 1200: 15 kWh; 1200 → 2000: 10 + 12 kWh (Teilladung zählt mit)
assert(count($kwh['verbrauchswerte']) === 3, 'drei Abschnitte Strom');
assert(abs($kwh['jahresdaten'][2026]['verbrauch_menge'] - 51.0) < 0.01);
assert(abs($kwh['jahresdaten'][2026]['verbrauch_km'] - 1000) < 0.01);
assert(abs($kwh['jahresdaten'][2026]['gekauft_ausgaben'] - 18.20) < 0.01);

// Bisherige Schlüssel zeigen weiter die erste Einheit
assert($d['jahresdaten'][2026]['verbrauch_l100km'] === $l['jahresdaten'][2026]['verbrauch_l100km']);

// Begriffe und CO₂ je Eintrag
assert(getKategorieName('Tankfuellung', 'phev', 'kwh') === 'Ladevorgang');
assert(getKategorieName('Tankfuellung', 'phev', 'l') === 'Tankfüllung');
assert(getCo2Faktor('phev', 'l') === 2.37 && getCo2Faktor('phev', 'kwh') === 0.363);
assert(einheitenFuerKraftstoff('phev') === ['l', 'kwh'] && einheitenFuerKraftstoff('hybrid') === ['l']);

echo "All tests passed\n";
