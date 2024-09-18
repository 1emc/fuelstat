<?php
// tabs/einstellungen.php

// Formular zur Bearbeitung der Fahrzeugparameter
?>

<div class="mt-4">
    <h3>Einstellungen</h3>
    <form method="post" action="fahrzeug_update.php" enctype="multipart/form-data">
        <input type="hidden" name="fahrzeug_id" value="<?php echo $fahrzeug_id; ?>">

        <div class="mb-3">
            <label for="marke" class="form-label">Marke:</label>
            <input type="text" class="form-control" name="marke" value="<?php echo htmlspecialchars($fahrzeug['marke']); ?>" required>
        </div>

        <div class="mb-3">
            <label for="modell" class="form-label">Modell:</label>
            <input type="text" class="form-control" name="modell" value="<?php echo htmlspecialchars($fahrzeug['modell']); ?>" required>
        </div>

        <!-- Weitere Felder für Tankgröße, Energiequelle etc. -->

        <div class="mb-3">
            <label for="tankgroesse" class="form-label">Tankgröße (Liter):</label>
            <input type="number" step="0.1" class="form-control" name="tankgroesse" value="<?php echo htmlspecialchars($fahrzeug['tankgroesse']); ?>">
        </div>

        <div class="mb-3">
            <label for="bild" class="form-label">Fahrzeugbild aktualisieren:</label>
            <input type="file" class="form-control" name="bild">
        </div>

        <button type="submit" class="btn btn-primary">Speichern</button>
    </form>
</div>

<?php
// Erstelle die Datei fahrzeug_update.php, um die Aktualisierung zu verarbeiten
?>
