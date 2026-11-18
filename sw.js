/*
 * Service worker for the installed app (PWA).
 *
 * - Pages are never cached: they hold private records. Every page is fetched from the server.
 * - When there is no connection, a page request shows offline.html instead.
 * - Only files under assets/ are cached (css, js, fonts, icons, images).
 * - POST requests (forms and uploads), downloads, file.php and other sites are not touched at all.
 *
 * Raise VERSION with every release; old caches are removed when the new worker takes over.
 */
'use strict';

const VERSION = '1.1.0';
const CACHE = 'cnpl-assets-' + VERSION;
const MAX_ENTRIES = 150;

// Scope of the app, for example "/coop-natcco/" on XAMPP or "/" at a domain root.
const SCOPE = new URL(self.registration.scope).pathname;
const ASSETS = SCOPE + 'assets/';
const OFFLINE_URL = SCOPE + 'offline.html';

const PRECACHE = [
  OFFLINE_URL,
  ASSETS + 'icons/icon-192.png',
  ASSETS + 'icons/icon-512.png',
  ASSETS + 'icons/maskable-192.png',
  ASSETS + 'icons/maskable-512.png',
  ASSETS + 'icons/apple-touch-icon.png',
  ASSETS + 'icons/favicon-32.png'
];

/** Addresses that start a download or serve a stored file: the browser handles them directly. */
function isDownload(url) {
  const p = url.searchParams;
  return url.pathname.endsWith('/file.php') || p.get('ics') === '1' || p.has('export') || p.has('download')
    || p.get('action') === 'export' || p.get('action') === 'template' || p.get('step') === 'errors';
}

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE)
      .then((cache) => cache.addAll(PRECACHE.map((u) => new Request(u, { cache: 'reload' }))))
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys.filter((k) => k.startsWith('cnpl-') && k !== CACHE).map((k) => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const req = event.request;
  if (req.method !== 'GET') {
    return; // forms, uploads, sign-in, sign-out
  }
  const url = new URL(req.url);
  if (url.origin !== self.location.origin || !url.pathname.startsWith(SCOPE)) {
    return; // other sites (video embeds, maps)
  }
  if (req.headers.has('range') || isDownload(url)) {
    return; // downloads, private files, media streams
  }

  if (req.mode === 'navigate') {
    event.respondWith(pageFromNetwork(req));
    return;
  }

  if (url.pathname === OFFLINE_URL) {
    event.respondWith(caches.match(OFFLINE_URL).then((hit) => hit || fetch(req)));
    return;
  }

  if (url.pathname.startsWith(ASSETS)) {
    event.respondWith(assetResponse(event, req, url));
  }
  // anything else (APIs, manifest.php) goes to the network untouched
});

/** Pages: always from the server, never stored. Without a connection, show the offline page. */
async function pageFromNetwork(req) {
  try {
    return await fetch(req);
  } catch (err) {
    const offline = await caches.match(OFFLINE_URL);
    return offline || new Response('<!doctype html><title>No internet connection</title><p>No internet connection. Check your connection and try again.</p>',
      { status: 503, headers: { 'Content-Type': 'text/html; charset=utf-8' } });
  }
}

/**
 * Assets: versioned files (?v=...) never change, so the cached copy is used.
 * Unversioned files (fonts referenced from CSS) are served from the cache and refreshed in the background.
 */
async function assetResponse(event, req, url) {
  const cache = await caches.open(CACHE);
  const hit = await cache.match(req);
  if (hit && url.searchParams.has('v')) {
    return hit;
  }
  const network = fetch(req).then((res) => {
    if (res.ok && res.type === 'basic') {
      event.waitUntil(cache.put(req, res.clone()).then(() => trim(cache)));
    }
    return res;
  });
  if (hit) {
    event.waitUntil(network.catch(() => {}));
    return hit;
  }
  return network;
}

async function trim(cache) {
  const keys = await cache.keys();
  const keep = new Set(PRECACHE.map((u) => new URL(u, self.location.origin).href));
  const removable = keys.filter((k) => !keep.has(k.url));
  for (let i = 0; i < removable.length - MAX_ENTRIES; i++) {
    await cache.delete(removable[i]);
  }
}
