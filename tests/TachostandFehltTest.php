<?php
// Tank- und Ladevorgänge ohne Tachostand (#41): zählen bei Kosten und Menge, schließen aber keinen
// Verbrauchsabschnitt ab; ihre Menge fließt in den nächsten vollen Abschnitt mit Tachostand ein.
require __DIR__ . '/../includes/api_helpers.php';

class TestApi
{
    public function __construct(private array $fillups) {}
    public function getAllVehicleFillups($id) { return $this->fillups; }
    public function getAllVehicleEntries($id) { return []; }
    public function getVehicleStats($id) { return []; }
}

$f = fn($id, $datum, $km, $menge, $voll = true, $einheit = 'l', $geschaetzt = false) => [
    'id' => $id, 'filled_at' => $datum . 'T12:00:00Z', 'odometer_km' => $km, 'odometer_estimated' => $geschaetzt,
    'amount' => $menge, 'unit' => $einheit, 'total_cost_eur' => $menge * 2, 'is_full' => $voll,
];
$verbrauch = fn(array $fillups) => getStatistikDatenFromApi(new TestApi($fillups), 'x');
$nah = fn($a, $b) => $a !== null && abs($a - $b) < 0.01;

// 1. Volle Füllung ohne Tachostand zwischen zwei vollen mit Tachostand: ein Abschnitt 1000 → 2000 mit 30 + 40 l
$d = $verbrauch([
    $f('3', '2025-03-20', 2000, 40.0),
    $f('2', '2025-03-10', null, 30.0),
    $f('1', '2025-03-01', 1000, 50.0),
]);
assert(count($d['verbrauchswerte']) === 1, 'ein Abschnitt erwartet, nicht ' . count($d['verbrauchswerte']));
assert($nah($d['verbrauchswerte']['2025-03-20'] ?? null, 70.0 / 1000 * 100), 'Menge ohne Tachostand zählt zum nächsten Abschnitt');
assert($d['jahresdaten'][2025]['anzahl_tankstops'] === 3, 'Eintrag ohne Tachostand zählt als Tankstopp');
assert($nah($d['jahresdaten'][2025]['gekauft_ausgaben'], 240.0), 'Kosten des Eintrags ohne Tachostand zählen mit');

// 2. Geschätzter Tachostand ist nur Platzhalter: gleiches Ergebnis wie ohne Tachostand
$d = $verbrauch([
    $f('3', '2025-03-20', 2000, 40.0),
    $f('2', '2025-03-10', 1450, 30.0, true, 'l', true),
    $f('1', '2025-03-01', 1000, 50.0),
]);
assert(count($d['verbrauchswerte']) === 1 && $nah($d['verbrauchswerte']['2025-03-20'], 7.0), 'geschätzter Tachostand schließt keinen Abschnitt ab');

// 3. Tag mit drei Einträgen, einer ohne Tachostand: er steht hinter denen mit Tachostand,
//    seine Menge zählt also zum nächsten Abschnitt (nicht zu dem, der an diesem Tag endet)
$d = $verbrauch([
    $f('5', '2025-04-10', 1800, 45.0),
    $f('4', '2025-04-01', null, 5.0, false),
    $f('3', '2025-04-01', 1500, 20.0),
    $f('2', '2025-04-01', 1400, 8.0, false),
    $f('1', '2025-03-25', 1000, 50.0),
]);
assert($nah($d['verbrauchswerte']['2025-04-01'] ?? null, 28.0 / 500 * 100), 'Abschnitt bis 1.500 km: 8 + 20 l, nicht ' . var_export($d['verbrauchswerte']['2025-04-01'] ?? null, true));
assert($nah($d['verbrauchswerte']['2025-04-10'] ?? null, 50.0 / 300 * 100), 'Abschnitt bis 1.800 km: 5 + 45 l');

// 4. Plug-in-Hybrid: Tanken ohne Tachostand, Laden mit Tachostand am selben Abend – Einheiten getrennt
$d = $verbrauch([
    $f('6', '2025-05-20', 3000, 12.0, true, 'kwh'),
    $f('5', '2025-05-20', 3000, 20.0, true, 'l'),
    $f('4', '2025-05-10', 2500, 10.0, true, 'kwh'),
    $f('3', '2025-05-10', null, 15.0, true, 'l'),
    $f('2', '2025-05-01', 2000, 11.0, true, 'kwh'),
    $f('1', '2025-05-01', 2000, 30.0, true, 'l'),
]);
$l = $d['einheiten']['l']['verbrauchswerte'];
$kwh = $d['einheiten']['kwh']['verbrauchswerte'];
assert(count($l) === 1 && $nah($l['2025-05-20'], 35.0 / 1000 * 100), 'Liter: ein Abschnitt 2.000 → 3.000 km mit 15 + 20 l');
assert(count($kwh) === 2 && $nah($kwh['2025-05-10'], 10.0 / 500 * 100) && $nah($kwh['2025-05-20'], 12.0 / 500 * 100), 'kWh unverändert');

// 5. Nachtragen des Tachostands teilt den Abschnitt wieder auf
$d = $verbrauch([
    $f('3', '2025-03-20', 2000, 40.0),
    $f('2', '2025-03-10', 1450, 30.0),
    $f('1', '2025-03-01', 1000, 50.0),
]);
assert(count($d['verbrauchswerte']) === 2, 'zwei Abschnitte nach dem Nachtragen');
assert($nah($d['verbrauchswerte']['2025-03-10'], 30.0 / 450 * 100) && $nah($d['verbrauchswerte']['2025-03-20'], 40.0 / 550 * 100), 'Abschnitte nach dem Nachtragen');

// 6. Hilfsfunktion
assert(hatEchtenTachostand(['tachostand' => 0]) === true, '0 km ist ein echter Wert');
assert(hatEchtenTachostand(['tachostand' => null]) === false);
assert(hatEchtenTachostand(['tachostand' => 1200, 'tachostand_geschaetzt' => true]) === false);

echo "All tests passed\n";
