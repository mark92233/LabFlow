const CACHE_NAME = 'labflow-cache-v1';
const urlsToCache = [
  '/LabFlow/',
  '/LabFlow/index.php',
  '/LabFlow/assets/css/style.css',
  '/LabFlow/HTML_Demo/img/labflow.jpg',
  '/LabFlow/HTML_Demo/img/labflow-192.png',
  '/LabFlow/HTML_Demo/img/labflow-512.png'
];

self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        console.log('Opened cache');
        return cache.addAll(urlsToCache);
      })
  );
});

self.addEventListener('fetch', event => {
  event.respondWith(
    caches.match(event.request)
      .then(response => response || fetch(event.request))
  );
});