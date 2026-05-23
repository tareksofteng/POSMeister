/*
 * Local product catalogue cache.
 *
 * Populated by the snapshotPreloader after login and on demand. Search
 * is intentionally simple — a tokenised lowercase scan — because most
 * shops have <5k products and IndexedDB cursor iteration is fast enough
 * for a per-keystroke search. For larger catalogues, swap the search
 * loop for a prebuilt inverted index without changing the public API.
 */
import { db, bulkPut, getAll, count, clearStore, indexGet, get } from './db';

const STORE = 'products';

export async function replaceProducts(products) {
    if (!Array.isArray(products)) return 0;
    const rows = products.map((p) => ({
        id:        p.id,
        sku:       p.sku || null,
        barcode:   p.barcode || null,
        name:      p.name,
        name_lc:   (p.name || '').toLowerCase(),
        unit:      p.unit_name || p.unit_symbol || null,
        unit_id:   p.unit_id || null,
        selling_price: Number(p.selling_price ?? 0),
        cost_price:    Number(p.cost_price    ?? 0),
        tax_rate:      Number(p.tax_rate      ?? 0),
        stock:         Number(p.stock         ?? 0),
        image:         p.image || null,
        category_id:   p.category_id || null,
        brand_id:      p.brand_id    || null,
    }));
    await clearStore(STORE);
    return bulkPut(STORE, rows);
}

export async function productById(id) {
    return get(STORE, id);
}

export async function productByBarcode(barcode) {
    if (!barcode) return null;
    return indexGet(STORE, 'barcode', barcode).catch(() => null);
}

export async function productBySku(sku) {
    if (!sku) return null;
    return indexGet(STORE, 'sku', sku).catch(() => null);
}

export async function searchProducts(query, limit = 25) {
    if (!query || query.length < 1) return [];
    const q = query.toLowerCase().trim();

    // Exact barcode / sku first — instant index hit
    const bc = await productByBarcode(query);
    if (bc) return [bc];
    const sk = await productBySku(query);
    if (sk) return [sk];

    // Fuzzy: cursor scan, score by where the token appears
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
                (v.sku     && v.sku.toLowerCase().includes(q)) ||
                (v.barcode && v.barcode.includes(q))
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

export async function productsCount() {
    return count(STORE);
}

export async function reserveStock(productId, qty) {
    // Soft reservation — we just decrement the local snapshot stock so the
    // cashier sees realistic numbers across multiple offline sales. The
    // authoritative inventory move happens server-side at sync time.
    const p = await get(STORE, productId);
    if (!p) return false;
    p.stock = Math.max(Number(p.stock || 0) - Number(qty || 0), 0);
    const { put: putRow } = await import('./db');
    await putRow(STORE, p);
    return true;
}
