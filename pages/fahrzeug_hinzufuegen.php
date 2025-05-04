<?php
// pages/fahrzeug_hinzufuegen.php
session_start();
include '../includes/db_connect.php';
include '../includes/functions.php';
include '../includes/header.php';
?>

<div class="container mt-5">
    <h2>Fahrzeug hinzufügen</h2>

    <form method="post" action="fahrzeug_speichern.php" enctype="multipart/form-data">
        <!-- Marke -->
        <div class="mb-3">
            <label for="marke" class="form-label">Marke</label>
            <input type="text"
                   id="marke"
                   name="marke"
                   class="form-control"
                   placeholder="z. B. Volkswagen"
                   required>
        </div>

        <!-- Modell -->
        <div class="mb-3">
            <label for="modell" class="form-label">Modell</label>
            <input type="text"
                   id="modell"
                   name="modell"
                   class="form-control"
                   placeholder="z. B. Golf"
                   required>
        </div>

        <!-- Baujahr -->
        <div class="mb-3">
            <label for="baujahr" class="form-label">Baujahr</label>
            <input type="number"
                   id="baujahr"
                   name="baujahr"
                   class="form-control"
                   min="1900"
                   max="<?php echo date('Y'); ?>"
                   placeholder="<?php echo date('Y'); ?>">
        </div>

        <!-- Tankgröße -->
        <div class="mb-3">
            <label for="tankgroesse" class="form-label">Tankgröße (Liter)</label>
            <input type="number"
                   step="0.1"
                   id="tankgroesse"
                   name="tankgroesse"
                   class="form-control"
                   placeholder="z. B. 55">
        </div>

        <!-- Kraftstofftyp -->
        <div class="mb-3">
            <label for="kraftstoff" class="form-label">Kraftstoff (bevorzugt)</label>
            <select id="kraftstoff" name="kraftstoff" class="form-select" required>
                <option value="diesel">Diesel</option>
                <option value="e5">Benzin (E5)</option>
                <option value="e10">Benzin (E10)</option>
                <option value="lpg">Autogas (LPG)</option>
                <option value="cng">Erdgas (CNG)</option>
                <option value="electric">Elektro</option>
                <option value="hybrid">Hybrid</option>
                <option value="hydrogen">Wasserstoff</option>
                <option value="other">Andere</option>
            </select>
        </div>
		
		<!-- Initialer Tachostand -->
		<div class="mb-3">
			<label for="tachostand_init" class="form-label">Tachostand bei Übernahme (km)</label>
			<input type="number"
				   id="tachostand_init"
				   name="tachostand"
				   class="form-control"
				   min="0"
				   step="1"
				   placeholder="z. B. 125 000"
				   required>
		</div>

        <!-- Bild -->
        <div class="mb-3">
            <label for="bild" class="form-label">Fahrzeugbild hochladen</label>
            <input type="file"
                   id="bild"
                   name="bild"
                   class="form-control">
            <small class="form-text text-muted">Nur JPEG, PNG oder GIF. Max. 2 MB.</small>
        </div>

        <button type="submit" class="btn btn-primary">Speichern</button>
        <a href="../index.php" class="btn btn-secondary ms-2">Abbrechen</a>
    </form>
</div>

<?php include '../includes/footer.php'; ?>
