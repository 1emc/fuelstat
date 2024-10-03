

</div> <!-- Ende des .container -->

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<!-- Custom JS -->
<script src="/fuelstat/js/script.js"></script>

<!-- Service Worker for PWA -->
<script>
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
      navigator.serviceWorker.register('/fuelstat/js/service-worker.js').then(registration => {
        console.log('Service Worker registriert mit Scope: ', registration.scope);
      }, err => {
        console.log('Service Worker Registrierung fehlgeschlagen: ', err);
      });
    });
  }
</script>

</body>
</html>
