/*
 * Snapshot preloader.
 *
 * After login, fetch the working dataset (products, customers, branches,
 * settings, tax rules) into IndexedDB so the cashier can keep operating
 * during an outage. Re-runs every SNAPSHOT_REFRESH_MS while the tab is
 * open and on every fresh login.
 *
 * Endpoint used: GET /api/system/snapshot  (single round-trip, bulk).
 */
import api from '@/services/api';
import { replaceProducts }  from './productsCache';
import { replaceCustomers } from './customersCache';
import { saveSettings, saveBranches, saveTaxRules } from './settingsCache';
import { put } from './db';

const SNAPSHOT_REFRESH_MS = 15 * 60_000;   // 15 min while logged in
let timerId = null;
let inflight = null;

export async function downloadSnapshot() {
    if (inflight) return inflight;
    if (typeof navigator !== 'undefined' && navigator.onLine === false) return null;

    inflight = (async () => {
        try {
            const { data } = await api.get('/system/snapshot');
            const snap = data?.data ?? data;
            if (!snap) return null;

            const counts = {};
            if (Array.isArray(snap.products))  counts.products  = await replaceProducts(snap.products);
            if (Array.isArray(snap.customers)) counts.customers = await replaceCustomers(snap.customers);
            if (Array.isArray(snap.branches))  await saveBranches(snap.branches);
            if (Array.isArray(snap.tax_rules)) await saveTaxRules(snap.tax_rules);
            if (snap.settings)                 await saveSettings(snap.settings);

            await put('meta', { k: 'snapshot.last_at', v: new Date().toISOString() });
            window.dispatchEvent(new CustomEvent('posmeister:snapshot:ready', { detail: counts }));
            return counts;
        } catch (err) {
            // Offline or 5xx — leave the previous snapshot in place
            return null;
        } finally {
            inflight = null;
        }
    })();

    return inflight;
}

export function startSnapshotLoop() {
    if (typeof window === 'undefined') return;
    if (timerId) return;

    // Kick once at boot, then periodically
    setTimeout(downloadSnapshot, 2000);
    timerId = setInterval(downloadSnapshot, SNAPSHOT_REFRESH_MS);

    window.addEventListener('online', downloadSnapshot);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) downloadSnapshot(); });
}

export function stopSnapshotLoop() {
    if (timerId) { clearInterval(timerId); timerId = null; }
}
