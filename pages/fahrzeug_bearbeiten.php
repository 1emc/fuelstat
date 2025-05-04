<?php
// pages/fahrzeug_bearbeiten.php
// Session-Handling
include '../includes/session.php';
// Andere Includes
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
            <label for="kraftstoff" class="form-label">Kraftstoff (bevorzugt)</label>
            <select id="kraftstoff" name="kraftstoff" class="form-select" required>
                <option value="diesel"   <?php if($fahrzeug['kraftstoff']==='diesel')   echo 'selected'; ?>>Diesel</option>
                <option value="e5"      <?php if($fahrzeug['kraftstoff']==='e5')      echo 'selected'; ?>>Benzin (E5)</option>
                <option value="e10"     <?php if($fahrzeug['kraftstoff']==='e10')     echo 'selected'; ?>>Benzin (E10)</option>
                <option value="lpg"     <?php if($fahrzeug['kraftstoff']==='lpg')     echo 'selected'; ?>>Autogas (LPG)</option>
                <option value="cng"     <?php if($fahrzeug['kraftstoff']==='cng')     echo 'selected'; ?>>Erdgas (CNG)</option>
                <option value="electric"<?php if($fahrzeug['kraftstoff']==='electric')echo 'selected'; ?>>Elektro</option>
                <option value="hybrid"  <?php if($fahrzeug['kraftstoff']==='hybrid')  echo 'selected'; ?>>Hybrid</option>
                <option value="hydrogen"<?php if($fahrzeug['kraftstoff']==='hydrogen')echo 'selected'; ?>>Wasserstoff</option>
                <option value="other"   <?php if($fahrzeug['kraftstoff']==='other')   echo 'selected'; ?>>Andere</option>
            </select>
        </div>

        <div class="mb-3">
            <label for="tachostand" class="form-label">Tachostand (km)</label>
            <input type="number" id="tachostand" name="tachostand" class="form-control"
                   value="<?php echo htmlspecialchars($fahrzeug['tachostand']); ?>" min="0">
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



<!-- Fahrzeug-Reihenfolge ändern (Drag&Drop) -->
<?php
if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT id, marke, modell FROM fahrzeuge WHERE benutzer_id = ? ORDER BY sortierung ASC, id ASC");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $fahrzeuge = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    if (count($fahrzeuge) > 1):
?>
<div class="container mt-5">
    <h4>Fahrzeug-Reihenfolge ändern</h4>
    <ul id="fahrzeugSortList" class="list-group mb-3">
        <?php foreach ($fahrzeuge as $fz): ?>
            <li class="list-group-item d-flex align-items-center" data-id="<?= $fz['id'] ?>">
                <span class="me-2"><i class="fas fa-arrows-alt"></i></span>
                <?= htmlspecialchars($fz['marke'] . ' ' . $fz['modell']) ?>
                <?php if ($fz['id'] == $fahrzeug_id): ?>
                    <span class="badge bg-primary ms-auto">Bearbeitet</span>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
    <button id="saveSortBtn" class="btn btn-success">Reihenfolge speichern</button>
    <div id="sortFeedback" class="mt-2"></div>
</div>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
const list = document.getElementById('fahrzeugSortList');
const sortable = Sortable.create(list, {animation: 150});

document.getElementById('saveSortBtn').onclick = function() {
    const order = Array.from(list.children).map(li => li.getAttribute('data-id'));
    fetch('../includes/fahrzeug_sortieren.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: order.map(id => 'order[]=' + encodeURIComponent(id)).join('&')
    })
    .then(r => r.text())
    .then(txt => {
        document.getElementById('sortFeedback').innerHTML = '<div class="alert alert-success">' + txt + '</div>';
        setTimeout(() => location.reload(), 1000);
    })
    .catch(() => {
        document.getElementById('sortFeedback').innerHTML = '<div class="alert alert-danger">Fehler beim Speichern!</div>';
    });
};
</script>
<?php endif; } ?>

<?php include '../includes/footer.php'; ?>
