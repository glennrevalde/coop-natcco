/* Service worker: makes the system installable as a desktop or mobile app.
   Only static assets are cached. Pages and data are always fetched from the server,
   so confidential records are never stored on the device. */
const CACHE = 'cnpl-static-v1';
self.addEventListener('install', (e) => { self.skipWaiting(); });
self.addEventListener('activate', (e) => {
  e.waitUntil(caches.keys().then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k)))));
});
self.addEventListener('fetch', (e) => {
  const url = new URL(e.request.url);
  if (e.request.method !== 'GET' || url.origin !== location.origin || !url.pathname.includes('/assets/')) { return; }
  e.respondWith(caches.open(CACHE).then((c) => c.match(e.request).then((hit) => hit || fetch(e.request).then((res) => { if (res.ok) { c.put(e.request, res.clone()); } return res; }))));
});
