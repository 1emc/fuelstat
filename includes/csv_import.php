<?php
/**
 * Hilfsfunktionen für den CSV-Import (pages/import.php).
 * Ohne API-Zugriff, damit sie sich einzeln testen lassen (tests/CsvImportTest.php).
 */

/** Felder, denen CSV-Spalten zugeordnet werden können, mit Anzeigename. */
function importFelder(): array
{
    return [
        'datum' => 'Datum *',
        'art' => 'Art / Kategorie',
        'tachostand' => 'Tachostand (km)',
        'menge' => 'Menge (l/kWh/kg)',
        'kosten' => 'Kosten gesamt (€)',
        'preis' => 'Preis pro Einheit (€)',
        'vollgetankt' => 'Voll getankt bzw. geladen',
        'luecke' => 'Lücke davor (vorheriger Tank-/Ladevorgang fehlt)',
        'ort' => 'Tankstelle / Ladestation / Ort',
        'beschreibung' => 'Beschreibung / Notiz',
    ];
}

/** Spaltenüberschrift vereinheitlichen: klein, ohne Klammerzusatz, nur Buchstaben/Ziffern. */
function importNormalisiereKopf(string $kopf): string
{
    $kopf = mb_strtolower(trim($kopf), 'UTF-8');
    $kopf = preg_replace('/\(.*?\)/u', '', $kopf);
    $kopf = strtr($kopf, ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss']);
    return preg_replace('/[^a-z0-9]/', '', $kopf);
}

/**
 * Spalten automatisch zuordnen anhand üblicher Überschriften (Fuelstat-Export, Spritmonitor, Excel-Listen).
 * @return array Feld => Spaltenindex
 */
function importErkenneZuordnung(array $kopfzeile): array
{
    $synonyme = [
        'datum' => ['datum', 'date', 'tankdatum', 'ladedatum', 'tag'],
        'art' => ['art', 'typ', 'type', 'kategorie', 'category', 'eintragstyp'],
        'tachostand' => ['tachostand', 'kilometerstand', 'kmstand', 'odometer', 'tacho', 'km', 'odometerkm'],
        'menge' => ['menge', 'liter', 'litre', 'liters', 'amount', 'quantity', 'tankmenge', 'kwh', 'getankt', 'geladen', 'energie', 'lademenge'],
        'kosten' => ['kosten', 'gesamtpreis', 'betrag', 'preisgesamt', 'gesamtkosten', 'total', 'cost', 'costs', 'summe', 'preis'],
        'preis' => ['preisproeinheit', 'preisproliter', 'literpreis', 'preisl', 'priceperliter', 'priceperunit', 'einzelpreis', 'preisprokwh', 'kwhpreis', 'strompreis', 'preiskwh'],
        'vollgetankt' => ['vollgetankt', 'vollgeladen', 'voll', 'full', 'volltank', 'isfull', 'vollgetanktbzwgeladen'],
        'luecke' => ['lueckedavor', 'luecke', 'skipprevious', 'vorherigeignorieren', 'vorherigetankfuellungignorieren'],
        'ort' => ['ort', 'tankstelle', 'ladestation', 'ladesaeule', 'station', 'ortanbieter', 'anbieter', 'vendor', 'location', 'standort'],
        'beschreibung' => ['beschreibung', 'notiz', 'bemerkung', 'note', 'notes', 'description', 'kommentar', 'comment'],
    ];
    $zuordnung = [];
    foreach ($kopfzeile as $index => $kopf) {
        $norm = importNormalisiereKopf((string)$kopf);
        foreach ($synonyme as $feld => $liste) {
            if (!isset($zuordnung[$feld]) && in_array($norm, $liste, true)) {
                $zuordnung[$feld] = $index;
                break;
            }
        }
    }
    return $zuordnung;
}

/**
 * CSV-Text einlesen: BOM entfernen, Windows-1252 nach UTF-8 wandeln, Trennzeichen (; , Tab) erkennen.
 * @return array ['kopf' => array, 'zeilen' => array]
 */
function importLeseCsv(string $inhalt): array
{
    $inhalt = preg_replace('/^\xEF\xBB\xBF/', '', $inhalt);
    if (!mb_check_encoding($inhalt, 'UTF-8')) {
        $inhalt = mb_convert_encoding($inhalt, 'UTF-8', 'Windows-1252');
    }
    $ersteZeile = strtok($inhalt, "\r\n") ?: '';
    $trenner = ';';
    $anzahl = -1;
    foreach ([';', ',', "\t"] as $kandidat) {
        $n = substr_count($ersteZeile, $kandidat);
        if ($n > $anzahl) {
            $anzahl = $n;
            $trenner = $kandidat;
        }
    }

    $stream = fopen('php://memory', 'r+');
    fwrite($stream, $inhalt);
    rewind($stream);
    $kopf = null;
    $zeilen = [];
    while (($zeile = fgetcsv($stream, 0, $trenner, '"', '')) !== false) {
        if ($zeile === [null] || implode('', array_map('trim', $zeile)) === '') {
            continue;
        }
        if ($kopf === null) {
            $kopf = array_map('trim', $zeile);
        } else {
            $zeilen[] = $zeile;
        }
    }
    fclose($stream);
    return ['kopf' => $kopf ?? [], 'zeilen' => $zeilen];
}

/** Dezimalzahl aus "1.234,56", "1234.56", "41,2 l", "75,31 €" usw. */
function importZahl(?string $wert): ?float
{
    $wert = trim((string)$wert);
    $wert = preg_replace('/[^0-9,.\-]/', '', $wert);
    if ($wert === '' || $wert === '-') {
        return null;
    }
    $komma = strrpos($wert, ',');
    $punkt = strrpos($wert, '.');
    if ($komma !== false && $punkt !== false) {
        // Das zuletzt stehende Zeichen ist das Dezimaltrennzeichen
        $wert = $komma > $punkt
            ? str_replace(['.', ','], ['', '.'], $wert)
            : str_replace(',', '', $wert);
    } elseif ($komma !== false) {
        $wert = str_replace(',', '.', $wert);
    }
    return is_numeric($wert) ? (float)$wert : null;
}

/** Ganzzahl (Tachostand): Tausenderpunkte und Einheiten werden ignoriert, Nachkommastellen abgeschnitten. */
function importGanzzahl(?string $wert): ?int
{
    $wert = trim((string)$wert);
    $wert = preg_replace('/[,.]\d{1,2}$/', '', $wert);
    $ziffern = preg_replace('/\D/', '', $wert);
    return $ziffern === '' ? null : (int)$ziffern;
}

/** Datum aus 31.12.2025, 31.12.25, 2025-12-31, 31/12/2025 (optional mit Uhrzeit) als Y-m-d. */
function importDatum(?string $wert): ?string
{
    $wert = trim((string)$wert);
    if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})/', $wert, $m)) {
        [$j, $mo, $t] = [(int)$m[1], (int)$m[2], (int)$m[3]];
    } elseif (preg_match('/^(\d{1,2})[.\/](\d{1,2})[.\/](\d{2,4})/', $wert, $m)) {
        [$t, $mo, $j] = [(int)$m[1], (int)$m[2], (int)$m[3]];
        if ($j < 100) {
            $j += $j > 69 ? 1900 : 2000;
        }
    } else {
        return null;
    }
    return checkdate($mo, $t, $j) ? sprintf('%04d-%02d-%02d', $j, $mo, $t) : null;
}

