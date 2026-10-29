<?php
include '../includes/session.php';
include '../includes/db_connect.php';
include '../includes/header.php';
include '../includes/functions.php';

if (!isset($_GET['id'])) {
    die("Fahrzeug-ID fehlt.");
}
$fahrzeug_id = $_GET['id'];

try {
    $fahrzeug = $api->getVehicle($fahrzeug_id);
} catch (Throwable $e) {
    die("Fahrzeug nicht gefunden.");
}

// Für Tabs: API-Fahrzeug in altes Format abbilden (marke/modell wo erwartet)
$fahrzeug['marke'] = '';
$fahrzeug['modell'] = $fahrzeug['name'] ?? '';

// Mengeneinheit für alle Tabs (l, kWh oder kg)
$einheitKurz = einheitKurz(getEinheitFuerKraftstoff($fahrzeug['fuel_type'] ?? null));
?>

<div class="container mt-lg-5">
    <h1 class="d-none d-lg-block"><?= htmlspecialchars($fahrzeug['name'] ?? 'Fahrzeug') ?></h1>

    <!-- Reiter: am PC Tabs, mobil wischbare Leiste, die unter der Kopfleiste stehen bleibt -->
    <ul class="nav nav-tabs fahrzeug-reiter" id="fahrzeugTabs" role="tablist">
        <li class="nav-item" role="presentation"><a class="nav-link" id="allgemein-tab" data-bs-toggle="tab" href="#allgemein" role="tab">Allgemein</a></li>
        <li class="nav-item" role="presentation"><a class="nav-link" id="eintraege-tab" data-bs-toggle="tab" href="#eintraege" role="tab">Einträge</a></li>
        <li class="nav-item" role="presentation"><a class="nav-link" id="statistiken-tab" data-bs-toggle="tab" href="#statistiken" role="tab">Statistiken</a></li>
        <li class="nav-item" role="presentation"><a class="nav-link" id="ausgaben-tab" data-bs-toggle="tab" href="#ausgaben" role="tab">Ausgaben</a></li>
        <li class="nav-item" role="presentation"><a class="nav-link" id="einstellungen-tab" data-bs-toggle="tab" href="#einstellungen" role="tab">Einstellungen</a></li>
    </ul>

    <!-- Tab Inhalte -->
    <div class="tab-content" id="fahrzeugTabsContent">
        <div class="tab-pane fade" id="allgemein" role="tabpanel"><?php include '../tabs/allgemein.php'; ?></div>
        <div class="tab-pane fade" id="eintraege" role="tabpanel"><?php include '../tabs/eintraege.php'; ?></div>
        <div class="tab-pane fade" id="statistiken" role="tabpanel"><?php include '../tabs/statistiken.php'; ?></div>
        <div class="tab-pane fade" id="ausgaben" role="tabpanel"><?php include '../tabs/ausgaben.php'; ?></div>
        <div class="tab-pane fade" id="einstellungen" role="tabpanel"><?php include '../tabs/einstellungen.php'; ?></div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var reiter = document.getElementById('fahrzeugTabs');

  function zeige(hash) {
    var link = reiter.querySelector('.nav-link[href="' + hash + '"]') || reiter.querySelector('.nav-link[href="#allgemein"]');
    bootstrap.Tab.getOrCreateInstance(link).show();
  }

  reiter.querySelectorAll('.nav-link').forEach(function (link) {
    // Kein Springen zum Anker, aber Hash setzen, damit Neuladen und Zurück funktionieren
    link.addEventListener('click', function (e) {
      e.preventDefault();
      bootstrap.Tab.getOrCreateInstance(link).show();
      history.replaceState(null, '', link.getAttribute('href'));
    });

    link.addEventListener('shown.bs.tab', function () {
      // Aktiven Reiter mobil in die Mitte der Leiste schieben
      reiter.scrollTo({left: link.offsetLeft - (reiter.clientWidth - link.offsetWidth) / 2, behavior: 'smooth'});
      // War die Seite weit nach unten gescrollt, zum Anfang des neuen Reiters springen
      var oben = reiter.getBoundingClientRect().top + window.scrollY - (parseFloat(getComputedStyle(reiter).top) || 0);
      if (window.scrollY > oben) window.scrollTo(0, oben);
      // Untere Leiste: "Statistik" oder "Fahrzeuge" hervorheben
      var statistik = link.getAttribute('href') === '#statistiken';
      var tabStatistik = document.getElementById('tabbarStatistik');
      var tabFahrzeuge = document.getElementById('tabbarFahrzeuge');
      if (tabStatistik) tabStatistik.classList.toggle('active', statistik);
      if (tabFahrzeuge) tabFahrzeuge.classList.toggle('active', !statistik);
    });
  });

  zeige(window.location.hash || '#allgemein');
  // z. B. "Statistik" in der unteren Leiste, während diese Seite schon offen ist
  window.addEventListener('hashchange', function () { zeige(window.location.hash); });
});
</script>

<?php include '../includes/footer.php'; ?>
