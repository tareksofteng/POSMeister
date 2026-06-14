/*
 * POSmeister Service Worker (v3 — offline-resilient)
 * --------------------------------------------------
 * Three cache buckets:
 *
 *   - shell   : HTML for navigations (root + last-visited URL)
 *   - assets  : Vite-hashed JS/CSS/fonts from /build/*
 *   - pages   : cached navigation responses (fallback when offline)
 *
 * Boot flow:
 *   1. install   → pre-cache "/" and ALL hashed bundles listed in
 *                  /build/manifest.json so the app boots offline
 *                  even on the very first PWA install.
 *   2. activate  → drop old cache versions, take control. Trigger a
 *                  background re-precache so chunks that 404'd during
 *                  install get a second chance.
 *   3. fetch     → /build/*    cache-first (ignore query strings)
 *                  /api/*      network-first w/ offline JSON fallback
 *                  navigation  network-first; on failure return ANY
 *                              cached HTML (current URL, then "/",
 *                              then most recent navigation).
 *   4. message   → 'PRECACHE_ASSETS' from client re-pulls the manifest
 *                  and tops up the asset cache. Client posts this on
 *                  every boot so chunks added between deploys land in
 *                  the cache as soon as the user is briefly online.
 *
 * Bumping CACHE_VERSION forces every client to reload on next visit.
 */
const CACHE_VERSION = 'posmeister-v5';
const SHELL_CACHE   = `${CACHE_VERSION}-shell`;
const ASSETS_CACHE  = `${CACHE_VERSION}-assets`;
const PAGES_CACHE   = `${CACHE_VERSION}-pages`;
const API_TIMEOUT   = 6000;

// ── INSTALL ──────────────────────────────────────────────────────────────
self.addEventListener('install', (event) => {
    event.waitUntil((async () => {
        await precacheEverything();
        self.skipWaiting();
    })());
});

async function precacheEverything() {
    const shell = await caches.open(SHELL_CACHE);
    await safeAdd(shell, ['/', '/manifest.webmanifest']);

    try {
        const res = await fetch('/build/manifest.json', { cache: 'no-cache' });
        if (!res.ok) return;
        const manifest = await res.json();
        const urls = collectAssetUrls(manifest);
        if (!urls.length) return;
        const assets = await caches.open(ASSETS_CACHE);
        await safeAdd(assets, urls);
    } catch {
        // Manifest missing or network blip — assets get cached on demand.
    }
}

function collectAssetUrls(manifest) {
    const out = new Set();
    for (const entry of Object.values(manifest || {})) {
        if (entry.file) out.add('/build/' + entry.file);
        for (const c of entry.css || []) out.add('/build/' + c);
        for (const a of entry.assets || []) out.add('/build/' + a);
        for (const i of entry.imports || []) {
            const dep = manifest[i];
            if (dep?.file) out.add('/build/' + dep.file);
            for (const c of dep?.css || []) out.add('/build/' + c);
        }
        for (const i of entry.dynamicImports || []) {
            const dep = manifest[i];
            if (dep?.file) out.add('/build/' + dep.file);
            for (const c of dep?.css || []) out.add('/build/' + c);
        }
    }
    return [...out];
}

async function safeAdd(cache, urls) {
    // Add each url individually so one 404 doesn't abort the whole batch.
    return Promise.all(urls.map((u) =>
        fetch(u, { cache: 'no-cache' })
            .then((r) => r.ok && cache.put(u, r.clone()))
            .catch(() => null)
    ));
}

// ── ACTIVATE ─────────────────────────────────────────────────────────────
self.addEventListener('activate', (event) => {
    event.waitUntil((async () => {
        const keys = await caches.keys();
        await Promise.all(keys
            .filter((k) => !k.startsWith(CACHE_VERSION))
            .map((k) => caches.delete(k)));
        await self.clients.claim();
        // Second-chance pre-cache: catches chunks that 404'd or were
        // unreachable during install (slow CDN, race with deploy, etc.).
        precacheEverything().catch(() => {});
    })());
});

