</div> <!-- Ende von .hauptinhalt -->

<!-- Footer -->
<footer class="bg-body-tertiary text-center text-muted py-3 mt-4">
  <small>
    &copy; <?php echo date('Y'); ?> Mario Caraggiu — 
    <a href="<?php echo htmlspecialchars($baseUrl); ?>pages/impressum.php" class="text-decoration-none">Impressum</a> | 
    <a href="<?php echo htmlspecialchars($baseUrl); ?>pages/datenschutz.php" class="text-decoration-none">Datenschutz</a>
  </small>
</footer>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<!-- Custom JS -->
<script src="<?php echo htmlspecialchars($baseUrl); ?>js/script.js?v=<?= @filemtime(__DIR__ . '/../js/script.js') ?: 1 ?>"></script>

<!-- CSRF-Token in alle POST-Formulare einfügen (wird in includes/session.php geprüft) -->
<script>
  document.querySelectorAll('form').forEach(function (form) {
    if ((form.getAttribute('method') || '').toLowerCase() !== 'post' || form.querySelector('input[name="csrf_token"]')) return;
    var input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'csrf_token';
    input.value = <?= json_encode(function_exists('csrfToken') ? csrfToken() : '') ?>;
    form.appendChild(input);
  });
</script>

<!-- Darkmode-Umschalter und Diagrammfarben -->
<script>
  (function () {
    var root = document.documentElement;
    var toggles = document.querySelectorAll('.theme-umschalter');
    var themeMeta = document.querySelector('meta[name="theme-color"]');

    function applyChartTheme() {
      if (!window.Chart) return;
      var css = getComputedStyle(root);
      var text = css.getPropertyValue('--bs-body-color').trim();
      var grid = css.getPropertyValue('--bs-border-color-translucent').trim();
      Chart.defaults.color = text;
      Chart.defaults.borderColor = grid;
      Object.values(Chart.instances || {}).forEach(function (chart) {
        Object.values(chart.options.scales || {}).forEach(function (scale) {
          if (scale.ticks) scale.ticks.color = text;
          if (scale.grid) scale.grid.color = grid;
        });
        if (chart.options.plugins && chart.options.plugins.legend && chart.options.plugins.legend.labels) {
          chart.options.plugins.legend.labels.color = text;
        }
        chart.update('none');
      });
    }

    function updateIcon() {
      var dark = root.getAttribute('data-bs-theme') === 'dark';
      if (themeMeta) themeMeta.setAttribute('content', dark ? '#2b3035' : '#f8f9fa');
      toggles.forEach(function (toggle) {
        var icon = toggle.querySelector('i');
        icon.classList.toggle('fa-sun', dark);
        icon.classList.toggle('fa-moon', !dark);
        toggle.title = dark ? 'Hell darstellen' : 'Dunkel darstellen';
        var text = toggle.querySelector('.theme-text');
        if (text) text.textContent = dark ? 'Helle Darstellung' : 'Dunkle Darstellung';
      });
    }

    toggles.forEach(function (toggle) {
      toggle.addEventListener('click', function () {
        var next = root.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
        root.setAttribute('data-bs-theme', next);
        try { localStorage.setItem('fuelstat-theme', next); } catch (e) {}
        updateIcon();
        applyChartTheme();
      });
    });

    updateIcon();
    applyChartTheme();
  })();
</script>

<!-- Mobil: Fahrzeug-Blatt öffnet je nach Knopf Details, neuen Eintrag oder Statistik -->
<script>
  (function () {
    var blatt = document.getElementById('blattFahrzeuge');
    if (!blatt) return;
    var titel = {details: 'Meine Fahrzeuge', eintrag: 'Eintrag für welches Fahrzeug?', statistik: 'Statistik für welches Fahrzeug?'};
    blatt.addEventListener('show.bs.offcanvas', function (event) {
      var aktion = (event.relatedTarget && event.relatedTarget.dataset.aktion) || 'details';
      document.getElementById('blattFahrzeugeTitel').textContent = titel[aktion] || titel.details;
      blatt.querySelectorAll('.blatt-fahrzeug').forEach(function (link) {
        link.href = link.dataset[aktion] || link.dataset.details;
      });
      var neu = blatt.querySelector('.blatt-neu');
      if (neu) neu.classList.toggle('d-none', aktion !== 'details');
    });
    // Link auf dieselbe Seite mit anderem #Reiter: Blatt trotzdem schließen
    blatt.addEventListener('click', function (event) {
      if (event.target.closest('a')) bootstrap.Offcanvas.getOrCreateInstance(blatt).hide();
    });
  })();
</script>

<!-- Service Worker for PWA -->
<script>
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
      navigator.serviceWorker.register('<?php echo htmlspecialchars($baseUrl); ?>service-worker.js', { scope: '<?php echo htmlspecialchars($basePath === '/' ? '/' : $basePath . '/'); ?>' }).then(registration => {
        console.log('Service Worker registriert mit Scope: ', registration.scope);
      }, err => {
        console.log('Service Worker Registrierung fehlgeschlagen: ', err);
      });
		// Alte Registrierungen unter /js/ bereinigen
		navigator.serviceWorker.getRegistrations && navigator.serviceWorker.getRegistrations().then(registrations => {
			registrations.forEach(reg => {
				const sw = reg.active || reg.installing || reg.waiting;
				const scriptURL = sw && sw.scriptURL ? sw.scriptURL : '';
				if (scriptURL.endsWith('/js/service-worker.js')) {
					reg.unregister();
				}
			});
		});
    });
  }
</script>

</body>
</html>
