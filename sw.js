self.addEventListener('install', (e) => {
  console.log('PsySpace Service Worker installé !');
  // Force le SW à s'activer immédiatement sans attendre
  self.skipWaiting();
});

self.addEventListener('activate', (e) => {
  console.log('PsySpace Service Worker activé !');
});

self.addEventListener('fetch', (e) => {
  e.respondWith(
    fetch(e.request).catch(() => {
      // Si le réseau échoue (mode hors-ligne), on ne crash pas
      console.log('Récupération échouée pour :', e.request.url);
      return new Response("Mode hors-ligne : Ressource non disponible.");
    })
  );
});