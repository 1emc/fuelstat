<?php
// pages/datenschutz.php
// Session-Handling
include '../includes/session.php';
// Header
include '../includes/header.php';
?>

<div class="container mt-5">
  <h1>Datenschutzerklärung</h1>
  <p>Verantwortlich gemäß Art. 4 Nr. 7 DSGVO:</p>
  <p>Max Mustermann<br>
     Musterstraße 1<br>
     12345 Musterstadt<br>
     E-Mail: <a href="mailto:datenschutz@example.com">datenschutz@example.com</a>
  </p>

  <h2>Erhebung und Speicherung personenbezogener Daten</h2>
  <p>Beim Aufrufen dieser Website werden durch den von Ihnen verwendeten Browser automatisch Informationen an den Server übertragen, die in Logfiles gespeichert werden. Diese sind:</p>
  <ul>
    <li>IP-Adresse</li>
    <li>Datum und Uhrzeit der Anfrage</li>
    <li>Browsertyp und Version</li>
    <li>Betriebssystem</li>
    <li>Referrer-URL</li>
  </ul>
  <p>Diese Daten sind nicht bestimmten Personen zuordenbar und werden auch nicht mit anderen Datenquellen zusammengeführt.</p>

  <h2>Verwendung von Cookies</h2>
  <p>Unsere Anwendung verwendet folgende Cookies:</p>
  <ul>
    <li><strong>PHPSESSID</strong> (Session-Cookie): Dient zur Verwaltung Ihrer Sitzung, z. B. für das Login und um Statusinformationen zu speichern.</li>
    <li><strong>Cache-Management</strong> (über Service Worker): Wir nutzen die <code>Cache API</code> Ihres Browsers, um Ressourcen offline verfügbar zu machen. Hierfür werden keine zusätzlichen Tracking-Cookies gesetzt.</li>
  </ul>
  <p>Ein Deaktivieren von Cookies kann zu Funktionseinschränkungen führen.</p>

  <h2>Content Security</h2>
  <p>Alle Seiteninhalte werden gemäß den Vorgaben der DSGVO verarbeitet. Wir erheben und verarbeiten keine weiteren personenbezogenen Daten, etwa für Analysen oder Marketingzwecke.</p>

  <h2>Auskunftsrecht und Kontakt</h2>
  <p>Sie haben das Recht auf Auskunft über die bei uns gespeicherten personenbezogenen Daten sowie ein Recht auf Berichtigung, Sperrung oder Löschung dieser Daten. Bei Fragen wenden Sie sich bitte an:</p>
  <p>Max Mustermann<br>
     E-Mail: <a href="mailto:datenschutz@example.com">datenschutz@example.com</a>
  </p>

  <p>Stand: April 2025</p>
</div>

<?php include '../includes/footer.php'; ?>