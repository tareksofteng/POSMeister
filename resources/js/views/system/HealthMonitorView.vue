<template>
    <div class="p-6 lg:p-8 space-y-5 max-w-6xl mx-auto">
        <header class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
            <div>
                <p class="text-xs font-medium text-indigo-600 uppercase tracking-wider mb-1">{{ t('systemOps.module') }}</p>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">{{ t('systemOps.monitor.title') }}</h1>
                <p class="mt-1 text-sm text-slate-500">{{ t('systemOps.monitor.subtitle') }}</p>
            </div>
            <div class="flex items-center gap-2">
                <label class="flex items-center gap-2 text-xs text-slate-500">
                    <input v-model="autoRefresh" type="checkbox" class="rounded" />
                    {{ t('systemOps.monitor.autoRefresh') }}
                </label>
                <button @click="load" :disabled="loading" class="btn-soft">
                    <ArrowPathIcon :class="['w-4 h-4', loading && 'animate-spin']" /> {{ t('common.refresh') }}
                </button>
            </div>
        </header>

        <SkeletonLoader v-if="!d && loading" kind="card" :count="2" />

        <template v-if="d">
            <section class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
                <Pulse :label="t('systemOps.monitor.database')" :ok="d.environment.database.ok" :detail="d.environment.database.ping_ms + ' ms'" />
                <Pulse :label="t('systemOps.monitor.cache')"    :ok="d.environment.cache.ok"    :detail="d.environment.cache.driver" />
                <Pulse :label="t('systemOps.monitor.queue')"    :ok="d.queue.failed === 0"      :detail="d.queue.driver" />
                <Pulse :label="t('systemOps.monitor.scheduler')" :ok="d.scheduler.status === 'ok'" :detail="d.scheduler.status" />
                <Pulse :label="t('systemOps.monitor.storage')"  :ok="d.environment.storage.ok" :detail="storagePct + '%'" />
                <Pulse :label="t('systemOps.monitor.security')" :ok="d.environment.security.ok" :detail="d.environment.security.ok ? 'OK' : 'CHECK'" />
            </section>

            <section class="card">
                <h3 class="card-title">{{ t('systemOps.monitor.deployment') }}</h3>
                <dl class="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
                    <div><dt class="text-[10px] uppercase text-slate-500 font-semibold">{{ t('systemOps.monitor.version') }}</dt><dd class="font-semibold">{{ d.deployment.version }}</dd></div>
                    <div><dt class="text-[10px] uppercase text-slate-500 font-semibold">{{ t('systemOps.monitor.release') }}</dt><dd class="font-semibold">{{ d.deployment.release }}</dd></div>
                    <div><dt class="text-[10px] uppercase text-slate-500 font-semibold">{{ t('systemOps.monitor.commit') }}</dt><dd class="font-mono text-xs">{{ d.deployment.commit || '–' }}</dd></div>
                    <div><dt class="text-[10px] uppercase text-slate-500 font-semibold">{{ t('systemOps.monitor.builtAt') }}</dt><dd class="font-mono text-xs">{{ d.deployment.built_at || '–' }}</dd></div>
                    <div><dt class="text-[10px] uppercase text-slate-500 font-semibold">{{ t('systemOps.monitor.php') }}</dt><dd class="font-semibold">{{ d.deployment.php }}</dd></div>
                    <div><dt class="text-[10px] uppercase text-slate-500 font-semibold">{{ t('systemOps.monitor.laravel') }}</dt><dd class="font-semibold">{{ d.deployment.laravel }}</dd></div>
                    <div><dt class="text-[10px] uppercase text-slate-500 font-semibold">{{ t('systemOps.monitor.environment') }}</dt><dd class="font-semibold uppercase">{{ d.deployment.environment }}</dd></div>
                    <div><dt class="text-[10px] uppercase text-slate-500 font-semibold">{{ t('systemOps.monitor.maintenance') }}</dt><dd class="font-semibold">{{ d.deployment.maintenance ? '⚠ ON' : 'OFF' }}</dd></div>
                </dl>
            </section>
        </template>
    </div>
</template>

<script setup>
import { ref, computed, h, onMounted, onUnmounted, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { systemOpsService } from '@/services/systemOpsService';
import SkeletonLoader from '@/components/SkeletonLoader.vue';
import { ArrowPathIcon } from '@heroicons/vue/24/outline';

const { t } = useI18n();
const d = ref(null);
const loading = ref(false);
const autoRefresh = ref(false);
let timer = null;

const Pulse = (props) => h('div', {
    class: `rounded-xl px-4 py-3 border ${props.ok ? 'border-emerald-200 bg-emerald-50/40' : 'border-rose-200 bg-rose-50/40'}`,
}, [
    h('div', { class: 'flex items-center gap-2' }, [
        h('span', { class: `w-2.5 h-2.5 rounded-full ${props.ok ? 'bg-emerald-500' : 'bg-rose-500 animate-pulse'}` }),
        h('p', { class: 'text-[10px] uppercase tracking-wider font-bold text-slate-600' }, props.label),
    ]),
    h('p', { class: 'mt-1 text-sm font-bold text-slate-800' }, props.detail || ''),
]);
Pulse.props = ['label', 'ok', 'detail'];

const storagePct = computed(() => d.value?.storage_usage?.used_pct ?? 0);

async function load() {
    loading.value = true;
    try {
        const { data } = await systemOpsService.dashboard();
        d.value = data.data;
    } finally { loading.value = false; }
}

watch(autoRefresh, (on) => {
    if (on)  timer = setInterval(load, 15000);
    else if (timer) { clearInterval(timer); timer = null; }
});

onMounted(load);
onUnmounted(() => { if (timer) clearInterval(timer); });
</script>

<style scoped>
@reference '../../../css/app.css';
.card       { @apply bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-sm p-5; }
.card-title { @apply text-xs font-bold text-slate-500 uppercase tracking-wider mb-3; }
.btn-soft   { @apply inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 text-sm hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors disabled:opacity-50; }
</style>
