/*
 * POSmeister offline database
 * ---------------------------
 * Single versioned IndexedDB instance shared by every offline module.
 * Stores are deliberately small and purpose-built so migrations stay
 * trivial. Every store keeps its own key strategy:
 *
 *   auth_session      keyPath: 'k'     (singleton row, k = 'current')
 *   settings          keyPath: 'k'     (k = 'app' | 'branches' | 'tax')
 *   products          keyPath: 'id'    +index: barcode, sku, name_lc
 *   customers         keyPath: 'id'    +index: phone, name_lc
 *   offline_sales     autoincrement   +index: status, idempotencyKey unique
 *   sync_queue        autoincrement   +index: createdAt, idempotencyKey unique
 *   meta              keyPath: 'k'     misc metadata (last-snapshot-at, device-id)
 *
 * Versioned via DB_VERSION. Bumping creates indexes/stores on next open.
 */

const DB_NAME    = 'posmeister-offline';
const DB_VERSION = 2;

let dbPromise = null;

export function db() {
    if (dbPromise) return dbPromise;
    dbPromise = new Promise((resolve, reject) => {
        const req = indexedDB.open(DB_NAME, DB_VERSION);

        req.onupgradeneeded = (event) => {
            const idb = req.result;
            const oldVersion = event.oldVersion;

            // v1 → v2 (Phase Ω): full offline stores
            if (oldVersion < 2) {
                ensureStore(idb, 'auth_session',  { keyPath: 'k' });
                ensureStore(idb, 'settings',      { keyPath: 'k' });
                ensureStore(idb, 'meta',          { keyPath: 'k' });

                ensureStore(idb, 'products',  { keyPath: 'id' }, (store) => {
                    if (!store.indexNames.contains('barcode')) store.createIndex('barcode', 'barcode', { unique: false });
                    if (!store.indexNames.contains('sku'))     store.createIndex('sku',     'sku',     { unique: false });
                    if (!store.indexNames.contains('name_lc')) store.createIndex('name_lc', 'name_lc', { unique: false });
                });
                ensureStore(idb, 'customers', { keyPath: 'id' }, (store) => {
                    if (!store.indexNames.contains('phone'))   store.createIndex('phone',   'phone',   { unique: false });
                    if (!store.indexNames.contains('name_lc')) store.createIndex('name_lc', 'name_lc', { unique: false });
                });

                ensureStore(idb, 'offline_sales', { keyPath: 'id', autoIncrement: true }, (store) => {
                    if (!store.indexNames.contains('status'))         store.createIndex('status',         'status',         { unique: false });
                    if (!store.indexNames.contains('idempotencyKey')) store.createIndex('idempotencyKey', 'idempotencyKey', { unique: true });
                    if (!store.indexNames.contains('createdAt'))      store.createIndex('createdAt',      'createdAt',      { unique: false });
                });

                // v1 already had sync_queue with idempotencyKey unique; keep it.
                if (!idb.objectStoreNames.contains('sync_queue')) {
                    const os = idb.createObjectStore('sync_queue', { keyPath: 'id', autoIncrement: true });
                    os.createIndex('createdAt',      'createdAt',      { unique: false });
                    os.createIndex('idempotencyKey', 'idempotencyKey', { unique: true });
                }
            }
        };

        req.onsuccess = () => resolve(req.result);
        req.onerror   = () => reject(req.error);
        req.onblocked = () => reject(new Error('IndexedDB upgrade blocked — close other tabs'));
    });
    return dbPromise;
}

function ensureStore(idb, name, options, mutate) {
    if (idb.objectStoreNames.contains(name)) return;
    const store = idb.createObjectStore(name, options);
    if (mutate) mutate(store);
}

// ── Tiny promise helpers around IDBRequest ─────────────────────────────────

export async function tx(stores, mode = 'readonly') {
    const idb = await db();
    const t = idb.transaction(stores, mode);
    const objStores = {};
    (Array.isArray(stores) ? stores : [stores]).forEach((s) => { objStores[s] = t.objectStore(s); });
    return { t, objStores };
}

export function req(request) {
    return new Promise((resolve, reject) => {
        request.onsuccess = () => resolve(request.result);
        request.onerror   = () => reject(request.error);
    });
}

export async function getAll(storeName) {
    const { objStores } = await tx(storeName);
    return req(objStores[storeName].getAll());
}

export async function get(storeName, key) {
    const { objStores } = await tx(storeName);
    return req(objStores[storeName].get(key));
}

export async function put(storeName, value) {
    const { t, objStores } = await tx(storeName, 'readwrite');
    objStores[storeName].put(value);
    return new Promise((resolve, reject) => { t.oncomplete = () => resolve(true); t.onerror = () => reject(t.error); });
}

export async function bulkPut(storeName, values) {
    if (!values?.length) return 0;
    const { t, objStores } = await tx(storeName, 'readwrite');
    const os = objStores[storeName];
    values.forEach((v) => os.put(v));
    return new Promise((resolve, reject) => {
        t.oncomplete = () => resolve(values.length);
        t.onerror    = () => reject(t.error);
    });
}

export async function del(storeName, key) {
    const { t, objStores } = await tx(storeName, 'readwrite');
    objStores[storeName].delete(key);
    return new Promise((resolve, reject) => { t.oncomplete = () => resolve(true); t.onerror = () => reject(t.error); });
}

export async function clearStore(storeName) {
    const { t, objStores } = await tx(storeName, 'readwrite');
    objStores[storeName].clear();
    return new Promise((resolve, reject) => { t.oncomplete = () => resolve(true); t.onerror = () => reject(t.error); });
}

export async function count(storeName) {
    const { objStores } = await tx(storeName);
    return req(objStores[storeName].count());
}

export async function indexGet(storeName, indexName, value) {
    const { objStores } = await tx(storeName);
    const idx = objStores[storeName].index(indexName);
    return req(idx.get(value));
}

export function uuid() {
    if (typeof crypto !== 'undefined' && crypto.randomUUID) return crypto.randomUUID();
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
        const r = (Math.random() * 16) | 0;
        const v = c === 'x' ? r : (r & 0x3) | 0x8;
        return v.toString(16);
    });
}
