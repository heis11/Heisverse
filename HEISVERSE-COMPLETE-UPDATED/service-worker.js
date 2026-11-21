/**
 * HEISVERSE — Progressive Web App Service Worker (v2.0)
 * Intelligent caching of static stylesheets, 300-theme assets, logos, and scripts.
 * Keeps authentication, dynamic database, and admin requests strictly network-first.
 */

const CACHE_NAME = 'heisverse-v2.1';
const STATIC_ASSETS = [
  'assets/css/style.css',
  'assets/js/main.js',
  'assets/js/theme-engine-300.js',
  'assets/js/voice-dictation.js',
  'assets/js/text-to-speech.js',
  'assets/data/themes-300.json',
  'assets/images/logo.svg',
  'assets/images/logo-symbol.svg',
  'assets/images/favicon.svg',
  'manifest.json',
  'offline.html'
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      console.log('[HEISVERSE PWA] Pre-caching static assets');
      return cache.addAll(STATIC_ASSETS);
    })
  );
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => {
      return Promise.all(
        keys.map((key) => {
          if (key !== CACHE_NAME) {
            return caches.delete(key);
          }
        })
      );
    })
  );
  self.clients.claim();
});

self.addEventListener('fetch', (event) => {
  const request = event.request;
  const url = new URL(request.url);

  // Never cache API responses (user-specific), authentication, admin actions, or POST submissions
  if (request.method !== 'GET' || url.pathname.endsWith('api.php') || url.origin !== self.location.origin || url.pathname.includes('login') || url.pathname.includes('register') || url.pathname.includes('admin') || url.pathname.includes('forgot') || url.pathname.includes('reset')) {
    return;
  }

  // Stale-While-Revalidate for static assets
  if (request.destination === 'style' || request.destination === 'script' || request.destination === 'image' || (url.pathname.endsWith('.json') && url.pathname.includes('/assets/')) || url.pathname.endsWith('manifest.json') || url.pathname.endsWith('.svg')) {
    event.respondWith(
      caches.match(request).then((cachedResponse) => {
        const fetchPromise = fetch(request).then((networkResponse) => {
          if (networkResponse && networkResponse.status === 200) {
            const clone = networkResponse.clone();
            caches.open(CACHE_NAME).then((cache) => cache.put(request, clone));
          }
          return networkResponse;
        }).catch(() => cachedResponse);
        return cachedResponse || fetchPromise;
      })
    );
    return;
  }

  // Network-First with Offline Fallback for Navigation
  if (request.mode === 'navigate') {
    event.respondWith(
      fetch(request).catch(() => caches.match('offline.html'))
    );
  }
});
