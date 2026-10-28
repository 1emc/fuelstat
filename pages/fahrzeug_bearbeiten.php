<?php
include '../includes/session.php';
include '../includes/db_connect.php';
include '../includes/header.php';
include_once '../includes/functions.php';

if (!isset($_GET['id'])) {
    die("Fahrzeug-ID fehlt.");
}
$fahrzeug_id = trim($_GET['id']);

try {
    $fahrzeug = $api->getVehicle($fahrzeug_id);
} catch (Throwable $e) {
    die("Fahrzeug nicht gefunden.");
}

$formAction = 'fahrzeug_update.php';
$abbrechenUrl = 'fahrzeug_detail.php?id=' . urlencode($fahrzeug_id);
?>

<div class="container mt-5">
    <h2>Fahrzeug bearbeiten</h2>
    <?php include '../includes/form_fahrzeug.php'; ?>

    <div class="card border-danger mt-5 mb-4">
        <div class="card-body">
            <h5 class="card-title text-danger"><i class="fas fa-trash-alt text-danger me-2"></i>Fahrzeug löschen</h5>
            <p class="card-text">Löscht das Fahrzeug mit allen Tankfüllungen, Ausgaben und Bildern endgültig.</p>
            <form method="post" action="fahrzeug_loeschen.php"
                  onsubmit="return confirm(<?= htmlspecialchars(json_encode('„' . ($fahrzeug['name'] ?? 'Fahrzeug') . '“ mit allen Einträgen endgültig löschen?'), ENT_QUOTES) ?>);">
                <input type="hidden" name="fahrzeug_id" value="<?= htmlspecialchars($fahrzeug_id) ?>">
                <button type="submit" class="btn btn-danger"><i class="fas fa-trash-alt me-1"></i> Fahrzeug löschen</button>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
