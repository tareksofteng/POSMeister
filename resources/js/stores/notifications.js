/*
 * Smart Notification store.
 *
 * Polls /api/notifications every 60s, exposes unread count for the
 * Topbar badge, and bridges actions (mark-read / ack / archive) so
 * the dropdown and the full-page view share state.
 */
import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import { notificationService } from '@/services/notificationService';

const POLL_MS = 60_000;

export const useNotificationsStore = defineStore('notifications', () => {
    const items   = ref([]);
    const unread  = ref(0);
    const loading = ref(false);
    let timer = null;

    const top5 = computed(() => items.value.slice(0, 5));

    async function fetch() {
        loading.value = true;
        try {
            const { data } = await notificationService.list({});
            items.value  = data.data || [];
            unread.value = data.unread ?? 0;
        } catch { /* offline-friendly */ }
        finally { loading.value = false; }
    }

    async function markRead(id) {
        await notificationService.markRead(id).catch(() => {});
        await fetch();
    }
    async function ack(id) {
        await notificationService.ack(id).catch(() => {});
        await fetch();
    }
    async function archive(id) {
        await notificationService.archive(id).catch(() => {});
        await fetch();
    }
    async function markAllRead() {
        await notificationService.markAllRead().catch(() => {});
        await fetch();
    }
    async function clearRead() {
        await notificationService.clearRead().catch(() => {});
        await fetch();
    }
    async function clearAll() {
        await notificationService.clearAll().catch(() => {});
        await fetch();
    }

    function startPolling() {
        if (timer) return;
        fetch();
        timer = setInterval(fetch, POLL_MS);
        window.addEventListener('focus', fetch);
    }
    function stopPolling() {
        if (timer) { clearInterval(timer); timer = null; }
        window.removeEventListener('focus', fetch);
    }

    return { items, unread, loading, top5, fetch, markRead, ack, archive, markAllRead, clearRead, clearAll, startPolling, stopPolling };
});
