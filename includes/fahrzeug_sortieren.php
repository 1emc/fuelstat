<?php
// includes/fahrzeug_sortieren.php
include 'session.php';
include 'db_connect.php';

header('Content-Type: text/plain; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit('Nicht eingeloggt.');
}

if (!isset($_POST['order']) || !is_array($_POST['order'])) {
    http_response_code(400);
    exit('Fehlerhafte Daten.');
}

$user_id = $_SESSION['user_id'];
$order = array_map('intval', $_POST['order']);

// Hole alle Fahrzeug-IDs des Nutzers
$stmt = $conn->prepare("SELECT id FROM fahrzeuge WHERE benutzer_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
$userFahrzeuge = array();
while ($row = $res->fetch_assoc()) {
    $userFahrzeuge[] = (int)$row['id'];
}
$stmt->close();

// Prüfe, ob alle übergebenen IDs zu den Fahrzeugen des Nutzers gehören
foreach ($order as $id) {
    if (!in_array($id, $userFahrzeuge, true)) {
        http_response_code(403);
        exit('Ungültige Fahrzeug-ID.');
    }
}

// Update sortierung für jedes Fahrzeug
foreach ($order as $sort => $fahrzeug_id) {
    $stmt = $conn->prepare("UPDATE fahrzeuge SET sortierung = ? WHERE id = ? AND benutzer_id = ?");
    $stmt->bind_param("iii", $sort, $fahrzeug_id, $user_id);
    $stmt->execute();
    $stmt->close();
}

exit('Reihenfolge gespeichert!');
