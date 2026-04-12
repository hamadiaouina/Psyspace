const CACHE_NAME = 'psyspace-offline-v1';
const OFFLINE_URL = 'offline.php';

const ASSETS_TO_CACHE = [
  OFFLINE_URL,
  'assets/images/logo.png',
  'assets/js/tailwind.min.js'
];

self.addEventListener('install', (e) => {
  e.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      return cache.addAll(ASSETS_TO_CACHE);
    })
  );
  self.skipWaiting();
});

self.addEventListener('activate', (e) => {
  e.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames.map((cacheName) => {
          if (cacheName !== CACHE_NAME) {
            return caches.delete(cacheName);
          }
        })
      );
    })
  );
  self.clients.claim();
});

self.addEventListener('fetch', (e) => {

  // Ne jamais intercepter admin_poll.php — laisser passer directement
  if (e.request.url.includes('admin_poll.php')) {
    return;
  }

  if (e.request.mode === 'navigate') {
    e.respondWith(
      fetch(e.request).catch(() => {
        return caches.match(OFFLINE_URL);
      })
    );
  } else {
    e.respondWith(
      caches.match(e.request).then((cachedResponse) => {
        return cachedResponse || fetch(e.request).catch(() => {
          // Retourne une réponse vide valide au lieu de undefined
          return new Response('', { status: 408, statusText: 'Offline' });
        });
      })
    );
  }
});