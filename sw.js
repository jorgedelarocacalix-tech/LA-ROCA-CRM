// Service Worker v2.4 — sin caché, fuerza contenido fresco
var CACHE_NAME = 'laroca-crm-v2.4';

self.addEventListener('install', function(e) {
  self.skipWaiting();
});

self.addEventListener('activate', function(e) {
  e.waitUntil(
    caches.keys().then(function(keys) {
      return Promise.all(keys.map(function(key) {
        return caches.delete(key);
      }));
    }).then(function() {
      return self.clients.claim();
    })
  );
});

// Pass all requests through to network (no caching)
self.addEventListener('fetch', function(e) {
  e.respondWith(fetch(e.request).catch(function() {
    return caches.match(e.request);
  }));
});
