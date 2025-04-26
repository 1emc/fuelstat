<?php
// pages/fahrzeug_detail.php
session_start();
include '../includes/db_connect.php';
include '../includes/header.php';
include '../includes/functions.php';

// Fahrzeug-ID prüfen
if (isset($_GET['id'])) {
    $fahrzeug_id = intval($_GET['id']);
} else {
    die("Fahrzeug-ID fehlt.");
}

// Fahrzeugdaten abrufen
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
    <h1><?php echo htmlspecialchars($fahrzeug['marke'] . ' ' . $fahrzeug['modell']); ?></h1>

    <!-- Tab Navigation -->
    <ul class="nav nav-tabs flex-nowrap overflow-auto" id="fahrzeugTabs" role="tablist" style="white-space: nowrap;">
        <li class="nav-item">
            <a class="nav-link" id="allgemein-tab" data-bs-toggle="tab" href="#allgemein" role="tab" aria-controls="allgemein" aria-selected="false">Allgemein</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="eintraege-tab" data-bs-toggle="tab" href="#eintraege" role="tab" aria-controls="eintraege" aria-selected="false">Einträge</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="statistiken-tab" data-bs-toggle="tab" href="#statistiken" role="tab" aria-controls="statistiken" aria-selected="false">Statistiken</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="ausgaben-tab" data-bs-toggle="tab" href="#ausgaben" role="tab" aria-controls="ausgaben" aria-selected="false">Ausgaben</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="einstellungen-tab" data-bs-toggle="tab" href="#einstellungen" role="tab" aria-controls="einstellungen" aria-selected="false">Einstellungen</a>
        </li>
    </ul>

    <!-- Tab Inhalte -->
    <div class="tab-content" id="fahrzeugTabsContent">
        <div class="tab-pane fade" id="allgemein" role="tabpanel" aria-labelledby="allgemein-tab">
            <?php include '../tabs/allgemein.php'; ?>
        </div>
        <div class="tab-pane fade" id="eintraege" role="tabpanel" aria-labelledby="eintraege-tab">
            <?php include '../tabs/eintraege.php'; ?>
        </div>
        <div class="tab-pane fade" id="statistiken" role="tabpanel" aria-labelledby="statistiken-tab">
            <?php include '../tabs/statistiken.php'; ?>
        </div>
        <div class="tab-pane fade" id="ausgaben" role="tabpanel" aria-labelledby="ausgaben-tab">
            <?php include '../tabs/ausgaben.php'; ?>
        </div>
        <div class="tab-pane fade" id="einstellungen" role="tabpanel" aria-labelledby="einstellungen-tab">
            <?php include '../tabs/einstellungen.php'; ?>
        </div>
    </div>
</div>

<script>
// Aktiviere Tab entsprechend dem URL-Hash
document.addEventListener('DOMContentLoaded', function() {
    var hash = window.location.hash;
    if (hash) {
        var trigger = document.querySelector('.nav-link[href="' + hash + '"]');
        if (trigger) {
            var bsTab = new bootstrap.Tab(trigger);
            bsTab.show();
        }
    } else {
        // Standardmäßig Allgemein aktivieren
        var defaultTab = document.querySelector('#allgemein-tab');
        new bootstrap.Tab(defaultTab).show();
    }
});
</script>

<?php include '../includes/footer.php'; ?>
