<template>
    <div class="p-6 lg:p-8 space-y-8 max-w-7xl mx-auto">

        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-3">
                <RouterLink :to="{ name: 'expenses' }" class="p-2 text-slate-400 hover:text-slate-700 hover:bg-slate-100 rounded-lg">
                    <ArrowLeftIcon class="w-5 h-5" />
                </RouterLink>
                <div>
                    <h1 class="text-2xl font-bold text-slate-900 tracking-tight">{{ t('expenses.reports.title') }}</h1>
                    <p class="mt-1 text-sm text-slate-500">{{ t('expenses.reports.subtitle') }}</p>
                </div>
            </div>
            <button @click="refreshAll" :disabled="anyLoading" class="btn-soft">
                <ArrowPathIcon :class="['w-4 h-4', anyLoading && 'animate-spin']" />
                {{ t('expenses.reports.refresh') }}
            </button>
        </div>

        <section v-if="dashboard" class="space-y-5">
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
                <KpiCard :label="t('expenses.reports.kpi_total')"      :value="fmtCurrency(dashboard.total_amount)"     tone="slate" />
                <KpiCard :label="t('expenses.reports.kpi_thisMonth')"  :value="fmtCurrency(dashboard.this_month_total)" tone="indigo" />
                <KpiCard :label="t('expenses.reports.kpi_lastMonth')"  :value="fmtCurrency(dashboard.last_month_total)" tone="slate" />
                <KpiCard :label="t('expenses.reports.kpi_paid')"       :value="fmtCurrency(dashboard.paid_total)"       tone="emerald" />
                <KpiCard :label="t('expenses.reports.kpi_unpaid')"     :value="fmtCurrency(dashboard.unpaid_total)"     tone="amber" />
                <KpiCard
                    :label="t('expenses.reports.kpi_momDelta')"
                    :value="dashboard.mom_delta === null ? '—' : `${dashboard.mom_delta >= 0 ? '+' : ''}${dashboard.mom_delta}%`"
                    :tone="dashboard.mom_delta === null ? 'slate' : (dashboard.mom_delta >= 0 ? 'rose' : 'emerald')"
                />
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                <div class="card">
                    <h3 class="card-title">{{ t('expenses.reports.topCategories') }}</h3>
                    <BarList
                        :rows="dashboard.top_categories"
                        name-key="name"
                        value-key="total"
                        :format-value="fmtCurrency"
                        tone="sky"
                    />
                </div>
                <div class="card">
                    <h3 class="card-title">{{ t('expenses.reports.byPaymentMethod') }}</h3>
                    <BarList
                        :rows="paymentMethodRows"
                        name-key="label"
                        value-key="total"
                        :format-value="fmtCurrency"
                        tone="indigo"
                    />
                </div>
            </div>
        </section>

        <section class="space-y-3">
            <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3">
                <div>
                    <h2 class="text-lg font-bold text-slate-900">{{ t('expenses.reports.categoryBreakdown') }}</h2>
                    <p class="text-xs text-slate-500 mt-0.5">{{ t('expenses.reports.categoryHint') }}</p>
                </div>
                <div class="flex items-end gap-2">
                    <div>
                        <label class="lbl">{{ t('common.dateFrom') }}</label>
                        <input v-model="categoryFilters.from" @change="loadCategoryBreakdown" type="date" class="ctrl w-40" />
                    </div>
                    <div>
                        <label class="lbl">{{ t('common.dateTo') }}</label>
                        <input v-model="categoryFilters.to" @change="loadCategoryBreakdown" type="date" class="ctrl w-40" />
                    </div>
                </div>
            </div>

            <div class="card">
                <table v-if="categoryReport && categoryReport.rows.length" class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-[11px] text-slate-500 uppercase tracking-wide border-b border-slate-100">
                            <th class="py-2">{{ t('expenses.fields.category') }}</th>
                            <th class="py-2">{{ t('expenses.fields.code') }}</th>
                            <th class="py-2 text-right">{{ t('expenses.reports.count') }}</th>
                            <th class="py-2 text-right">{{ t('expenses.fields.amount') }}</th>
                            <th class="py-2 text-right w-40">{{ t('expenses.reports.share') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <tr v-for="r in categoryReport.rows" :key="r.id">
                            <td class="py-2 font-medium text-slate-800">{{ r.name }}</td>
                            <td class="py-2 font-mono text-xs text-slate-500">{{ r.code || '—' }}</td>
                            <td class="py-2 text-right font-mono text-slate-700">{{ r.count }}</td>
                            <td class="py-2 text-right font-mono font-semibold text-slate-900">{{ fmtCurrency(r.total) }}</td>
                            <td class="py-2">
                                <div class="flex items-center justify-end gap-2">
                                    <div class="flex-1 h-2 bg-slate-100 rounded-full overflow-hidden max-w-[120px]">
                                        <div class="h-full bg-sky-500 rounded-full" :style="{ width: r.share + '%' }" />
                                    </div>
                                    <span class="text-xs font-mono text-slate-600 w-12 text-right">{{ r.share }}%</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="border-t-2 border-slate-200">
                            <td colspan="3" class="py-2.5 font-semibold text-slate-800">{{ t('expenses.reports.grandTotal') }}</td>
                            <td class="py-2.5 text-right font-mono font-bold text-slate-900">{{ fmtCurrency(categoryReport.grand_total) }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
                <p v-else class="text-sm text-slate-400 py-6 text-center">{{ t('expenses.reports.noData') }}</p>
            </div>
        </section>

        <section class="space-y-3">
            <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3">
                <div>
                    <h2 class="text-lg font-bold text-slate-900">{{ t('expenses.reports.monthlyTrend') }}</h2>
                    <p class="text-xs text-slate-500 mt-0.5">{{ t('expenses.reports.monthlyHint') }}</p>
                </div>
                <div>
                    <label class="lbl">{{ t('expenses.reports.year') }}</label>
                    <select v-model.number="trendYear" @change="loadMonthlyTrend" class="ctrl w-28">
                        <option v-for="y in yearChoices" :key="y" :value="y">{{ y }}</option>
                    </select>
                </div>
            </div>

            <div class="card">
                <div v-if="trend && trend.months.length" class="space-y-2">
                    <div v-for="m in trend.months" :key="m.month" class="grid grid-cols-12 gap-3 items-center">
                        <span class="col-span-2 text-xs text-slate-600 font-medium">{{ monthNames[m.month - 1] }}</span>
                        <div class="col-span-7 h-2 bg-slate-100 rounded-full overflow-hidden">
                            <div class="h-full bg-indigo-500 rounded-full" :style="{ width: trendPct(m.total) + '%' }" />
                        </div>
                        <span class="col-span-3 text-right font-mono text-xs text-slate-800">{{ fmtCurrency(m.total) }}</span>
                    </div>
                    <div class="pt-3 mt-3 border-t border-slate-100 flex items-center justify-between">
                        <span class="text-sm font-semibold text-slate-700">{{ t('expenses.reports.yearTotal') }}</span>
                        <span class="font-mono font-bold text-indigo-700">{{ fmtCurrency(trend.total) }}</span>
                    </div>
                </div>
                <p v-else class="text-sm text-slate-400 py-6 text-center">{{ t('expenses.reports.noData') }}</p>
            </div>
        </section>

        <section v-if="branchReport && branchReport.rows.length" class="space-y-3">
            <div>
                <h2 class="text-lg font-bold text-slate-900">{{ t('expenses.reports.branchBreakdown') }}</h2>
                <p class="text-xs text-slate-500 mt-0.5">{{ t('expenses.reports.branchHint') }}</p>
            </div>
            <div class="card">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-[11px] text-slate-500 uppercase tracking-wide border-b border-slate-100">
                            <th class="py-2">{{ t('expenses.fields.branch') }}</th>
                            <th class="py-2 text-right">{{ t('expenses.reports.count') }}</th>
                            <th class="py-2 text-right">{{ t('expenses.status_pending') }}</th>
                            <th class="py-2 text-right">{{ t('expenses.status_paid') }}</th>
                            <th class="py-2 text-right">{{ t('expenses.reports.total') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <tr v-for="r in branchReport.rows" :key="r.id">
                            <td class="py-2 font-medium text-slate-800">{{ r.name }}</td>
                            <td class="py-2 text-right font-mono">{{ r.count }}</td>
                            <td class="py-2 text-right font-mono text-amber-700">{{ fmtCurrency(r.pending) }}</td>
                            <td class="py-2 text-right font-mono text-emerald-700">{{ fmtCurrency(r.paid) }}</td>
                            <td class="py-2 text-right font-mono font-semibold text-slate-900">{{ fmtCurrency(r.total) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, h } from 'vue';
import { useI18n } from 'vue-i18n';
import { RouterLink } from 'vue-router';
import { expenseReportsService } from '@/services/expenseService';
import { useCurrency } from '@/composables/useCurrency';
import { ArrowLeftIcon, ArrowPathIcon } from '@heroicons/vue/24/outline';

const { t, locale } = useI18n();
const { fmtCurrency } = useCurrency();

const dashboard      = ref(null);
const categoryReport = ref(null);
const trend          = ref(null);
const branchReport   = ref(null);

const loading = ref({ dashboard: false, category: false, trend: false, branch: false });
const anyLoading = computed(() => Object.values(loading.value).some(Boolean));

const today      = new Date().toISOString().slice(0, 10);
const monthStart = (() => { const d = new Date(); return new Date(d.getFullYear(), d.getMonth(), 1).toISOString().slice(0, 10); })();

const categoryFilters = ref({ from: monthStart, to: today });
const trendYear       = ref(new Date().getFullYear());

const yearChoices = computed(() => {
    const cur = new Date().getFullYear();
    return [cur - 3, cur - 2, cur - 1, cur, cur + 1];
});

const monthNames = computed(() => {
    const fmt = new Intl.DateTimeFormat(locale.value || 'de-DE', { month: 'short' });
    return Array.from({ length: 12 }, (_, i) => fmt.format(new Date(2025, i, 1)));
});

const paymentMethodRows = computed(() => {
    if (!dashboard.value?.by_payment_method) return [];
    return dashboard.value.by_payment_method.map(r => ({
        label: r.method === 'cheque' ? t('expenses.paymentMethod.cheque') : t('paymentMethod.' + r.method),
        total: r.total,
        count: r.count,
    }));
});

function trendPct(value) {
    if (!trend.value) return 0;
    const max = Math.max(...trend.value.months.map(m => Number(m.total) || 0));
    if (max <= 0) return 0;
    return Math.max(2, (value / max) * 100);
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

const BarList = (props) => {
    const rows = props.rows ?? [];
    if (rows.length === 0) {
        return h('p', { class: 'text-sm text-slate-400 py-4 text-center' }, t('expenses.reports.noData'));
    }
    const max = Math.max(...rows.map(r => Number(r[props.valueKey] ?? 0))) || 1;
    const palette = {
        sky:     'bg-sky-500',
        indigo:  'bg-indigo-500',
        emerald: 'bg-emerald-500',
        amber:   'bg-amber-500',
    };
    const tone = palette[props.tone] ?? 'bg-sky-500';
    return h('div', { class: 'space-y-2' }, rows.map((r, idx) => {
        const value = Number(r[props.valueKey] ?? 0);
        const pct = max > 0 ? Math.max(2, (value / max) * 100) : 0;
        const display = props.formatValue ? props.formatValue(value) : value;
        return h('div', { key: r[props.nameKey] ?? idx, class: 'space-y-0.5' }, [
            h('div', { class: 'flex items-center justify-between text-xs' }, [
                h('span', { class: 'text-slate-700' }, String(r[props.nameKey] ?? '—')),
                h('span', { class: 'font-mono font-medium text-slate-800' }, String(display)),
            ]),
            h('div', { class: 'h-2 bg-slate-100 rounded-full overflow-hidden' }, [
                h('div', { class: `${tone} h-full rounded-full`, style: { width: pct + '%' } }),
            ]),
        ]);
    }));
};
BarList.props = ['rows', 'nameKey', 'valueKey', 'tone', 'formatValue'];

async function loadDashboard() {
    loading.value.dashboard = true;
    try {
        const { data } = await expenseReportsService.dashboard();
        dashboard.value = data.data;
    } finally { loading.value.dashboard = false; }
}

async function loadCategoryBreakdown() {
    loading.value.category = true;
    try {
        const { data } = await expenseReportsService.categoryBreakdown(categoryFilters.value);
        categoryReport.value = data.data;
    } finally { loading.value.category = false; }
}

async function loadMonthlyTrend() {
    loading.value.trend = true;
    try {
        const { data } = await expenseReportsService.monthlyTrend({ year: trendYear.value });
        trend.value = data.data;
    } finally { loading.value.trend = false; }
}

async function loadBranchBreakdown() {
    loading.value.branch = true;
    try {
        const { data } = await expenseReportsService.branchBreakdown(categoryFilters.value);
        branchReport.value = data.data;
    } finally { loading.value.branch = false; }
}

function refreshAll() {
    loadDashboard();
    loadCategoryBreakdown();
    loadMonthlyTrend();
    loadBranchBreakdown();
}

onMounted(refreshAll);
</script>

<style scoped>
@reference '../../../css/app.css';
.card       { @apply bg-white border border-slate-200 rounded-xl shadow-sm p-5; }
.card-title { @apply text-xs font-bold text-slate-500 uppercase tracking-wider mb-3; }
.btn-soft   { @apply inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-slate-300 text-slate-700 text-sm hover:bg-slate-50 transition-colors disabled:opacity-50; }
.lbl        { @apply block text-xs font-medium text-slate-600 mb-1; }
.ctrl       { @apply px-3 py-2 text-sm border border-slate-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent; }
</style>
