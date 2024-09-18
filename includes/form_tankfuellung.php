<?php
// form_tankfuellung.php
?>
<h2>Neue Tankfüllung hinzufügen</h2>
<form method="post" action="eintrag_speichern.php">
    <input type="hidden" name="fahrzeug_id" value="<?php echo $fahrzeug_id; ?>">
    <input type="hidden" name="kategorie" value="Tankfüllung">

    <label for="datum">Datum:</label>
    <input type="date" name="datum" required><br>

    <label for="tachostand">Tachostand (km):</label>
    <input type="number" name="tachostand" required><br>

    <label for="standort">Standort:</label>
    <input type="text" name="standort"><br>

    <label for="gesamtpreis">Gesamtpreis (€):</label>
    <input type="number" step="0.01" name="gesamtpreis" required><br>

    <label for="preis_pro_einheit">Preis pro Liter/kWh (€):</label>
    <input type="number" step="0.01" name="preis_pro_einheit" required><br>

    <label for="menge">Tankmenge (Liter/kWh):</label>
    <input type="number" step="0.01" name="menge" required><br>

    <label for="vollgetankt">Vollgetankt?</label>
    <input type="checkbox" name="vollgetankt" value="1"><br><br>

    <button type="submit" name="eintrag_speichern">Eintrag speichern</button>
</form>
