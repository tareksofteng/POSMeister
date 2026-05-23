/*
 * POSmeister Service Worker
 * --------------------------
 * Keeps the Vue shell installable and snappy. Two cache buckets:
 *
 *   - app-shell  : the small set of HTML/CSS/JS needed to boot offline
 *   - assets     : everything fetched from /build (Vite hashed files)
 *
 * API calls (/api/*) are network-first with a short timeout; if the
 * network is down we fall through to a JSON envelope that tells the
 * frontend "you are offline" so it can show the right UI.
 *
 * Bumping the CACHE_VERSION below busts every cache on the next load.
 */
const CACHE_VERSION = 'posmeister-v1';
const SHELL_CACHE   = `${CACHE_VERSION}-shell`;
const ASSETS_CACHE  = `${CACHE_VERSION}-assets`;
const API_TIMEOUT   = 6000;

const SHELL_URLS = ['/', '/manifest.webmanifest'];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(SHELL_CACHE).then((cache) => cache.addAll(SHELL_URLS).catch(() => null))
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil((async () => {
        const keys = await caches.keys();
        await Promise.all(keys
            .filter((k) => !k.startsWith(CACHE_VERSION))
            .map((k) => caches.delete(k)));
        await self.clients.claim();
    })());
});

self.addEventListener('message', (event) => {
    if (event.data === 'SKIP_WAITING') self.skipWaiting();
    if (event.data === 'PING')         event.source?.postMessage({ type: 'PONG', at: Date.now() });
});

self.addEventListener('fetch', (event) => {
    const req = event.request;
    if (req.method !== 'GET') return;
    const url = new URL(req.url);
    if (url.origin !== self.location.origin) return;

    if (url.pathname.startsWith('/build/')) {
        event.respondWith(cacheFirst(req, ASSETS_CACHE));
        return;
    }

    if (url.pathname.startsWith('/api/')) {
        event.respondWith(networkFirstApi(req));
        return;
    }

    if (req.mode === 'navigate') {
        event.respondWith(navigationHandler(req));
        return;
    }
});

async function cacheFirst(req, cacheName) {
    const cache = await caches.open(cacheName);
    const hit = await cache.match(req);
    if (hit) return hit;
    try {
        const fresh = await fetch(req);
        if (fresh.ok) cache.put(req, fresh.clone());
        return fresh;
    } catch {
        return hit || Response.error();
    }
}

async function networkFirstApi(req) {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), API_TIMEOUT);
    try {
        const fresh = await fetch(req, { signal: controller.signal });
        clearTimeout(timer);
        return fresh;
    } catch (err) {
        clearTimeout(timer);
        return new Response(JSON.stringify({
            offline: true,
            message: 'Offline — request will be retried when connectivity returns.',
        }), {
            status: 503,
            headers: { 'Content-Type': 'application/json', 'X-Posmeister-Offline': '1' },
        });
    }
}

async function navigationHandler(req) {
    try {
        const fresh = await fetch(req);
        const cache = await caches.open(SHELL_CACHE);
        cache.put('/', fresh.clone());
        return fresh;
    } catch {
        const cache = await caches.open(SHELL_CACHE);
        const cached = await cache.match('/');
        return cached || Response.error();
    }
}
