<?php
// pages/eintrag_bearbeiten.php
session_start();
include '../includes/db_connect.php';
include '../includes/functions.php';
include '../includes/header.php';

// Parameter prüfen
if (!isset($_GET['id'], $_GET['fahrzeug_id'])) {
    die("Eintrag-ID oder Fahrzeug-ID fehlt.");
}
$eintrag_id    = intval($_GET['id']);
$fahrzeug_id   = intval($_GET['fahrzeug_id']);

// Eintrag laden
$stmt = $conn->prepare("SELECT * FROM eintraege WHERE id = ? AND fahrzeug_id = ?");
$stmt->bind_param("ii", $eintrag_id, $fahrzeug_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    die("Eintrag nicht gefunden.");
}
$eintrag = $result->fetch_assoc();
$stmt->close();
?>

<div class="container mt-5">
    <h2>Eintrag bearbeiten</h2>
    <form method="post" action="eintrag_update.php" class="mt-4">
        <input type="hidden" name="id" value="<?php echo $eintrag_id; ?>">
        <input type="hidden" name="fahrzeug_id" value="<?php echo $fahrzeug_id; ?>">
        <!-- Kategorie (readonly) -->
        <div class="mb-3">
            <label class="form-label">Kategorie</label>
            <input type="text" class="form-control" name="kategorie" value="<?php echo htmlspecialchars($eintrag['kategorie']); ?>" readonly>
        </div>

        <!-- Gemeinsame Felder -->
        <div class="mb-3">
            <label class="form-label">Datum</label>
            <input type="date" class="form-control" name="datum" value="<?php echo $eintrag['datum']; ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Tachostand (km)</label>
            <input type="number" class="form-control" name="tachostand" value="<?php echo $eintrag['tachostand']; ?>" required>
        </div>

        <?php if ($eintrag['kategorie'] === 'Tankfuellung'): ?>
            <!-- Tankfüllungs-Felder -->
            <div class="mb-3">
                <label class="form-label">Standort</label>
                <input type="text" class="form-control" name="standort" value="<?php echo htmlspecialchars($eintrag['standort']); ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">Menge (Liter)</label>
                <input type="number" step="0.01" class="form-control" name="menge" value="<?php echo $eintrag['menge']; ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Kosten (€)</label>
                <input type="number" step="0.01" class="form-control" name="kosten" value="<?php echo $eintrag['kosten']; ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Preis pro Einheit (€)</label>
                <input type="number" step="0.001" class="form-control" name="preis_pro_einheit" value="<?php echo $eintrag['preis_pro_einheit']; ?>" required>
            </div>
            <div class="form-check mb-3">
                <input type="checkbox" class="form-check-input" name="vollgetankt" value="1" <?php echo $eintrag['vollgetankt'] ? 'checked' : ''; ?>>
                <label class="form-check-label">Vollgetankt</label>
            </div>
        <?php elseif ($eintrag['kategorie'] === 'Andere Ausgabe'): ?>
            <!-- Andere Ausgabe-Felder -->
            <div class="mb-3">
                <label class="form-label">Kategorie</label>
                <input type="text" class="form-control" value="<?php echo htmlspecialchars($eintrag['kategorie']); ?>" readonly>
            </div>
            <div class="mb-3">
                <label class="form-label">Kosten (€)</label>
                <input type="number" step="0.01" class="form-control" name="kosten" value="<?php echo $eintrag['kosten']; ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Beschreibung</label>
                <textarea class="form-control" name="beschreibung"><?php echo htmlspecialchars($eintrag['beschreibung']); ?></textarea>
            </div>
        <?php elseif ($eintrag['kategorie'] === 'Fahrt'): ?>
            <!-- Fahrt-Felder -->
            <div class="mb-3">
                <label class="form-label">Startort</label>
                <input type="text" class="form-control" name="startort" value="<?php echo htmlspecialchars($eintrag['startort']); ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Zielort</label>
                <input type="text" class="form-control" name="zielort" value="<?php echo htmlspecialchars($eintrag['zielort']); ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Zweck</label>
                <input type="text" class="form-control" name="zweck" value="<?php echo htmlspecialchars($eintrag['zweck']); ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">Gefahrene Kilometer</label>
                <input type="number" step="0.1" class="form-control" name="gefahrene_km" value="<?php echo $eintrag['gefahrene_km']; ?>" required>
            </div>
        <?php endif; ?>

        <div class="d-flex mt-4">
            <button type="submit" class="btn btn-primary me-2">Speichern</button>
            <a href="fahrzeug_detail.php?id=<?php echo $fahrzeug_id; ?>" class="btn btn-secondary me-auto">Abbrechen</a>
        </div>
    </form>

    <!-- Lösch-Formular mit Bestätigung -->
    <form method="post" action="eintrag_loeschen.php" onsubmit="return confirm('Möchtest du diesen Eintrag wirklich löschen?');" class="mt-3">
        <input type="hidden" name="id" value="<?php echo $eintrag_id; ?>">
        <input type="hidden" name="fahrzeug_id" value="<?php echo $fahrzeug_id; ?>">
        <button type="submit" class="btn btn-danger">Eintrag löschen</button>
    </form>
</div>

<?php include '../includes/footer.php'; ?>
