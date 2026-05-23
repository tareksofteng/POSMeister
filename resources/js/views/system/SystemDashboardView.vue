<template>
    <div class="p-6 lg:p-8 space-y-6 max-w-7xl mx-auto">

        <header class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
            <div>
                <p class="text-xs font-medium text-indigo-600 uppercase tracking-wider mb-1">{{ t('systemOps.module') }}</p>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100 tracking-tight">{{ t('systemOps.dashboard.title') }}</h1>
                <p class="mt-1 text-sm text-slate-500">{{ t('systemOps.dashboard.subtitle') }}</p>
            </div>
            <button @click="load" :disabled="loading" class="btn-soft">
                <ArrowPathIcon :class="['w-4 h-4', loading && 'animate-spin']" />
                {{ t('common.refresh') }}
            </button>
        </header>

        <SkeletonLoader v-if="!d && loading" kind="card" :count="4" />

        <template v-if="d">
            <!-- Health score banner -->
            <section :class="['rounded-2xl shadow-sm p-5 flex items-center gap-5', gradeBg(d.health_score.grade)]">
                <div class="w-20 h-20 rounded-full bg-white/70 dark:bg-slate-900/40 flex flex-col items-center justify-center shadow-inner">
                    <span class="text-3xl font-extrabold text-slate-900 dark:text-white">{{ d.health_score.points }}</span>
                    <span class="text-[10px] uppercase tracking-wider text-slate-500">{{ t('systemOps.dashboard.score') }}</span>
                </div>
                <div class="flex-1">
                    <p class="text-xs uppercase tracking-wider font-semibold text-slate-600 dark:text-slate-300">{{ t('systemOps.dashboard.grade.' + d.health_score.grade) }}</p>
                    <p class="text-sm text-slate-700 dark:text-slate-200 mt-0.5">
                        {{ d.health_score.reasons.length ? d.health_score.reasons.join(' · ') : t('systemOps.dashboard.allGood') }}
                    </p>
                </div>
                <div class="hidden sm:block text-right">
                    <p class="text-xs text-slate-500">{{ t('systemOps.dashboard.deployment') }}</p>
                    <p class="text-sm font-bold text-slate-900 dark:text-slate-100">{{ d.deployment.version }}</p>
                    <p class="text-[10px] text-slate-500">{{ d.deployment.environment }} · PHP {{ d.deployment.php }}</p>
                </div>
            </section>

            <!-- Widget grid -->
            <section class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                <RouterLink to="/system/queue" class="widget">
                    <div class="widget-icon bg-indigo-100 text-indigo-600"><QueueListIcon class="w-5 h-5" /></div>
                    <div>
                        <p class="widget-label">{{ t('systemOps.queue.title') }}</p>
                        <p class="widget-value">{{ d.queue.pending }} / {{ d.queue.failed }}</p>
                        <p class="widget-sub">{{ t('systemOps.queue.pendingFailed') }}</p>
                    </div>
                </RouterLink>

                <RouterLink to="/system/environment" class="widget">
                    <div class="widget-icon bg-emerald-100 text-emerald-600"><BoltIcon class="w-5 h-5" /></div>
                    <div>
                        <p class="widget-label">{{ t('systemOps.environment.title') }}</p>
                        <p class="widget-value">{{ envSummary.ok }} / {{ envSummary.total }}</p>
                        <p class="widget-sub">{{ t('systemOps.environment.checksPassing') }}</p>
                    </div>
                </RouterLink>

                <RouterLink to="/system/backup" class="widget">
                    <div class="widget-icon bg-amber-100 text-amber-700"><CircleStackIcon class="w-5 h-5" /></div>
                    <div>
                        <p class="widget-label">{{ t('systemOps.backup.title') }}</p>
                        <p class="widget-value">{{ formatRelative(d.backup.last_success_at) }}</p>
                        <p class="widget-sub">{{ t('systemOps.backup.lastSuccess') }}</p>
                    </div>
                </RouterLink>

                <RouterLink to="/system/sync" class="widget">
                    <div class="widget-icon bg-rose-100 text-rose-600"><ArrowsRightLeftIcon class="w-5 h-5" /></div>
                    <div>
                        <p class="widget-label">{{ t('systemOps.sync.title') }}</p>
                        <p class="widget-value">{{ d.sync.offline_sales_today }}</p>
                        <p class="widget-sub">{{ t('systemOps.sync.syncedToday') }}</p>
                    </div>
                </RouterLink>
            </section>

            <!-- Two-column detail -->
            <section class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <div class="card">
                    <h3 class="card-title">{{ t('systemOps.scheduler.title') }}</h3>
                    <div class="flex items-center gap-3">
                        <span :class="['w-3 h-3 rounded-full', schedulerDot(d.scheduler.status)]" />
                        <div class="flex-1">
                            <p class="text-sm font-medium text-slate-800 dark:text-slate-100">
                                {{ d.scheduler.last_ping || t('systemOps.scheduler.noHeartbeat') }}
                            </p>
                            <p class="text-xs text-slate-500">
                                <template v-if="d.scheduler.age_seconds !== null">{{ d.scheduler.age_seconds }}s {{ t('systemOps.scheduler.ago') }}</template>
                                <template v-else>{{ t('systemOps.scheduler.cronNotConfigured') }}</template>
                            </p>
                        </div>
                    </div>
                    <p v-if="d.scheduler.status !== 'ok'" class="mt-3 text-xs font-mono text-slate-600 dark:text-slate-300 bg-slate-50 dark:bg-slate-800 px-3 py-2 rounded">{{ d.scheduler.cron_hint }}</p>
                </div>

                <div class="card">
                    <h3 class="card-title">{{ t('systemOps.dashboard.storage') }}</h3>
                    <div class="space-y-2">
                        <div class="w-full h-3 bg-slate-100 dark:bg-slate-700 rounded-full overflow-hidden">
                            <div :class="['h-full rounded-full', storageBar(d.storage_usage.used_pct)]" :style="{ width: d.storage_usage.used_pct + '%' }" />
                        </div>
                        <p class="text-xs text-slate-500">
                            {{ humanBytes(d.storage_usage.used_bytes) }} {{ t('common.of') }} {{ humanBytes(d.storage_usage.total_bytes) }}
                            <span class="ml-1 text-slate-400">({{ d.storage_usage.used_pct }}%)</span>
                        </p>
                    </div>
                </div>
            </section>

            <!-- Release notes -->
            <section class="card">
                <h3 class="card-title">{{ t('systemOps.dashboard.releaseNotes') }} · {{ d.deployment.release }}</h3>
                <ul class="space-y-1.5 text-sm text-slate-700 dark:text-slate-200">
                    <li v-for="(note, i) in d.deployment.release_notes" :key="i" class="flex items-start gap-2">
                        <span class="mt-1 w-1.5 h-1.5 rounded-full bg-indigo-500 flex-shrink-0" />
                        <span>{{ note }}</span>
                    </li>
                </ul>
            </section>
        </template>

    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { systemOpsService } from '@/services/systemOpsService';
