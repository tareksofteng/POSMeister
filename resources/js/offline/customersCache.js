import { db, bulkPut, clearStore, get, indexGet, count } from './db';

const STORE = 'customers';

export async function replaceCustomers(customers) {
    if (!Array.isArray(customers)) return 0;
    const rows = customers.map((c) => ({
        id:      c.id,
        name:    c.name,
        name_lc: (c.name || '').toLowerCase(),
        phone:   c.phone || null,
        email:   c.email || null,
        code:    c.code  || null,
        address: c.address || null,
    }));
    await clearStore(STORE);
    return bulkPut(STORE, rows);
}

export async function customerById(id) {
    return get(STORE, id);
}

export async function customerByPhone(phone) {
    if (!phone) return null;
    return indexGet(STORE, 'phone', phone).catch(() => null);
}

export async function searchCustomers(query, limit = 25) {
    if (!query || query.length < 1) return [];
    const q = query.toLowerCase().trim();

    const idb = await db();
    return new Promise((resolve, reject) => {
        const out = [];
        const tx = idb.transaction(STORE, 'readonly');
        const cur = tx.objectStore(STORE).openCursor();
        cur.onsuccess = (e) => {
            const c = e.target.result;
            if (!c) return;
            const v = c.value;
            if (
                (v.name_lc && v.name_lc.includes(q)) ||
                (v.phone   && v.phone.includes(query)) ||
                (v.code    && v.code.toLowerCase().includes(q))
            ) {
                out.push(v);
                if (out.length >= limit) return;
            }
            c.continue();
        };
        tx.oncomplete = () => resolve(out);
        tx.onerror    = () => reject(tx.error);
    });
}

export async function customersCount() {
    return count(STORE);
}
