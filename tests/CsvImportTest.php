<?php
require __DIR__ . '/../includes/csv_import.php';

// Zahlen
assert(importZahl('1.234,56') === 1234.56);
assert(importZahl('1,234.56') === 1234.56);
assert(importZahl('41,2 l') === 41.2);
assert(importZahl('75.31 €') === 75.31);
assert(importZahl('') === null);
assert(importGanzzahl('39.945') === 39945);
assert(importGanzzahl('39945,5') === 39945);
assert(importGanzzahl('39 945 km') === 39945);

// Datum
assert(importDatum('31.12.2025') === '2025-12-31');
assert(importDatum('1.2.25') === '2025-02-01');
assert(importDatum('2025-12-31 14:30') === '2025-12-31');
assert(importDatum('31/12/2025') === '2025-12-31');
assert(importDatum('31.02.2025') === null);

// Ja/Nein und Art
assert(importJaNein('') === true);
assert(importJaNein('nein') === false);
assert(importJaNein('', false) === false);
assert(importJaNein('ja', false) === true);
assert(importArt('Tankfüllung', null, 'Werkstatt')['typ'] === 'fillup');
assert(importArt('', 40.0, 'Werkstatt')['typ'] === 'fillup');
assert(importArt('TÜV', null, 'Werkstatt')['kategorie'] === 'TUV');
assert(importArt('', null, 'Werkstatt')['kategorie'] === 'Werkstatt');

// Eigener Export wird wieder erkannt (Windows-1252, Semikolon, BOM-los)
$csv = "Datum;Kategorie;Tachostand (km);Menge (l);Kosten (EUR);Vollgetankt;Verbrauch (l/100km);Ort/Anbieter;Beschreibung\r\n"
     . "05.01.2026;Tankfüllung;38.337;33,50;61,20;ja;7,94;SB Tankstelle;\r\n"
     . "02.01.2026;Versicherung;;;420,00;;;HUK;Jahresbeitrag\r\n"
     . "20.02.2026;Tankfüllung;40.200;20,00;36,50;nein;;;\r\n"
     . "kaputt;Tankfüllung;1;1;1;;;;\r\n";
$daten = importLeseCsv(mb_convert_encoding($csv, 'Windows-1252', 'UTF-8'));
assert(count($daten['zeilen']) === 4);
$zuordnung = importErkenneZuordnung($daten['kopf']);
assert($zuordnung === ['datum' => 0, 'art' => 1, 'tachostand' => 2, 'menge' => 3, 'kosten' => 4, 'vollgetankt' => 5, 'ort' => 7, 'beschreibung' => 8]);
$r = importWandleUm($daten['zeilen'], $zuordnung, 'Werkstatt');
assert(count($r['fillups']) === 2);
assert(count($r['entries']) === 1);
assert($r['fehler'] === [5 => 'Datum fehlt oder ist ungültig']);
assert($r['fillups'][0] === ['filledAt' => '2026-01-05T12:00:00.000Z', 'odometerKm' => 38337, 'amount' => 33.5, 'totalCostEur' => 61.2, 'station' => 'SB Tankstelle', 'isFull' => true, 'skipPrevious' => false]);
assert($r['fillups'][1]['isFull'] === false);
assert($r['entries'][0]['category'] === 'Versicherung' && $r['entries'][0]['odometerKm'] === null && $r['entries'][0]['vendor'] === 'HUK');

// Kosten aus Menge x Preis, Komma-getrennt mit englischen Überschriften
$daten = importLeseCsv("date,odometer,liters,price per liter\n2025-03-01,1000,40,1.75\n");
$r = importWandleUm($daten['zeilen'], importErkenneZuordnung($daten['kopf']), 'Werkstatt');
assert($r['fillups'][0]['totalCostEur'] === 70.0);

echo "All tests passed\n";
