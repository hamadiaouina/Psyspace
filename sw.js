self.addEventListener('install', (e) => {
  console.log('PsySpace Service Worker installé !');
});

self.addEventListener('fetch', (e) => {
  // Indispensable pour le mode hors-ligne basique
  e.respondWith(fetch(e.request));
});