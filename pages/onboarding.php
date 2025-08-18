<?php
// pages/onboarding.php

// Session handling and DB connection
include '../includes/session.php';
include '../includes/db_connect.php';

// Helper function to count rows
function getCount($conn, $sql, $param) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $param);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    return (int)$result['cnt'];
}

// Pre-calculate onboarding state and redirect before output
$vehicleCount = 0;
$vehicleId = null;
if (isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];
    $vehicleCount = getCount($conn, 'SELECT COUNT(*) AS cnt FROM fahrzeuge WHERE benutzer_id = ?', $userId);
    if ($vehicleCount > 0) {
        $entryCountStmt = $conn->prepare('SELECT COUNT(e.id) AS cnt FROM eintraege e JOIN fahrzeuge f ON e.fahrzeug_id = f.id WHERE f.benutzer_id = ?');
        $entryCountStmt->bind_param('i', $userId);
        $entryCountStmt->execute();
        $entryCount = (int)$entryCountStmt->get_result()->fetch_assoc()['cnt'];
        if ($entryCount > 0) {
            header('Location: ../index.php');
            exit;
        }
        // Get first vehicle id for entry link
        $vehicleStmt = $conn->prepare('SELECT id FROM fahrzeuge WHERE benutzer_id = ? ORDER BY id ASC LIMIT 1');
        $vehicleStmt->bind_param('i', $userId);
        $vehicleStmt->execute();
        $vehicleId = $vehicleStmt->get_result()->fetch_assoc()['id'];
    }
}

include '../includes/header.php';

?>
<div class="container mt-5">
    <?php if (!isset($_SESSION['user_id'])): ?>
        <h1 class="mb-3">Willkommen bei Fuelstat</h1>
        <p class="mb-4">Um zu starten, erstellen Sie bitte ein Konto.</p>
        <a href="register.php" class="btn btn-primary">
            <i class="fas fa-user-plus me-2"></i>Jetzt registrieren
        </a>
    <?php else: ?>
        <?php if ($vehicleCount === 0): ?>
            <h1 class="mb-3">Erstes Fahrzeug anlegen</h1>
            <p class="mb-4">Fügen Sie Ihr erstes Fahrzeug hinzu, um loszulegen.</p>
            <a href="fahrzeug_hinzufuegen.php" class="btn btn-primary">
                <i class="fas fa-car-side me-2"></i>Fahrzeug hinzufügen
            </a>
        <?php else: ?>
            <h1 class="mb-3">Ersten Eintrag erfassen</h1>
            <p class="mb-4">Erfassen Sie eine Tankfüllung oder andere Ausgabe, um Statistiken zu erhalten.</p>
            <a href="eintrag_hinzufuegen.php?fahrzeug_id=<?= intval($vehicleId) ?>" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>Eintrag hinzufügen
            </a>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>
