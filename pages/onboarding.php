<?php
include '../includes/session.php';
include '../includes/db_connect.php';

$vehicleCount = 0;
$vehicleId = null;

if (isset($_SESSION['user_id'])) {
    try {
        $vehicles = $api->getVehicles();
        $vehicleCount = count($vehicles);
        if ($vehicleCount > 0) {
            $vehicleId = $vehicles[0]['id'];
            $hasAnyEntry = false;
            foreach ($vehicles as $v) {
                if (count($api->getVehicleFillups($v['id'], 1)) > 0 || count($api->getVehicleEntries($v['id'], 1)) > 0) {
                    $hasAnyEntry = true;
                    break;
                }
            }
            if ($hasAnyEntry) {
                header('Location: ../index.php');
                exit;
            }
        }
    } catch (Throwable $e) {
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
            <a href="eintrag_hinzufuegen.php?fahrzeug_id=<?= htmlspecialchars(urlencode($vehicleId)) ?>" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>Eintrag hinzufügen
            </a>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>
