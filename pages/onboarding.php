<?php
include '../includes/session.php';
include '../includes/db_connect.php';
require_once __DIR__ . '/../includes/api_helpers.php';

$vehicleCount = 0;
$vehicleId = null;
$isAuthenticated = isApiAuthenticated();
$api = getApiClient();

if ($isAuthenticated) {
    try {
        $token = getApiToken() ?? '';
        error_log('[Onboarding] token_len=' . strlen($token) . ' base_url=' . getApiBaseUrl());
        $vehicles = $api->getVehicles();
        $vehicleCount = count($vehicles);
        error_log('[Onboarding] getVehicles ok count=' . $vehicleCount);

        if ($vehicleCount > 0) {
            $vehicleId = $vehicles[0]['id'];
            $hasAnyEntry = false;
            foreach ($vehicles as $v) {
                $fillups = $api->getVehicleFillups($v['id'], 1);
                $entries = $api->getVehicleEntries($v['id'], 1);
                if (count($fillups) > 0 || count($entries) > 0) {
                    $hasAnyEntry = true;
                    break;
                }
            }
            if ($hasAnyEntry) {
                header('Location: /index.php', true, 302);
                exit;
            }
        }
    } catch (Throwable $e) {
        $code = method_exists($e, 'getCode') ? (int)$e->getCode() : 0;
        error_log('[Onboarding] API call failed code=' . $code . ' message=' . $e->getMessage());
        if ($code === 401) {
            clearApiToken();
            header('Location: /pages/login.php', true, 302);
            exit;
        }
    }
}

include '../includes/header.php';
?>

<div class="container mt-5">
    <?php if (!$isAuthenticated): ?>
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
