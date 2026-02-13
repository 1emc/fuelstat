<?php
include '../includes/session.php';
include '../includes/db_connect.php';
include '../includes/header.php';
?>

<div class="container mt-5">
    <h2>Fahrzeug hinzufügen</h2>

    <form method="post" action="fahrzeug_speichern.php">
        <div class="mb-3">
            <label for="name" class="form-label">Name / Bezeichnung</label>
            <input type="text" id="name" name="name" class="form-control"
                   placeholder="z. B. VW Golf oder Kia Ceed" required>
        </div>
        <div class="mb-3">
            <label for="fuelType" class="form-label">Kraftstoffart</label>
            <select id="fuelType" name="fuelType" class="form-select" required>
                <!-- 'diesel', 'e5', 'e10', 'lpg', 'cng', 'electric', 'hybrid', 'hydrogen', 'other' -->
                <option value="diesel">Diesel</option>
                <option value="e5">Benzin</option>
                <option value="hydrogen">Wasserstoff</option>
                <option value="electric">Elektro</option>
                <option value="hybrid">Hybrid</option>
                <option value="cng">Erdgas (CNG)</option>
                <option value="lpg">Autogas (LPG)</option>
                <option value="other">Andere</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Speichern</button>
        <a href="../index.php" class="btn btn-secondary ms-2">Abbrechen</a>
    </form>
</div>

<?php include '../includes/footer.php'; ?>
