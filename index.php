<?php include 'includes/db_connect.php'; ?>
<?php include 'includes/header.php'; ?>

<?php
$benutzer_id = 1; // Temporär, bis ein Login-System existiert
$sql = "SELECT * FROM Fahrzeuge WHERE benutzer_id = $benutzer_id";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    echo "<div class='container mt-4'>";
    echo "<div class='row'>";
    
    while($row = $result->fetch_assoc()) {
        // Überprüfen, ob ein Bild vorhanden ist
        if (!empty($row['bild'])) {
            // Fahrzeugbild verwenden
            $bildPfad = "images/" . htmlspecialchars($row['bild']);
        } else {
            // Platzhalterbild verwenden
            $bildPfad = "images/platzhalter.jpg";
        }

        // Fahrzeugdetails in einer Bootstrap Card anzeigen
        echo "<div class='col-md-4'>";
        echo "<div class='card mb-4'>";
        echo "<img src='" . $bildPfad . "' class='card-img-top' alt='Bild von " . htmlspecialchars($row['marke']) . " " . htmlspecialchars($row['modell']) . "' style='height: 200px; object-fit: cover;'>";
        echo "<div class='card-body'>";
        echo "<h5 class='card-title'>" . htmlspecialchars($row['marke']) . " " . htmlspecialchars($row['modell']) . "</h5>";
        echo "<p class='card-text'>Tachostand: " . number_format($row['tachostand'], 0, ',', '.') . " km</p>";

        // Button zu Fahrzeugdetails
        echo "<a href='pages/fahrzeug_detail.php?id=" . intval($row['id']) . "' class='btn btn-primary'>Details ansehen</a> ";

        // Button zum Hinzufügen eines neuen Eintrags
        echo "<a href='pages/eintrag_hinzufuegen.php?fahrzeug_id=" . intval($row['id']) . "' class='btn btn-success mt-2'>Neuen Eintrag hinzufügen</a>";
        echo "</div>"; // Ende card-body
        echo "</div>"; // Ende card
        echo "</div>"; // Ende col-md-4
    }

    echo "</div>"; // Ende row
    echo "</div>"; // Ende container
} else {
    echo "<div class='alert alert-warning'>Keine Fahrzeuge gefunden.</div>";
}
?>

<?php include 'includes/footer.php'; ?>
