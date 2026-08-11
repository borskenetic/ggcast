/* ggcast hybrid SW — must live at site root so scope covers /opac, /attendance, etc. */
const CACHE = 'ggcast-hybrid-shell-v4';

const PRECACHE = [
  '/opac-offline.html',
  '/js/hybrid/hybrid.js',
  '/js/hybrid/opac-bridge.js',
  '/js/hybrid/status.css',
  '/js/hybrid/catalog-bridge.js',
  '/manifest.webmanifest',
  '/vendor/bootstrap/css/bootstrap.min.css',
  '/css/books/landing.css',
  '/css/site-responsive.css',
  '/images/pantasLogo.png',
  '/images/d.png',
  '/images/default-book.png',
];

const SHELL_PATHS = new Set([
  '/opac',
  '/attendance',
  '/book/create',
  '/opac-offline.html',
]);

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches
      .open(CACHE)
      .then(async (cache) => {
        for (const url of PRECACHE) {
          try {
            await cache.add(url);
          } catch (e) {
            // ignore missing asset
          }
        }
      })
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches
      .keys()
      .then((keys) =>
        Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k)))
      )
      .then(() => self.clients.claim())
  );
});

self.addEventListener('message', (event) => {
  const data = event.data || {};
  if (data.type === 'CACHE_URLS' && Array.isArray(data.urls)) {
    event.waitUntil(
      caches.open(CACHE).then(async (cache) => {
        for (const url of data.urls) {
          try {
            await cache.add(url);
          } catch (e) {
            /* ignore */
          }
        }
      })
    );
  }
});

function isShellNavigate(url) {
  const path = url.pathname.replace(/\/$/, '') || '/';
  if (SHELL_PATHS.has(path) || SHELL_PATHS.has(url.pathname)) return true;
  if (path === '/opac' || url.pathname.startsWith('/opac')) return true;
  if (path === '/attendance' || url.pathname.startsWith('/attendance')) return true;
  if (url.pathname.startsWith('/js/hybrid/')) return true;
  if (url.pathname.startsWith('/css/books/')) return true;
  if (url.pathname.startsWith('/css/attendance/')) return true;
  if (url.pathname.startsWith('/vendor/bootstrap/')) return true;
  return false;
}

self.addEventListener('fetch', (event) => {
  const req = event.request;
  if (req.method !== 'GET') return;

  const url = new URL(req.url);
  if (url.origin !== self.location.origin) return;

  // HTML navigations for OPAC / attendance → cache then network, offline fallback
  if (req.mode === 'navigate' || (req.headers.get('accept') || '').includes('text/html')) {
    if (
      url.pathname === '/opac' ||
      url.pathname.startsWith('/opac?') ||
      url.pathname === '/attendance' ||
      url.pathname.startsWith('/attendance') ||
      url.pathname.startsWith('/book/create')
    ) {
      event.respondWith(networkThenCacheNavigate(req, url));
      return;
    }
  }

  if (!isShellNavigate(url)) return;

  event.respondWith(cacheFirstAsset(req));
});

async function networkThenCacheNavigate(req, url) {
  const cache = await caches.open(CACHE);
  try {
    const fresh = await fetch(req);
    if (fresh && fresh.ok) {
      // Store under clean path for offline reopen
      const pathKey = url.pathname === '/opac' || url.pathname.startsWith('/opac')
        ? '/opac'
        : url.pathname.replace(/\/$/, '') || url.pathname;
      try {
        await cache.put(pathKey, fresh.clone());
        await cache.put(req, fresh.clone());
      } catch (e) {
        /* ignore put failures */
      }
    }
    return fresh;
  } catch (e) {
    // Offline — for OPAC always prefer dedicated offline shell
    if (url.pathname === '/opac' || url.pathname.startsWith('/opac')) {
      const offlineShell =
        (await cache.match('/opac-offline.html')) ||
        (await cache.match(new Request('/opac-offline.html')));
      if (offlineShell) {
        // 302-like: client may need rewrite; return offline document as response
        return offlineShell;
      }
    }

    let cached =
      (await cache.match(req)) ||
      (await cache.match('/opac')) ||
      (await cache.match('/attendance'));

    if (url.pathname.startsWith('/opac')) {
      cached =
        (await cache.match('/opac-offline.html')) ||
        (await cache.match('/opac')) ||
        cached;
    }
    if (url.pathname.startsWith('/attendance')) {
      cached = (await cache.match('/attendance')) || cached;
    }

    if (cached) return cached;
    const offline = await cache.match('/opac-offline.html');
    if (offline) return offline;
    return new Response(
      '<!DOCTYPE html><html><head><meta http-equiv="refresh" content="0;url=/opac-offline.html"></head><body style="font-family:sans-serif;padding:2rem"><p>Offline — <a href="/opac-offline.html">Open offline OPAC</a></p></body></html>',
      { headers: { 'Content-Type': 'text/html; charset=utf-8' } }
    );
  }
}

async function cacheFirstAsset(req) {
  const cache = await caches.open(CACHE);
  const hit = await cache.match(req);
  if (hit) return hit;
  try {
    const fresh = await fetch(req);
    if (fresh && fresh.ok) {
      try {
        await cache.put(req, fresh.clone());
      } catch (e) {
        /* ignore */
      }
    }
    return fresh;
  } catch (e) {
    const offline = await cache.match(req);
    if (offline) return offline;
    throw e;
  }
}
