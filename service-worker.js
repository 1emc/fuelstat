const cacheName = 'fahrzeugverwaltung-cache-v2';
const assetsToCache = [
  './',
  './index.php',
  './css/style.css',
  './js/script.js',
  './images/platzhalter.jpg',
  './images/icon-192x192.png',
  './images/new-icon-512x512.png'
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

// Aktivieren: Alte Caches bereinigen
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.filter(name => name !== cacheName).map(name => caches.delete(name))
      );
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


