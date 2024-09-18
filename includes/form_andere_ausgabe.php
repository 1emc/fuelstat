<?php
// form_andere_ausgabe.php
?>
<h2>Neue Ausgabe hinzufügen</h2>
<form method="post" action="eintrag_speichern.php">
    <input type="hidden" name="fahrzeug_id" value="<?php echo $fahrzeug_id; ?>">

    <label for="kategorie">Kategorie:</label>
    <select name="kategorie" required>
        <option value="">Kategorie wählen</option>
        <option value="Versicherung">Versicherung</option>
        <option value="Werkstatt">Werkstatt</option>
        <option value="Inspektion">Inspektion</option>
        <option value="Steuer">Steuer</option>
        <option value="Reparatur">Reparatur</option>
        <option value="Reifen">Reifen</option>
        <option value="TÜV">TÜV</option>
        <option value="Wartung">Wartung</option>
        <option value="Dekor">Dekor</option>
    </select><br>

    <label for="datum">Datum:</label>
    <input type="date" name="datum" required><br>

    <label for="tachostand">Tachostand (km):</label>
    <input type="number" name="tachostand"><br>

    <label for="standort">Standort:</label>
    <input type="text" name="standort"><br>

    <label for="kosten">Kosten (€):</label>
    <input type="number" step="0.01" name="kosten" required><br>

    <label for="beschreibung">Beschreibung:</label>
    <textarea name="beschreibung"></textarea><br><br>

    <button type="submit" name="eintrag_speichern">Eintrag speichern</button>
</form>
