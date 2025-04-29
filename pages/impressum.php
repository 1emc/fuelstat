<?php
// pages/impressum.php
session_start();
include '../includes/header.php';
?>

<div class="container mt-5">
  <h1>Impressum</h1>
  <p><strong>Angaben gemäß § 5 TMG:</strong></p>
  <p>Max Mustermann<br>
     Musterstraße 1<br>
     12345 Musterstadt</p>

  <h2>Kontakt</h2>
  <p>Telefon: +49 (0) 123 456789<br>
     E-Mail: <a href="mailto:info@example.com">info@example.com</a></p>

  <h2>Umsatzsteuer-ID</h2>
  <p>USt-IdNr. gemäß §27 a Umsatzsteuergesetz: DE123456789</p>

  <h2>Verantwortlich für den Inhalt nach § 55 Abs. 2 RStV</h2>
  <p>Max Mustermann<br>
     Musterstraße 1<br>
     12345 Musterstadt</p>

  <p>Dies ist eine Muster-Impressumseite mit Dummy-Daten.</p>
</div>

<?php include '../includes/footer.php'; ?>