/** Ja/Nein-Werte; leere Angaben ergeben $standard. */
function importJaNein(?string $wert, bool $standard = true): bool
{
    $wert = mb_strtolower(trim((string)$wert), 'UTF-8');
    if ($wert === '') {
        return $standard;
    }
    return !in_array($wert, ['nein', 'no', 'n', '0', 'false', 'falsch', 'teil', 'teilweise', 'partial'], true);
}

/**
 * Art/Kategorie einer Zeile bestimmen.
 * @return array ['typ' => 'fillup'|'entry', 'kategorie' => string]
 */
function importArt(?string $wert, ?float $menge, string $standardKategorie): array
{
    $norm = importNormalisiereKopf((string)$wert);
    $tank = ['tankfuellung', 'tanken', 'tankstopp', 'laden', 'ladevorgang', 'fuel', 'refuel', 'refueling', 'fillup', 'kraftstoff'];
    if (in_array($norm, $tank, true) || ($norm === '' && $menge !== null && $menge > 0)) {
        return ['typ' => 'fillup', 'kategorie' => 'Tankfuellung'];
    }
    $bekannt = [
        'versicherung' => 'Versicherung', 'werkstatt' => 'Werkstatt', 'inspektion' => 'Inspektion',
        'steuer' => 'Steuer', 'kfzsteuer' => 'Steuer', 'reparatur' => 'Reparatur', 'reifen' => 'Reifen',
        'tuev' => 'TUV', 'tuv' => 'TUV', 'hu' => 'TUV', 'wartung' => 'Wartung', 'kundendienst' => 'Wartung',
        'dekor' => 'Dekor', 'fahrt' => 'Fahrt',
    ];
    if ($norm === '') {
        return ['typ' => 'entry', 'kategorie' => $standardKategorie];
    }
    return ['typ' => 'entry', 'kategorie' => $bekannt[$norm] ?? trim((string)$wert)];
}