import SkeletonLoader from '@/components/SkeletonLoader.vue';
import { ArrowPathIcon, QueueListIcon, BoltIcon, CircleStackIcon, ArrowsRightLeftIcon } from '@heroicons/vue/24/outline';

const { t } = useI18n();
const d = ref(null);
const loading = ref(false);

const envSummary = computed(() => {
    if (!d.value) return { ok: 0, total: 0 };
    const env = d.value.environment;
    const checks = ['php', 'extensions', 'database', 'cache', 'queue', 'mail', 'storage', 'security', 'scheduler'];
    const total = checks.length;
    const ok = checks.filter((k) => env?.[k]?.ok).length;
    return { ok, total };
});

function gradeBg(g) {
    return {
        excellent: 'bg-gradient-to-r from-emerald-50 to-teal-50 dark:from-emerald-900/40 dark:to-teal-900/30 border border-emerald-200 dark:border-emerald-800',
        good:      'bg-gradient-to-r from-sky-50 to-indigo-50 dark:from-sky-900/40 dark:to-indigo-900/30 border border-sky-200 dark:border-sky-800',
        attention: 'bg-gradient-to-r from-amber-50 to-orange-50 dark:from-amber-900/40 dark:to-orange-900/30 border border-amber-200 dark:border-amber-800',
        critical:  'bg-gradient-to-r from-rose-50 to-red-50 dark:from-rose-900/40 dark:to-red-900/30 border border-rose-200 dark:border-rose-800',
    }[g] || 'bg-slate-50';
}
function schedulerDot(s) {
    return { ok: 'bg-emerald-500', warning: 'bg-amber-500', critical: 'bg-rose-500' }[s] || 'bg-slate-400';
}
function storageBar(p) {
    if (p < 70) return 'bg-emerald-500';
    if (p < 90) return 'bg-amber-500';
    return 'bg-rose-500';
}
function humanBytes(b) {
    if (!b) return '0 B';
    const u = ['B','KB','MB','GB','TB'];
    let i = 0; let v = b;
    while (v >= 1024 && i < u.length - 1) { v /= 1024; i++; }
    return `${v.toFixed(1)} ${u[i]}`;
}
function formatRelative(iso) {
    if (!iso) return t('systemOps.backup.never');
    const diff = (Date.now() - new Date(iso).getTime()) / 1000;
    if (diff < 60) return `${Math.round(diff)}s`;
    if (diff < 3600) return `${Math.round(diff/60)}m`;
    if (diff < 86400) return `${Math.round(diff/3600)}h`;
    return `${Math.round(diff/86400)}d`;
}

async function load() {
    loading.value = true;
    try {
        const { data } = await systemOpsService.dashboard();
        d.value = data.data;
    } finally {
        loading.value = false;
    }
}

onMounted(load);
</script>

<style scoped>
@reference '../../../css/app.css';
.card        { @apply bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-sm p-5; }
.card-title  { @apply text-xs font-bold text-slate-500 uppercase tracking-wider mb-3; }
.btn-soft    { @apply inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 text-sm hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors disabled:opacity-50; }
.widget      { @apply flex items-center gap-3 p-4 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm hover:shadow-md transition-shadow; }
.widget-icon { @apply w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0; }
.widget-label{ @apply text-[10px] uppercase tracking-wider text-slate-500 font-semibold; }
.widget-value{ @apply text-lg font-bold text-slate-900 dark:text-slate-100 leading-tight; }
.widget-sub  { @apply text-[10px] text-slate-500; }
</style>