self.addEventListener('message', (event) => {
    if (event.data === 'SKIP_WAITING')      self.skipWaiting();
    if (event.data === 'PING')              event.source?.postMessage({ type: 'PONG', at: Date.now() });
    if (event.data === 'PRECACHE_ASSETS')   precacheEverything().catch(() => {});
});

// ── FETCH ────────────────────────────────────────────────────────────────
self.addEventListener('fetch', (event) => {
    const req = event.request;
    if (req.method !== 'GET') return;

    const url = new URL(req.url);
    if (url.origin !== self.location.origin) return;

    if (url.pathname.startsWith('/build/')) {
        event.respondWith(buildAssetHandler(req));
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

    // Default: try cache, then network. Safe for icons, manifest, etc.
    event.respondWith(cacheFirst(req, SHELL_CACHE));
});

async function buildAssetHandler(req) {
    // Vite occasionally appends query strings (?import, ?t=…). Match
    // ignoring the query so a cached entry still serves the request.
    const cache = await caches.open(ASSETS_CACHE);
    let hit = await cache.match(req, { ignoreSearch: true });
    if (hit) return hit;

    try {
        const fresh = await fetch(req);
        if (fresh.ok) {
            // Cache under the canonical (query-less) URL so future requests
            // with or without query strings all land on the same entry.
            const url = new URL(req.url);
            url.search = '';
            cache.put(url.toString(), fresh.clone());
        }
        return fresh;
    } catch {
        // Last-ditch: check shell cache too (in case install dropped it there).
        const shell = await caches.open(SHELL_CACHE);
        hit = await shell.match(req, { ignoreSearch: true });
        if (hit) return hit;
        return Response.error();
    }
}

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
    } catch {
        clearTimeout(timer);
        return new Response(JSON.stringify({
            offline: true,
            data: null,
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
        if (fresh.ok) {
            const pages = await caches.open(PAGES_CACHE);
            pages.put(req, fresh.clone());
            const shell = await caches.open(SHELL_CACHE);
            shell.put('/', fresh.clone());      // keep root mirror up-to-date
        }
        return fresh;
    } catch {
        // Offline: try cached current URL → cached root → any cached navigation
        const pages = await caches.open(PAGES_CACHE);
        const exact = await pages.match(req);
        if (exact) return exact;

        const shell = await caches.open(SHELL_CACHE);
        const root  = await shell.match('/');
        if (root) return root;

        // Last-ditch fallback — return whatever HTML we have
        const keys = await pages.keys();
        for (const k of keys) {
            const cached = await pages.match(k);
            if (cached) return cached;
        }
        return Response.error();
    }
}


/* ──────────────────────────────────────────────────────────────────────
 * Phase AD — Web Push handlers.
 *
 * The backend ships a JSON payload shaped like:
 *   {
 *     id, code, category, severity, urgency, branch_id,
 *     title, body,
 *     actions: [{action, title}, ...],
 *     click:   { route, params },
 *     sent_at,
 *   }
 *
 * `severity` drives the badge tone; `actions` map to native notification
 * buttons; `click` and each `action` carry a route name we hand off to
 * the SPA via `?notif=<id>&route=<name>` when the user clicks.
 * ──────────────────────────────────────────────────────────────────── */

/*
 * Grouping rule (Phase AD R3):
 *   – tag = the notification's `code` (e.g. "inventory.low_stock")
 *   – a second push with the same code REPLACES the first natively
 *   – we count how many notifications share the tag and rewrite the
 *     title/body to read "3 Inventory alerts" instead of three pops
 *   – `renotify` only re-buzzes for critical severity; everything else
 *     updates silently so the phone doesn't vibrate for each refresh
 */
self.addEventListener('push', (event) => {
    if (!event.data) return;
    let data = {};
    try { data = event.data.json(); } catch { data = { title: 'POSmeister', body: event.data.text() }; }

    const tag = `posmeister-${data.code || data.id || Date.now()}`;

    event.waitUntil((async () => {
        // Peek at existing notifications under this tag so we can group.
        const existing = await self.registration.getNotifications({ tag });
        const groupSize = existing.length + 1;

        let title = data.title || 'POSmeister';
        let body  = data.body  || '';
        if (groupSize > 1) {
            const categoryLabel = categoryHeadline(data.category, groupSize);
            title = categoryLabel || title;
            body  = data.title ? `Latest: ${data.title}` : body;
        }

        return self.registration.showNotification(title, {
            body,
            icon:    '/icons/icon-192.png',
            badge:   '/icons/icon-72.png',
            tag,
            renotify: data.severity === 'critical',
            requireInteraction: data.severity === 'critical',
            silent: data.severity !== 'critical' && groupSize > 1,
            timestamp: data.sent_at ? Date.parse(data.sent_at) : Date.now(),
            data: { ...data, _group: groupSize },
            actions: Array.isArray(data.actions) ? data.actions.slice(0, 2) : [],
        });
    })());
});

function categoryHeadline(category, count) {
    const map = {
        inventory:  'Inventory alerts',
        sales:      'Sales alerts',
        purchase:   'Purchase alerts',
        customer:   'Customer alerts',
        supplier:   'Supplier alerts',
        finance:    'Finance alerts',
        accounting: 'Accounting alerts',
        hrm:        'HR alerts',
        system:     'System alerts',
    };
    const label = map[category] || 'POSmeister alerts';
    return `${count} ${label}`;
}

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const data   = event.notification.data || {};
    const action = event.action || '';
    const dismissed = false;

    // Route resolution: action button > primary click target > inbox.
    let target = '/notifications';
    if (action) {
        target = `/?_route=${encodeURIComponent(action)}&_notif=${data.id || ''}`;
    } else if (data.click && data.click.route) {
        target = `/?_route=${encodeURIComponent(data.click.route)}&_notif=${data.id || ''}`;
    } else {
        target = `/notifications?_notif=${data.id || ''}`;
    }

    event.waitUntil((async () => {
        // Beacon the click — fire-and-forget so it never blocks navigation.
        try {
            const sub = await self.registration.pushManager.getSubscription();
            const payload = JSON.stringify({
                notification_id: data.id || null,
                code:            data.code || null,
                action:          action || null,
                endpoint:        sub?.endpoint || null,
                dismissed,
            });
            // navigator.sendBeacon isn't available in SW; use fetch + keepalive.
            fetch('/api/push/click', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    payload,
                keepalive: true,
                credentials: 'include',
            }).catch(() => { /* tolerable */ });
        } catch (e) { /* don't block on telemetry */ }

        // Focus an existing window if one is already showing POSmeister.
        const all = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
        for (const c of all) {
            try {
                await c.navigate(target);
                return c.focus();
            } catch { /* navigate not allowed, fall through */ }
        }
        return self.clients.openWindow(target);
    })());
});

self.addEventListener('notificationclose', (event) => {
    // User swiped away without tapping — log as dismissed for CTR math.
    const data = event.notification.data || {};
    event.waitUntil((async () => {
        try {
            const sub = await self.registration.pushManager.getSubscription();
            fetch('/api/push/click', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    notification_id: data.id || null,
                    code:            data.code || null,
                    action:          null,
                    endpoint:        sub?.endpoint || null,
                    dismissed:       true,
                }),
                keepalive: true,
                credentials: 'include',
            }).catch(() => {});
        } catch (e) { /* no-op */ }
    })());
});

self.addEventListener('pushsubscriptionchange', (event) => {
    // Browser asked us to renew (key rotation, expiry). Re-subscribe with
    // the SAME applicationServerKey and POST the new endpoint up to the
    // backend so we don't lose the device silently.
    event.waitUntil((async () => {
        try {
            const old = event.oldSubscription;
            const newSub = await self.registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: old ? old.options.applicationServerKey : undefined,
            });
            await fetch('/api/push/resubscribe', {
                method: 'POST',
                credentials: 'include',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ subscription: newSub, oldEndpoint: old?.endpoint }),
            });
        } catch (e) { /* fall back to next launch */ }
    })());
});
