<template>
    <div class="p-6 lg:p-8 max-w-6xl mx-auto space-y-6">

        <div class="flex items-center gap-3">
            <RouterLink :to="{ name: 'finance-budgets' }" class="p-2 text-slate-400 hover:text-slate-700 hover:bg-slate-100 rounded-lg">
                <ArrowLeftIcon class="w-5 h-5" />
            </RouterLink>
            <div class="flex-1 min-w-0">
                <h1 class="text-2xl font-bold text-slate-900 tracking-tight truncate">
                    {{ analysis?.budget.title ?? t('finance.analytics.title') }}
                </h1>
                <p v-if="analysis" class="text-sm text-slate-500">
                    {{ formatDate(analysis.budget.start_date) }} - {{ formatDate(analysis.budget.end_date) }}
                    <span v-if="analysis.budget.branch_name"> · {{ analysis.budget.branch_name }}</span>
                </p>
            </div>
        </div>

        <div v-if="loading" class="text-center py-16 text-slate-400">
            <div class="w-8 h-8 border-2 border-indigo-500 border-t-transparent rounded-full animate-spin mx-auto mb-3"></div>
            <p class="text-sm">{{ t('common.loading') }}</p>
        </div>

        <template v-else-if="analysis">
            <div v-if="analysis.totals.overspent" class="rounded-lg border border-rose-200 bg-rose-50 p-4 flex items-start gap-3">
                <ExclamationTriangleIcon class="w-5 h-5 text-rose-600 mt-0.5 flex-shrink-0" />
                <div>
                    <p class="text-sm font-semibold text-rose-700">{{ t('finance.analytics.overspentBanner') }}</p>
                    <p class="text-xs text-rose-600 mt-0.5">
                        {{ t('finance.analytics.overspentBy', { amount: fmtCurrency(analysis.totals.total_actual - analysis.totals.total_allocated) }) }}
                    </p>
                </div>
            </div>

            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                <KpiCard :label="t('finance.analytics.allocated')" :value="fmtCurrency(analysis.totals.total_allocated)" tone="slate" />
                <KpiCard :label="t('finance.analytics.actual')"    :value="fmtCurrency(analysis.totals.total_actual)" tone="indigo" />
                <KpiCard :label="t('finance.analytics.remaining')" :value="fmtCurrency(analysis.totals.total_remaining)" :tone="analysis.totals.total_remaining < 0 ? 'rose' : 'emerald'" />
                <KpiCard :label="t('finance.analytics.percentUsed')" :value="`${analysis.totals.percent_used}%`" :tone="healthTone(analysis.totals.health)" />
            </div>

            <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-5">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-xs font-bold text-slate-500 uppercase tracking-wider">{{ t('finance.analytics.overallProgress') }}</h3>
                    <span class="text-xs text-slate-500">
                        {{ t('finance.analytics.expectedPace', { pct: analysis.totals.expected_percent }) }}
                        <PaceBadge :pace="analysis.totals.pace" class="ml-2" />
                    </span>
                </div>
                <div class="relative h-4 bg-slate-100 rounded-full overflow-hidden">
                    <div :class="['absolute top-0 left-0 h-full transition-all', barColor(analysis.totals.health)]"
                         :style="{ width: Math.min(analysis.totals.percent_used, 100) + '%' }"></div>
                    <div class="absolute top-0 h-full w-0.5 bg-slate-500"
                         :style="{ left: Math.min(analysis.totals.expected_percent, 100) + '%' }"
                         :title="t('finance.analytics.expectedMark')"></div>
                </div>
                <div class="flex items-center justify-between mt-2 text-xs text-slate-500">
                    <span>0%</span>
                    <span class="font-mono font-semibold" :class="healthText(analysis.totals.health)">{{ analysis.totals.percent_used }}%</span>
                    <span>100%</span>
                </div>
            </div>

            <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
                <header class="px-5 py-3 border-b border-slate-100 flex items-center justify-between">
                    <h3 class="text-xs font-bold text-slate-500 uppercase tracking-wider">{{ t('finance.analytics.byCategory') }}</h3>
                </header>
                <div class="divide-y divide-slate-100">
                    <div v-for="cat in analysis.categories" :key="cat.expense_category_id" class="p-5 space-y-2">
                        <div class="flex items-center justify-between flex-wrap gap-2">
                            <div>
                                <p class="font-medium text-slate-900">{{ cat.category_name }}</p>
                                <p class="text-xs text-slate-500 font-mono">{{ cat.category_code || '—' }}</p>
                            </div>
                            <div class="text-right">
                                <p class="font-mono font-semibold" :class="healthText(cat.health)">
                                    {{ fmtCurrency(cat.actual) }} / {{ fmtCurrency(cat.allocated) }}
                                </p>
                                <p class="text-xs text-slate-500">
                                    {{ cat.percent }}% ·
                                    <span :class="cat.remaining < 0 ? 'text-rose-600 font-semibold' : 'text-emerald-700'">
                                        {{ cat.remaining < 0 ? '-' : '' }}{{ fmtCurrency(Math.abs(cat.remaining)) }}
                                        {{ cat.remaining < 0 ? t('finance.analytics.overshot') : t('finance.analytics.left') }}
                                    </span>
                                </p>
                            </div>
                        </div>
                        <div class="h-2 bg-slate-100 rounded-full overflow-hidden">
                            <div :class="['h-full transition-all', barColor(cat.health)]" :style="{ width: Math.min(cat.percent, 100) + '%' }"></div>
                        </div>
                    </div>
                    <div v-if="analysis.categories.length === 0" class="py-10 text-center text-sm text-slate-400">
                        {{ t('finance.analytics.noCategories') }}
                    </div>
                </div>
            </div>

            <div v-if="analysis.monthly_burn.length" class="bg-white border border-slate-200 rounded-xl shadow-sm p-5">
                <h3 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-3">{{ t('finance.analytics.burnRate') }}</h3>
                <div class="space-y-2">
                    <div v-for="m in analysis.monthly_burn" :key="m.month" class="grid grid-cols-12 gap-3 items-center">
                        <span class="col-span-3 text-xs text-slate-600 font-medium">{{ m.month }}</span>
                        <div class="col-span-6 h-2 bg-slate-100 rounded-full overflow-hidden">
                            <div class="h-full bg-indigo-500 rounded-full" :style="{ width: burnPct(m.total) + '%' }"></div>
                        </div>
                        <span class="col-span-3 text-right font-mono text-xs">{{ fmtCurrency(m.total) }}</span>
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, h } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute, RouterLink } from 'vue-router';
import { budgetService } from '@/services/financeService';
import { useCurrency } from '@/composables/useCurrency';
import { ArrowLeftIcon, ExclamationTriangleIcon } from '@heroicons/vue/24/outline';

