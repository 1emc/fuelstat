<?php
// includes/functions.php  — vollständige, lauffähige Version (alle Helfer + Statistikfunktionen)

// --------------------------------------------------
// 0. System-Checks
// --------------------------------------------------
function isGdExtensionAvailable(): bool
{
    return extension_loaded('gd') && function_exists('imagecreatefromjpeg');
}

// --------------------------------------------------
// 1. Icons
// --------------------------------------------------
function getCategoryIcon(string $kategorie): string
{
    $icons = [
        'Tankfuellung' => 'fa-gas-pump',
        'Versicherung' => 'fa-shield-alt',
        'Werkstatt'    => 'fa-tools',
        'Inspektion'   => 'fa-wrench',
        'Steuer'       => 'fa-money-bill-wave',
        'Reparatur'    => 'fa-car-crash',
        'Reifen'       => 'fa-car-side',
        'TUV'          => 'fa-calendar-check',
        'Wartung'      => 'fa-cogs',
        'Dekor'        => 'fa-paint-roller',
        'Verbrauch'    => 'fa-chart-line',
        'Fahrt'        => 'fa-route',
    ];
    return $icons[$kategorie] ?? 'fa-question-circle';
}

// --------------------------------------------------
// 2. Kilometer‑ & Verbrauchsfunktionen
// --------------------------------------------------
function berechneGefahreneKm(array $e, int $fid): ?int
{
    global $conn;
    if ($e['kategorie'] !== 'Tankfuellung') return null;
    $sql = $e['skip_previous']
        ? "SELECT tachostand FROM eintraege WHERE fahrzeug_id=? AND kategorie='Tankfuellung' AND vollgetankt=1 AND datum<? ORDER BY datum DESC LIMIT 1"
        : "SELECT tachostand FROM eintraege WHERE fahrzeug_id=? AND kategorie='Tankfuellung' AND vollgetankt=1 AND skip_previous=0 AND datum<? ORDER BY datum DESC LIMIT 1";
    $st = $conn->prepare($sql);
    $st->bind_param('is', $fid, $e['datum']);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    if (!$row) return null;
    $km = $e['tachostand'] - $row['tachostand'];
    return $km > 0 ? $km : null;
}

function berechneVerbrauchEintrag(array $e, int $fid): ?float
{
    global $conn;
    if ($e['kategorie'] !== 'Tankfuellung' || !$e['vollgetankt']) return null;
    $st = $conn->prepare("SELECT menge,tachostand,datum FROM eintraege WHERE fahrzeug_id=? AND kategorie='Tankfuellung' AND vollgetankt=1 AND datum<? ORDER BY datum DESC LIMIT 1");
    $st->bind_param('is', $fid, $e['datum']);
    $st->execute();
    $prev = $st->get_result()->fetch_assoc();
    $st->close();
    if (!$prev) return null;
    $km = $e['tachostand'] - $prev['tachostand'];
    if ($km <= 0) return null;
    $st = $conn->prepare("SELECT SUM(menge) AS s FROM eintraege WHERE fahrzeug_id=? AND kategorie='Tankfuellung' AND vollgetankt=0 AND datum>? AND datum<?");
    $st->bind_param('iss', $fid, $prev['datum'], $e['datum']);
    $st->execute();
    $zwischen = $st->get_result()->fetch_assoc()['s'] ?? 0;
    $st->close();
    return round((($e['menge'] + $zwischen) / $km) * 100, 2);
}

function berechneGesamtverbrauch(int $fid): ?float
{
    global $conn;

    $stmt = $conn->prepare(
        "SELECT datum, menge, tachostand, vollgetankt\n         FROM eintraege\n         WHERE fahrzeug_id = ?\n           AND kategorie = 'Tankfuellung'\n         ORDER BY datum"
    );
    $stmt->bind_param('i', $fid);
    $stmt->execute();
    $res = $stmt->get_result();

    $letzteVoll = null;
    $mengeSeitVoll = 0;
    $verbrauchGesamt = 0;

    while ($row = $res->fetch_assoc()) {
        $mengeSeitVoll += $row['menge'];
        if ($row['vollgetankt']) {
            if ($letzteVoll) {
                $km = $row['tachostand'] - $letzteVoll['tachostand'];
                if ($km > 0) {
                    $verbrauchGesamt += $mengeSeitVoll;
                }
            }
            $letzteVoll = $row;
            $mengeSeitVoll = 0;
        }
    }
    $stmt->close();

    $kmBasis = getKmBasis($fid, 'since_first_full');
    return ($kmBasis > 0) ? round(($verbrauchGesamt / $kmBasis) * 100, 2) : null;
}

