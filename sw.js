/* PuchoNow service worker — offline support + static asset caching.
   Strategy: cache-first for versioned static assets, network-first for pages
   with an offline fallback. Bump CACHE_VERSION when deploying asset changes. */
const CACHE_VERSION = 'pn-v1';
const STATIC_CACHE = CACHE_VERSION + '-static';
const PAGE_CACHE = CACHE_VERSION + '-pages';
const OFFLINE_URL = '/offline.html';

const PRECACHE = [
  OFFLINE_URL,
  '/assets/css/tailwind.min.css',
  '/assets/dist/js/theme.js',
  '/assets/dist/js/ui.js',
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(STATIC_CACHE).then((cache) => cache.addAll(PRECACHE)).then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.filter((k) => !k.startsWith(CACHE_VERSION)).map((k) => caches.delete(k)))
    ).then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const req = event.request;
  if (req.method !== 'GET') return;
  const url = new URL(req.url);
  if (url.origin !== location.origin) return;
  // Never cache authenticated/admin/api responses.
  if (/^\/(admin|api|inbox|checkout|logout|login|register)/.test(url.pathname)) return;

  // Static assets: cache-first.
  if (/\.(css|js|png|jpe?g|gif|svg|webp|ico|woff2?)$/.test(url.pathname)) {
    event.respondWith(
      caches.match(req).then((hit) => hit || fetch(req).then((res) => {
        if (res.ok) {
          const copy = res.clone();
          caches.open(STATIC_CACHE).then((c) => c.put(req, copy));
        }
        return res;
      }))
    );
    return;
  }

  // Pages: network-first, cache success for offline reading, offline page as fallback.
  if (req.mode === 'navigate') {
    event.respondWith(
      fetch(req).then((res) => {
        if (res.ok) {
          const copy = res.clone();
          caches.open(PAGE_CACHE).then((c) => c.put(req, copy));
        }
        return res;
      }).catch(() =>
        caches.match(req).then((hit) => hit || caches.match(OFFLINE_URL))
      )
    );
  }
});
