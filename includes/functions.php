<?php
// includes/functions.php

/**
 * Gibt das entsprechende Font Awesome Icon für eine gegebene Kategorie zurück.
 *
 * @param string $kategorie Die Kategorie des Eintrags.
 * @return string Der Font Awesome Klassennamen für das Icon.
 */
function getCategoryIcon($kategorie) {
    $icons = [
        'Tankfüllung'     => 'fa-gas-pump',
        'Versicherung'    => 'fa-shield-alt',
        'Werkstatt'       => 'fa-tools',
        'Inspektion'      => 'fa-wrench',
        'Steuer'          => 'fa-money-bill-wave',
        'Reparatur'       => 'fa-car-crash',
        'Reifen'          => 'fa-car-side',
        'TÜV'             => 'fa-calendar-check',
        'Wartung'         => 'fa-cogs',
        'Dekor'           => 'fa-paint-roller',
        'Verbrauch'       => 'fa-chart-line',
        // Füge weitere Kategorien und Icons nach Bedarf hinzu
    ];

    return isset($icons[$kategorie]) ? $icons[$kategorie] : 'fa-question-circle';
}

/**
 * Berechnet die gefahrenen Kilometer seit der letzten Tankfüllung.
 *
 * @param array $eintrag Der aktuelle Eintrag aus der Datenbank.
 * @param int $fahrzeug_id Die ID des Fahrzeugs.
 * @return int|null Die gefahrenen Kilometer oder null, wenn nicht verfügbar.
 */
