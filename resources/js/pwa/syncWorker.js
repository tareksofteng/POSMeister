/*
 * Background-ish sync worker. Walks the IndexedDB queue and POSTs each
 * pending request to its target URL with the same Idempotency-Key, so
 * a half-failed retry never creates a second sale on the server.
 *
 * Triggers:
 *   - window 'online' event
 *   - manual call to syncNow() from the offline indicator
 *   - app boot (App.vue mounts it once)
 *   - 60s interval while the tab is open
 *
 * Emits 'posmeister:sync:state' events with { running, pending, succeeded, failed }.
 */
import { listQueue, removeQueueItem, updateQueueItem } from './offlineQueue';

const MAX_ATTEMPTS = 5;
let running = false;

function emit(state) {
    window.dispatchEvent(new CustomEvent('posmeister:sync:state', { detail: state }));
}

async function postOne(item) {
    const token = localStorage.getItem('posmeister_auth_token');
    const headers = {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'Idempotency-Key': item.idempotencyKey,
    };
    if (token) headers.Authorization = `Bearer ${token}`;

    const res = await fetch(item.url, {
        method: item.method || 'POST',
        headers,
        body: typeof item.payload === 'string' ? item.payload : JSON.stringify(item.payload),
        credentials: 'include',
    });

    if (res.status >= 200 && res.status < 300) return { ok: true };
    if (res.status === 409 || res.status === 422) {
        // Conflict or validation error — server rejected the payload itself,
        // retrying will not help. Drop it but log.
        let body = null;
        try { body = await res.json(); } catch {}
        return { ok: false, drop: true, body, status: res.status };
    }
    return { ok: false, drop: false, status: res.status };
}

export async function syncNow() {
    if (running) return;
    if (!navigator.onLine) {
        emit({ running: false, pending: -1, offline: true });
        return;
    }

    running = true;
    let succeeded = 0;
    let failed = 0;
    try {
        const queue = await listQueue();
        emit({ running: true, pending: queue.length, succeeded, failed });

        for (const item of queue) {
            try {
                const result = await postOne(item);
                if (result.ok) {
                    await removeQueueItem(item.id);
                    succeeded++;
                } else if (result.drop) {
                    await removeQueueItem(item.id);
                    failed++;
                } else {
                    const attempts = (item.attempts || 0) + 1;
                    if (attempts >= MAX_ATTEMPTS) {
                        await updateQueueItem(item.id, { attempts, lastError: `HTTP ${result.status} after ${attempts} tries` });
                        failed++;
                    } else {
                        await updateQueueItem(item.id, { attempts, lastError: `HTTP ${result.status}` });
                    }
                }
            } catch (err) {
                const attempts = (item.attempts || 0) + 1;
                await updateQueueItem(item.id, { attempts, lastError: err?.message || 'network error' });
                if (attempts >= MAX_ATTEMPTS) failed++;
            }
            emit({ running: true, pending: queue.length - succeeded - failed, succeeded, failed });
        }
    } finally {
        running = false;
        emit({ running: false, pending: 0, succeeded, failed });
    }
}

let timerId = null;

export function startSyncWorker() {
    if (typeof window === 'undefined') return;
    window.addEventListener('online', () => syncNow());
    window.addEventListener('focus', () => syncNow());
    if (!timerId) timerId = setInterval(() => syncNow(), 60_000);
    // Kick once at boot in case items are already pending.
    setTimeout(() => syncNow(), 1500);
}
