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
    // Die Fahrzeugseite braucht die Einträge in mehreren Reitern: einmal pro Seitenaufruf laden (#89)
    // WeakMap: der Zwischenspeicher hängt am API-Objekt und verschwindet mit ihm (keine veralteten Daten)
    static $zwischenspeicher = null;
    $zwischenspeicher ??= new WeakMap();
    $jeFahrzeug = $zwischenspeicher[$api] ?? [];
    if (!isset($jeFahrzeug[$vehicleId])) {
        $jeFahrzeug[$vehicleId] = ladeMergedEintraegeFromApi($api, $vehicleId);
        $zwischenspeicher[$api] = $jeFahrzeug;
    }
    return $jeFahrzeug[$vehicleId];
}

function ladeMergedEintraegeFromApi($api, string $vehicleId): array
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
            // null = Tachostand fehlt (#41), kann nachgetragen werden
            'tachostand' => isset($f['odometer_km']) ? (int)$f['odometer_km'] : null,
            'tachostand_geschaetzt' => !empty($f['odometer_estimated']),
            'vollgetankt' => $f['is_full'] ?? true,
            'skip_previous' => $f['skip_previous'] ?? false,
            'standort' => $f['station'] ?? '',
            'einheit' => $f['unit'] ?? 'l',
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
        // Ohne Tachostand bei gleichem Datum oben (= nach den Einträgen mit Tachostand, wie in der Statistik)
        $ta = $a['tachostand'] ?? PHP_INT_MAX;
        $tb = $b['tachostand'] ?? PHP_INT_MAX;
        return $tb <=> $ta;
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
    // Nur echte Tachostände (fehlende und geschätzte zählen nicht, #41)
    $odos = array_filter(array_map(fn($f) => empty($f['odometer_estimated']) ? ($f['odometer_km'] ?? null) : null, $fillups), fn($o) => $o !== null);
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

/** Einträge ohne Kosten (Fahrten, notierte Kilometerstände) zählen nicht zu den Ausgaben */
function istEintragOhneKosten(?string $kategorie): bool
{
    return in_array($kategorie, ['Fahrt', 'Kilometerstand'], true);
}

