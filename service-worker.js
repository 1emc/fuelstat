const cacheName = 'fahrzeugverwaltung-cache-v7';
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
  // Neue Version sofort übernehmen, statt zu warten, bis alle Tabs geschlossen sind
  self.skipWaiting();
});

// Aktivieren: Alte Caches bereinigen
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.filter(name => name !== cacheName).map(name => caches.delete(name))
      );
    }).then(() => self.clients.claim())
  );
});

// HTML/PHP-Seiten nie aus Cache – immer Netzwerk (Session-Inhalt ist dynamisch)
function isDocumentRequest(request) {
  return request.mode === 'navigate' ||
    (request.url.includes('.php') && !request.url.includes('?')) ||
    request.destination === 'document';
}

// CSS und JS der App: nach einem Deploy muss die neue Version kommen, Cache nur offline
function isAppAsset(request) {
  const url = new URL(request.url);
  return url.origin === self.location.origin && /\.(css|js)$/.test(url.pathname);
}

// Fetch: Dokumente, CSS und JS = Network-First, Rest (Bilder) = Cache-First
self.addEventListener('fetch', event => {
  if (isDocumentRequest(event.request) || isAppAsset(event.request)) {
    event.respondWith(
      fetch(event.request)
        .then(response => response)
        .catch(() => caches.match(event.request, { ignoreSearch: isAppAsset(event.request) }))
    );
    return;
  }
  event.respondWith(
    caches.match(event.request).then(cached => cached || fetch(event.request))
  );
});


