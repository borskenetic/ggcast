/* Service worker — cache hybrid app shells for offline re-open */
const CACHE = 'ggcast-hybrid-shell-v1';
const PRECACHE = [
  '/js/hybrid/hybrid.js',
  '/js/hybrid/status.css',
  '/manifest.webmanifest',
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE).then((cache) => cache.addAll(PRECACHE)).then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k)))
    ).then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const req = event.request;
  if (req.method !== 'GET') return;

  const url = new URL(req.url);
  // Only handle same-origin shell assets & hybrid pages hints
  if (url.origin !== self.location.origin) return;

  if (
    url.pathname.startsWith('/js/hybrid/') ||
    url.pathname === '/manifest.webmanifest' ||
    url.pathname === '/attendance' ||
    url.pathname === '/opac' ||
    url.pathname.startsWith('/book/create')
  ) {
    event.respondWith(
      caches.open(CACHE).then(async (cache) => {
        try {
          const fresh = await fetch(req);
          if (fresh && fresh.ok) cache.put(req, fresh.clone());
          return fresh;
        } catch {
          const cached = await cache.match(req);
          if (cached) return cached;
          // offline document fallback
          if (req.mode === 'navigate') {
            const offline = await cache.match('/attendance') || await cache.match('/opac');
            if (offline) return offline;
          }
          throw new Error('offline');
        }
      })
    );
  }
});
