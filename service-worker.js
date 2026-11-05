// Service Worker (#89)
// - Seiten (Navigation): immer vom Netz, mit Navigation Preload. Die Anfrage startet so parallel zum Aufwecken
//   des Service Workers und wartet nicht auf ihn. Seiten werden nie gespeichert (persönliche Daten).
// - CSS/JS/Schriften/Bilder der App mit ?v=… (Version) oder fester Datei: aus dem Cache, sonst vom Netz und merken.
//   Neue Versionen haben eine neue Adresse (?v=Änderungszeit), veraltete Dateien gibt es dadurch nicht.
// - API, signierte Bilder (/api/v1/files/…) und alles andere: nicht abfangen.
const cacheName = 'fuelstat-v8';
const vorab = [
  './images/platzhalter.jpg',
  './images/icon-192x192.png',
  './images/new-icon-512x512.png'
];

self.addEventListener('install', (event) => {
  event.waitUntil(caches.open(cacheName).then((cache) => cache.addAll(vorab)).catch(() => {}));
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil((async () => {
    const namen = await caches.keys();
    await Promise.all(namen.filter((n) => n !== cacheName).map((n) => caches.delete(n)));
    if (self.registration.navigationPreload) {
      await self.registration.navigationPreload.enable().catch(() => {});
    }
    await self.clients.claim();
  })());
});

// Statische Datei der App, die sich unter derselben Adresse nie ändert
function istStatisch(url) {
  if (url.origin !== self.location.origin || url.pathname.includes('/api/')) return false;
  if (/\.(css|js)$/.test(url.pathname)) return url.searchParams.has('v');
  return /\.(woff2?|ttf|png|jpe?g|webp|svg|ico|gif)$/.test(url.pathname) && !url.pathname.includes('/uploads/');
}

function offlineSeite() {
  return new Response(
    '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
    + '<title>Offline</title><body style="font-family:system-ui;padding:2rem;text-align:center">'
    + '<h1>Keine Verbindung</h1><p>Fuelstat braucht eine Internetverbindung. Bitte später erneut versuchen.</p>'
    + '<p><a href="" onclick="location.reload();return false">Erneut versuchen</a></p>',
    { status: 503, headers: { 'Content-Type': 'text/html; charset=utf-8' } }
  );
}

self.addEventListener('fetch', (event) => {
  const req = event.request;
  if (req.method !== 'GET') return;

  if (req.mode === 'navigate') {
    event.respondWith((async () => {
      try {
        const vorgeladen = await event.preloadResponse;
        if (vorgeladen) return vorgeladen;
        return await fetch(req);
      } catch (e) {
        return offlineSeite();
      }
    })());
    return;
  }

  const url = new URL(req.url);
  if (!istStatisch(url)) return;
  event.respondWith((async () => {
    const cache = await caches.open(cacheName);
    const treffer = await cache.match(req);
    if (treffer) return treffer;
    const antwort = await fetch(req);
    if (antwort.ok && antwort.type === 'basic') {
      const kopie = antwort.clone();
      event.waitUntil((async () => {
        // Ältere Versionen derselben Datei entfernen, damit der Cache nicht wächst
        for (const alt of await cache.keys()) {
          if (new URL(alt.url).pathname === url.pathname && alt.url !== req.url) await cache.delete(alt);
        }
        await cache.put(req, kopie);
      })().catch(() => {}));
    }
    return antwort;
  })());
});
