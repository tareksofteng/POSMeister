/*
 * POSmeister sync engine
 * ----------------------
 * Drains the offline_sales store to the server in batches. Each batch
 * goes to POST /api/system/sync/sales and the server is responsible for
 * deduping via idempotency keys. The engine itself is intentionally
 * dumb: it tries, marks the rows it succeeded on, backs off, retries.
 *
 * Public API:
 *   startSyncEngine()   wire up event listeners + 60s interval
 *   syncNow()           run a sync attempt right now
 *   onState(cb)         subscribe to status updates
 *
 * Events emitted on window:
 *   posmeister:sync:state  { running, pending, succeeded, failed, offline }
 */
import { listOfflineSales, markSyncing, markSynced, markFailed, pendingCount } from './offlineSales';

const BATCH_SIZE = 25;
const RETRY_BASE_MS = 5_000;        // 5s
const RETRY_MAX_MS  = 5 * 60_000;   // 5min cap
const INTERVAL_MS   = 60_000;       // 60s heartbeat

let running = false;
let timerId = null;
let backoffMs = RETRY_BASE_MS;

function emit(state) {
    window.dispatchEvent(new CustomEvent('posmeister:sync:state', { detail: state }));
}

function authHeaders() {
    const token = localStorage.getItem('pos_token');
    const headers = { 'Content-Type': 'application/json', 'Accept': 'application/json' };
    if (token) headers.Authorization = `Bearer ${token}`;
    return headers;
}

async function postBatch(batch) {
    const payload = {
        device_id: localStorage.getItem('posmeister:device_id') || 'unknown',
        sales: batch.map((row) => ({
            idempotency_key:   row.idempotencyKey,
            offline_reference: row.tempInvoiceNumber,
            created_at:        row.createdAt,
            data:              row.payload,
        })),
    };

    const res = await fetch('/api/system/sync/sales', {
        method: 'POST',
        headers: authHeaders(),
        credentials: 'include',
        body: JSON.stringify(payload),
    });

    if (!res.ok) {
        const body = await res.text().catch(() => '');
        const err  = new Error(`HTTP ${res.status} — ${body.slice(0, 200)}`);
        err.status = res.status;
        err.retryable = res.status >= 500 || res.status === 0;
        throw err;
    }
    return res.json();
}

export async function syncNow() {
    if (running) return;
    if (typeof navigator !== 'undefined' && navigator.onLine === false) {
        const pend = await pendingCount();
        emit({ running: false, pending: pend, offline: true });
        return;
    }

    running = true;
    let succeeded = 0;
    let failed = 0;
    try {
        const all = await listOfflineSales();
        const drainable = all.filter((s) => s.status === 'pending' || s.status === 'failed');
        emit({ running: true, pending: drainable.length, succeeded, failed });

        for (let i = 0; i < drainable.length; i += BATCH_SIZE) {
            const batch = drainable.slice(i, i + BATCH_SIZE);
            try {
                await Promise.all(batch.map((row) => markSyncing(row.id)));
                const result = await postBatch(batch);

                // result.results = [{ idempotency_key, status, sale: { id, sale_number } }]
                const byKey = new Map();
                (result.results || []).forEach((r) => byKey.set(r.idempotency_key, r));

                for (const row of batch) {
                    const r = byKey.get(row.idempotencyKey);
                    if (r && (r.status === 'ok' || r.status === 'duplicate')) {
                        await markSynced(row.id, r.sale || null);
                        succeeded++;
                    } else {
                        await markFailed(row.id, new Error(r?.error || 'sync rejected'));
                        failed++;
                    }
                }
                backoffMs = RETRY_BASE_MS;
            } catch (err) {
                for (const row of batch) {
                    await markFailed(row.id, err);
                }
                failed += batch.length;
                if (err.retryable !== false) {
                    backoffMs = Math.min(backoffMs * 2, RETRY_MAX_MS);
                }
            }
            emit({ running: true, pending: drainable.length - succeeded - failed, succeeded, failed });
        }
    } finally {
        running = false;
        const pend = await pendingCount();
        emit({ running: false, pending: pend, succeeded, failed });
    }
}

export function startSyncEngine() {
    if (typeof window === 'undefined') return;
    if (!localStorage.getItem('posmeister:device_id')) {
        localStorage.setItem('posmeister:device_id', cryptoRandomId());
    }

    const tick = () => syncNow().catch(() => {});

    window.addEventListener('online', tick);
    window.addEventListener('focus',  tick);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) tick(); });

    if (!timerId) timerId = setInterval(tick, INTERVAL_MS);
    setTimeout(tick, 1500);  // kick once after boot
}

function cryptoRandomId() {
    if (typeof crypto !== 'undefined' && crypto.randomUUID) return crypto.randomUUID();
    return Math.random().toString(36).slice(2) + Date.now().toString(36);
}
