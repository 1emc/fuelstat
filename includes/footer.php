</div> <!-- Ende des .container -->

<!-- Footer -->
<footer class="bg-light text-center text-muted py-3 mt-4">
  <small>
    &copy; <?php echo date('Y'); ?> Mario Caraggiu — 
    <a href="<?php echo htmlspecialchars($basePath); ?>/pages/impressum.php" class="text-decoration-none">Impressum</a> | 
    <a href="<?php echo htmlspecialchars($basePath); ?>/pages/datenschutz.php" class="text-decoration-none">Datenschutz</a>
  </small>
</footer>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<!-- Custom JS -->
<script src="<?php echo htmlspecialchars($basePath); ?>/js/script.js"></script>

<!-- Service Worker for PWA -->
<script>
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
      navigator.serviceWorker.register('<?php echo htmlspecialchars($basePath); ?>/js/service-worker.js', { scope: '<?php echo htmlspecialchars($basePath); ?>/' }).then(registration => {
        console.log('Service Worker registriert mit Scope: ', registration.scope);
      }, err => {
        console.log('Service Worker Registrierung fehlgeschlagen: ', err);
      });
    });
  }
</script>

</body>
</html>
