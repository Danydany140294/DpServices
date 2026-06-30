const CACHE_NAME = 'dp-services-v1';
const URLS_TO_CACHE = [
    '/',
    '/manifest.json',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            return cache.addAll(URLS_TO_CACHE);
        })
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames
                    .filter((name) => name !== CACHE_NAME)
                    .map((name) => caches.delete(name))
            );
        })
    );
    self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    // Stratégie "network first, fallback cache" : on privilégie toujours
    // les données fraîches (missions, calendrier), le cache ne sert que
    // de filet de sécurité hors-ligne.
    event.respondWith(
        fetch(event.request).catch(() => {
            return caches.match(event.request);
        })
    );
});

// TODO PROD (J44) : gestion des notifications push nécessite HTTPS.
// Une fois le domaine HTTPS actif (déploiement Hetzner), ajouter ici :
// self.addEventListener('push', (event) => { ... });
// self.addEventListener('notificationclick', (event) => { ... });