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
function getCategoryIcon(string $kategorie, ?string $fuelType = null, ?string $einheit = null): string
{
    if ($kategorie === 'Tankfuellung' && $fuelType !== null) {
        return begriffeFuerEinheit($fuelType, $einheit)['icon'];
    }
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

function formatMonat(string $monat, bool $lang = false): string
{
    $kurz = [1 => 'Jan', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez'];
    $voll = [1 => 'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];
    $date = DateTime::createFromFormat('!Y-m', $monat);
    if (!$date) {
        return $monat;
    }
    $namen = $lang ? $voll : $kurz;
    return $namen[(int)$date->format('n')] . ' ' . $date->format('Y');
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
/**
 * Kraftstoffarten, wie die API sie kennt, mit Anzeigenamen.
 */
function getKraftstoffOptionen(): array
{
    return [
        'diesel' => 'Diesel',
        'e5' => 'Benzin (E5)',
        'e10' => 'Benzin (E10)',
        'lpg' => 'Autogas (LPG)',
        'cng' => 'Erdgas (CNG)',
        'electric' => 'Elektro',
        'hybrid' => 'Hybrid (ohne Stecker)',
        'phev' => 'Plug-in-Hybrid',
        'hydrogen' => 'Wasserstoff',
        'other' => 'Andere',
    ];
}

/**
 * URL des Fahrzeugbilds, sonst Platzhalter. $baseUrl ist die App-Basis-URL mit Slash am Ende.
 */
function fahrzeugBildUrl(array $fahrzeug, string $baseUrl): string
{
    $url = trim((string)($fahrzeug['image_url'] ?? ''));
    if ($url === '') {
        return $baseUrl . 'images/platzhalter.jpg';
    }
    if (preg_match('#^https?://#i', $url)) {
        return $url;
    }
    return $baseUrl . ltrim($url, '/');
}

/**
 * CO₂-Emissionsfaktor in kg pro Mengeneinheit (l, kWh bzw. kg).
 * Verbrenner: direkte Emission bei der Verbrennung; Elektro: deutscher Strommix.
 * Plug-in-Hybrid: je nach Einheit des Eintrags Benzin oder Strom.
 * @return float|null null, wenn kein sinnvoller Faktor bekannt ist
 */
function getCo2Faktor(?string $fuelType, ?string $einheit = null): ?float
{
    if (strtolower((string)$fuelType) === 'phev') {
        $fuelType = strtolower((string)$einheit) === 'kwh' ? 'electric' : 'e5';
    }
    $faktoren = [
        'e5' => 2.37, 'e10' => 2.37, 'petrol' => 2.37, 'hybrid' => 2.37,
        'diesel' => 2.65,
        'lpg' => 1.64,
        'cng' => 2.79,
        'electric' => 0.363,
        'hydrogen' => 0.0,
    ];
    return $faktoren[strtolower((string)$fuelType)] ?? null;
}

/**
 * Mengeneinheit passend zum Kraftstoff (wie im Backend abgeleitet): 'l', 'kwh' oder 'kg'.
 */
function getEinheitFuerKraftstoff(?string $fuelType): string
{
    $fuelType = strtolower((string)$fuelType);
    if ($fuelType === 'electric') {
        return 'kwh';
    }
    if ($fuelType === 'cng' || $fuelType === 'hydrogen') {
        return 'kg';
    }
    return 'l';
}

/** Alle Einheiten eines Fahrzeugs, Standard zuerst: Plug-in-Hybride tanken (l) und laden (kWh). */
function einheitenFuerKraftstoff(?string $fuelType): array
{
    return strtolower((string)$fuelType) === 'phev' ? ['l', 'kwh'] : [getEinheitFuerKraftstoff($fuelType)];
}

/** Kurzform für Anzeigen wie "41,2 l" oder "kWh/100km". */
function einheitKurz(?string $einheit): string
{
    return ['kwh' => 'kWh', 'kg' => 'kg'][strtolower((string)$einheit)] ?? 'l';
}

/** Langform für Formularbeschriftungen wie "Menge (Liter)". */
function einheitLang(?string $einheit): string
{
    return ['kwh' => 'kWh', 'kg' => 'kg'][strtolower((string)$einheit)] ?? 'Liter';
}

/**
 * Begriffe passend zur Antriebsart: Verbrenner tanken an der Tankstelle, E-Autos laden an der Ladestation.
 * Alle sichtbaren Texte zu Tank-/Ladevorgängen sollen von hier kommen, nie fest "tanken" oder "laden".
 * Ohne Fahrzeug (null) gibt es neutrale Begriffe, z. B. für Übersichten über mehrere Fahrzeuge.
 */
function verbrauchsBegriffe(?string $fuelType): array
{
    $fuelType = strtolower((string)$fuelType);
    if ($fuelType === 'electric') {
        return [
            'art' => 'strom',
            'vorgang' => 'Ladevorgang', 'vorgaenge' => 'Ladevorgänge', 'vorgaengen' => 'Ladevorgängen',
            'stopps' => 'Ladestopps', 'verb' => 'laden', 'Verb' => 'Laden',
            'menge' => 'Geladen', 'menge_gesamt' => 'Geladen gesamt',
            'voll' => 'Vollgeladen', 'teil' => 'Teilladung',
            'luecke' => 'Vorherige Ladevorgänge ignorieren',
            'station' => 'Ladestation', 'stationen' => 'Ladestationen',
            'energie' => 'Strom', 'kosten' => 'Stromkosten', 'preis' => 'Strompreis',
            'speicher' => 'Akku', 'icon' => 'fa-charging-station',
            'beispiel_ort' => 'z. B. Zuhause (Wallbox)',
        ];
    }
    if ($fuelType === '' || $fuelType === 'phev') {
        return [
            'art' => $fuelType === 'phev' ? 'phev' : 'neutral',
            'vorgang' => 'Tank-/Ladevorgang', 'vorgaenge' => 'Tank- und Ladevorgänge', 'vorgaengen' => 'Tank- und Ladevorgängen',
            'stopps' => 'Tank-/Ladestopps', 'verb' => 'tanken/laden', 'Verb' => 'Tanken/Laden',
            'menge' => 'Getankt/geladen', 'menge_gesamt' => 'Getankt/geladen gesamt',
            'voll' => 'Voll getankt/geladen', 'teil' => 'Teilweise',
            'luecke' => 'Vorherige Einträge ignorieren',
            'station' => 'Tankstelle/Ladestation', 'stationen' => 'Tankstellen/Ladestationen',
            'energie' => 'Kraftstoff/Strom', 'kosten' => 'Energiekosten', 'preis' => 'Preis',
            'speicher' => 'Tank/Akku', 'icon' => 'fa-gas-pump',
            'beispiel_ort' => 'z. B. Aral München Nord',
        ];
    }
    return [
        'art' => in_array($fuelType, ['cng', 'hydrogen'], true) ? 'gas' : 'fluessig',
        'vorgang' => 'Tankfüllung', 'vorgaenge' => 'Tankfüllungen', 'vorgaengen' => 'Tankfüllungen',
        'stopps' => 'Tankstopps', 'verb' => 'tanken', 'Verb' => 'Tanken',
        'menge' => 'Getankt', 'menge_gesamt' => 'Getankt gesamt',
        'voll' => 'Vollgetankt', 'teil' => 'Teilbetankung',
        'luecke' => 'Vorherige Tankfüllungen ignorieren',
        'station' => 'Tankstelle', 'stationen' => 'Tankstellen',
        'energie' => ['hydrogen' => 'Wasserstoff', 'cng' => 'Erdgas', 'lpg' => 'Autogas'][$fuelType] ?? 'Kraftstoff',
        'kosten' => ['hydrogen' => 'Wasserstoffkosten', 'cng' => 'Gaskosten', 'lpg' => 'Gaskosten'][$fuelType] ?? 'Kraftstoffkosten',
        'preis' => ['hydrogen' => 'Wasserstoffpreis', 'cng' => 'Gaspreis', 'lpg' => 'Gaspreis'][$fuelType] ?? 'Spritpreis',
        'speicher' => 'Tank', 'icon' => 'fa-gas-pump',
        'beispiel_ort' => 'z. B. Aral München Nord',
    ];
}

/**
 * Begriffe für einen einzelnen Eintrag: beim Plug-in-Hybrid entscheidet dessen Einheit
 * (kWh = Ladevorgang, sonst Tankfüllung), bei allen anderen die Antriebsart.
 */
function begriffeFuerEinheit(?string $fuelType, ?string $einheit): array
{
    if (strtolower((string)$fuelType) === 'phev' && $einheit !== null) {
        return verbrauchsBegriffe(strtolower($einheit) === 'kwh' ? 'electric' : 'e5');
    }
    return verbrauchsBegriffe($fuelType);
}

/**
 * Anzeigename einer Kategorie. Mit Kraftstoff des Fahrzeugs heißt "Tankfuellung" beim E-Auto "Ladevorgang",
 * beim Plug-in-Hybrid je nach Einheit des Eintrags.
 */
function getKategorieName($kategorie, ?string $fuelType = null, ?string $einheit = null) {
    if ($kategorie === 'Tankfuellung') {
        return $fuelType === null ? 'Tankfüllung' : begriffeFuerEinheit($fuelType, $einheit)['vorgang'];
    }
    return ['TUV' => 'TÜV'][$kategorie] ?? $kategorie;
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

    $context = stream_context_create(['http' => ['timeout' => 10]]);
    $json = @file_get_contents($url, false, $context);
    if ($json === false) {
        return null;
    }
    $data = json_decode($json, true);
    if (!isset($data['stations'])) {
        return null;
    }
    return $data['stations'];
}
