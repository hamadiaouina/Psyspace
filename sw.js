const CACHE_NAME = 'psyspace-offline-v1';
const OFFLINE_URL = 'offline.php';

// Les fichiers vitaux à garder dans le téléphone/PC de l'utilisateur
const ASSETS_TO_CACHE = [
  OFFLINE_URL,
  'assets/images/logo.png',
  'assets/js/tailwind.min.js'
];

self.addEventListener('install', (e) => {
  console.log('[PsySpace SW] Service Worker installé !');
  
  // On télécharge la page hors-ligne en arrière-plan
  e.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      console.log('[PsySpace SW] Mise en cache des fichiers hors-ligne...');
      return cache.addAll(ASSETS_TO_CACHE);
    })
  );
  
  // Force le SW à s'activer immédiatement sans attendre
  self.skipWaiting();
});

self.addEventListener('activate', (e) => {
  console.log('[PsySpace SW] Service Worker activé !');
  
  // On nettoie les vieux caches si on met à jour l'application
  e.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames.map((cacheName) => {
          if (cacheName !== CACHE_NAME) {
            console.log('[PsySpace SW] Suppression de l\'ancien cache :', cacheName);
            return caches.delete(cacheName);
          }
        })
      );
    })
  );
  
  // Prend le contrôle immédiat de la page sans avoir besoin de rafraîchir
  self.clients.claim();
});

self.addEventListener('fetch', (e) => {
  // 1. Si l'utilisateur demande une page web (HTML / Navigation)
  if (e.request.mode === 'navigate') {
    e.respondWith(
      // On tente de charger la page normalement avec Internet
      fetch(e.request).catch(() => {
        // SI INTERNET COUPE -> On affiche la belle page de respiration
        console.log('[PsySpace SW] Réseau indisponible, affichage de la page hors-ligne.');
        return caches.match(OFFLINE_URL);
      })
    );
  } 
  // 2. Pour les autres ressources (Images, CSS, JS)
  else {
    e.respondWith(
      caches.match(e.request).then((cachedResponse) => {
        // On retourne la ressource depuis le cache si elle existe, sinon on utilise Internet
        return cachedResponse || fetch(e.request).catch(() => {
            console.log('[PsySpace SW] Ressource non disponible hors-ligne :', e.request.url);
        });
      })
    );
  }
});