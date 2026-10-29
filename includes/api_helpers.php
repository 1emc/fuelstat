<?php
/**
 * Hilfsfunktionen für die Ausgabe von API-Daten im bisherigen Anzeigeformat.
 * Stellt z.B. zusammengeführte Einträge (Fillups + Entries) und Stats bereit.
 */

require_once __DIR__ . '/api_config.php';
require_once __DIR__ . '/fuelstat_api.php';

if (!function_exists('getApiClient')) {
    function getApiClient(?string $token = null): FuelstatApi
    {
        return new FuelstatApi($token ?? getApiToken());
    }
}

/**
 * URL einer mitgelieferten Datei mit ?v=<Änderungszeit>, damit nach einem Deploy nie eine alte Version aus dem Cache kommt
 * @param string $praefix $baseUrl oder ein relativer Pfad wie '../'
 * @param string $pfad Pfad ab dem App-Verzeichnis, z. B. 'vendor/bootstrap/bootstrap.min.css'
 */
function assetUrl(string $praefix, string $pfad): string
{
    return $praefix . $pfad . '?v=' . (@filemtime(__DIR__ . '/../' . $pfad) ?: 1);
}

/**
 * Läuft die Beta-Phase? (aus /health der API, 10 Minuten in der Session zwischengespeichert)
 */
function istBetaPhase(): bool
{
    $cache = $_SESSION['beta_phase_cache'] ?? null;
    if (is_array($cache) && $cache['bis'] > time()) {
        return $cache['wert'];
    }
    try {
        $wert = (getApiClient(null)->getHealth()['beta_phase'] ?? false) === true;
    } catch (Throwable $e) {
        return false;
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION['beta_phase_cache'] = ['wert' => $wert, 'bis' => time() + 600];
    }
    return $wert;
}

/**
 * Fillups und Entries einer Fahrzeug-ID holen und zu einer einheitlichen Liste
 * mit Keys (id, datum, kategorie, menge, kosten, tachostand, vollgetankt, …) zusammenführen.
 * @return array [ 'eintraege' => [ 'Y-m' => [ ... ] ], 'jahrListe' => [ Jahre ] ]
 */
function getMergedEintraegeFromApi($api, string $vehicleId): array
{
    $fillups = [];
    $entries = [];
    try {
        $fillups = $api->getAllVehicleFillups($vehicleId);
        $entries = $api->getAllVehicleEntries($vehicleId);
    } catch (Throwable $e) {
        return ['eintraege' => [], 'jahrListe' => []];
    }

    $stats = null;
    try {
        $stats = $api->getVehicleStats($vehicleId);
    } catch (Throwable $e) {
    }

    $lPer100ByFillupId = [];
    if ($stats && !empty($stats['segments'])) {
        foreach ($stats['segments'] as $seg) {
            if (isset($seg['full_id'], $seg['l_per_100km'])) {
                $lPer100ByFillupId[$seg['full_id']] = $seg['l_per_100km'];
            }
        }
    }

    $list = [];

    foreach ($fillups as $f) {
        $datum = isset($f['filled_at']) ? date('Y-m-d', strtotime($f['filled_at'])) : '';
        $list[] = [
            'id' => $f['id'],
            'type' => 'fillup',
            'datum' => $datum,
            'kategorie' => 'Tankfuellung',
            'menge' => $f['amount'] ?? $f['liters'] ?? 0,
            'kosten' => $f['total_cost_eur'] ?? $f['price_total_eur'] ?? 0,
            'tachostand' => $f['odometer_km'] ?? 0,
            'vollgetankt' => $f['is_full'] ?? true,
            'skip_previous' => $f['skip_previous'] ?? false,
            'standort' => $f['station'] ?? '',
            'l_per_100km' => $lPer100ByFillupId[$f['id']] ?? null,
        ];
    }

    foreach ($entries as $e) {
        $datum = isset($e['occurred_at']) ? date('Y-m-d', strtotime($e['occurred_at'])) : '';
        $list[] = [
            'id' => $e['id'],
            'type' => 'entry',
            'datum' => $datum,
            'kategorie' => $e['category'] ?? 'Sonstige',
            'kosten' => $e['amount_eur'] ?? 0,
            'tachostand' => $e['odometer_km'] ?? null,
            'beschreibung' => $e['description'] ?? '',
            'standort' => $e['vendor'] ?? '',
            'menge' => null,
            'vollgetankt' => false,
        ];
    }

    usort($list, function ($a, $b) {
        $d = strcmp($b['datum'], $a['datum']);
        if ($d !== 0) return $d;
        $ta = $a['tachostand'] ?? 0;
        $tb = $b['tachostand'] ?? 0;
        return $tb - $ta;
    });

    $eintraege = [];
    $jahre = [];
    foreach ($list as $row) {
        $monat = $row['datum'] ? substr($row['datum'], 0, 7) : '';
        if ($monat) {
            $eintraege[$monat][] = $row;
            $y = (int)substr($row['datum'], 0, 4);
            if ($y && !in_array($y, $jahre, true)) {
                $jahre[] = $y;
            }
        }
    }
    rsort($jahre);

    return ['eintraege' => $eintraege, 'jahrListe' => $jahre];
}

