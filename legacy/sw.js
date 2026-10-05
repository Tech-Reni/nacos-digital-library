/* ============================================================
 * NACOS App — Service Worker
 * Provides offline caching + app shell so the installed PWA works
 * even when the student is offline.
 * ============================================================ */

/* Version this cache when you want users to refresh assets. */
const CACHE_PREFIX = 'nacos-library-';
const CACHE_VERSION = 'v3';
const CACHE_NAME = CACHE_PREFIX + CACHE_VERSION;
const APP_BASE = new URL('./', self.location).pathname;
const appPath = (path) => APP_BASE + path;

/* Core app-shell assets pre-cached at install. */
const PRECACHE = [
  APP_BASE,
  appPath('home.php'),
  appPath('manifest.json'),
  appPath('assets/images/NACOS_LOGO.png'),
  appPath('assets/images/YCT_LOGO.png'),
  appPath('assets/css/main.css'),
  appPath('assets/css/animation.css'),
  appPath('assets/css/library.css')
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => cache.addAll(PRECACHE))
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  // Remove old caches so stale assets are never served.
  event.waitUntil(
    caches.keys()
      .then((keys) =>
        Promise.all(
          keys
            .filter((key) => key.startsWith(CACHE_PREFIX) && key !== CACHE_NAME)
            .map((key) => caches.delete(key))
        )
      )
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const request = event.request;

  // Only handle simple GET navigation/asset requests.
  if (request.method !== 'GET') return;

  // Do not cache cross-origin requests (CDNs, analytics, etc.) here.
  let url;
  try {
    url = new URL(request.url);
  } catch (e) {
    return;
  }
  if (url.origin !== self.location.origin) return;

  // Skip non-http(s) like chrome-extension.
  if (request.mode === 'navigate') {
    // Network-first for page navigations: always prefer fresh content,
    // fall back to the cached copy when offline.
    event.respondWith(
      fetch(request)
        .then((response) => {
          const copy = response.clone();
          caches.open(CACHE_NAME).then((cache) => cache.put(request, copy));
          return response;
        })
        .catch(() =>
          caches.match(request).then(
            (cached) => cached || caches.match(appPath('home.php'))
          )
        )
    );
    return;
  }

  // Cache-first for static assets (images, css, js).
  event.respondWith(
    caches.match(request).then(
      (cached) =>
        cached ||
        fetch(request).then((response) => {
          // Only cache successful, opaque-safe GETs.
          if (response.ok) {
            const copy = response.clone();
            caches.open(CACHE_NAME).then((cache) => cache.put(request, copy));
          }
          return response;
        })
    )
  );
});