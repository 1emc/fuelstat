<?php
// Gemeinsames Fahrzeugformular für Anlegen und Bearbeiten.
// Erwartet: $fahrzeug (array, leer beim Anlegen), $formAction, $abbrechenUrl, $baseUrl
$f = $fahrzeug ?? [];
$aktuellerKraftstoff = $f['fuel_type'] ?? 'e5';
if ($aktuellerKraftstoff === 'petrol') {
    $aktuellerKraftstoff = 'e5';
}
$hatBild = !empty($f['image_url']);
?>
<form method="post" action="<?= htmlspecialchars($formAction) ?>" enctype="multipart/form-data" id="fahrzeugForm">
    <?php if (!empty($f['id'])): ?>
        <input type="hidden" name="fahrzeug_id" value="<?= htmlspecialchars($f['id']) ?>">
    <?php endif; ?>
    <div class="row">
        <div class="col-md-6 mb-3">
            <label for="name" class="form-label">Name / Bezeichnung</label>
            <input type="text" id="name" name="name" class="form-control" placeholder="z. B. Familienkutsche"
                   value="<?= htmlspecialchars($f['name'] ?? '') ?>" required>
        </div>
        <div class="col-md-6 mb-3">
            <label for="fuelType" class="form-label">Antriebsart</label>
            <select id="fuelType" name="fuelType" class="form-select" required>
                <?php foreach (getKraftstoffOptionen() as $wert => $text): ?>
                    <option value="<?= $wert ?>" <?= $wert === $aktuellerKraftstoff ? 'selected' : '' ?>><?= $text ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4 mb-3">
            <label for="brand" class="form-label">Marke</label>
            <input type="text" id="brand" name="brand" class="form-control" placeholder="z. B. Kia" value="<?= htmlspecialchars($f['brand'] ?? '') ?>">
        </div>
        <div class="col-md-4 mb-3">
            <label for="model" class="form-label">Modell</label>
            <input type="text" id="model" name="model" class="form-control" placeholder="z. B. Ceed SW" value="<?= htmlspecialchars($f['model'] ?? '') ?>">
        </div>
        <div class="col-md-4 mb-3">
            <label for="year" class="form-label">Baujahr</label>
            <input type="number" id="year" name="year" class="form-control" min="1886" max="2100" value="<?= htmlspecialchars((string)($f['year'] ?? '')) ?>">
        </div>
        <div class="col-md-6 mb-3">
            <label for="odometerKm" class="form-label">Tachostand bei Anschaffung (km)</label>
            <input type="number" id="odometerKm" name="odometerKm" class="form-control" min="0" value="<?= htmlspecialchars((string)($f['odometer_km'] ?? '')) ?>">
            <div class="form-text">Wird für die Fahrleistung verwendet.</div>
        </div>
        <div class="col-md-6 mb-3">
            <label for="tankCapacity" class="form-label" id="tankCapacityLabel">Tank- bzw. Akkugröße</label>
            <input type="number" id="tankCapacity" name="tankCapacity" class="form-control" min="0" step="0.1" value="<?= isset($f['tank_capacity']) ? htmlspecialchars((string)(float)$f['tank_capacity']) : '' ?>">
            <div class="form-text" id="tankCapacityHinweis">In Liter, kWh bzw. kg.</div>
            <script>
              // Beschriftung passend zur Antriebsart (Tank in l/kg, Akku in kWh)
              (function () {
                var auswahl = document.getElementById('fuelType');
                var texte = {
                  electric: ['Akkugröße (kWh)', 'Nutzbare Kapazität laut Hersteller.'],
                  cng: ['Tankgröße (kg)', 'Fassungsvermögen in Kilogramm.'],
                  hydrogen: ['Tankgröße (kg)', 'Fassungsvermögen in Kilogramm.']
                };
                function setze() {
                  var t = texte[auswahl.value] || ['Tankgröße (Liter)', 'Fassungsvermögen in Litern.'];
                  document.getElementById('tankCapacityLabel').textContent = t[0];
                  document.getElementById('tankCapacityHinweis').textContent = t[1];
                }
                if (auswahl) { auswahl.addEventListener('change', setze); setze(); }
              })();
            </script>
        </div>
        <div class="col-12 mb-3">
            <label for="bild" class="form-label">Fahrzeugbild</label>
            <?php if ($hatBild): ?>
                <div class="mb-2"><img src="<?= htmlspecialchars(fahrzeugBildUrl($f, $baseUrl)) ?>" alt="Aktuelles Fahrzeugbild" class="img-thumbnail" style="max-height:120px"></div>
            <?php endif; ?>
            <input type="file" id="bild" name="bild" class="form-control" accept="image/jpeg,image/png,image/gif,image/webp">
            <div class="form-text">Große Fotos werden vor dem Hochladen automatisch verkleinert.</div>
            <?php if ($hatBild): ?>
                <div class="form-check mt-2">
                    <input class="form-check-input" type="checkbox" id="bild_entfernen" name="bild_entfernen" value="1">
                    <label class="form-check-label" for="bild_entfernen">Bild entfernen</label>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <button type="submit" class="btn btn-primary">Speichern</button>
    <a href="<?= htmlspecialchars($abbrechenUrl) ?>" class="btn btn-secondary ms-2">Abbrechen</a>
</form>

<script>
// Große Fotos (z. B. vom Handy) vor dem Upload auf max. 1600 px verkleinern
document.getElementById('bild').addEventListener('change', async (event) => {
    const input = event.target;
    const file = input.files && input.files[0];
    if (!file || !file.type.startsWith('image/') || file.type === 'image/gif' || !window.DataTransfer) return;
    try {
        const bitmap = await createImageBitmap(file);
        const max = 1600;
        const scale = Math.min(1, max / Math.max(bitmap.width, bitmap.height));
        if (scale === 1 && file.size < 1500000) return;
        const canvas = document.createElement('canvas');
        canvas.width = Math.round(bitmap.width * scale);
        canvas.height = Math.round(bitmap.height * scale);
        canvas.getContext('2d').drawImage(bitmap, 0, 0, canvas.width, canvas.height);
        const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/jpeg', 0.85));
        if (!blob) return;
        const dt = new DataTransfer();
        dt.items.add(new File([blob], file.name.replace(/\.\w+$/, '') + '.jpg', { type: 'image/jpeg' }));
        input.files = dt.files;
    } catch (e) {
        // Verkleinern nicht möglich: Originaldatei hochladen
    }
});
</script>