function berechneGefahreneKm($eintrag, $fahrzeug_id) {
    global $conn;

    // Stelle sicher, dass der aktuelle Eintrag eine Tankfüllung ist
    if ($eintrag['kategorie'] !== 'Tankfüllung') {
        return null;
    }

    // Wenn skip_previous aktiviert ist, ignoriere alle vorherigen Tankfüllungen
    if ($eintrag['skip_previous']) {
        // Suche nach der letzten Tankfüllung, die vollgetankt war, unabhängig von skipped entries
        $stmt = $conn->prepare("
            SELECT tachostand 
            FROM eintraege 
            WHERE fahrzeug_id = ? 
              AND kategorie = 'Tankfüllung' 
              AND vollgetankt = b'1' 
              AND datum < ? 
            ORDER BY datum DESC 
            LIMIT 1
        ");
        $stmt->bind_param("is", $fahrzeug_id, $eintrag['datum']);
    } else {
        // Suche nach der letzten Tankfüllung, die vollgetankt war und nicht skipped
        $stmt = $conn->prepare("
            SELECT tachostand 
            FROM eintraege 
            WHERE fahrzeug_id = ? 
              AND kategorie = 'Tankfüllung' 
              AND vollgetankt = b'1' 
              AND datum < ? 
              AND skip_previous = b'0'
            ORDER BY datum DESC 
            LIMIT 1
        ");
        $stmt->bind_param("is", $fahrzeug_id, $eintrag['datum']);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $vorheriger_eintrag = $result->fetch_assoc();
        $gefahrene_km = $eintrag['tachostand'] - $vorheriger_eintrag['tachostand'];

        // Stelle sicher, dass die gefahrenen Kilometer positiv sind
        if ($gefahrene_km > 0) {
            return $gefahrene_km;
        } else {
            return null;
        }
    } else {
        // Kein vorheriger Eintrag gefunden
        return null;
    }

    $stmt->close();
}

/**
 * Berechnet den Kraftstoffverbrauch (l/100km) für einen Tankfüllungseintrag.
 *
 * @param array $eintrag Der aktuelle Eintrag aus der Datenbank.
 * @param int $fahrzeug_id Die ID des Fahrzeugs.
 * @return float|null Der berechnete Verbrauch oder null, wenn keine Daten vorhanden sind.
 */
function berechneVerbrauchEintrag($eintrag, $fahrzeug_id) {
    global $conn;

    // Stelle sicher, dass der aktuelle Eintrag eine Tankfüllung ist und vollgetankt wurde
    if ($eintrag['kategorie'] !== 'Tankfüllung' || $eintrag['vollgetankt'] == 0) {
        return "zwischen"; // Kein Verbrauch zu berechnen, wenn nicht vollgetankt wurde
    }

    // Suche den vorherigen vollgetankten Eintrag (vor dem aktuellen)
    $stmt = $conn->prepare("
        SELECT menge, tachostand, datum 
        FROM eintraege 
        WHERE fahrzeug_id = ? 
          AND kategorie = 'Tankfüllung' 
          AND vollgetankt = 1 
          AND datum < ? 
        ORDER BY datum DESC 
        LIMIT 1
    ");
    $stmt->bind_param("is", $fahrzeug_id, $eintrag['datum']);
    $stmt->execute();
    $result = $stmt->get_result();
    $vorheriger_eintrag = $result->fetch_assoc();
    $stmt->close();

    if (!$vorheriger_eintrag) {
        // Kein vorheriger vollgetankter Eintrag gefunden
        return null;
    }

    // Berechne die gefahrenen Kilometer zwischen den beiden vollgetankten Einträgen
    $gefahrene_km = $eintrag['tachostand'] - $vorheriger_eintrag['tachostand'];

    if ($gefahrene_km <= 0) {
        // Ungültige Kilometerdifferenz
        return null;
    }

    // Suche alle Teilbetankungen (vollgetankt = 0) zwischen den zwei vollgetankten Einträgen
    $stmt = $conn->prepare("
        SELECT SUM(menge) AS gesamt_menge 
        FROM eintraege 
        WHERE fahrzeug_id = ? 
          AND kategorie = 'Tankfüllung' 
          AND vollgetankt = 0 
          AND datum > ? 
          AND datum < ?
    ");
    $stmt->bind_param("iss", $fahrzeug_id, $vorheriger_eintrag['datum'], $eintrag['datum']);
    $stmt->execute();
    $result = $stmt->get_result();
    $teilbetankungen = $result->fetch_assoc();
    $stmt->close();

    // Berechne die gesamte getankte Menge (aktuelle Menge + Teilbetankungen)
    $gesamt_menge = $eintrag['menge'] + ($teilbetankungen['gesamt_menge'] ?? 0);

    // Berechne den Verbrauch (gesamt_menge / gefahrene Kilometer * 100)
    $verbrauch = ($gesamt_menge / $gefahrene_km) * 100;

    return $verbrauch;
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

    // Schritt 1: Finde den vorherigen Tankfüllungseintrag (e_prev)
    $stmt_prev = $conn->prepare("
        SELECT * FROM eintraege 
        WHERE fahrzeug_id = ? 
          AND kategorie = 'Tankfüllung' 
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

    // Schritt 2: Finde den Eintrag vor dem vorherigen Tankfüllungseintrag (e_prev_prev)
    $stmt_prev_prev = $conn->prepare("
        SELECT tachostand FROM eintraege 
        WHERE fahrzeug_id = ? 
          AND kategorie = 'Tankfüllung' 
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
 * Berechnet den gesamten Kraftstoffverbrauch (l/100km) für ein Fahrzeug.
 *
 * @param int $fahrzeug_id Die ID des Fahrzeugs.
 * @return float|null Der Gesamtverbrauch oder null, wenn keine Daten vorhanden sind.
 */
function berechneGesamtverbrauch($fahrzeug_id) {
    global $conn;

    // Berechne den Gesamtverbrauch basierend auf allen Tankfüllungen
    $stmt = $conn->prepare("
        SELECT SUM(menge) AS gesamt_menge, MAX(tachostand) - MIN(tachostand) AS gefahrene_km
        FROM eintraege
        WHERE fahrzeug_id = ? AND kategorie = 'Tankfüllung' AND vollgetankt = 1
    ");
    $stmt->bind_param("i", $fahrzeug_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();

    if ($data['gefahrene_km'] > 0) {
        return ($data['gesamt_menge'] / $data['gefahrene_km']) * 100;
    } else {
        return null;
    }
}


/**
 * Berechnet den aktuellen Kraftstoffverbrauch (l/100km) für die letzte Tankfüllung.
 *
 * @param int $fahrzeug_id Die ID des Fahrzeugs.
 * @return float|null Der aktuelle Verbrauch oder null, wenn keine Daten vorhanden sind.
 */
function berechneAktuellenVerbrauch($fahrzeug_id) {
    global $conn;

    // Berechne den Verbrauch basierend auf der letzten Tankfüllung
    $stmt = $conn->prepare("
        SELECT menge, tachostand - (
            SELECT MAX(tachostand) FROM eintraege WHERE fahrzeug_id = ? AND kategorie = 'Tankfüllung' AND vollgetankt = 1 AND datum < e1.datum
        ) AS gefahrene_km
        FROM eintraege e1
        WHERE fahrzeug_id = ? AND kategorie = 'Tankfüllung' AND vollgetankt = 1
        ORDER BY datum DESC
        LIMIT 1
    ");
    $stmt->bind_param("ii", $fahrzeug_id, $fahrzeug_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();

    if ($data['gefahrene_km'] > 0) {
        return ($data['menge'] / $data['gefahrene_km']) * 100;
    } else {
        return null;
    }
}

/**
 * Zeigt die Tendenz des Kraftstoffverbrauchs an.
 *
 * @param int $fahrzeug_id Die ID des Fahrzeugs.
 * @return string Der HTML-Code für den Trendpfeil (auf, ab, neutral).
 */
function zeigeVerbrauchstendenz($fahrzeug_id) {
    $aktuellerVerbrauch = berechneAktuellenVerbrauch($fahrzeug_id);
    $vorherigerVerbrauch = getPreviousVerbrauchByFahrzeug($fahrzeug_id);

    if ($vorherigerVerbrauch !== null && $aktuellerVerbrauch !== null) {
        if ($aktuellerVerbrauch > $vorherigerVerbrauch) {
            return '<i class="fas fa-arrow-up text-danger"></i>';
        } elseif ($aktuellerVerbrauch < $vorherigerVerbrauch) {
            return '<i class="fas fa-arrow-down text-success"></i>';
        }
    }

    return '<i class="fas fa-minus text-muted"></i>';
}

/**
 * Berechnet die Gesamtkosten pro Kilometer (alle Ausgaben geteilt durch gefahrene Kilometer).
 *
 * @param int $fahrzeug_id Die ID des Fahrzeugs.
 * @return float|null Die Gesamtkosten pro Kilometer oder null, wenn keine Daten vorhanden sind.
 */
function berechneKostenProKmGesamt($fahrzeug_id) {
    global $conn;

    // Berechne die Gesamtkosten pro Kilometer basierend auf allen Ausgaben und gefahrenen Kilometern
    $stmt = $conn->prepare("
        SELECT SUM(kosten) AS gesamt_kosten, MAX(tachostand) - MIN(tachostand) AS gefahrene_km
        FROM eintraege
        WHERE fahrzeug_id = ?
    ");
    $stmt->bind_param("i", $fahrzeug_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();

    if ($data['gefahrene_km'] > 0) {
        return $data['gesamt_kosten'] / $data['gefahrene_km'];
    } else {
        return null;
    }
}

/**
 * Berechnet die Kraftstoffkosten pro Kilometer.
 *
 * @param int $fahrzeug_id Die ID des Fahrzeugs.
 * @return float|null Die Kraftstoffkosten pro Kilometer oder null, wenn keine Daten vorhanden sind.
 */
function berechneKraftstoffkostenProKm($fahrzeug_id) {
    global $conn;

    // Berechne die Kraftstoffkosten pro Kilometer basierend auf den Tankfüllungen
    $stmt = $conn->prepare("
        SELECT SUM(kosten) AS kraftstoff_kosten, MAX(tachostand) - MIN(tachostand) AS gefahrene_km
        FROM eintraege
        WHERE fahrzeug_id = ? AND kategorie = 'Tankfüllung'
    ");
    $stmt->bind_param("i", $fahrzeug_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();

    if ($data['gefahrene_km'] > 0) {
        return $data['kraftstoff_kosten'] / $data['gefahrene_km'];
    } else {
        return null;
    }
}

/**
 * Holt den Verbrauchswert der letzten Tankfüllung für ein Fahrzeug.
 *
 * @param int $fahrzeug_id Die ID des Fahrzeugs.
 * @return float|null Der vorherige Verbrauch oder null, wenn keine Daten vorhanden sind.
 */
function getPreviousVerbrauchByFahrzeug($fahrzeug_id) {
    global $conn;

    // Schritt 1: Finde den letzten Tankfüllungseintrag (e_prev)
    $stmt_prev = $conn->prepare("
        SELECT menge, tachostand - (
            SELECT MAX(tachostand) FROM eintraege WHERE fahrzeug_id = ? AND kategorie = 'Tankfüllung' AND vollgetankt = 1 AND datum < e1.datum
        ) AS gefahrene_km
        FROM eintraege e1
        WHERE fahrzeug_id = ? AND kategorie = 'Tankfüllung' AND vollgetankt = 1
        ORDER BY datum DESC
        LIMIT 1
    ");
    if (!$stmt_prev) {
        die("Fehler bei der Vorbereitung der SQL-Anweisung: " . $conn->error);
    }
    
    $stmt_prev->bind_param("ii", $fahrzeug_id, $fahrzeug_id);
    $stmt_prev->execute();
    $result_prev = $stmt_prev->get_result();

    if ($result_prev->num_rows === 0) {
        // Kein vorheriger Eintrag gefunden
        $stmt_prev->close();
        return null;
    }

    $previous_entry = $result_prev->fetch_assoc();
    $stmt_prev->close();

    // Berechne den Verbrauch
    if ($previous_entry['gefahrene_km'] > 0) {
        return ($previous_entry['menge'] / $previous_entry['gefahrene_km']) * 100;
    } else {
        return null;
    }
}

/**
 * Holt den aktuellsten Tachostand für ein Fahrzeug basierend auf den Einträgen.
 *
 * @param int $fahrzeug_id Die ID des Fahrzeugs.
 * @return int|null Der aktuellste Tachostand oder null, wenn keine Daten vorhanden sind.
 */
function getAktuellenTachostand($fahrzeug_id) {
    global $conn;

    // Suche nach dem höchsten Tachostand für das Fahrzeug
    $stmt = $conn->prepare("
        SELECT MAX(tachostand) AS aktueller_tachostand 
        FROM eintraege 
        WHERE fahrzeug_id = ?
    ");
    if (!$stmt) {
        die("Fehler bei der Vorbereitung der SQL-Anweisung: " . $conn->error);
    }

    $stmt->bind_param("i", $fahrzeug_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    $stmt->close();

    return $data['aktueller_tachostand'] ?? null;
}

/**
 * Berechnet die Fahrleistung basierend auf dem aktuellen Tachostand und dem Initialwert im Fahrzeugobjekt.
 *
 * @param int $fahrzeug_id Die ID des Fahrzeugs.
 * @param int $initial_tachostand Der initiale Tachostand, der im Fahrzeugobjekt gespeichert ist.
 * @return int|null Die Fahrleistung oder null, wenn keine Daten vorhanden sind.
 */
function berechneFahrleistung($fahrzeug_id, $initial_tachostand) {
    // Hole den aktuellen Tachostand
    $aktueller_tachostand = getAktuellenTachostand($fahrzeug_id);

    if ($aktueller_tachostand !== null) {
        // Berechne die Fahrleistung
        return $aktueller_tachostand - $initial_tachostand;
    }

    return null; // Falls kein aktueller Tachostand vorhanden ist
}
?>