/**
 * Alle Zeilen anhand der Zuordnung umwandeln.
 * @return array ['fillups' => [...], 'entries' => [...], 'fehler' => [zeilennummer => meldung], 'vorschau' => [...]]
 */
function importWandleUm(array $zeilen, array $zuordnung, string $standardKategorie): array
{
    $hole = fn(array $zeile, string $feld) => isset($zuordnung[$feld]) && $zuordnung[$feld] !== '' ? ($zeile[(int)$zuordnung[$feld]] ?? null) : null;
    $ergebnis = ['fillups' => [], 'entries' => [], 'fehler' => [], 'vorschau' => []];

    foreach ($zeilen as $i => $zeile) {
        $nr = $i + 2; // Zeilennummer in der Datei (1 = Kopfzeile)
        $datum = importDatum($hole($zeile, 'datum'));
        $menge = importZahl($hole($zeile, 'menge'));
        $kosten = importZahl($hole($zeile, 'kosten'));
        $preis = importZahl($hole($zeile, 'preis'));
        $tacho = importGanzzahl($hole($zeile, 'tachostand'));
        $art = importArt($hole($zeile, 'art'), $menge, $standardKategorie);
        $ort = trim((string)$hole($zeile, 'ort'));
        $beschreibung = trim((string)$hole($zeile, 'beschreibung'));

        if ($kosten === null && $menge !== null && $preis !== null) {
            $kosten = round($menge * $preis, 2);
        }

        $fehler = null;
        if ($datum === null) {
            $fehler = 'Datum fehlt oder ist ungültig';
        } elseif ($art['typ'] === 'fillup' && $tacho === null) {
            $fehler = 'Tank-/Ladevorgang ohne Tachostand';
        } elseif ($art['typ'] === 'fillup' && ($menge === null || $menge < 0)) {
            $fehler = 'Tank-/Ladevorgang ohne gültige Menge';
        } elseif ($kosten === null || $kosten < 0) {
            $fehler = 'Kosten fehlen oder sind ungültig';
        }

        $vorschau = [
            'nr' => $nr, 'datum' => $datum, 'kategorie' => $art['kategorie'], 'tachostand' => $tacho,
            'menge' => $art['typ'] === 'fillup' ? $menge : null, 'kosten' => $kosten,
            'vollgetankt' => $art['typ'] === 'fillup' ? importJaNein($hole($zeile, 'vollgetankt')) : null,
            'ort' => $ort, 'beschreibung' => $beschreibung, 'fehler' => $fehler,
        ];
        $ergebnis['vorschau'][] = $vorschau;
        if ($fehler !== null) {
            $ergebnis['fehler'][$nr] = $fehler;
            continue;
        }

        $zeitpunkt = $datum . 'T12:00:00.000Z';
        if ($art['typ'] === 'fillup') {
            $ergebnis['fillups'][] = [
                'filledAt' => $zeitpunkt,
                'odometerKm' => $tacho,
                'amount' => $menge,
                'totalCostEur' => $kosten,
                'station' => $ort !== '' ? $ort : null,
                'isFull' => $vorschau['vollgetankt'],
                'skipPrevious' => importJaNein($hole($zeile, 'luecke'), false),
            ];
        } else {
            $ergebnis['entries'][] = [
                'occurredAt' => $zeitpunkt,
                'category' => $art['kategorie'],
                'amountEur' => $kosten,
                'odometerKm' => $tacho,
                'vendor' => $ort !== '' ? $ort : null,
                'description' => $beschreibung !== '' ? $beschreibung : null,
            ];
        }
    }
    return $ergebnis;
}
