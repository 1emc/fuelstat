<?php
// Mehrere Ladungen am selben Tag (z. B. Hin- und Rückfahrt) dürfen den Verbrauch nicht verfälschen
require __DIR__ . '/../includes/api_helpers.php';

class TestApi
{
    public function __construct(private array $fillups) {}
    public function getAllVehicleFillups($id) { return $this->fillups; }
    public function getAllVehicleEntries($id) { return []; }
    public function getVehicleStats($id) { return []; }
}

$f = fn($id, $datum, $km, $kwh, $voll = true) => ['id' => $id, 'filled_at' => $datum . 'T12:00:00Z', 'odometer_km' => $km, 'amount' => $kwh, 'total_cost_eur' => 0, 'is_full' => $voll];
// API liefert neueste zuerst; am 10.06. zwei Ladungen auf der Fahrt, am 11.06. eine Zwischen- und eine Vollladung
$api = new TestApi([
    $f('6', '2025-06-11', 1440, 12.0),
    $f('5', '2025-06-11', 1420, 8.0, false),
    $f('4', '2025-06-10', 1300, 24.0),
    $f('3', '2025-06-10', 1150, 25.0),
    $f('2', '2025-06-08', 1000, 20.0),
    $f('1', '2025-06-01', 850, 22.0),
]);

$d = getStatistikDatenFromApi($api, 'x');
$werte = $d['verbrauchswerte'];

assert(count($werte) === 4, 'vier Verbrauchswerte erwartet, nicht ' . count($werte));
assert(abs($werte['2025-06-10'] - 25.0 / 150 * 100) < 0.01, 'erste Ladung am 10.06.');
assert(abs($werte['2025-06-10 #2'] - 24.0 / 150 * 100) < 0.01, 'zweite Ladung am 10.06.');
assert(abs($werte['2025-06-11'] - 20.0 / 140 * 100) < 0.01, 'Zwischenladung + Vollladung am 11.06.');
// Gesamt: alle kWh nach der ersten Vollladung / Strecke
$summe = $d['jahresdaten'][2025]['verbrauch_menge'] ?? null;
assert($summe !== null && abs($summe - 89.0) < 0.01, 'verbrauchte Menge 89 kWh, nicht ' . var_export($summe, true));

echo "All tests passed\n";
