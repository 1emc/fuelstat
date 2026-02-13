const cacheName = 'fahrzeugverwaltung-cache-v3';
const assetsToCache = [
  './',
  './css/style.css',
  './js/script.js',
  './images/platzhalter.jpg',
  './images/icon-192x192.png',
  './images/new-icon-512x512.png'
];

// Install: Nur statische Assets cachen (keine .php-Seiten)
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

// HTML/PHP-Seiten nie aus Cache – immer Netzwerk (Session-Inhalt ist dynamisch)
function isDocumentRequest(request) {
  return request.mode === 'navigate' ||
    (request.url.includes('.php') && !request.url.includes('?')) ||
    request.destination === 'document';
}

// Fetch: Dokumente = Network-First, Rest = Cache-First
self.addEventListener('fetch', event => {
  if (isDocumentRequest(event.request)) {
    event.respondWith(
      fetch(event.request)
        .then(response => response)
        .catch(() => caches.match(event.request))
    );
    return;
  }
  event.respondWith(
    caches.match(event.request).then(cached => cached || fetch(event.request))
  );
});


