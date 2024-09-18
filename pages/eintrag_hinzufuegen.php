<?php
// eintrag_hinzufuegen.php
session_start();
include '../includes/db_connect.php';
include '../includes/header.php';

// Überprüfe, ob die Fahrzeug-ID übergeben wurde
if (isset($_GET['fahrzeug_id'])) {
    $fahrzeug_id = intval($_GET['fahrzeug_id']);
} else {
    // Fehlerbehandlung oder Weiterleitung
    die("Fahrzeug-ID fehlt.");
}
?>
<h2>Neuen Eintrag hinzufügen</h2>
<form method="post" action="eintrag_hinzufuegen.php?fahrzeug_id=<?php echo $fahrzeug_id; ?>">
    <label for="eintragstyp">Was möchtest du hinzufügen?</label><br>
    <input type="radio" id="tankfuellung" name="eintragstyp" value="Tankfüllung" required>
    <label for="tankfuellung">Tankfüllung</label><br>
    <input type="radio" id="andere_ausgabe" name="eintragstyp" value="Andere Ausgabe">
    <label for="andere_ausgabe">Andere Ausgabe</label><br><br>
    <button type="submit" name="auswahl_bestaetigen">Weiter</button>
</form>
<?php
if (isset($_POST['auswahl_bestaetigen'])) {
    $eintragstyp = $_POST['eintragstyp'];

    if ($eintragstyp == 'Tankfüllung') {
        // Formular für Tankfüllung anzeigen
        include 'includes/form_tankfuellung.php';
    } else {
        // Formular für andere Ausgaben anzeigen
        include 'includes/form_andere_ausgabe.php';
    }
}
?>
