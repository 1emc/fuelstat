<?php
include '../includes/session.php';
include '../includes/db_connect.php';

$fahrzeug_id = trim($_POST['fahrzeug_id'] ?? '');
$eintragstyp = $_POST['eintragstyp'] ?? '';
$datum = $_POST['datum'] ?? null;
$tachostand = isset($_POST['tachostand']) ? (int)$_POST['tachostand'] : null;

if (!$fahrzeug_id || !$datum || $tachostand === null) {
    $_SESSION['error_message'] = 'Fahrzeug, Datum und Tachostand sind erforderlich.';
    header('Location: eintrag_hinzufuegen.php?fahrzeug_id=' . rawurlencode($fahrzeug_id));
    exit;
}

$dateTime = $datum . 'T12:00:00.000Z'; // ISO für API

try {
    if ($eintragstyp === 'Tankfuellung') {
        $menge = (float)($_POST['menge'] ?? 0);
        $kosten = (float)($_POST['kosten'] ?? 0);
        $station = trim($_POST['standort'] ?? '');
        $vollgetankt = isset($_POST['vollgetankt']);
        $skipPrevious = isset($_POST['skip_previous']);
        $api->postFillup([
            'vehicleId' => $fahrzeug_id,
            'odometerKm' => $tachostand,
            'unit' => 'l',
            'amount' => $menge,
            'totalCostEur' => $kosten,
            'station' => $station !== '' ? $station : null,
            'filledAt' => $dateTime,
            'isFull' => $vollgetankt,
            'skipPrevious' => $skipPrevious,
        ]);
    } elseif ($eintragstyp === 'Andere Ausgabe') {
        $kategorie = trim($_POST['kategorie'] ?? '');
        $kosten = (float)($_POST['kosten'] ?? 0);
        $beschreibung = trim($_POST['beschreibung'] ?? '');
        if ($kategorie === '') {
            $_SESSION['error_message'] = 'Bitte eine Kategorie wählen.';
            header('Location: eintrag_hinzufuegen.php?fahrzeug_id=' . rawurlencode($fahrzeug_id));
            exit;
        }
        $api->postEntry([
            'vehicleId' => $fahrzeug_id,
            'category' => $kategorie,
            'amountEur' => $kosten,
            'odometerKm' => $tachostand,
            'occurredAt' => $dateTime,
            'description' => $beschreibung !== '' ? $beschreibung : null,
        ]);
    } elseif ($eintragstyp === 'Fahrt') {
        $api->postEntry([
            'vehicleId' => $fahrzeug_id,
            'category' => 'Fahrt',
            'amountEur' => 0,
            'odometerKm' => $tachostand,
            'occurredAt' => $dateTime,
            'description' => trim($_POST['startort'] ?? '') . ' → ' . trim($_POST['zielort'] ?? '') . ' – ' . trim($_POST['zweck'] ?? ''),
        ]);
    } else {
        $_SESSION['error_message'] = 'Ungültiger Eintragstyp.';
        header('Location: eintrag_hinzufuegen.php?fahrzeug_id=' . rawurlencode($fahrzeug_id));
        exit;
    }
    $_SESSION['success_message'] = 'Eintrag gespeichert.';
    header('Location: fahrzeug_detail.php?id=' . rawurlencode($fahrzeug_id));
    exit;
} catch (Throwable $e) {
    $_SESSION['error_message'] = $e->getMessage();
    header('Location: eintrag_hinzufuegen.php?fahrzeug_id=' . rawurlencode($fahrzeug_id));
    exit;
}
