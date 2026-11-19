/* Service worker minimal : rend l'application installable et affiche une page claire hors connexion.
   Aucune donnée de l'API n'est mise en cache (stocks, ventes et factures doivent toujours être à jour). */
const CACHE = 'gestcom-offline-v1'
const OFFLINE = '/offline.html'

self.addEventListener('install', (event) => {
  // La page hors connexion est un confort : si elle ne peut pas être mise en cache, l'installation ne doit pas échouer.
  event.waitUntil(
    caches.open(CACHE)
      .then((c) => fetch(OFFLINE, { cache: 'reload' }).then((r) => (r.ok ? c.put(OFFLINE, r) : undefined)))
      .catch(() => {})
      .then(() => self.skipWaiting()),
  )
})

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k)))).then(() => self.clients.claim()),
  )
})

self.addEventListener('fetch', (event) => {
  const req = event.request
  if (req.method !== 'GET' || req.mode !== 'navigate') return // tout le reste passe directement par le réseau
  event.respondWith(fetch(req).catch(() => caches.match(OFFLINE)))
})
