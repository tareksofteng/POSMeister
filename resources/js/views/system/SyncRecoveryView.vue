<template>
    <div class="p-6 lg:p-8 space-y-5 max-w-6xl mx-auto">
        <header class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
            <div>
                <p class="text-xs font-medium text-indigo-600 uppercase tracking-wider mb-1">{{ t('systemOps.module') }}</p>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">{{ t('systemOps.sync.title') }}</h1>
                <p class="mt-1 text-sm text-slate-500">{{ t('systemOps.sync.subtitle') }}</p>
            </div>
            <div class="flex items-center gap-2">
                <button @click="forceSync" :disabled="syncing" class="btn-soft">
                    <ArrowsRightLeftIcon class="w-4 h-4" /> {{ t('systemOps.sync.retryNow') }}
                </button>
                <button @click="load" :disabled="loading" class="btn-soft">
                    <ArrowPathIcon :class="['w-4 h-4', loading && 'animate-spin']" /> {{ t('common.refresh') }}
                </button>
            </div>
        </header>

        <SkeletonLoader v-if="!d && loading" kind="card" :count="2" />

        <template v-if="d">
            <section class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <Card :label="t('systemOps.sync.localQueue')" :value="localQueueCount" tone="indigo" />
                <Card :label="t('systemOps.sync.serverKeys24h')" :value="d.summary.idempotency_keys_24h" tone="emerald" />
                <Card :label="t('systemOps.sync.offlineTotal')" :value="d.summary.offline_sales_total" tone="amber" />
                <Card :label="t('systemOps.sync.syncedToday')" :value="d.summary.offline_sales_today" tone="emerald" />
            </section>

            <section v-if="localQueueItems.length" class="card">
                <h3 class="card-title">{{ t('systemOps.sync.localQueueDetail') }}</h3>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-[10px] uppercase tracking-wider text-slate-500 border-b border-slate-200 dark:border-slate-800">
                            <th class="py-2">{{ t('systemOps.sync.idempotencyKey') }}</th>
                            <th>{{ t('systemOps.sync.url') }}</th>
                            <th>{{ t('systemOps.sync.attempts') }}</th>
                            <th>{{ t('systemOps.sync.createdAt') }}</th>
                            <th>{{ t('systemOps.sync.lastError') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in localQueueItems" :key="row.id" class="border-b border-slate-100 dark:border-slate-800 last:border-0">
                            <td class="py-2 text-[10px] font-mono">{{ row.idempotencyKey }}</td>
                            <td class="text-[11px] font-mono text-slate-500">{{ row.url }}</td>
                            <td class="text-xs text-center">{{ row.attempts }}</td>
                            <td class="text-xs text-slate-500">{{ formatDate(row.createdAt) }}</td>
                            <td class="text-xs text-rose-600">{{ row.lastError || '–' }}</td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <section class="card">
                <h3 class="card-title">{{ t('systemOps.sync.recentServerSide') }}</h3>
                <table v-if="d.recent.length" class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-[10px] uppercase tracking-wider text-slate-500 border-b border-slate-200 dark:border-slate-800">
                            <th class="py-2">{{ t('systemOps.sync.saleNumber') }}</th>
                            <th>{{ t('systemOps.sync.idempotencyKey') }}</th>
                            <th>{{ t('systemOps.sync.offlineReference') }}</th>
                            <th>{{ t('systemOps.sync.syncedAt') }}</th>
                            <th class="text-right">{{ t('systemOps.sync.amount') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="r in d.recent" :key="r.id" class="border-b border-slate-100 dark:border-slate-800 last:border-0">
                            <td class="py-2 text-xs font-semibold">{{ r.sale_number }}</td>
                            <td class="text-[10px] font-mono">{{ r.idempotency_key }}</td>
                            <td class="text-xs">{{ r.offline_reference || '–' }}</td>
                            <td class="text-xs text-slate-500">{{ formatDate(r.offline_synced_at || r.created_at) }}</td>
                            <td class="text-xs text-right font-semibold">{{ formatMoney(r.grand_total) }}</td>
                        </tr>
                    </tbody>
                </table>
                <p v-else class="text-sm text-slate-500">{{ t('systemOps.sync.empty') }}</p>
            </section>
        </template>
    </div>
</template>

<script setup>
import { ref, h, onMounted, onUnmounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { systemOpsService } from '@/services/systemOpsService';
import { listQueue, countQueue } from '@/pwa/offlineQueue';
import { syncNow } from '@/pwa/syncWorker';
import SkeletonLoader from '@/components/SkeletonLoader.vue';
import { ArrowPathIcon, ArrowsRightLeftIcon } from '@heroicons/vue/24/outline';

const { t, locale } = useI18n();
const d = ref(null);
const loading = ref(false);
const syncing = ref(false);
const localQueueCount = ref(0);
const localQueueItems = ref([]);

const toneClass = (tone) => ({
    indigo:  'border-indigo-200 bg-indigo-50/60 text-indigo-700',
    emerald: 'border-emerald-200 bg-emerald-50/60 text-emerald-700',
    amber:   'border-amber-200 bg-amber-50/60 text-amber-700',
}[tone] || 'border-slate-200');

const Card = (props) => h('div', { class: `rounded-xl border px-4 py-3 ${toneClass(props.tone)}` }, [
    h('p', { class: 'text-[10px] uppercase tracking-wider font-semibold opacity-70' }, props.label),
    h('p', { class: 'mt-1 text-2xl font-bold' }, String(props.value ?? '–')),
]);
Card.props = ['label', 'value', 'tone'];

function formatDate(iso) {
    if (!iso) return '–';
    return new Intl.DateTimeFormat(locale.value || 'de-DE', {
        day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit',
    }).format(new Date(iso));
}
function formatMoney(v) {
    return new Intl.NumberFormat(locale.value || 'de-DE', { style: 'currency', currency: 'EUR' }).format(v || 0);
}

async function refreshLocal() {
    try {
        localQueueCount.value = await countQueue();
        localQueueItems.value = await listQueue();
    } catch { /* IndexedDB unavailable */ }
}

async function load() {
    loading.value = true;
    try {
        const { data } = await systemOpsService.syncPending();
        d.value = data.data;
        await refreshLocal();
    } finally { loading.value = false; }
}

async function forceSync() {
    syncing.value = true;
    try {
        await syncNow();
        await load();
    } finally { syncing.value = false; }
}

let listener;
onMounted(() => {
    load();
    listener = () => refreshLocal();
    window.addEventListener('posmeister:sync:state', listener);
});
onUnmounted(() => {
    if (listener) window.removeEventListener('posmeister:sync:state', listener);
});
</script>

<style scoped>
@reference '../../../css/app.css';
.card       { @apply bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-sm p-5; }
.card-title { @apply text-xs font-bold text-slate-500 uppercase tracking-wider mb-3; }
.btn-soft   { @apply inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 text-sm hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors disabled:opacity-50; }
</style>
