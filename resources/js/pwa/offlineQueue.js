/*
 * IndexedDB-backed queue for POS sales (and any other write) that need
 * to survive a network interruption. The shape is intentionally generic:
 * each queued item carries an opaque payload plus a target URL, so this
 * file does not have to know about sale schemas.
 *
 * Schema:
 *   db: "posmeister-offline" v1
 *   store: "sync_queue" { id auto, url, method, payload, idempotencyKey,
 *                         createdAt, attempts, lastError }
 *
 * Replay protection is done on the server (idempotency_keys table) —
 * this file just generates a stable key and keeps it for the lifetime
 * of the queued row, so retries reuse the same key.
 */

const DB_NAME = 'posmeister-offline';
const DB_VERSION = 1;
const STORE = 'sync_queue';

function openDb() {
    return new Promise((resolve, reject) => {
        const req = indexedDB.open(DB_NAME, DB_VERSION);
        req.onupgradeneeded = () => {
            const db = req.result;
            if (!db.objectStoreNames.contains(STORE)) {
                const os = db.createObjectStore(STORE, { keyPath: 'id', autoIncrement: true });
                os.createIndex('createdAt', 'createdAt', { unique: false });
                os.createIndex('idempotencyKey', 'idempotencyKey', { unique: true });
            }
        };
        req.onsuccess = () => resolve(req.result);
        req.onerror = () => reject(req.error);
    });
}

export function makeIdempotencyKey(prefix = 'pos') {
    const rand = (typeof crypto !== 'undefined' && crypto.randomUUID)
        ? crypto.randomUUID()
        : Math.random().toString(36).slice(2) + Date.now().toString(36);
    return `${prefix}-${rand}`;
}

export async function enqueue({ url, method = 'POST', payload, idempotencyKey }) {
    const db = await openDb();
    const key = idempotencyKey || makeIdempotencyKey();
    return new Promise((resolve, reject) => {
        const tx = db.transaction(STORE, 'readwrite');
        const os = tx.objectStore(STORE);
        const row = {
            url,
            method,
            payload,
            idempotencyKey: key,
            createdAt: new Date().toISOString(),
            attempts: 0,
            lastError: null,
        };
        const r = os.add(row);
        r.onsuccess = () => resolve({ id: r.result, idempotencyKey: key });
        r.onerror = () => reject(r.error);
    });
}

export async function listQueue() {
    const db = await openDb();
    return new Promise((resolve, reject) => {
        const tx = db.transaction(STORE, 'readonly');
        const os = tx.objectStore(STORE);
        const r = os.getAll();
        r.onsuccess = () => resolve(r.result || []);
        r.onerror = () => reject(r.error);
    });
}

export async function countQueue() {
    const db = await openDb();
    return new Promise((resolve, reject) => {
        const tx = db.transaction(STORE, 'readonly');
        const r = tx.objectStore(STORE).count();
        r.onsuccess = () => resolve(r.result || 0);
        r.onerror = () => reject(r.error);
    });
}

export async function removeQueueItem(id) {
    const db = await openDb();
    return new Promise((resolve, reject) => {
        const tx = db.transaction(STORE, 'readwrite');
        tx.objectStore(STORE).delete(id);
        tx.oncomplete = () => resolve(true);
        tx.onerror = () => reject(tx.error);
    });
}

export async function updateQueueItem(id, patch) {
    const db = await openDb();
    return new Promise((resolve, reject) => {
        const tx = db.transaction(STORE, 'readwrite');
        const os = tx.objectStore(STORE);
        const g = os.get(id);
        g.onsuccess = () => {
            const row = g.result;
            if (!row) return resolve(false);
            Object.assign(row, patch);
            os.put(row);
        };
        tx.oncomplete = () => resolve(true);
        tx.onerror = () => reject(tx.error);
    });
}
