/*
 * Reactive offline state shared across the app.
 * Subscribes to:
 *   - navigator online/offline events
 *   - posmeister:sync:state custom events from the sync engine
 *
 * Exposes:
 *   online           boolean
 *   pending          number of sales waiting to sync
 *   syncing          number currently in flight
 *   lastSyncAt       ISO timestamp of last attempted batch
 *   forceSync()      manual retry trigger
 */
import { defineStore } from 'pinia';
import { ref, computed, onMounted } from 'vue';
import { syncNow } from '@/offline/syncEngine';
import { pendingCount as offlinePending } from '@/offline/offlineSales';

export const useOfflineStore = defineStore('offline', () => {
    const online      = ref(typeof navigator === 'undefined' || navigator.onLine !== false);
    const pending     = ref(0);
    const syncing     = ref(false);
    const lastSyncAt  = ref(null);
    const lastError   = ref(null);

    const showBanner  = computed(() => !online.value || pending.value > 0);

    async function refreshPending() {
        try { pending.value = await offlinePending(); } catch { /* IDB unavailable */ }
    }

    function forceSync() { syncNow().catch(() => {}); }

    function _onOnline()  { online.value = true;  forceSync(); }
    function _onOffline() { online.value = false; }

    function _onSyncEvent(e) {
        const d = e?.detail || {};
        if (typeof d.pending === 'number')  pending.value = d.pending;
        if (typeof d.running === 'boolean') syncing.value = d.running;
        if (d.offline)                      online.value  = false;
        if (!d.running)                     lastSyncAt.value = new Date().toISOString();
    }

    function init() {
        if (typeof window === 'undefined') return;
        refreshPending();
        window.addEventListener('online',  _onOnline);
        window.addEventListener('offline', _onOffline);
        window.addEventListener('posmeister:sync:state', _onSyncEvent);
    }

    return { online, pending, syncing, lastSyncAt, lastError, showBanner, init, forceSync, refreshPending };
});
