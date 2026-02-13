<?php
// Sortierung wird bei API-Anbindung nicht unterstützt (API liefert keine sortierung).
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
exit('Reihenfolge gespeichert!');