function berechneAktuellenVerbrauch(int $fid): ?float
{
    global $conn;
    $st = $conn->prepare("SELECT menge,
      tachostand-(SELECT MAX(tachostand) FROM eintraege WHERE fahrzeug_id=? AND kategorie='Tankfuellung' AND vollgetankt=1 AND datum<e1.datum) AS km
      FROM eintraege e1 WHERE fahrzeug_id=? AND kategorie='Tankfuellung' AND vollgetankt=1 ORDER BY datum DESC LIMIT 1");
    $st->bind_param('ii', $fid, $fid);
    $st->execute();
    $d = $st->get_result()->fetch_assoc();
    $st->close();
    return ($d && $d['km'] > 0) ? round(($d['menge'] / $d['km']) * 100, 2) : null;
}

function getAktuellenTachostand(int $fid): ?int
{
    global $conn;

    // 1) Höchsten Tachostand aus den Einträgen lesen
    $st = $conn->prepare(
        'SELECT MAX(tachostand) AS t FROM eintraege WHERE fahrzeug_id = ?'
    );
    $st->bind_param('i', $fid);
    $st->execute();
    $tachostand = $st->get_result()->fetch_assoc()['t'] ?? null;
    $st->close();

    // 2) Fallback auf den initialen Tachostand des Fahrzeugs
    if ($tachostand === null) {
        $st = $conn->prepare(
            'SELECT tachostand FROM fahrzeuge WHERE id = ? LIMIT 1'
        );
        $st->bind_param('i', $fid);
        $st->execute();
        $tachostand = $st->get_result()->fetch_assoc()['tachostand'] ?? null;
        $st->close();
    }

    // Immer als int zurückgeben, wenn vorhanden
    return $tachostand !== null ? (int)$tachostand : null;
}

function berechneFahrleistung(int $fid, int $initial): ?int
{
    $t = getAktuellenTachostand($fid);
    return $t !== null ? $t - $initial : null;
}

/**
 * Liefert die Kilometerbasis je nach gewünschtem Modus.
 *
 *   - since_first_full: Distanz zwischen erster und letzter Vollbetankung
 *   - vehicle_since_created: Distanz seit Anlegen des Fahrzeugs
 *   - minmax_all_entries: Distanz zwischen erstem und letztem Eintrag
 */
function getKmBasis(int $fid, string $modus = 'since_first_full'): ?int
{
    global $conn;

    switch ($modus) {
        case 'vehicle_since_created':
            $start = getInitialTachostand($fid);
            $ende  = getAktuellenTachostand($fid);
            if ($start === null || $ende === null) return null;
            $km = $ende - $start;
            return $km > 0 ? $km : null;

        case 'minmax_all_entries':
            $st = $conn->prepare("SELECT MAX(tachostand) AS max_t, MIN(tachostand) AS min_t FROM eintraege WHERE fahrzeug_id=?");
            $st->bind_param('i', $fid);
            $st->execute();
            $row = $st->get_result()->fetch_assoc();
            $st->close();
            if (!$row) return null;
            $km = ($row['max_t'] ?? 0) - ($row['min_t'] ?? 0);
            return $km > 0 ? (int)$km : null;

        case 'since_first_full':
        default:
            $st = $conn->prepare("SELECT MAX(tachostand) AS max_t, MIN(tachostand) AS min_t FROM eintraege WHERE fahrzeug_id=? AND kategorie='Tankfuellung' AND vollgetankt=1 AND skip_previous=0");
            $st->bind_param('i', $fid);
            $st->execute();
            $row = $st->get_result()->fetch_assoc();
            $st->close();
            if (!$row) return null;
            $km = ($row['max_t'] ?? 0) - ($row['min_t'] ?? 0);
            return $km > 0 ? (int)$km : null;
    }
}

function zeigeVerbrauchstendenz(int $fid): string
{
    global $conn;
    // Letzte vier volle Tanksegmente (aktuelles + drei vorherige)
    $st = $conn->prepare(
        "SELECT menge,
                tachostand-(SELECT MAX(tachostand)
                              FROM eintraege
                              WHERE fahrzeug_id=?
                                AND kategorie='Tankfuellung'
                                AND vollgetankt=1
                                AND datum<e1.datum) AS km
         FROM eintraege e1
         WHERE fahrzeug_id=?
           AND kategorie='Tankfuellung'
           AND vollgetankt=1
         ORDER BY datum DESC
         LIMIT 4"
    );
    $st->bind_param('ii', $fid, $fid);
    $st->execute();
    $res = $st->get_result();
    $segments = [];
    while ($row = $res->fetch_assoc()) {
        if ($row['km'] > 0) {
            $segments[] = ($row['menge'] / $row['km']) * 100;
        }
    }
    $st->close();

    if (count($segments) < 2) {
        return '<i class="fas fa-minus text-muted"></i>';
    }

    $current = array_shift($segments);
    $countPrev = min(3, count($segments));
    $avgPrev = array_sum(array_slice($segments, 0, $countPrev)) / $countPrev;
    $diff = $current - $avgPrev;

    if (abs($diff) < 0.01) {
        return '<i class="fas fa-minus text-muted"></i>';
    }

    $icon = $diff > 0 ? '<i class="fas fa-arrow-up text-danger"></i>' : '<i class="fas fa-arrow-down text-success"></i>';
    $sign = $diff > 0 ? '+' : '-';
    return $icon . ' ' . $sign . number_format(abs($diff), 2, ',', '.') . ' l/100km ggü. Ø der letzten ' . $countPrev;
}

// --------------------------------------------------
// 3. Kostenfunktionen
// --------------------------------------------------
function berechneKostenProKmGesamt(int $fid, string $basisModus = 'vehicle_since_created'): ?float
{
    global $conn;
    $km = getKmBasis($fid, $basisModus);
    if ($km === null || $km <= 0) {
        return null;
    }
    $st = $conn->prepare("SELECT SUM(kosten) AS k FROM eintraege WHERE fahrzeug_id=?");
    $st->bind_param('i', $fid);
    $st->execute();
    $d = $st->get_result()->fetch_assoc();
    $st->close();
    return ($d['k'] > 0) ? round($d['k'] / $km, 4) : null;
}

function berechneKraftstoffkostenProKm(int $fid, string $basisModus = 'since_first_full'): ?float
{
    global $conn;
    $km = getKmBasis($fid, $basisModus);
    if ($km === null || $km <= 0) {
        return null;
    }
    $st = $conn->prepare("SELECT SUM(kosten) AS k FROM eintraege WHERE fahrzeug_id=? AND kategorie='Tankfuellung'");
    $st->bind_param('i', $fid);
    $st->execute();
    $d = $st->get_result()->fetch_assoc();
    $st->close();
    return ($d['k'] > 0) ? round($d['k'] / $km, 4) : null;
}

function berechneDurchschnittPreisProLiter(int $fid): ?float
{
    global $conn;
    $st = $conn->prepare("SELECT SUM(kosten) AS k, SUM(menge) AS m FROM eintraege WHERE fahrzeug_id=? AND kategorie='Tankfuellung'");
    $st->bind_param('i', $fid);
    $st->execute();
    $d = $st->get_result()->fetch_assoc();
    $st->close();
    return ($d['m'] > 0) ? round($d['k'] / $d['m'], 3) : null;
}

function getInitialTachostand(int $fid): ?int
{
    global $conn;
    $st = $conn->prepare('SELECT tachostand FROM fahrzeuge WHERE id=? LIMIT 1');
    $st->bind_param('i', $fid);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    return $row ? (int)$row['tachostand'] : null;
}

// --------------------------------------------------
// 4. Statistikdatensätze (für Tab "Statistiken")
// --------------------------------------------------
function holeVerbrauchswerte(int $fahrzeug_id): array
{
    global $conn;
    $verbrauchswerte = [];
    $stmt = $conn->prepare(
        "SELECT datum, menge, tachostand, vollgetankt
         FROM eintraege
         WHERE fahrzeug_id = ?
           AND kategorie = 'Tankfuellung'
         ORDER BY datum"
    );
    $stmt->bind_param('i', $fahrzeug_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $letzteVoll = null;
    $mengeSeitVoll = 0;
    while ($row = $res->fetch_assoc()) {
        $mengeSeitVoll += $row['menge'];
        if ($row['vollgetankt']) {
            if ($letzteVoll) {
                $km = $row['tachostand'] - $letzteVoll['tachostand'];
                if ($km > 0) {
                    $verbrauchswerte[$row['datum']] = round(($mengeSeitVoll / $km) * 100, 2);
                }
            }
            $letzteVoll = $row;
            $mengeSeitVoll = 0;
        }
    }
    $stmt->close();
    return $verbrauchswerte;
}

function holeVerbrauchProMonat(int $fahrzeug_id): array
{
    global $conn;
    $stmt = $conn->prepare(
        "SELECT datum, menge, tachostand, vollgetankt
         FROM eintraege
         WHERE fahrzeug_id = ?
           AND kategorie = 'Tankfuellung'
         ORDER BY datum"
    );
    $stmt->bind_param('i', $fahrzeug_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();
    return berechneVerbrauchProMonatAusTankfuellungen($rows);
}

function berechneVerbrauchProMonatAusTankfuellungen(array $rows): array
{
    $zwischen = [];
    $letzteVoll = null;
    $mengeSeitVoll = 0.0;
    foreach ($rows as $row) {
        $mengeSeitVoll += (float)$row['menge'];
        if ($row['vollgetankt']) {
            if ($letzteVoll) {
                $km = $row['tachostand'] - $letzteVoll['tachostand'];
                if ($km > 0) {
                    $monat = date('Y-m', strtotime($row['datum']));
                    if (!isset($zwischen[$monat])) {
                        $zwischen[$monat] = ['liter' => 0.0, 'km' => 0];
                    }
                    $zwischen[$monat]['liter'] += $mengeSeitVoll;
                    $zwischen[$monat]['km']    += $km;
                }
            }
            $letzteVoll = $row;
            $mengeSeitVoll = 0.0;
        }
    }

    $verbrauchProMonat = [];
    foreach ($zwischen as $monat => $daten) {
        $verbrauchProMonat[$monat] = $daten['km'] > 0
            ? round(($daten['liter'] / $daten['km']) * 100, 2)
            : 0.0;
    }
    return $verbrauchProMonat;
}

function holeKostenProMonat(int $fahrzeug_id): array
{
    global $conn;

    $result = [];
    $stmt = $conn->prepare(
        "SELECT DATE_FORMAT(datum, '%Y-%m') AS monat, SUM(kosten) AS summe\n         FROM eintraege\n         WHERE fahrzeug_id = ? AND kategorie <> 'Fahrt'\n         GROUP BY monat\n         ORDER BY monat"
    );
    $stmt->bind_param('i', $fahrzeug_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $result[$row['monat']] = (float)$row['summe'];
    }
    $stmt->close();
    return $result;
}

function holeJahresdaten(int $fahrzeug_id): array
{
    global $conn;

    $jahresdaten = [];

    // Gekaufte Mengen und Kosten je Jahr
    $stmt = $conn->prepare(
        "SELECT YEAR(datum) AS jahr, SUM(menge) AS gekauft_menge, COUNT(*) AS stops, SUM(kosten) AS gekauft_ausgaben\n         FROM eintraege\n         WHERE fahrzeug_id = ? AND kategorie = 'Tankfuellung'\n         GROUP BY jahr"
    );
    $stmt->bind_param('i', $fahrzeug_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $jahresdaten[$row['jahr']] = [
            'anzahl_tankstops'   => $row['stops'],
            'gekauft_menge'      => (float)$row['gekauft_menge'],
            'gekauft_ausgaben'   => (float)$row['gekauft_ausgaben'],
            'verbrauch_menge'    => 0.0,
            'verbrauch_km'       => 0.0,
            'verbrauch_l100km'   => 0.0,
        ];
    }
    $stmt->close();

    // Verbrauch (full-to-full) je Jahr berechnen
    $stmt = $conn->prepare(
        "SELECT datum, menge, tachostand, vollgetankt\n         FROM eintraege\n         WHERE fahrzeug_id = ? AND kategorie = 'Tankfuellung'\n         ORDER BY datum"
    );
    $stmt->bind_param('i', $fahrzeug_id);
    $stmt->execute();
    $res = $stmt->get_result();

    $letzteVoll = null;
    $mengeSeitVoll = 0;
    while ($row = $res->fetch_assoc()) {
        $mengeSeitVoll += $row['menge'];
        if ($row['vollgetankt']) {
            if ($letzteVoll) {
                $km = $row['tachostand'] - $letzteVoll['tachostand'];
                if ($km > 0) {
                    $jahr = date('Y', strtotime($row['datum']));
                    if (!isset($jahresdaten[$jahr])) {
                        $jahresdaten[$jahr] = [
                            'anzahl_tankstops' => 0,
                            'gekauft_menge' => 0.0,
                            'gekauft_ausgaben' => 0.0,
                            'verbrauch_menge' => 0.0,
                            'verbrauch_km' => 0.0,
                            'verbrauch_l100km' => 0.0,
                        ];
                    }
                    $jahresdaten[$jahr]['verbrauch_menge'] += $mengeSeitVoll;
                    $jahresdaten[$jahr]['verbrauch_km'] += $km;
                }
            }
            $letzteVoll = $row;
            $mengeSeitVoll = 0;
        }
    }
    $stmt->close();

    foreach ($jahresdaten as &$daten) {
        if ($daten['verbrauch_km'] > 0) {
            $daten['verbrauch_l100km'] = round(($daten['verbrauch_menge'] / $daten['verbrauch_km']) * 100, 2);
        }
    }
    unset($daten);

    krsort($jahresdaten); // nach Jahr absteigend
    return $jahresdaten;
}

function formatMonat(string $monat): string
{
    $date = DateTime::createFromFormat('Y-m', $monat);
    return $date ? $date->format('M Y') : $monat;
}

function formatLabels(array $arr): array
{
    $formatted = [];
    foreach ($arr as $key => $value) {
        $formatted[formatMonat($key)] = $value;
    }
    return $formatted;
}

/**
 * Holt den vorherigen Verbrauchswert für ein Fahrzeug.
 *
 * @param array $eintrag Der aktuelle Eintrag aus der Datenbank.
 * @param int $fahrzeug_id Die ID des Fahrzeugs.
 * @return float|null Der vorherige Verbrauch oder null, wenn nicht verfügbar.
 */
function getPreviousVerbrauch($eintrag, $fahrzeug_id) {
    global $conn;

    // Schritt 1: Finde den vorherigen Tankfuellungseintrag (e_prev)
    $stmt_prev = $conn->prepare("
        SELECT * FROM eintraege 
        WHERE fahrzeug_id = ? 
          AND kategorie = 'Tankfuellung' 
          AND vollgetankt = 1 
          AND datum < ? 
          AND skip_previous = 0 
        ORDER BY datum DESC 
        LIMIT 1
    ");
    if (!$stmt_prev) {
        die("Fehler bei der Vorbereitung der SQL-Anweisung: " . $conn->error);
    }
    $stmt_prev->bind_param("is", $fahrzeug_id, $eintrag['datum']);
    $stmt_prev->execute();
    $result_prev = $stmt_prev->get_result();

    if ($result_prev->num_rows === 0) {
        // Kein vorheriger Eintrag gefunden
        $stmt_prev->close();
        return null;
    }

    $previous_entry = $result_prev->fetch_assoc();
    $stmt_prev->close();

    // Schritt 2: Finde den Eintrag vor dem vorherigen Tankfuellungseintrag (e_prev_prev)
    $stmt_prev_prev = $conn->prepare("
        SELECT tachostand FROM eintraege 
        WHERE fahrzeug_id = ? 
          AND kategorie = 'Tankfuellung' 
          AND vollgetankt = 1 
          AND datum < ? 
          AND skip_previous = 0 
        ORDER BY datum DESC 
        LIMIT 1
    ");
    if (!$stmt_prev_prev) {
        die("Fehler bei der Vorbereitung der SQL-Anweisung: " . $conn->error);
    }
    $stmt_prev_prev->bind_param("is", $fahrzeug_id, $previous_entry['datum']);
    $stmt_prev_prev->execute();
    $result_prev_prev = $stmt_prev_prev->get_result();

    if ($result_prev_prev->num_rows === 0) {
        // Kein Eintrag vor dem vorherigen gefunden
        $stmt_prev_prev->close();
        return null;
    }

    $entry_before_previous = $result_prev_prev->fetch_assoc();
    $stmt_prev_prev->close();

    // Schritt 3: Berechne den Verbrauch des vorherigen Eintrags
    $gefahrene_km = $previous_entry['tachostand'] - $entry_before_previous['tachostand'];

    if ($gefahrene_km <= 0) {
        // Ungültige Kilometerdifferenz
        return null;
    }

    $verbrauch = ($previous_entry['menge'] / $gefahrene_km) * 100;

    return $verbrauch;
}

/**
 * Holt den letzten Eintrag einer bestimmten Kategorie für ein Fahrzeug
 * 
 * @param int $fahrzeug_id Die ID des Fahrzeugs
 * @param string $kategorie Die Kategorie des Eintrags (z.B. 'Reifen', 'Wartung', 'TUV')
 * @return array|null Der letzte Eintrag oder null, wenn keiner gefunden wurde
 */
function getLetzterEintrag($fahrzeug_id, $kategorie) {
    global $conn;
    $stmt = $conn->prepare("
        SELECT datum, tachostand, kosten, beschreibung 
        FROM eintraege 
        WHERE fahrzeug_id = ? AND kategorie = ? 
        ORDER BY datum DESC 
        LIMIT 1
    ");
    $stmt->bind_param("is", $fahrzeug_id, $kategorie);
    $stmt->execute();
    $eintrag = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $eintrag;
}

/**
 * Übersetzt interne Kategorienamen in benutzerfreundliche Namen mit Umlauten
 * 
 * @param string $kategorie Der interne Kategoriename
 * @return string Der benutzerfreundliche Name mit Umlauten
 */
function getKategorieName($kategorie) {
    $uebersetzung = [
        'Tankfuellung' => 'Tankfüllung',
        'TUV' => 'TÜV'
    ];
    return $uebersetzung[$kategorie] ?? $kategorie;
}

/**
 * Holt Tankstellen in der Nähe per Tankerkönig-API
 *
 * @param float $lat   Breitengrad
 * @param float $lng   Längengrad
 * @param int   $radius Suchradius in km
 * @param string $type  Spritsorte ('e5', 'e10', 'diesel', 'all')
 * @param string $apikey Tankerkönig-API-Key
 * @return array|null   Array der Tankstellen oder null bei Fehler
 */
function getNearbyStationsTankerkoenig(float $lat, float $lng, int $radius, string $type, string $apikey): ?array {
    $url = 'https://creativecommons.tankerkoenig.de/json/list.php'
        . '?lat=' . urlencode($lat)
        . '&lng=' . urlencode($lng)
        . '&rad=' . urlencode($radius)
        . '&type=' . urlencode($type)
        . '&sort=dist'
        . '&apikey=' . urlencode($apikey);

    $json = @file_get_contents($url);
    if ($json === false) {
        return null;
    }
    $data = json_decode($json, true);
    if (!isset($data['stations'])) {
        return null;
    }
    return $data['stations'];
}
