</div> <!-- Ende des .container -->

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
<script src="<?php echo htmlspecialchars($baseUrl); ?>js/script.js"></script>

<!-- Darkmode-Umschalter und Diagrammfarben -->
<script>
  (function () {
    var root = document.documentElement;
    var toggle = document.getElementById('themeToggle');

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
      if (!toggle) return;
      var dark = root.getAttribute('data-bs-theme') === 'dark';
      toggle.querySelector('i').className = dark ? 'fas fa-sun' : 'fas fa-moon';
      toggle.title = dark ? 'Hell darstellen' : 'Dunkel darstellen';
    }

    if (toggle) {
      toggle.addEventListener('click', function () {
        var next = root.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
        root.setAttribute('data-bs-theme', next);
        try { localStorage.setItem('fuelstat-theme', next); } catch (e) {}
        updateIcon();
        applyChartTheme();
      });
    }

    updateIcon();
    applyChartTheme();
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
