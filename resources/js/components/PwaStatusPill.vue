<template>
    <div class="flex items-center gap-1">
        <!-- Install button -->
        <button
            v-if="installable"
            @click="install"
            class="hidden md:inline-flex items-center gap-1.5 px-2.5 py-1.5 text-xs font-semibold text-indigo-700 dark:text-indigo-300 bg-indigo-50 dark:bg-indigo-900/40 hover:bg-indigo-100 dark:hover:bg-indigo-900/60 border border-indigo-200 dark:border-indigo-800 rounded-lg transition-colors"
            :title="t('pwa.install')"
        >
            <ArrowDownTrayIcon class="w-4 h-4" />
            <span>{{ t('pwa.install') }}</span>
        </button>

        <!-- Update available -->
        <button
            v-if="updateReady"
            @click="applyUpdate"
            class="hidden md:inline-flex items-center gap-1.5 px-2.5 py-1.5 text-xs font-semibold text-amber-700 dark:text-amber-300 bg-amber-50 dark:bg-amber-900/40 hover:bg-amber-100 border border-amber-200 dark:border-amber-800 rounded-lg transition-colors"
            :title="t('pwa.updateAvailable')"
        >
            <ArrowPathIcon class="w-4 h-4" />
            <span>{{ t('pwa.update') }}</span>
        </button>

        <!-- Connection status -->
        <button
            :class="[
                'inline-flex items-center gap-1.5 px-2.5 py-1.5 text-xs font-semibold rounded-lg transition-colors',
                online
                    ? 'text-emerald-700 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-900/30 border border-emerald-200 dark:border-emerald-800'
                    : 'text-rose-700 dark:text-rose-300 bg-rose-50 dark:bg-rose-900/30 border border-rose-200 dark:border-rose-800'
            ]"
            :title="online ? t('pwa.online') : t('pwa.offline')"
            @click="forceSync"
        >
            <span :class="['w-2 h-2 rounded-full', online ? 'bg-emerald-500' : 'bg-rose-500 animate-pulse']" />
            <span class="hidden sm:inline">{{ online ? t('pwa.online') : t('pwa.offline') }}</span>
            <span v-if="pendingCount > 0" class="ml-0.5 px-1.5 rounded-full text-[10px] bg-white/70 dark:bg-slate-900/40">
                {{ pendingCount }}
            </span>
        </button>
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ArrowDownTrayIcon, ArrowPathIcon } from '@heroicons/vue/24/outline';
import { countQueue } from '@/pwa/offlineQueue';
import { syncNow } from '@/pwa/syncWorker';
import { applyUpdateNow } from '@/pwa/register';

const { t } = useI18n();

const online      = ref(typeof navigator !== 'undefined' ? navigator.onLine : true);
const installable = ref(false);
const updateReady = ref(false);
const pendingCount = ref(0);

let installEvent = null;
let pollId = null;

function setOnline() { online.value = true; refreshPending(); }
function setOffline() { online.value = false; refreshPending(); }
function onInstallable(e) { installEvent = e.detail?.event; installable.value = true; }
function onInstalled() { installEvent = null; installable.value = false; }
function onUpdateReady() { updateReady.value = true; }
function onSyncState(e) { pendingCount.value = e.detail?.pending ?? 0; }

async function refreshPending() {
    try { pendingCount.value = await countQueue(); } catch { /* IndexedDB unavailable */ }
}

async function install() {
    if (!installEvent) return;
    installEvent.prompt();
    const choice = await installEvent.userChoice.catch(() => ({ outcome: 'dismissed' }));
    if (choice.outcome === 'accepted') installable.value = false;
}

function applyUpdate() { applyUpdateNow(); }
function forceSync() { syncNow(); }

onMounted(() => {
    window.addEventListener('online', setOnline);
    window.addEventListener('offline', setOffline);
    window.addEventListener('posmeister:pwa:installable', onInstallable);
    window.addEventListener('posmeister:pwa:installed', onInstalled);
    window.addEventListener('posmeister:pwa:update-ready', onUpdateReady);
    window.addEventListener('posmeister:sync:state', onSyncState);
    if (window.__posmeisterInstallEvent) { installEvent = window.__posmeisterInstallEvent; installable.value = true; }
    refreshPending();
    pollId = setInterval(refreshPending, 5000);
});

onUnmounted(() => {
    window.removeEventListener('online', setOnline);
    window.removeEventListener('offline', setOffline);
    window.removeEventListener('posmeister:pwa:installable', onInstallable);
    window.removeEventListener('posmeister:pwa:installed', onInstalled);
    window.removeEventListener('posmeister:pwa:update-ready', onUpdateReady);
    window.removeEventListener('posmeister:sync:state', onSyncState);
    if (pollId) clearInterval(pollId);
});
</script>
