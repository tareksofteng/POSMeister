<template>
    <div class="p-6 lg:p-8 space-y-5 max-w-6xl mx-auto">
        <header class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
            <div>
                <p class="text-xs font-medium text-indigo-600 uppercase tracking-wider mb-1">{{ t('systemOps.module') }}</p>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">{{ t('systemOps.queue.title') }}</h1>
                <p class="mt-1 text-sm text-slate-500">{{ t('systemOps.queue.subtitle') }}</p>
            </div>
            <button @click="load" :disabled="loading" class="btn-soft">
                <ArrowPathIcon :class="['w-4 h-4', loading && 'animate-spin']" /> {{ t('common.refresh') }}
            </button>
        </header>

        <SkeletonLoader v-if="!d && loading" kind="card" :count="2" />

        <template v-if="d">
            <section class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <Tile :label="t('systemOps.queue.driver')"      :value="d.snapshot.driver" tone="indigo" />
                <Tile :label="t('systemOps.queue.pending')"     :value="d.snapshot.pending" :tone="d.snapshot.pending > 0 ? 'amber' : 'emerald'" />
                <Tile :label="t('systemOps.queue.failed')"      :value="d.snapshot.failed" :tone="d.snapshot.failed > 0 ? 'rose' : 'emerald'" />
                <Tile :label="t('systemOps.queue.oldestPending')" :value="d.snapshot.oldest_pending_age_seconds !== null ? d.snapshot.oldest_pending_age_seconds + 's' : '–'" tone="slate" />
            </section>

            <section class="card">
                <h3 class="card-title">{{ t('systemOps.queue.failedJobs') }} ({{ d.failed.length }})</h3>
                <table v-if="d.failed.length" class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-[10px] uppercase tracking-wider text-slate-500 border-b border-slate-200 dark:border-slate-800">
                            <th class="py-2">ID</th>
                            <th>{{ t('systemOps.queue.connection') }}</th>
                            <th>{{ t('systemOps.queue.queueName') }}</th>
                            <th>{{ t('systemOps.queue.failedAt') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="r in d.failed" :key="r.id" class="border-b border-slate-100 dark:border-slate-800 last:border-0">
                            <td class="py-2 font-mono text-xs">{{ r.id }}</td>
                            <td class="text-xs">{{ r.connection }}</td>
                            <td class="text-xs">{{ r.queue }}</td>
                            <td class="text-xs text-slate-500">{{ r.failed_at }}</td>
                        </tr>
                    </tbody>
                </table>
                <p v-else class="text-sm text-slate-500">{{ t('systemOps.queue.empty') }}</p>
            </section>

            <section class="card">
                <h3 class="card-title">{{ t('systemOps.queue.pendingJobs') }} ({{ d.pending.length }})</h3>
                <table v-if="d.pending.length" class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-[10px] uppercase tracking-wider text-slate-500 border-b border-slate-200 dark:border-slate-800">
                            <th class="py-2">ID</th>
                            <th>{{ t('systemOps.queue.queueName') }}</th>
                            <th>{{ t('systemOps.queue.attempts') }}</th>
                            <th>{{ t('systemOps.queue.availableAt') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="r in d.pending" :key="r.id" class="border-b border-slate-100 dark:border-slate-800 last:border-0">
                            <td class="py-2 font-mono text-xs">{{ r.id }}</td>
                            <td class="text-xs">{{ r.queue }}</td>
                            <td class="text-xs">{{ r.attempts }}</td>
                            <td class="text-xs text-slate-500">{{ r.available_at }}</td>
                        </tr>
                    </tbody>
                </table>
                <p v-else class="text-sm text-slate-500">{{ t('systemOps.queue.empty') }}</p>
            </section>
        </template>
    </div>
</template>

<script setup>
import { ref, h, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { systemOpsService } from '@/services/systemOpsService';
import SkeletonLoader from '@/components/SkeletonLoader.vue';
import { ArrowPathIcon } from '@heroicons/vue/24/outline';

const { t } = useI18n();
const d = ref(null);
const loading = ref(false);

const toneClass = (tone) => ({
    indigo:  'border-indigo-200 bg-indigo-50/60 text-indigo-700',
    emerald: 'border-emerald-200 bg-emerald-50/60 text-emerald-700',
    amber:   'border-amber-200 bg-amber-50/60 text-amber-700',
    rose:    'border-rose-200 bg-rose-50/60 text-rose-700',
    slate:   'border-slate-200 bg-slate-50/60 text-slate-700',
}[tone] || 'border-slate-200');

const Tile = (props) => h('div', {
    class: `rounded-xl border px-4 py-3 ${toneClass(props.tone)}`,
}, [
    h('p', { class: 'text-[10px] uppercase tracking-wider font-semibold opacity-70' }, props.label),
    h('p', { class: 'mt-1 text-xl font-bold' }, String(props.value)),
]);
Tile.props = ['label', 'value', 'tone'];

async function load() {
    loading.value = true;
    try {
        const { data } = await systemOpsService.queue();
        d.value = data.data;
    } finally { loading.value = false; }
}
onMounted(load);
</script>

<style scoped>
@reference '../../../css/app.css';
.card       { @apply bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-sm p-5; }
.card-title { @apply text-xs font-bold text-slate-500 uppercase tracking-wider mb-3; }
.btn-soft   { @apply inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 text-sm hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors disabled:opacity-50; }
</style>
