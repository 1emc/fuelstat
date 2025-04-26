<?php
// pages/fahrzeug_bearbeiten.php
session_start();
include '../includes/db_connect.php';
include '../includes/functions.php';
include '../includes/header.php';

// Fahrzeug-ID auslesen
if (!isset($_GET['id'])) {
    die("Fahrzeug-ID fehlt.");
}
$fahrzeug_id = intval($_GET['id']);

// Fahrzeugdaten laden
$stmt = $conn->prepare("SELECT * FROM fahrzeuge WHERE id = ?");
$stmt->bind_param("i", $fahrzeug_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    die("Fahrzeug nicht gefunden.");
}
$fahrzeug = $result->fetch_assoc();
$stmt->close();
?>

<div class="container mt-5">
    <h2>Fahrzeug bearbeiten</h2>
    <form method="post" action="fahrzeug_update.php" enctype="multipart/form-data">
        <input type="hidden" name="fahrzeug_id" value="<?php echo $fahrzeug_id; ?>">

        <div class="mb-3">
            <label for="marke" class="form-label">Marke</label>
            <input type="text" id="marke" name="marke" class="form-control"
                   value="<?php echo htmlspecialchars($fahrzeug['marke']); ?>" required>
        </div>

        <div class="mb-3">
            <label for="modell" class="form-label">Modell</label>
            <input type="text" id="modell" name="modell" class="form-control"
                   value="<?php echo htmlspecialchars($fahrzeug['modell']); ?>" required>
        </div>

        <div class="mb-3">
            <label for="baujahr" class="form-label">Baujahr</label>
            <input type="number" id="baujahr" name="baujahr" class="form-control"
                   value="<?php echo htmlspecialchars($fahrzeug['baujahr']); ?>" min="1900" max="<?php echo date('Y'); ?>">
        </div>

        <div class="mb-3">
            <label for="tankgroesse" class="form-label">Tankgröße (Liter)</label>
            <input type="number" step="0.1" id="tankgroesse" name="tankgroesse" class="form-control"
                   value="<?php echo htmlspecialchars($fahrzeug['tankgroesse']); ?>">
        </div>

        <div class="mb-3">
            <label class="form-label">Aktuelles Fahrzeugbild</label><br>
            <?php if (!empty($fahrzeug['bild'])): ?>
                <img src="../images/<?php echo htmlspecialchars($fahrzeug['bild']); ?>" 
                     alt="Fahrzeugbild" class="img-thumbnail mb-3" style="max-width:200px;">
            <?php else: ?>
                <p>Kein Bild hinterlegt.</p>
            <?php endif; ?>
        </div>

        <div class="mb-3">
            <label for="bild" class="form-label">Neues Bild hochladen</label>
            <input type="file" id="bild" name="bild" class="form-control">
            <small class="form-text text-muted">Nur JPEG, PNG oder GIF.</small>
        </div>

        <button type="submit" class="btn btn-primary">Speichern</button>
        <a href="fahrzeug_detail.php?id=<?php echo $fahrzeug_id; ?>" class="btn btn-secondary ms-2">Abbrechen</a>
    </form>
</div>

<?php include '../includes/footer.php'; ?>