const { t } = useI18n();
const route = useRoute();
const { fmtCurrency } = useCurrency();

const analysis = ref(null);
const loading = ref(true);

function formatDate(s) {
    return s ? new Date(s + 'T00:00:00').toLocaleDateString('en-US') : '';
}

function healthTone(health) {
    return { healthy: 'emerald', normal: 'slate', warning: 'amber', critical: 'rose' }[health] ?? 'slate';
}

function healthText(health) {
    return { healthy: 'text-emerald-700', normal: 'text-slate-800', warning: 'text-amber-700', critical: 'text-rose-700' }[health] ?? 'text-slate-800';
}

function barColor(health) {
    return { healthy: 'bg-emerald-500', normal: 'bg-indigo-500', warning: 'bg-amber-500', critical: 'bg-rose-500' }[health] ?? 'bg-indigo-500';
}

const KpiCard = (props) => {
    const palette = {
        slate:   'border-slate-200 bg-white',
        emerald: 'border-emerald-200 bg-emerald-50/40',
        indigo:  'border-indigo-200 bg-indigo-50/40',
        amber:   'border-amber-200 bg-amber-50/40',
        rose:    'border-rose-200 bg-rose-50/40',
    }[props.tone] ?? 'border-slate-200 bg-white';
    return h('div', { class: `border rounded-xl shadow-sm px-4 py-3 ${palette}` }, [
        h('p', { class: 'text-[11px] uppercase tracking-wide text-slate-500 font-medium' }, props.label),
        h('p', { class: 'text-lg font-bold text-slate-900 mt-1 font-mono' }, String(props.value ?? 0)),
    ]);
};
KpiCard.props = ['label', 'value', 'tone'];

const PaceBadge = (props) => {
    const palette = {
        on_track: { tone: 'bg-emerald-100 text-emerald-700', text: t('finance.analytics.paceOnTrack') },
        ahead:    { tone: 'bg-rose-100 text-rose-700',       text: t('finance.analytics.paceAhead') },
        behind:   { tone: 'bg-amber-100 text-amber-700',     text: t('finance.analytics.paceBehind') },
    }[props.pace] ?? { tone: 'bg-slate-100 text-slate-700', text: '' };
    return h('span', { class: `inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium ${palette.tone}` }, palette.text);
};
PaceBadge.props = ['pace'];

function burnPct(value) {
    if (!analysis.value) return 0;
    const max = Math.max(...analysis.value.monthly_burn.map(m => Number(m.total) || 0));
    if (max <= 0) return 0;
    return Math.max(2, (value / max) * 100);
}

async function load() {
    loading.value = true;
    try {
        const { data } = await budgetService.analytics(route.params.id);
        analysis.value = data.data;
    } finally {
        loading.value = false;
    }
}

onMounted(load);
</script>

<style scoped>
@reference '../../../css/app.css';
</style>
