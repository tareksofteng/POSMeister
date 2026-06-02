/*
 * Legacy "sync_queue" wrapper. Re-uses the shared IndexedDB instance
 * (offline/db.js, currently v2) so we don't open the same database
 * under a conflicting version number — that used to throw a
 * VersionError on app boot once Phase Ω rolled out.
 *
 * The store schema itself is unchanged (id auto, idempotencyKey unique,
 * createdAt index), so existing queued rows keep working.
 */
import { tx, getAll, count, del, get, put } from '@/offline/db';

const STORE = 'sync_queue';

export function makeIdempotencyKey(prefix = 'pos') {
    const rand = (typeof crypto !== 'undefined' && crypto.randomUUID)
        ? crypto.randomUUID()
        : Math.random().toString(36).slice(2) + Date.now().toString(36);
    return `${prefix}-${rand}`;
}

export async function enqueue({ url, method = 'POST', payload, idempotencyKey }) {
    const key = idempotencyKey || makeIdempotencyKey();
    const row = {
        url,
        method,
        payload,
        idempotencyKey: key,
        createdAt: new Date().toISOString(),
        attempts: 0,
        lastError: null,
    };
    const { t, objStores } = await tx(STORE, 'readwrite');
    return new Promise((resolve, reject) => {
        const r = objStores[STORE].add(row);
        r.onsuccess = () => { row.id = r.result; };
        t.oncomplete = () => resolve({ id: row.id, idempotencyKey: key });
        t.onerror    = () => reject(t.error);
    });
}

export async function listQueue() {
    return getAll(STORE);
}

export async function countQueue() {
    return count(STORE);
}

export async function removeQueueItem(id) {
    return del(STORE, id);
}

export async function updateQueueItem(id, patch) {
    const row = await get(STORE, id);
    if (!row) return false;
    Object.assign(row, patch);
    await put(STORE, row);
    return true;
}
