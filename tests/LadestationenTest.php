<?php
// Aufbereitung der Open-Charge-Map-Antwort (Struktur nach OCM.API.Model: ChargePoint, AddressInfo, ConnectionInfo)
require __DIR__ . '/../includes/ladestationen.php';

$pois = [
    [
        'ID' => 2,
        'UsageCost' => '0,59 €/kWh',
        'NumberOfPoints' => 4,
        'OperatorInfo' => ['Title' => 'EnBW mobility+'],
        'StatusType' => ['IsOperational' => true, 'Title' => 'Operational'],
        'AddressInfo' => ['Title' => 'EnBW HPC Ellwangen', 'AddressLine1' => 'Rotenbacher Str. 1', 'Town' => 'Ellwangen', 'Distance' => 12.345, 'Latitude' => 48.96, 'Longitude' => 10.13],
        'Connections' => [
            ['ConnectionType' => ['Title' => 'CCS (Type 2)'], 'PowerKW' => 300.0, 'Quantity' => 4],
            ['ConnectionType' => ['Title' => 'Type 2 (Socket Only)'], 'PowerKW' => 22.0, 'Quantity' => 1],
        ],
    ],
    [
        'ID' => 1,
        'OperatorInfo' => ['Title' => '(Unknown Operator)'],
        'StatusType' => ['IsOperational' => false],
        'AddressInfo' => ['Title' => 'Rathausplatz', 'Town' => 'Bopfingen', 'Distance' => 0.8],
        'Connections' => [
            ['ConnectionType' => ['Title' => 'Type 2 (Socket Only)'], 'PowerKW' => 11.0, 'Quantity' => 2],
        ],
    ],
    'kaputt',
];

$s = ocmStationenAufbereiten($pois);

assert(count($s) === 2, 'zwei gültige Stationen');
assert($s[0]['name'] === 'Rathausplatz', 'nach Entfernung sortiert');
assert($s[0]['betreiber'] === '', 'unbekannter Betreiber wird ausgeblendet');
assert($s[0]['in_betrieb'] === false, 'außer Betrieb erkannt');
assert($s[0]['punkte'] === 2, 'Ladepunkte aus Quantity, wenn NumberOfPoints fehlt');
assert($s[1]['max_kw'] === 300.0, 'höchste Leistung');
assert($s[1]['stecker'] === ['CCS', 'Typ 2'], 'Stecker kurz und deutsch');
assert($s[1]['entfernung'] === 12.3, 'Entfernung auf 0,1 km gerundet');
assert($s[1]['kosten'] === '0,59 €/kWh', 'Kostenhinweis übernommen');
assert($s[1]['ort'] === 'Ellwangen' && $s[1]['adresse'] === 'Rotenbacher Str. 1', 'Adresse');

assert(ocmSteckerName('CHAdeMO') === 'CHAdeMO');
assert(ocmSteckerName('Europlug 2-Pin (CEE 7/16)') === 'Eurostecker');
assert(ocmSteckerName('CEE 7/4 - Schuko - Type F') === 'Schuko');
assert(ocmSteckerName('IEC 60309 3-pin (CEE blue)') === 'CEE');
assert(ocmSteckerName('Tesla (Model S/X)') === 'Tesla');

echo "All tests passed\n";
