<?php
include '../includes/session.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
include '../includes/header.php';
?>
<div class="container mt-5">
    <div class="alert alert-info">
        <strong>Passwort ändern</strong><br>
        Bei Nutzung der API-Anbindung (OpenAPI 1.0.0) ist die Passwortänderung in dieser App nicht verfügbar. Bitte nutzen Sie die vom API-Betreiber bereitgestellte Möglichkeit (z. B. Backend oder Support).
    </div>
    <a href="einstellungen.php" class="btn btn-secondary">Zurück zu Einstellungen</a>
</div>
<?php include '../includes/footer.php'; ?>
