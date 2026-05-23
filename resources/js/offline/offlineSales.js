/*
 * Offline sales engine.
 *
 * Creates a sale entirely client-side when the network is down,
 * generates a temporary invoice number, persists everything to IndexedDB
 * and enqueues a signed sync payload. The syncEngine drains the queue
 * once connectivity is back. Idempotency keys travel from this layer all
 * the way through to the server's `idempotency_keys` table so a retried
 * batch never doubles a sale.
 *
 *   Temporary invoice format:   OFF-YYYY-NNNNNN
 *
 * After successful sync, the server returns the real sale_number; we
 * keep the temporary one in `offline_reference` for the audit trail.
 */
import { db, put, getAll, get, uuid } from './db';

const STORE = 'offline_sales';

export async function nextTempInvoiceNumber() {
    const all = await getAll(STORE);
    const year = new Date().getFullYear();
    const used = new Set(all.map((s) => s.tempInvoiceNumber || '').filter(Boolean));
    let n = 1;
    while (true) {
        const candidate = `OFF-${year}-${String(n).padStart(6, '0')}`;
        if (!used.has(candidate)) return candidate;
        n++;
    }
}

export async function createOfflineSale(payload) {
    const tempInvoice = await nextTempInvoiceNumber();
    const idempotencyKey = uuid();
    const row = {
        idempotencyKey,
        tempInvoiceNumber: tempInvoice,
        status: 'pending',          // pending → syncing → synced | failed
        attempts: 0,
        lastError: null,
        createdAt: new Date().toISOString(),
        payload: {
            ...payload,
            offline_reference: tempInvoice,
            idempotency_key:   idempotencyKey,
        },
    };
    await put(STORE, row);
    return row;
}

export async function listOfflineSales(status = null) {
    const all = await getAll(STORE);
    if (!status) return all.sort((a, b) => b.id - a.id);
    return all.filter((s) => s.status === status).sort((a, b) => b.id - a.id);
}

export async function pendingCount() {
    const all = await getAll(STORE);
    return all.filter((s) => s.status === 'pending' || s.status === 'failed').length;
}

export async function markSyncing(id) {
    const row = await get(STORE, id);
    if (!row) return;
    row.status = 'syncing';
    row.attempts = (row.attempts || 0) + 1;
    await put(STORE, row);
}

export async function markSynced(id, serverSale) {
    const row = await get(STORE, id);
    if (!row) return;
    row.status = 'synced';
    row.syncedAt = new Date().toISOString();
    row.serverId = serverSale?.id || null;
    row.serverNumber = serverSale?.sale_number || null;
    await put(STORE, row);
}

export async function markFailed(id, error) {
    const row = await get(STORE, id);
    if (!row) return;
    row.status = 'failed';
    row.lastError = error?.message || String(error || 'sync failed');
    await put(STORE, row);
}
