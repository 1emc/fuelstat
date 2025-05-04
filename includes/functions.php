<?php
// includes/functions.php  — vollständige, lauffähige Version (alle Helfer + Statistikfunktionen)
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

function getPreviousVerbrauchByFahrzeug(int $fid): ?float
{
    global $conn;
    $st = $conn->prepare("SELECT menge,
          tachostand-(SELECT MAX(tachostand) FROM eintraege WHERE fahrzeug_id=? AND kategorie='Tankfuellung' AND vollgetankt=1 AND datum<e1.datum) AS km
          FROM eintraege e1 WHERE fahrzeug_id=? AND kategorie='Tankfuellung' AND vollgetankt=1 ORDER BY datum DESC LIMIT 1");
    $st->bind_param('ii', $fid, $fid);
    $st->execute();
    $r = $st->get_result()->fetch_assoc();
    $st->close();
    return ($r && $r['km'] > 0) ? round(($r['menge'] / $r['km']) * 100, 2) : null;
}

function berechneGesamtverbrauch(int $fid): ?float
{
    global $conn;
    $st = $conn->prepare("SELECT SUM(menge) AS m, MAX(tachostand)-MIN(tachostand) AS km FROM eintraege WHERE fahrzeug_id=? AND kategorie='Tankfuellung' AND vollgetankt=1");
    $st->bind_param('i', $fid);
    $st->execute();
    $d = $st->get_result()->fetch_assoc();
    $st->close();
    return ($d['km'] > 0) ? round(($d['m'] / $d['km']) * 100, 2) : null;
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

function zeigeVerbrauchstendenz(int $fid): string
{
    $c = berechneAktuellenVerbrauch($fid);
    $p = getPreviousVerbrauchByFahrzeug($fid);
    if ($c !== null && $p !== null) {
        if ($c > $p) return '<i class="fas fa-arrow-up text-danger"></i>';
        if ($c < $p) return '<i class="fas fa-arrow-down text-success"></i>';
    }
    return '<i class="fas fa-minus text-muted"></i>';
}

// --------------------------------------------------
// 3. Kostenfunktionen
// --------------------------------------------------
function berechneKostenProKmGesamt(int $fid): ?float
{
    global $conn;
    $st = $conn->prepare("SELECT SUM(kosten) AS k, MAX(tachostand)-MIN(tachostand) AS km FROM eintraege WHERE fahrzeug_id=?");
    $st->bind_param('i', $fid);
    $st->execute();
    $d = $st->get_result()->fetch_assoc();
    $st->close();
    return ($d['km'] > 0) ? round($d['k'] / $d['km'], 4) : null;
}

function berechneKraftstoffkostenProKm(int $fid): ?float
{
    global $conn;
    $st = $conn->prepare("SELECT SUM(kosten) AS k, MAX(tachostand)-MIN(tachostand) AS km FROM eintraege WHERE fahrzeug_id=? AND kategorie='Tankfuellung'");
    $st->bind_param('i', $fid);
    $st->execute();
    $d = $st->get_result()->fetch_assoc();
    $st->close();
    return ($d['km'] > 0) ? round($d['k'] / $d['km'], 4) : null;
}

// --------------------------------------------------
// 4. Statistikdatensätze (für Tab "Statistiken")
// --------------------------------------------------
function holeVerbrauchswerte(int $fahrzeug_id): array
{
    global $conn;
    $verbrauchswerte = [];
    $stmt = $conn->prepare("
        SELECT datum, menge, tachostand 
        FROM eintraege 
        WHERE fahrzeug_id = ? 
          AND kategorie = 'Tankfuellung' 
          AND vollgetankt = 1 
        ORDER BY datum
    ");
    $stmt->bind_param('i', $fahrzeug_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $previous = null;
    while ($row = $res->fetch_assoc()) {
        if ($previous) {
            $gefahrene_km = $row['tachostand'] - $previous['tachostand'];
            if ($gefahrene_km > 0) {
                $verbrauchswerte[$row['datum']] = round(($row['menge'] / $gefahrene_km) * 100, 2);
            }
        }
        $previous = $row;
    }
    $stmt->close();
    return $verbrauchswerte;
}

function holeVerbrauchProMonat(int $fahrzeug_id): array
{
    global $conn;
    $stmt = $conn->prepare("
        SELECT DATE_FORMAT(datum, '%Y-%m') AS monat, menge, tachostand 
        FROM eintraege 
        WHERE fahrzeug_id = ? 
          AND kategorie = 'Tankfuellung' 
          AND vollgetankt = 1 
        ORDER BY datum
    ");
    $stmt->bind_param('i', $fahrzeug_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $zwischen = [];
    $previous = null;
    while ($row = $res->fetch_assoc()) {
        if ($previous) {
            $monat = $row['monat'];
            $gefahrene_km = $row['tachostand'] - $previous['tachostand'];
            if ($gefahrene_km > 0) {
                $zwischen[$monat][] = ($row['menge'] / $gefahrene_km) * 100;
            }
        }
        $previous = $row;
    }
    $stmt->close();
    $verbrauchProMonat = [];
    foreach ($zwischen as $monat => $werte) {
        $verbrauchProMonat[$monat] = round(array_sum($werte) / count($werte), 2);
    }
    return $verbrauchProMonat;
}

function holeKostenProMonat(int $fahrzeug_id): array
{
    global $conn;
    $kostenProMonat = [];
    $stmt = $conn->prepare("
        SELECT DATE_FORMAT(datum, '%Y-%m') AS monat, SUM(kosten) AS summe
        FROM eintraege 
        WHERE fahrzeug_id = ?
        GROUP BY monat
        ORDER BY monat ASC
    ");
    $stmt->bind_param('i', $fahrzeug_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $kostenProMonat[$row['monat']] = round($row['summe'], 2);
    }
    $stmt->close();
    return $kostenProMonat;
}

function holeJahresdaten(int $fahrzeug_id): array
{
    global $conn;
    $jahresdaten = [];
    $stmt = $conn->prepare("
        SELECT YEAR(datum) AS jahr, SUM(menge) AS gesamt_menge, COUNT(*) AS stops, SUM(kosten) AS gesamt_ausgaben
        FROM eintraege
        WHERE fahrzeug_id = ? 
          AND kategorie = 'Tankfuellung' 
          AND vollgetankt = 1
        GROUP BY jahr
        ORDER BY jahr DESC
    ");
    $stmt->bind_param('i', $fahrzeug_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $sub = $conn->prepare("
            SELECT MAX(tachostand) - MIN(tachostand) AS km 
            FROM eintraege 
            WHERE fahrzeug_id = ? 
              AND kategorie = 'Tankfuellung' 
              AND vollgetankt = 1 
              AND YEAR(datum) = ?
        ");
        $sub->bind_param('ii', $fahrzeug_id, $row['jahr']);
        $sub->execute();
        $km_result = $sub->get_result()->fetch_assoc();
        $sub->close();
        $gefahrene_km = $km_result['km'] ?? 0;
        $jahresdaten[$row['jahr']] = [
            'anzahl_tankstops' => $row['stops'],
            'gesamt_menge' => $row['gesamt_menge'],
            'durchschnitt_verbrauch' => ($gefahrene_km > 0) ? round(($row['gesamt_menge'] / $gefahrene_km) * 100, 2) : 0,
            'gesamt_ausgaben' => $row['gesamt_ausgaben'],
        ];
    }
    $stmt->close();
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
    return $stmt->get_result()->fetch_assoc();
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
