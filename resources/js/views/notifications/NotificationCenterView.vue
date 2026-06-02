<template>
    <div class="p-4 sm:p-6 lg:p-8 space-y-5 max-w-5xl mx-auto">

        <header class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
            <div>
                <p class="text-xs font-medium text-indigo-600 uppercase tracking-wider mb-1">{{ t('notifications.module') }}</p>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">{{ t('notifications.centerTitle') }}</h1>
                <p class="mt-1 text-sm text-slate-500">{{ t('notifications.centerSubtitle') }}</p>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <button @click="store.markAllRead()" :disabled="store.unread === 0" class="btn-soft">
                    {{ t('notifications.markAllRead') }}
                </button>
                <button @click="onClearRead" :disabled="!hasRead" class="btn-soft">
                    {{ t('notifications.clearRead') }}
                </button>
                <button @click="onClearAll" :disabled="store.items.length === 0" class="btn-soft text-rose-700 border-rose-200 hover:bg-rose-50">
                    {{ t('notifications.clearAll') }}
                </button>
                <RouterLink :to="{ name: 'notification-preferences' }" class="btn-soft">
                    <CogIcon class="w-4 h-4" /> {{ t('notifications.preferences') }}
                </RouterLink>
                <button @click="store.fetch()" :disabled="store.loading" class="btn-soft">
                    <ArrowPathIcon :class="['w-4 h-4', store.loading && 'animate-spin']" />
                    {{ t('common.refresh') }}
                </button>
            </div>
        </header>

        <!-- Filters -->
        <div class="flex flex-wrap items-center gap-2">
            <button
                v-for="c in categories"
                :key="c.key"
                @click="filterCategory = filterCategory === c.key ? null : c.key"
                :class="['px-3 py-1.5 text-xs font-semibold rounded-lg border',
                    filterCategory === c.key
                        ? 'bg-indigo-600 text-white border-indigo-600'
                        : 'bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-200 border-slate-200 dark:border-slate-700 hover:bg-slate-50']"
            >
                {{ c.label }} <span class="ml-1 text-[10px] opacity-70">{{ counts[c.key] || 0 }}</span>
            </button>
            <span class="mx-2 text-slate-300">·</span>
            <button
                v-for="s in severities"
                :key="s.key"
                @click="filterSeverity = filterSeverity === s.key ? null : s.key"
                :class="['px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider rounded',
                    filterSeverity === s.key ? sevBg(s.key) + ' ring-2 ring-offset-1 ring-current' : sevBg(s.key)]"
            >
                {{ s.label }}
            </button>
        </div>

        <!-- List -->
        <section class="card p-0 overflow-hidden">
            <SkeletonLoader v-if="store.loading && !visible.length" kind="list" :count="6" />

            <div v-else-if="!visible.length" class="px-6 py-16 text-center">
                <CheckCircleIcon class="w-12 h-12 text-emerald-400 mx-auto mb-3" />
                <p class="text-sm text-slate-500">{{ t('notifications.empty') }}</p>
            </div>

            <ul v-else class="divide-y divide-slate-100 dark:divide-slate-800">
                <li
                    v-for="n in visible"
                    :key="n.id"
                    :class="['p-4 hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors', !n.read_at && 'bg-indigo-50/30 dark:bg-indigo-900/10']"
                >
                    <div class="flex items-start gap-3">
                        <span :class="['mt-2 w-2.5 h-2.5 rounded-full flex-shrink-0', sevDot(n.severity)]" />
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500">{{ n.category }}</p>
                                <span :class="['text-[9px] font-bold uppercase tracking-wider px-1.5 rounded', sevBg(n.severity)]">{{ n.severity }}</span>
                                <span v-if="n.escalation_level > 0" class="text-[9px] font-bold uppercase tracking-wider px-1.5 rounded bg-orange-100 text-orange-700">
                                    ↑ {{ n.escalation_level }}
                                </span>
                                <span class="text-[10px] text-slate-400 ml-auto">{{ formatDate(n.created_at) }}</span>
                            </div>
                            <p class="mt-1 text-sm font-semibold text-slate-900 dark:text-slate-100">{{ n.title }}</p>
                            <p class="text-sm text-slate-600 dark:text-slate-300 mt-0.5">{{ n.message }}</p>

                            <div class="mt-2 flex flex-wrap items-center gap-2">
                                <RouterLink
                                    v-for="(a, ai) in (n.actions || [])"
                                    :key="ai"
                                    :to="{ name: a.route, params: a.params }"
                                    @click="store.markRead(n.id)"
                                    :class="['text-xs font-semibold px-3 py-1.5 rounded-lg',
                                        a.type === 'primary'
                                            ? 'bg-indigo-600 text-white hover:bg-indigo-700'
                                            : 'bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-300']"
                                >
                                    {{ t(a.label) }}
                                </RouterLink>
                                <button v-if="!n.acked_at" @click="store.ack(n.id)" class="text-xs text-emerald-700 dark:text-emerald-400 hover:underline">
                                    {{ t('notifications.ack') }}
                                </button>
                                <button @click="store.archive(n.id)" class="text-xs text-slate-500 hover:underline ml-auto">
                                    {{ t('notifications.archive') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </li>
            </ul>
        </section>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ArrowPathIcon, CogIcon, CheckCircleIcon } from '@heroicons/vue/24/outline';
import { useNotificationsStore } from '@/stores/notifications';
import SkeletonLoader from '@/components/SkeletonLoader.vue';

const { t, locale } = useI18n();
const store = useNotificationsStore();
const filterCategory = ref(null);
const filterSeverity = ref(null);

const categories = computed(() => [
    { key: 'inventory', label: t('notifications.category.inventory') },
    { key: 'sales',     label: t('notifications.category.sales') },
    { key: 'finance',   label: t('notifications.category.finance') },
    { key: 'hrm',       label: t('notifications.category.hrm') },
    { key: 'system',    label: t('notifications.category.system') },
]);
const severities = computed(() => [
    { key: 'info',     label: t('notifications.severity.info') },
    { key: 'warning',  label: t('notifications.severity.warning') },
    { key: 'danger',   label: t('notifications.severity.danger') },
    { key: 'critical', label: t('notifications.severity.critical') },
]);

const visible = computed(() => store.items.filter((n) => {
    if (filterCategory.value && n.category !== filterCategory.value) return false;
    if (filterSeverity.value && n.severity !== filterSeverity.value) return false;
    return true;
}));

const counts = computed(() => {
    const c = {};
    store.items.forEach((n) => { c[n.category] = (c[n.category] || 0) + 1; });
    return c;
});

function sevDot(s) {
    return ({ info: 'bg-sky-500', success: 'bg-emerald-500', warning: 'bg-amber-500', danger: 'bg-rose-500', critical: 'bg-red-600 animate-pulse' })[s] || 'bg-slate-400';
}
function sevBg(s) {
    return ({
        info:    'bg-sky-100 text-sky-700',
        success: 'bg-emerald-100 text-emerald-700',
        warning: 'bg-amber-100 text-amber-700',
        danger:  'bg-rose-100 text-rose-700',
        critical:'bg-red-200 text-red-900',
    })[s] || 'bg-slate-100 text-slate-700';
}
function formatDate(iso) {
    if (!iso) return '';
    return new Intl.DateTimeFormat(locale.value || 'en-US', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' }).format(new Date(iso));
}

const hasRead = computed(() => store.items.some(n => n.read_at));

async function onClearRead() {
    if (!window.confirm(t('notifications.confirmClearRead'))) return;
    await store.clearRead();
}
async function onClearAll() {
    if (!window.confirm(t('notifications.confirmClearAll'))) return;
    await store.clearAll();
}

onMounted(() => store.fetch());
</script>

<style scoped>
@reference '../../../css/app.css';
.card     { @apply bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-sm; }
.btn-soft { @apply inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 text-sm hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors disabled:opacity-50; }
</style>