/**
 * Stats für Anzeige (Tachostand, Verbrauch, Trend) aus API.
 */
function getVehicleStatsForDisplay($api, string $vehicleId): array
{
    try {
        return $api->getVehicleStats($vehicleId);
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Gefahrene Kilometer: aktueller Tachostand minus Anfangs-Tachostand des Fahrzeugs,
 * ersatzweise minus Tachostand der ersten erfassten Tankfüllung.
 * @return array|null [ 'km' => int, 'basis' => string ]
 */
function getFahrleistungFromApi($api, string $vehicleId, array $fahrzeug, $currentOdo): ?array
{
    if ($currentOdo === null) {
        return null;
    }
    $currentOdo = (int)$currentOdo;

    $initial = $fahrzeug['odometer_km'] ?? null;
    if ($initial !== null && (int)$initial <= $currentOdo) {
        return ['km' => $currentOdo - (int)$initial, 'basis' => 'seit Anschaffung'];
    }

    try {
        $fillups = $api->getAllVehicleFillups($vehicleId);
    } catch (Throwable $e) {
        return null;
    }
    $odos = array_filter(array_map(fn($f) => $f['odometer_km'] ?? null, $fillups), fn($o) => $o !== null);
    if (!$odos) {
        return null;
    }
    return ['km' => $currentOdo - (int)min($odos), 'basis' => 'seit dem ersten Eintrag'];
}

/**
 * Letzten Eintrag einer Kategorie für ein Fahrzeug (aus API Entries).
 */
function getLetzterEintragApi($api, string $vehicleId, string $category): ?array
{
    try {
        $entries = $api->getAllVehicleEntries($vehicleId);
        foreach ($entries as $e) {
            if (isset($e['category']) && strcasecmp($e['category'], $category) === 0) {
                return [
                    'datum' => $e['occurred_at'] ?? '',
                    'tachostand' => $e['odometer_km'] ?? null,
                    'kosten' => $e['amount_eur'] ?? 0,
                    'beschreibung' => $e['description'] ?? '',
                ];
            }
        }
    } catch (Throwable $e) {
    }
    return null;
}

/**
 * Statistik- und Ausgabendaten aus API (Fillups + Entries) für Statistiken-/Ausgaben-Tabs.
 */
function getStatistikDatenFromApi($api, string $vehicleId): array
{
    $merged = getMergedEintraegeFromApi($api, $vehicleId);
    $flat = [];
    foreach ($merged['eintraege'] as $entries) {
        foreach ($entries as $e) {
            $flat[] = $e;
        }
    }

    $verbrauchProMonat = [];
    $kostenProMonat = [];
    $verbrauchswerte = [];
    $jahresdaten = [];
    $ausgabenDaten = [];
    $detailAusgabenDaten = [];
    $verfuegbareJahre = [];

    $fillupsOnly = array_filter($flat, function ($e) {
        return ($e['kategorie'] ?? '') === 'Tankfuellung' && !empty($e['datum']);
    });
    // Bei mehreren Tankfüllungen/Ladungen am selben Tag entscheidet der Tachostand
    usort($fillupsOnly, fn($a, $b) => [$a['datum'], (float)($a['tachostand'] ?? 0)] <=> [$b['datum'], (float)($b['tachostand'] ?? 0)]);

    $letzteVoll = null;
    $mengeSeitVoll = 0.0;
    foreach ($fillupsOnly as $row) {
        $mengeSeitVoll += (float)($row['menge'] ?? 0);
        if (!empty($row['vollgetankt'])) {
            // skip_previous: nur neuer Startpunkt, kein Verbrauch über die Lücke
            if ($letzteVoll && empty($row['skip_previous']) && ($row['tachostand'] - $letzteVoll['tachostand']) > 0) {
                $km = $row['tachostand'] - $letzteVoll['tachostand'];
                $monat = substr($row['datum'], 0, 7);
                if (!isset($verbrauchProMonat[$monat])) {
                    $verbrauchProMonat[$monat] = ['liter' => 0.0, 'km' => 0];
                }
                $verbrauchProMonat[$monat]['liter'] += $mengeSeitVoll;
                $verbrauchProMonat[$monat]['km'] += $km;
                // Schlüssel = Datum; weitere Werte am selben Tag bekommen " #2", " #3" … angehängt
                $schluessel = $row['datum'];
                for ($n = 2; isset($verbrauchswerte[$schluessel]); $n++) {
                    $schluessel = $row['datum'] . ' #' . $n;
                }
                $verbrauchswerte[$schluessel] = ($mengeSeitVoll / $km) * 100;
            }
            $letzteVoll = $row;
            $mengeSeitVoll = 0.0;
        }
    }
    foreach ($verbrauchProMonat as $monat => $d) {
        $verbrauchProMonat[$monat] = $d['km'] > 0 ? round(($d['liter'] / $d['km']) * 100, 2) : 0.0;
    }

    foreach ($flat as $e) {
        $datum = $e['datum'] ?? '';
        if ($datum === '') continue;
        $monat = substr($datum, 0, 7);
        $jahr = (int)substr($datum, 0, 4);
        $kosten = (float)($e['kosten'] ?? 0);
        $kat = $e['kategorie'] ?? 'Sonstige';
        if ($kat === 'Fahrt') continue;
        if (!in_array($jahr, $verfuegbareJahre, true)) {
            $verfuegbareJahre[] = $jahr;
        }
        $kostenProMonat[$monat] = ($kostenProMonat[$monat] ?? 0) + $kosten;
        $ausgabenDaten[$kat] = ($ausgabenDaten[$kat] ?? 0) + $kosten;
        if (!isset($detailAusgabenDaten[$kat])) {
            $detailAusgabenDaten[$kat] = ['summe' => 0, 'anzahl' => 0];
        }
        $detailAusgabenDaten[$kat]['summe'] += $kosten;
        $detailAusgabenDaten[$kat]['anzahl']++;
    }
    rsort($verfuegbareJahre);

    // Jahresdaten: Tankstops, gekaufte Menge/Kosten, Verbrauch pro Jahr
    foreach ($verfuegbareJahre as $y) {
        $jahresdaten[$y] = [
            'anzahl_tankstops' => 0,
            'gekauft_menge' => 0.0,
            'gekauft_ausgaben' => 0.0,
            'verbrauch_menge' => 0.0,
            'verbrauch_km' => 0.0,
            'verbrauch_l100km' => 0.0,
        ];
    }
    $letzteVoll2 = null;
    $mengeSeitVoll2 = 0.0;
    foreach ($fillupsOnly as $row) {
        $y = (int)substr($row['datum'], 0, 4);
        if (!isset($jahresdaten[$y])) continue;
        $jahresdaten[$y]['anzahl_tankstops']++;
        $jahresdaten[$y]['gekauft_menge'] += (float)($row['menge'] ?? 0);
        $jahresdaten[$y]['gekauft_ausgaben'] += (float)($row['kosten'] ?? 0);
        $mengeSeitVoll2 += (float)($row['menge'] ?? 0);
        if (!empty($row['vollgetankt'])) {
            if ($letzteVoll2 && empty($row['skip_previous']) && ($row['tachostand'] - $letzteVoll2['tachostand']) > 0) {
                $km = $row['tachostand'] - $letzteVoll2['tachostand'];
                $jahresdaten[$y]['verbrauch_menge'] += $mengeSeitVoll2;
                $jahresdaten[$y]['verbrauch_km'] += $km;
            }
            $letzteVoll2 = $row;
            $mengeSeitVoll2 = 0.0;
        }
    }
    foreach ($jahresdaten as $y => &$d) {
        if ($d['verbrauch_km'] > 0) {
            $d['verbrauch_l100km'] = round(($d['verbrauch_menge'] / $d['verbrauch_km']) * 100, 2);
        }
    }
    unset($d);

    return [
        'verbrauchswerte' => $verbrauchswerte,
        'verbrauchProMonat' => $verbrauchProMonat,
        'kostenProMonat' => $kostenProMonat,
        'jahresdaten' => $jahresdaten,
        'ausgabenDaten' => $ausgabenDaten,
        'detailAusgabenDaten' => $detailAusgabenDaten,
        'verfuegbareJahre' => $verfuegbareJahre,
    ];
}

/** Filtert Statistikdaten nach Jahr (für Ausgaben-Tab). */
function getStatistikDatenFromApiForYear($api, string $vehicleId, ?int $year): array
{
    $all = getStatistikDatenFromApi($api, $vehicleId);
    if ($year === null) {
        return $all;
    }
    $flat = [];
    $merged = getMergedEintraegeFromApi($api, $vehicleId);
    foreach ($merged['eintraege'] as $entries) {
        foreach ($entries as $e) {
            if ((int)substr($e['datum'] ?? '', 0, 4) === $year) {
                $flat[] = $e;
            }
        }
    }
    $ausgabenDaten = [];
    $detailAusgabenDaten = [];
    $gesamtKosten = 0;
    $kostenProMonat = [];
    foreach ($flat as $e) {
        $kat = $e['kategorie'] ?? 'Sonstige';
        if ($kat === 'Fahrt') continue;
        $kosten = (float)($e['kosten'] ?? 0);
        $ausgabenDaten[$kat] = ($ausgabenDaten[$kat] ?? 0) + $kosten;
        $gesamtKosten += $kosten;
        if (!isset($detailAusgabenDaten[$kat])) {
            $detailAusgabenDaten[$kat] = ['summe' => 0, 'anzahl' => 0];
        }
        $detailAusgabenDaten[$kat]['summe'] += $kosten;
        $detailAusgabenDaten[$kat]['anzahl']++;
        $monat = substr($e['datum'] ?? '', 0, 7);
        if ($monat) {
            $kostenProMonat[$monat] = ($kostenProMonat[$monat] ?? 0) + $kosten;
        }
    }
    return array_merge($all, [
        'ausgabenDaten' => $ausgabenDaten,
        'detailAusgabenDaten' => $detailAusgabenDaten,
        'kostenProMonat' => $kostenProMonat,
        'gesamtKostenForYear' => $gesamtKosten,
        'anzahlMonateForYear' => count($kostenProMonat),
    ]);
}

function holeVerfügbareJahreFromApi($api, string $vehicleId): array
{
    $d = getStatistikDatenFromApi($api, $vehicleId);
    return $d['verfuegbareJahre'];
}

function holeAnzahlMonateFromApi($api, string $vehicleId, ?int $jahr): int
{
    $d = getStatistikDatenFromApi($api, $vehicleId);
    $kosten = $d['kostenProMonat'];
    if ($jahr !== null) {
        $kosten = array_filter($kosten, function ($monat) use ($jahr) {
            return (int)substr($monat, 0, 4) === $jahr;
        }, ARRAY_FILTER_USE_KEY);
    }
    return count($kosten);
}

/**
 * Fahrzeugdaten aus dem Formular (includes/form_fahrzeug.php) für die API aufbereiten.
 * Leere optionale Felder werden als null gesendet, damit sie beim Bearbeiten gelöscht werden.
 */
function fahrzeugDatenAusPost(array $post): array
{
    $optionalInt = function (string $key) use ($post) {
        $v = trim((string)($post[$key] ?? ''));
        return $v === '' ? null : (int)$v;
    };
    $optionalText = function (string $key) use ($post) {
        $v = trim((string)($post[$key] ?? ''));
        return $v === '' ? null : $v;
    };
    $tank = trim(str_replace(',', '.', (string)($post['tankCapacity'] ?? '')));

    return [
        'name' => trim((string)($post['name'] ?? '')),
        'fuelType' => (string)($post['fuelType'] ?? ''),
        'brand' => $optionalText('brand'),
        'model' => $optionalText('model'),
        'year' => $optionalInt('year'),
        'odometerKm' => $optionalInt('odometerKm'),
        'tankCapacity' => $tank === '' ? null : (float)$tank,
    ];
}

/**
 * Hochgeladenes Fahrzeugbild speichern bzw. Bild entfernen (Feld "bild" / Checkbox "bild_entfernen").
 * Ältere Bilder des Fahrzeugs werden dabei gelöscht.
 * @return string|null Fehlermeldung oder null bei Erfolg/keiner Änderung
 */
function speichereFahrzeugBild($api, string $vehicleId, array $files, array $post): ?string
{
    $datei = $files['bild'] ?? null;
    $hatDatei = $datei && ($datei['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    $entfernen = !empty($post['bild_entfernen']);

    if (!$hatDatei && !$entfernen) {
        return null;
    }

    try {
        $alteMedien = $api->getVehicleMedia($vehicleId);
    } catch (Throwable $e) {
        $alteMedien = [];
    }

    $neueMediaId = null;
    if ($hatDatei) {
        if ($datei['error'] !== UPLOAD_ERR_OK) {
            return $datei['error'] === UPLOAD_ERR_INI_SIZE || $datei['error'] === UPLOAD_ERR_FORM_SIZE
                ? 'Das Bild ist zu groß.'
                : 'Das Bild konnte nicht hochgeladen werden.';
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($datei['tmp_name']) ?: '';
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) {
            return 'Nur JPEG, PNG, GIF und WebP sind als Bild erlaubt.';
        }
        try {
            $media = $api->uploadVehicleMedia($vehicleId, $datei['tmp_name'], $mime, basename((string)$datei['name']));
            $api->patchVehicle($vehicleId, ['imageUrl' => $media['url']]);
            $neueMediaId = $media['id'] ?? null;
        } catch (Throwable $e) {
            return 'Bild-Upload fehlgeschlagen: ' . $e->getMessage();
        }
    } else {
        try {
            $api->patchVehicle($vehicleId, ['imageUrl' => null]);
        } catch (Throwable $e) {
            return 'Bild konnte nicht entfernt werden: ' . $e->getMessage();
        }
    }

    foreach ($alteMedien as $m) {
        if (isset($m['id']) && $m['id'] !== $neueMediaId) {
            try {
                $api->deleteVehicleMedia($vehicleId, $m['id']);
            } catch (Throwable $e) {
                error_log('[Fahrzeugbild] Altes Bild nicht gelöscht: ' . $e->getMessage());
            }
        }
    }
    return null;
}

/**
 * Fälligkeiten (TÜV, Wartung, Versicherung, Steuer) aus der API (Regeln in backend/src/services/reminders.js).
 * @return array Liste mit titel, kategorie, letzte, faellig_datum, faellig_km, status (ok|bald|ueberfaellig|unbekannt), tage
 */
function getFaelligkeitenFromApi($api, string $vehicleId): array
{
    try {
        $liste = $api->getVehicleReminders($vehicleId);
    } catch (Throwable $e) {
        return [];
    }
    return array_map(fn($r) => [
        'titel' => $r['title'],
        'kategorie' => $r['category'],
        'letzte' => $r['last'],
        'faellig_datum' => $r['due_date'],
        'faellig_km' => $r['due_km'],
        'status' => $r['status'],
        'tage' => $r['days'],
    ], $liste);
}
