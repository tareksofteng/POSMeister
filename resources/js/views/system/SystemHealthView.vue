<template>
    <div class="p-6 lg:p-8 space-y-5 max-w-7xl mx-auto">

        <header class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
            <div>
                <p class="text-xs font-medium text-indigo-600 uppercase tracking-wider mb-1">{{ t('system.module') }}</p>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100 tracking-tight">{{ t('system.health.title') }}</h1>
                <p class="mt-1 text-sm text-slate-500">{{ t('system.health.subtitle') }}</p>
            </div>
            <button @click="load" :disabled="loading" class="btn-soft">
                <ArrowPathIcon :class="['w-4 h-4', loading && 'animate-spin']" />
                {{ t('common.refresh') }}
            </button>
        </header>

        <SkeletonLoader v-if="!d && loading" kind="card" :count="4" />

        <template v-if="d">
            <!-- Critical-path checks -->
            <section class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <CheckTile :label="t('system.health.database')" :ok="d.database.ok" :detail="d.database.ping_ms ? d.database.ping_ms + ' ms' : d.database.error" />
                <CheckTile :label="t('system.health.cache')"    :ok="d.cache.ok"    :detail="d.cache.driver" />
                <CheckTile :label="t('system.health.queue')"    :ok="(d.queue.failed ?? 0) === 0" :detail="d.queue.driver + ' · ' + (d.queue.pending ?? 0) + ' pending'" />
                <CheckTile :label="t('system.health.app')"      :ok="true"          :detail="d.version.version + ' · ' + d.environment" />
            </section>

            <!-- Business / readiness checks -->
            <section class="card">
                <h3 class="card-title">{{ t('system.health.readiness') }}</h3>
                <ul class="divide-y divide-slate-100">
                    <li v-for="c in d.business" :key="c.key" class="flex items-center gap-3 py-2.5">
                        <span :class="['w-2 h-2 rounded-full flex-shrink-0', dotClass(c.severity, c.ok)]"></span>
                        <span class="flex-1 text-sm text-slate-800">{{ c.message }}</span>
                        <span :class="['text-[10px] uppercase tracking-wider font-bold px-2 py-0.5 rounded-md', sevBadge(c.severity)]">
                            {{ c.severity }}
                        </span>
                    </li>
                </ul>
            </section>

            <!-- Module map -->
            <section class="card">
                <h3 class="card-title">{{ t('system.health.modules') }}</h3>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                    <div v-for="m in d.modules" :key="m.key"
                         class="flex items-center gap-2 px-3 py-2 rounded-lg border border-slate-200">
                        <span :class="['w-2 h-2 rounded-full', m.migrated ? 'bg-emerald-500' : 'bg-slate-300']"></span>
                        <span class="text-xs font-semibold text-slate-700 capitalize flex-1">{{ m.key }}</span>
                        <span class="text-[10px] font-bold tracking-wider text-slate-500">{{ m.phase }}</span>
                    </div>
                </div>
            </section>

            <!-- Storage paths -->
            <section class="card">
                <h3 class="card-title">{{ t('system.health.storage') }}</h3>
                <ul class="divide-y divide-slate-100 text-sm">
                    <li v-for="p in d.storage" :key="p.path" class="flex items-center gap-3 py-2">
                        <span :class="['w-2 h-2 rounded-full', p.writable ? 'bg-emerald-500' : 'bg-rose-500']"></span>
                        <span class="font-mono text-xs text-slate-700 flex-1">{{ p.path }}</span>
                        <span :class="['text-[10px] uppercase font-bold', p.writable ? 'text-emerald-700' : 'text-rose-700']">
                            {{ p.writable ? t('system.health.writable') : (p.exists ? t('system.health.notWritable') : t('system.health.missing')) }}
                        </span>
                    </li>
                </ul>
            </section>

            <!-- Recent audit events -->
            <section v-if="d.recent_events?.length" class="card">
                <h3 class="card-title">{{ t('system.audit.title') }}</h3>
                <ol class="divide-y divide-slate-100 text-sm">
                    <li v-for="e in d.recent_events" :key="e.id" class="flex items-center gap-3 py-2">
                        <span :class="['w-2 h-2 rounded-full flex-shrink-0', dotClass(e.severity, true)]"></span>
                        <span class="font-mono text-xs text-slate-500 flex-shrink-0">{{ formatDate(e.created_at) }}</span>
                        <span class="text-xs font-semibold text-slate-700 flex-shrink-0">{{ e.action }}</span>
                        <span class="text-xs text-slate-600 truncate flex-1">{{ e.note }}</span>
                    </li>
                </ol>
            </section>
        </template>

    </div>
</template>

<script setup>
import { ref, h, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { systemService } from '@/services/systemService';
import { ArrowPathIcon, CheckCircleIcon, XCircleIcon } from '@heroicons/vue/24/outline';
import SkeletonLoader from '@/components/SkeletonLoader.vue';

const { t, locale } = useI18n();

const d = ref(null);
const loading = ref(false);

const CheckTile = (props) => {
    const tone = props.ok ? 'border-emerald-200 bg-emerald-50/40' : 'border-rose-200 bg-rose-50/40';
    const iconColor = props.ok ? 'text-emerald-600' : 'text-rose-600';
    const Icon = props.ok ? CheckCircleIcon : XCircleIcon;
    return h('div', { class: `border ${tone} rounded-xl shadow-sm px-4 py-3 flex items-center gap-3` }, [
        h(Icon, { class: `w-7 h-7 ${iconColor}` }),
        h('div', null, [
            h('p', { class: 'text-[10px] uppercase tracking-wide text-slate-500 font-medium' }, props.label),
            h('p', { class: 'text-sm font-bold text-slate-900 mt-0.5' }, props.detail || ''),
        ]),
    ]);
};
CheckTile.props = ['label', 'ok', 'detail'];

function dotClass(severity, ok) {
    if (!ok) return 'bg-rose-500';
    return { info: 'bg-emerald-500', warning: 'bg-amber-500', critical: 'bg-rose-500' }[severity] ?? 'bg-slate-400';
}
function sevBadge(s) {
    return {
        info:     'bg-emerald-50 text-emerald-700',
        warning:  'bg-amber-50 text-amber-700',
        critical: 'bg-rose-50 text-rose-700',
    }[s] ?? 'bg-slate-100 text-slate-700';
}
function formatDate(d) {
    if (!d) return '';
    return new Intl.DateTimeFormat(locale.value || 'de-DE', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' }).format(new Date(d));
}

async function load() {
    loading.value = true;
    try {
        const { data } = await systemService.health();
        d.value = data.data;
    } finally {
        loading.value = false;
    }
}

onMounted(load);
</script>

<style scoped>
@reference '../../../css/app.css';
.card        { @apply bg-white border border-slate-200 rounded-xl shadow-sm p-5; }
.card-title  { @apply text-xs font-bold text-slate-500 uppercase tracking-wider mb-3; }
.btn-soft    { @apply inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-slate-300 text-slate-700 text-sm hover:bg-slate-50 transition-colors disabled:opacity-50; }
</style>