/** Tachostand vorhanden und nicht nur geschätzt (#41)? */
function hatEchtenTachostand(array $row): bool
{
    return isset($row['tachostand']) && $row['tachostand'] !== '' && empty($row['tachostand_geschaetzt']);
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
    // Bei mehreren Tankfüllungen/Ladungen am selben Tag entscheidet der Tachostand; Einträge ohne Tachostand
    // kommen danach (wie in backend/src/services/stats.js), ihre Menge zählt zum nächsten Abschnitt
    usort($fillupsOnly, fn($a, $b) => [$a['datum'], $a['tachostand'] ?? PHP_INT_MAX] <=> [$b['datum'], $b['tachostand'] ?? PHP_INT_MAX]);
    // Je Einheit getrennt rechnen: beim Plug-in-Hybrid Liter von Tankfüllung zu Tankfüllung, kWh von Ladung zu Ladung
    $nachEinheit = [];
    foreach ($fillupsOnly as $row) {
        $nachEinheit[$row['einheit'] ?? 'l'][] = $row;
    }
    uksort($nachEinheit, fn($a, $b) => array_search($a, ['l', 'kwh', 'kg']) <=> array_search($b, ['l', 'kwh', 'kg']));

    foreach ($flat as $e) {
        $datum = $e['datum'] ?? '';
        if ($datum === '') continue;
        $monat = substr($datum, 0, 7);
        $jahr = (int)substr($datum, 0, 4);
        $kosten = (float)($e['kosten'] ?? 0);
        $kat = $e['kategorie'] ?? 'Sonstige';
        if (istEintragOhneKosten($kat)) continue;
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

    $einheiten = [];
    foreach ($nachEinheit as $einheit => $rows) {
        $einheiten[$einheit] = verbrauchAusTankfuellungen($rows, $verfuegbareJahre);
    }
    // Bisherige Schlüssel: Werte der ersten Einheit (beim Plug-in-Hybrid der Kraftstoff)
    $haupt = $einheiten ? reset($einheiten) : verbrauchAusTankfuellungen([], $verfuegbareJahre);
    $verbrauchswerte = $haupt['verbrauchswerte'];
    $verbrauchProMonat = $haupt['verbrauchProMonat'];
    $jahresdaten = $haupt['jahresdaten'];

    return [
        'verbrauchswerte' => $verbrauchswerte,
        'verbrauchProMonat' => $verbrauchProMonat,
        'kostenProMonat' => $kostenProMonat,
        'jahresdaten' => $jahresdaten,
        'ausgabenDaten' => $ausgabenDaten,
        'detailAusgabenDaten' => $detailAusgabenDaten,
        'verfuegbareJahre' => $verfuegbareJahre,
        'einheiten' => $einheiten,
    ];
}

/**
 * Verbrauch aus Tank- bzw. Ladevorgängen einer Einheit (nach Datum und Tachostand sortiert), Full-to-Full.
 * @return array verbrauchswerte (Datum => Wert je voller Füllung), verbrauchProMonat, jahresdaten je Jahr
 */
function verbrauchAusTankfuellungen(array $rows, array $jahre): array
{
    $verbrauchswerte = [];
    $verbrauchProMonat = [];
    $jahresdaten = [];
    foreach ($jahre as $y) {
        $jahresdaten[$y] = [
            'anzahl_tankstops' => 0,
            'gekauft_menge' => 0.0,
            'gekauft_ausgaben' => 0.0,
            'verbrauch_menge' => 0.0,
            'verbrauch_km' => 0.0,
            'verbrauch_l100km' => 0.0,
        ];
    }
    $letzteVoll = null;
    $mengeSeitVoll = 0.0;
    foreach ($rows as $row) {
        $y = (int)substr($row['datum'], 0, 4);
        if (isset($jahresdaten[$y])) {
            $jahresdaten[$y]['anzahl_tankstops']++;
            $jahresdaten[$y]['gekauft_menge'] += (float)($row['menge'] ?? 0);
            $jahresdaten[$y]['gekauft_ausgaben'] += (float)($row['kosten'] ?? 0);
        }
        $mengeSeitVoll += (float)($row['menge'] ?? 0);
        // Ohne echten Tachostand (fehlt oder geschätzt) zählt auch eine Vollbetankung wie eine Teilbetankung (#41)
        if (!empty($row['vollgetankt']) && hatEchtenTachostand($row)) {
            // skip_previous: nur neuer Startpunkt, kein Verbrauch über die Lücke
            if ($letzteVoll && empty($row['skip_previous']) && ($row['tachostand'] - $letzteVoll['tachostand']) > 0) {
                $km = $row['tachostand'] - $letzteVoll['tachostand'];
                $monat = substr($row['datum'], 0, 7);
                if (!isset($verbrauchProMonat[$monat])) {
                    $verbrauchProMonat[$monat] = ['liter' => 0.0, 'km' => 0];
                }
                $verbrauchProMonat[$monat]['liter'] += $mengeSeitVoll;
                $verbrauchProMonat[$monat]['km'] += $km;
                if (isset($jahresdaten[$y])) {
                    $jahresdaten[$y]['verbrauch_menge'] += $mengeSeitVoll;
                    $jahresdaten[$y]['verbrauch_km'] += $km;
                }
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
    foreach ($jahresdaten as $y => &$d) {
        if ($d['verbrauch_km'] > 0) {
            $d['verbrauch_l100km'] = round(($d['verbrauch_menge'] / $d['verbrauch_km']) * 100, 2);
        }
    }
    unset($d);
    return ['verbrauchswerte' => $verbrauchswerte, 'verbrauchProMonat' => $verbrauchProMonat, 'jahresdaten' => $jahresdaten];
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
        if (istEintragOhneKosten($kat)) continue;
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
    // Akku nur beim Plug-in-Hybrid (Elektroautos tragen ihren Akku im Feld für den Tank ein)
    $akku = ($post['fuelType'] ?? '') === 'phev' ? trim(str_replace(',', '.', (string)($post['batteryCapacity'] ?? ''))) : '';

    return [
        'name' => trim((string)($post['name'] ?? '')),
        'fuelType' => (string)($post['fuelType'] ?? ''),
        'brand' => $optionalText('brand'),
        'model' => $optionalText('model'),
        'year' => $optionalInt('year'),
        'odometerKm' => $optionalInt('odometerKm'),
        'tankCapacity' => $tank === '' ? null : (float)$tank,
        'batteryCapacity' => $akku === '' ? null : (float)$akku,
    ] + fahrzeugZusatzAusPost($post);
}

/** Zusatzangaben zum Fahrzeug (#45): Formularfeld => [API-Name, Typ, Beschriftung für Fehlermeldungen] */
function fahrzeugZusatzFelder(): array
{
    return [
        'licensePlate' => ['text', 'Kennzeichen'],
        'vin' => ['text', 'FIN'],
        'firstRegistration' => ['datum', 'Erstzulassung'],
        'purchaseDate' => ['datum', 'Kaufdatum'],
        'purchasePrice' => ['zahl', 'Kaufpreis'],
        'saleDate' => ['datum', 'Verkaufsdatum'],
        'salePrice' => ['zahl', 'Verkaufspreis'],
        'serviceIntervalMonths' => ['ganzzahl', 'Wartungsintervall (Monate)'],
        'serviceIntervalKm' => ['ganzzahl', 'Wartungsintervall (km)'],
        'oilIntervalMonths' => ['ganzzahl', 'Ölwechsel (Monate)'],
        'oilIntervalKm' => ['ganzzahl', 'Ölwechsel (km)'],
        'insuranceCompany' => ['text', 'Versicherung'],
        'insurancePremium' => ['zahl', 'Versicherungsbeitrag'],
        'insuranceDueMonth' => ['ganzzahl', 'Fälligkeit Versicherung'],
        'taxAmount' => ['zahl', 'Kfz-Steuer'],
        'taxDueMonth' => ['ganzzahl', 'Fälligkeit Kfz-Steuer'],
        'tireSizeSummer' => ['text', 'Reifengröße Sommer'],
        'tireSizeWinter' => ['text', 'Reifengröße Winter'],
    ];
}

/** Zusatzangaben aus dem Formular; leere Felder werden als null gesendet (= löschen). */
function fahrzeugZusatzAusPost(array $post): array
{
    $daten = [];
    foreach (fahrzeugZusatzFelder() as $feld => [$typ]) {
        $wert = trim((string)($post[$feld] ?? ''));
        if ($wert === '') {
            $daten[$feld] = null;
        } elseif ($typ === 'zahl') {
            $daten[$feld] = is_numeric(str_replace(',', '.', $wert)) ? (float)str_replace(',', '.', $wert) : $wert;
        } elseif ($typ === 'ganzzahl') {
            $daten[$feld] = ctype_digit($wert) ? (int)$wert : $wert;
        } else {
            $daten[$feld] = $wert;
        }
    }
    return $daten;
}

/** Verständliche Meldung für Fehler der Fahrzeug-API (z. B. invalid_taxDueMonth) */
function fahrzeugFehlerText(Throwable $e): string
{
    $code = $e->getMessage();
    if (str_starts_with($code, 'invalid_')) {
        $feld = substr($code, 8);
        $namen = array_map(fn($f) => $f[1], fahrzeugZusatzFelder()) + ['year' => 'Baujahr', 'odometerKm' => 'Tachostand', 'tankCapacity' => 'Tank-/Akkugröße', 'batteryCapacity' => 'Akkugröße', 'name' => 'Name'];
        if (isset($namen[$feld])) {
            return 'Bitte prüfen: ' . $namen[$feld] . '.';
        }
    }
    return tarifHinweis($e) ?? $code;
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
        'quelle' => $r['source'] ?? null, // eintrag | erstzulassung | faelligkeitsmonat (#45)
    ], $liste);
}

/**
 * Menge und Gesamtpreis eines Tank- bzw. Ladevorgangs aus dem Formular. Fehlt einer der beiden Werte,
 * wird er aus dem Preis pro Einheit berechnet. Leer ist nicht 0: ohne Angabe gibt es eine Fehlermeldung,
 * eine bewusst eingetragene 0 (z. B. kostenloses Laden) bleibt erlaubt.
 * @return array ['menge' => float|null, 'kosten' => float|null, 'fehler' => string|null]
 */
function mengeUndKostenAusFormular(array $post): array
{
    $zahl = function (string $key) use ($post): ?float {
        $v = trim(str_replace(',', '.', (string)($post[$key] ?? '')));
        return $v === '' || !is_numeric($v) ? null : (float)$v;
    };
    $menge = $zahl('menge');
    $preis = $zahl('preis_pro_einheit');
    $kosten = $zahl('kosten');

    if ($kosten === null && $menge !== null && $preis !== null) {
        $kosten = round($menge * $preis, 2);
    }
    if ($menge === null && $kosten !== null && $preis !== null && $preis > 0) {
        $menge = round($kosten / $preis, 2);
    }

    $fehler = null;
    if ($menge === null || $menge <= 0) {
        $fehler = 'Bitte die Menge angeben (oder Gesamtpreis und Preis pro Einheit).';
    } elseif ($kosten === null || $kosten < 0) {
        $fehler = 'Bitte den Gesamtpreis angeben (oder den Preis pro Einheit). Für kostenloses Laden 0 eintragen.';
    }
    return ['menge' => $menge, 'kosten' => $kosten, 'fehler' => $fehler];
}

/**
 * Beschreibung einer Fahrt aus dem Formular, z. B. "Bolbingen → Aalen – Arbeit (42 km)".
 * Bis zum neuen Fahrtenbuch (#52) stehen die gefahrenen Kilometer in der Beschreibung (#43).
 */
function fahrtBeschreibung(array $post): string
{
    $start = trim((string)($post['startort'] ?? ''));
    $ziel = trim((string)($post['zielort'] ?? ''));
    $zweck = trim((string)($post['zweck'] ?? ''));
    $km = trim(str_replace(',', '.', (string)($post['gefahrene_km'] ?? '')));

    $strecke = implode(' → ', array_filter([$start, $ziel], fn($t) => $t !== ''));
    $text = implode(' – ', array_filter([$strecke, $zweck], fn($t) => $t !== ''));
    if (is_numeric($km) && (float)$km > 0) {
        $text .= ($text !== '' ? ' ' : '') . '(' . number_format((float)$km, fmod((float)$km, 1) ? 1 : 0, ',', '.') . ' km)';
    }
    return $text !== '' ? $text : 'Fahrt';
}

/**
 * Tarife (Issue #44): Text für die API-Antwort 403 plan_required, sonst null.
 * Solange PLAN_LIMITS_ENABLED in der API aus ist, kommt diese Antwort nie.
 */
function tarifHinweis(Throwable $e): ?string
{
    if (!($e instanceof FuelstatApiException) || $e->getMessage() !== 'plan_required') {
        return null;
    }
    $texte = [
        'maxVehicles' => 'Dein Tarif umfasst %s. Weitere Fahrzeuge sind eine Premium-Funktion.',
        'sharing' => 'Fahrzeuge teilen ist eine Premium-Funktion.',
        'comparison' => 'Der Fahrzeugvergleich ist eine Premium-Funktion.',
        'pdfReport' => 'Der PDF-Bericht ist eine Premium-Funktion.',
        'tripCategories' => 'Weitere Fahrtenbuch-Kategorien sind eine Premium-Funktion.',
    ];
    $feature = (string)($e->data['feature'] ?? '');
    $limit = (int)($e->data['limit'] ?? 1);
    return sprintf($texte[$feature] ?? 'Das ist eine Premium-Funktion.', $limit === 1 ? 'ein Fahrzeug' : $limit . ' Fahrzeuge');
}

/** Anzeigenamen der Tarife */
function tarifNamen(): array
{
    return ['free' => 'Free', 'plus' => 'Plus', 'fleet' => 'Flotte'];
}
