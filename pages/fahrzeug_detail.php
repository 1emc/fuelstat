<?php
// eintrag_hinzufuegen.php
session_start();
include '../includes/db_connect.php';
include '../includes/header.php';
include '../includes/functions.php';

// Überprüfe, ob die Fahrzeug-ID übergeben wurde
if (isset($_GET['id'])) {
    $fahrzeug_id = intval($_GET['id']);
} else {
    // Fehlerbehandlung oder Weiterleitung
    die("Fahrzeug-ID fehlt.");
}

// Fahrzeugdetails aus der Datenbank abrufen
$stmt = $conn->prepare("SELECT * FROM Fahrzeuge WHERE id = ?");
$stmt->bind_param("i", $fahrzeug_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $fahrzeug = $result->fetch_assoc();
} else {
    die("Fahrzeug nicht gefunden.");
}
?>

<!-- fahrzeug_detail.php -->

<div class="container mt-5">
    <h1><?php echo htmlspecialchars($fahrzeug['marke'] . ' ' . $fahrzeug['modell']); ?></h1>

    <!-- Tab Navigation -->
    <ul class="nav nav-tabs" id="fahrzeugTabs" role="tablist">
        <li class="nav-item">
            <a class="nav-link active" id="allgemein-tab" data-bs-toggle="tab" href="#allgemein" role="tab" aria-controls="allgemein" aria-selected="true">Allgemein</a>
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
        <!-- Reiter 1: Allgemein -->
        <div class="tab-pane fade show active" id="allgemein" role="tabpanel" aria-labelledby="allgemein-tab">
            <?php include '../tabs/allgemein.php'; ?>
        </div>
        <!-- Reiter 2: Einträge -->
        <div class="tab-pane fade" id="eintraege" role="tabpanel" aria-labelledby="eintraege-tab">
            <?php include '../tabs/eintraege.php'; ?>
        </div>
        <!-- Reiter 3: Statistiken -->
        <div class="tab-pane fade" id="statistiken" role="tabpanel" aria-labelledby="statistiken-tab">
            <?php include '../tabs/statistiken.php'; ?>
        </div>
        <!-- Reiter 4: Ausgaben -->
        <div class="tab-pane fade" id="ausgaben" role="tabpanel" aria-labelledby="ausgaben-tab">
            <?php include '../tabs/ausgaben.php'; ?>
        </div>
        <!-- Reiter 5: Einstellungen -->
        <div class="tab-pane fade" id="einstellungen" role="tabpanel" aria-labelledby="einstellungen-tab">
            <?php include '../tabs/einstellungen.php'; ?>asd
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>



<?php include 'includes/footer.php'; ?>