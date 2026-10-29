<?php
// pages/impressum.php – Angaben kommen aus includes/betreiber.php
include '../includes/session.php';
include '../includes/header.php';

$b = require __DIR__ . '/../includes/betreiber.php';
// Fehlende Pflichtangabe sichtbar machen, statt eine leere Zeile auszugeben
$angabe = fn(string $k) => ($b[$k] ?? '') !== ''
    ? htmlspecialchars($b[$k])
    : '<span class="text-danger">[' . $k . ' fehlt in includes/betreiber.php]</span>';
$anschrift = $angabe('name') . '<br>' . $angabe('strasse') . '<br>' . $angabe('plz_ort');
// E-Mail wie auf caraggiu.com mit [at] statt @ (erschwert das Einsammeln durch Spam-Bots)
$email = ($b['email'] ?? '') !== '' ? htmlspecialchars(str_replace('@', '[at]', $b['email'])) : $angabe('email');
?>

<div class="container mt-5 mb-5 rechtstext">
  <h1 class="mb-4">Impressum</h1>

  <h2 class="h5">Angaben gemäß § 5 DDG</h2>
  <p><?= $anschrift ?></p>

  <h2 class="h5">Kontakt</h2>
  <p>
    <?php if (($b['telefon'] ?? '') !== ''): ?>Telefon: <?= htmlspecialchars($b['telefon']) ?><br><?php endif; ?>
    E-Mail: <?= $email ?>
  </p>
  <p>Fragen, Fehler oder Ideen zur App kannst du nach dem Anmelden auch direkt über „Feedback senden“ schicken.</p>

  <h2 class="h5">Verantwortlich für den Inhalt nach § 18 Abs. 2 MStV</h2>
  <p><?= $anschrift ?></p>

  <h2 class="h5">Über Fuelstat</h2>
  <p>Fuelstat ist ein privates, nicht-kommerzielles Projekt und befindet sich in der Beta-Phase. Die Nutzung ist kostenlos. Es gibt keine Werbung und kein Tracking.</p>

  <h2 class="h5">Verbraucherstreitbeilegung</h2>
  <p>Ich bin nicht bereit und nicht verpflichtet, an Streitbeilegungsverfahren vor einer Verbraucherschlichtungsstelle teilzunehmen.</p>

  <h2 class="h5">Haftung für Inhalte</h2>
  <p>Die Inhalte und Berechnungen dieser Anwendung (z. B. Verbrauch, Kosten, CO₂-Werte und Fälligkeiten von TÜV oder Wartung) werden mit Sorgfalt erstellt, beruhen aber auf deinen Eingaben und vereinfachten Annahmen. Für ihre Richtigkeit, Vollständigkeit und Aktualität kann ich keine Gewähr übernehmen. Verbindlich sind immer die Angaben deiner Werkstatt, Prüfstelle, Versicherung und Behörden. Als Diensteanbieter bin ich gemäß § 7 Abs. 1 DDG für eigene Inhalte nach den allgemeinen Gesetzen verantwortlich, nach §§ 8 bis 10 DDG aber nicht verpflichtet, übermittelte oder gespeicherte fremde Informationen zu überwachen. Sobald mir Rechtsverletzungen bekannt werden, entferne ich die betreffenden Inhalte umgehend.</p>

  <h2 class="h5">Haftung für Links</h2>
  <p>Für Inhalte verlinkter externer Seiten sind ausschließlich deren Betreiber verantwortlich. Zum Zeitpunkt der Verlinkung waren keine Rechtsverstöße erkennbar. Bei Bekanntwerden von Rechtsverletzungen entferne ich solche Links umgehend.</p>

  <h2 class="h5">Urheberrecht</h2>
  <p>Texte, Grafiken und Programmcode dieser Anwendung unterliegen dem deutschen Urheberrecht, soweit nicht anders angegeben. Von Benutzern hochgeladene Fotos bleiben deren Eigentum und sind nur im jeweiligen Konto sichtbar.</p>

  <h2 class="h5">Widerspruch gegen Werbe-Mails</h2>
  <p>Der Nutzung der im Rahmen der Impressumspflicht veröffentlichten Kontaktdaten zur Übersendung von nicht ausdrücklich angeforderter Werbung und Informationsmaterialien wird hiermit ausdrücklich widersprochen. Ich behalte mir rechtliche Schritte im Falle der unverlangten Zusendung von Werbeinformationen, etwa durch Spam-Mails, vor.</p>

  <h2 class="h5">Datenquellen und Lizenzen</h2>
  <ul>
    <li>Tankstellen und Spritpreise: <a href="https://creativecommons.tankerkoenig.de" target="_blank" rel="noopener">Tankerkönig</a> mit Daten der Markttransparenzstelle für Kraftstoffe (MTS-K), Lizenz <a href="https://creativecommons.org/licenses/by/4.0/deed.de" target="_blank" rel="noopener">CC BY 4.0</a></li>
    <li>Ladestationen: <a href="https://openchargemap.org" target="_blank" rel="noopener">Open Charge Map</a>, Lizenz <a href="https://creativecommons.org/licenses/by/4.0/deed.de" target="_blank" rel="noopener">CC BY 4.0</a> bzw. Lizenz des jeweiligen Datenanbieters</li>
    <li>Ortssuche: <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">© OpenStreetMap-Mitwirkende</a> (Nominatim)</li>
    <li>Symbole: <a href="https://fontawesome.com" target="_blank" rel="noopener">Font Awesome Free</a> (Icons CC BY 4.0, Schriften SIL OFL 1.1)</li>
    <li>Oberfläche und Diagramme: <a href="https://getbootstrap.com" target="_blank" rel="noopener">Bootstrap</a> und <a href="https://www.chartjs.org" target="_blank" rel="noopener">Chart.js</a> (MIT-Lizenz)</li>
  </ul>
</div>

<?php include '../includes/footer.php'; ?>
