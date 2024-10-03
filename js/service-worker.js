const cacheName = 'fahrzeugverwaltung-cache-v1';
const assetsToCache = [
  '/fuelstat/',
  '/fuelstat/index.php',
  '/fuelstat/css/style.css',
  '/fuelstat/js/script.js',
  '/fuelstat/images/platzhalter.jpg',
  '/fuelstat/images/icon-192x192.png',
  '/fuelstat/images/icon-512x512.png'
];

// Installiere den Service Worker
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(cacheName).then(cache => {
      return cache.addAll(assetsToCache).catch(error => {
        console.error('Caching failed:', error);
      });
    })
  );
});

// Fetch event abfangen und Ressourcen aus dem Cache bereitstellen
self.addEventListener('fetch', event => {
  event.respondWith(
    caches.match(event.request).then(response => {
      return response || fetch(event.request);
    })
  );
});
