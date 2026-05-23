<template>
    <div class="p-6 lg:p-8 space-y-5 max-w-6xl mx-auto">
        <header class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
            <div>
                <p class="text-xs font-medium text-indigo-600 uppercase tracking-wider mb-1">{{ t('systemOps.module') }}</p>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">{{ t('systemOps.backup.title') }}</h1>
                <p class="mt-1 text-sm text-slate-500">{{ t('systemOps.backup.subtitle') }}</p>
            </div>
            <div class="flex items-center gap-2">
                <button @click="prune" :disabled="running" class="btn-soft">{{ t('systemOps.backup.prune') }}</button>
                <button @click="run" :disabled="running" class="btn-primary">
                    <CircleStackIcon class="w-4 h-4" />
                    {{ running ? t('systemOps.backup.runningNow') : t('systemOps.backup.runNow') }}
                </button>
            </div>
        </header>

        <SkeletonLoader v-if="!d && loading" kind="card" :count="2" />

        <template v-if="d">
            <section class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <Card :label="t('systemOps.backup.totalRuns')" :value="d.summary.total_runs" />
                <Card :label="t('systemOps.backup.lastSuccess')" :value="formatDate(d.summary.last_success_at)" />
                <Card :label="t('systemOps.backup.lastSize')" :value="humanBytes(d.summary.last_success_size)" />
                <Card :label="t('systemOps.backup.diskUsed')" :value="humanBytes(d.summary.disk_used_bytes)" />
            </section>

            <section class="card">
                <h3 class="card-title">{{ t('systemOps.backup.history') }}</h3>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-[10px] uppercase tracking-wider text-slate-500 border-b border-slate-200 dark:border-slate-800">
                            <th class="py-2">{{ t('systemOps.backup.startedAt') }}</th>
                            <th>{{ t('systemOps.backup.type') }}</th>
                            <th>{{ t('common.status') }}</th>
                            <th>{{ t('systemOps.backup.size') }}</th>
                            <th>{{ t('systemOps.backup.checksum') }}</th>
                            <th>{{ t('systemOps.backup.file') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="r in d.runs" :key="r.id" class="border-b border-slate-100 dark:border-slate-800 last:border-0">
                            <td class="py-2 text-xs text-slate-500">{{ formatDate(r.started_at) }}</td>
                            <td class="text-xs uppercase font-semibold">{{ r.type }}</td>
                            <td>
                                <span :class="['text-[10px] uppercase font-bold px-2 py-0.5 rounded-full',
                                    r.status === 'success' ? 'bg-emerald-100 text-emerald-700' :
                                    r.status === 'failed'  ? 'bg-rose-100 text-rose-700'      :
                                                              'bg-amber-100 text-amber-700']">
                                    {{ r.status }}
                                </span>
                            </td>
                            <td class="text-xs">{{ r.size_human || '–' }}</td>
                            <td class="text-[10px] font-mono text-slate-500 truncate max-w-[160px]">{{ r.checksum ? r.checksum.substring(0, 16) + '…' : '–' }}</td>
                            <td class="text-[10px] font-mono text-slate-500 truncate max-w-[260px]">{{ r.file_path || r.error || '–' }}</td>
                        </tr>
                    </tbody>
                </table>
                <p v-if="!d.runs.length" class="text-sm text-slate-500">{{ t('systemOps.backup.empty') }}</p>
            </section>

            <div class="text-xs text-slate-500 italic">{{ t('systemOps.backup.restoreNote') }}</div>
        </template>
    </div>
</template>

<script setup>
import { ref, h, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { systemOpsService } from '@/services/systemOpsService';
import SkeletonLoader from '@/components/SkeletonLoader.vue';
import { CircleStackIcon } from '@heroicons/vue/24/outline';

const { t, locale } = useI18n();
const d = ref(null);
const loading = ref(false);
const running = ref(false);

const Card = (props) => h('div', { class: 'rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-4 py-3' }, [
    h('p', { class: 'text-[10px] uppercase tracking-wider font-semibold text-slate-500' }, props.label),
    h('p', { class: 'mt-1 text-lg font-bold text-slate-900 dark:text-slate-100' }, String(props.value ?? '–')),
]);
Card.props = ['label', 'value'];

function humanBytes(b) {
    if (!b) return '–';
    const u = ['B','KB','MB','GB','TB'];
    let i = 0; let v = b;
    while (v >= 1024 && i < u.length - 1) { v /= 1024; i++; }
    return `${v.toFixed(1)} ${u[i]}`;
}
function formatDate(iso) {
    if (!iso) return '–';
    return new Intl.DateTimeFormat(locale.value || 'de-DE', {
        day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit',
    }).format(new Date(iso));
}

async function load() {
    loading.value = true;
    try {
        const { data } = await systemOpsService.backupStatus();
        d.value = data.data;
    } finally { loading.value = false; }
}

async function run() {
    running.value = true;
    try {
        await systemOpsService.backupRun(null);
        await load();
    } finally { running.value = false; }
}

async function prune() {
    running.value = true;
    try {
        await systemOpsService.backupPrune();
        await load();
    } finally { running.value = false; }
}

onMounted(load);
</script>

<style scoped>
@reference '../../../css/app.css';
.card        { @apply bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-sm p-5; }
.card-title  { @apply text-xs font-bold text-slate-500 uppercase tracking-wider mb-3; }
.btn-soft    { @apply inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 text-sm hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors disabled:opacity-50; }
.btn-primary { @apply inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold shadow-sm transition-colors disabled:opacity-50; }
</style>
