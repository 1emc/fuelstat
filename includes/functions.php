<?php
// includes/functions.php

//function berechneGesamtverbrauch($fahrzeug_id) {
//    global $conn;
//
//    // Implementiere die Logik zur Berechnung des Gesamtverbrauchs
//    // Beispiel:
//    $stmt = $conn->prepare("SELECT SUM(menge) AS gesamt_menge, (MAX(tachostand) - MIN(tachostand)) AS gefahrene_km FROM Eintraege WHERE fahrzeug_id = ? AND kategorie = 'Tankfüllung'");
//    $stmt->bind_param("i", $fahrzeug_id);
//    $stmt->execute();
//    $result = $stmt->get_result();
//    $daten = $result->fetch_assoc();
//
//    if ($daten['gefahrene_km'] > 0) {
//        $verbrauch = ($daten['gesamt_menge'] / $daten['gefahrene_km']) * 100;
//        return number_format($verbrauch, 2, ',', '.');
//    } else {
//        return '0,00';
//    }
//}

// Weitere Funktionen...
?><!-- include function -->
