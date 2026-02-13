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
?>

<div class="container mt-5">
    <h1><?= htmlspecialchars($fahrzeug['name'] ?? 'Fahrzeug') ?></h1>

    <!-- Offcanvas-Trigger (Mobil) -->
    <button class="btn btn-outline-secondary d-sm-none mb-3" type="button" data-bs-toggle="offcanvas" data-bs-target="#tabOffcanvas" aria-controls="tabOffcanvas">
        <i class="fas fa-bars"></i> <span id="tabOffcanvasLabel">Fahrzeug-Details</span>
    </button>

    <!-- Desktop-Tabs -->
    <ul class="nav nav-tabs d-none d-sm-flex flex-nowrap" id="fahrzeugTabs" role="tablist" style="white-space: nowrap;">
        <li class="nav-item"><a class="nav-link" id="allgemein-tab" data-bs-toggle="tab" href="#allgemein" role="tab">Allgemein</a></li>
        <li class="nav-item"><a class="nav-link" id="eintraege-tab" data-bs-toggle="tab" href="#eintraege" role="tab">Einträge</a></li>
        <li class="nav-item"><a class="nav-link" id="statistiken-tab" data-bs-toggle="tab" href="#statistiken" role="tab">Statistiken</a></li>
        <li class="nav-item"><a class="nav-link" id="ausgaben-tab" data-bs-toggle="tab" href="#ausgaben" role="tab">Ausgaben</a></li>
        <li class="nav-item"><a class="nav-link" id="einstellungen-tab" data-bs-toggle="tab" href="#einstellungen" role="tab">Einstellungen</a></li>
    </ul>

    <!-- Offcanvas für mobile Tabs -->
    <div class="offcanvas offcanvas-start" tabindex="-1" id="tabOffcanvas" aria-labelledby="tabOffcanvasLabel">
      <div class="offcanvas-header">
        <!-- Label wird dynamisch per JS gesetzt -->
        <h5 class="offcanvas-title" id="tabOffcanvasLabel">Fahrzeug-Details</h5>
        <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas"></button>
      </div>
      <div class="offcanvas-body p-0">
        <div class="list-group list-group-flush">
          <a href="#allgemein" class="list-group-item list-group-item-action">Allgemein</a>
          <a href="#eintraege" class="list-group-item list-group-item-action">Einträge</a>
          <a href="#statistiken" class="list-group-item list-group-item-action">Statistiken</a>
          <a href="#ausgaben" class="list-group-item list-group-item-action">Ausgaben</a>
          <a href="#einstellungen" class="list-group-item list-group-item-action">Einstellungen</a>
        </div>
      </div>
    </div>

    <!-- Tab Inhalte -->
    <div class="tab-content mt-3" id="fahrzeugTabsContent">
        <div class="tab-pane fade" id="allgemein" role="tabpanel"><?php include '../tabs/allgemein.php'; ?></div>
        <div class="tab-pane fade" id="eintraege" role="tabpanel"><?php include '../tabs/eintraege.php'; ?></div>
        <div class="tab-pane fade" id="statistiken" role="tabpanel"><?php include '../tabs/statistiken.php'; ?></div>
        <div class="tab-pane fade" id="ausgaben" role="tabpanel"><?php include '../tabs/ausgaben.php'; ?></div>
        <div class="tab-pane fade" id="einstellungen" role="tabpanel"><?php include '../tabs/einstellungen.php'; ?></div>
    </div>
</div>

<script>
// Auto-activate Tab based on URL hash
window.addEventListener('DOMContentLoaded', function() {
    var hash = window.location.hash || '#allgemein';
    var trigger = document.querySelector('.nav-link[href="' + hash + '"]');
    if (trigger) bootstrap.Tab.getOrCreateInstance(trigger).show();
});
</script>

<script>
// Offcanvas menu: switch tab and close offcanvas on mobile
window.addEventListener('DOMContentLoaded', function() {
    var offcanvasEl = document.getElementById('tabOffcanvas');
    var offcanvas = bootstrap.Offcanvas.getOrCreateInstance(offcanvasEl);
    document.querySelectorAll('#tabOffcanvas .list-group-item').forEach(function(el) {
        el.addEventListener('click', function() {
            var hash = this.getAttribute('href');
            var trigger = document.querySelector('.nav-link[href="' + hash + '"]');
            if (trigger) bootstrap.Tab.getOrCreateInstance(trigger).show();
            offcanvas.hide();
            history.replaceState(null, null, hash);
        });
    });
});
</script>

<script>
document.addEventListener('DOMContentLoaded', function() {
  // 1. Desktop-Tabs: Klicks abfangen, Springen verhindern, Tab aktivieren
  document.querySelectorAll('#fahrzeugTabs .nav-link').forEach(link => {
    link.addEventListener('click', e => {
      e.preventDefault();  // verhindert das Scrollen zum Anker
      bootstrap.Tab.getOrCreateInstance(link).show();
      // URL-Hash trotzdem setzen, damit Reload funktioniert:
      history.replaceState(null, null, link.getAttribute('href'));
    });
  });

  // 2. Offcanvas-Menü (Mobil): Klicks abfangen, Springen verhindern, Tab aktivieren, Offcanvas schließen
  const offcanvasEl = document.getElementById('tabOffcanvas');
  const offcanvas = bootstrap.Offcanvas.getOrCreateInstance(offcanvasEl);
  offcanvasEl.querySelectorAll('.list-group-item').forEach(item => {
    item.addEventListener('click', e => {
      e.preventDefault();  // kein Springen
      const hash = item.getAttribute('href');
      const targetLink = document.querySelector('.nav-link[href="' + hash + '"]');
      if (targetLink) bootstrap.Tab.getOrCreateInstance(targetLink).show();
      offcanvas.hide();
      history.replaceState(null, null, hash);
    });
  });
});
</script>


<?php include '../includes/footer.php'; ?>
