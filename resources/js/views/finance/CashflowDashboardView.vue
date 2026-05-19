<template>
    <div class="p-6 lg:p-8 space-y-6 max-w-7xl mx-auto">

        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 tracking-tight">{{ t('finance.cashflow.title') }}</h1>
                <p class="mt-1 text-sm text-slate-500">{{ t('finance.cashflow.subtitle') }}</p>
            </div>
            <div class="flex items-end gap-2">
                <div>
                    <label class="lbl">{{ t('common.dateFrom') }}</label>
                    <input v-model="filters.from" @change="loadDashboard" type="date" class="ctrl w-40" />
                </div>
                <div>
                    <label class="lbl">{{ t('common.dateTo') }}</label>
                    <input v-model="filters.to" @change="loadDashboard" type="date" class="ctrl w-40" />
                </div>
                <button @click="refreshAll" :disabled="loading.dashboard || loading.alerts" class="btn-soft">
                    <ArrowPathIcon :class="['w-4 h-4', (loading.dashboard || loading.alerts) && 'animate-spin']" />
                    {{ t('finance.cashflow.refresh') }}
                </button>
            </div>
        </div>

        <section v-if="alerts.length" class="space-y-2">
            <AlertCard v-for="(a, i) in alerts" :key="i" :alert="a" />
        </section>

        <section v-if="dashboard" class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <KpiCard :label="t('finance.cashflow.inflow')"  :value="fmtCurrency(dashboard.inflow.total)"  tone="emerald" />
            <KpiCard :label="t('finance.cashflow.outflow')" :value="fmtCurrency(dashboard.outflow.total)" tone="rose" />
            <KpiCard
                :label="t('finance.cashflow.net')"
                :value="fmtCurrency(dashboard.net.amount)"
                :tone="dashboard.net.health === 'positive' ? 'emerald' : 'rose'"
            />
            <KpiCard
                v-if="forecast"
                :label="t('finance.forecast.predictedTotal')"
                :value="fmtCurrency(forecast.predicted_total_next_mo)"
                tone="indigo"
            />
        </section>

        <section v-if="dashboard" class="grid grid-cols-1 lg:grid-cols-2 gap-5">
            <div class="card">
                <h3 class="card-title">{{ t('finance.cashflow.inflowBreakdown') }}</h3>
                <ul class="text-sm divide-y divide-slate-100">
                    <li class="flex justify-between py-2">
                        <span class="text-slate-700">{{ t('finance.cashflow.posCash') }}</span>
                        <span class="font-mono font-semibold text-emerald-700">{{ fmtCurrency(dashboard.inflow.sale_cash) }}</span>
                    </li>
                    <li class="flex justify-between py-2">
                        <span class="text-slate-700">{{ t('finance.cashflow.posCard') }}</span>
                        <span class="font-mono font-semibold text-emerald-700">{{ fmtCurrency(dashboard.inflow.sale_card) }}</span>
                    </li>
                    <li class="flex justify-between py-2">
                        <span class="text-slate-700">{{ t('finance.cashflow.customerPayments') }}</span>
                        <span class="font-mono font-semibold text-emerald-700">{{ fmtCurrency(dashboard.inflow.customer_payments) }}</span>
                    </li>
                    <li class="flex justify-between py-2 border-t-2 border-slate-200">
                        <span class="font-semibold text-slate-900">{{ t('finance.cashflow.total') }}</span>
                        <span class="font-mono font-bold text-emerald-700">{{ fmtCurrency(dashboard.inflow.total) }}</span>
                    </li>
                </ul>
            </div>

            <div class="card">
                <h3 class="card-title">{{ t('finance.cashflow.outflowBreakdown') }}</h3>
                <ul class="text-sm divide-y divide-slate-100">
                    <li class="flex justify-between py-2">
                        <span class="text-slate-700">{{ t('finance.cashflow.paidExpenses') }}</span>
                        <span class="font-mono font-semibold text-rose-700">{{ fmtCurrency(dashboard.outflow.expenses) }}</span>
                    </li>
                    <li class="flex justify-between py-2">
                        <span class="text-slate-700">{{ t('finance.cashflow.supplierPayments') }}</span>
                        <span class="font-mono font-semibold text-rose-700">{{ fmtCurrency(dashboard.outflow.supplier_payments) }}</span>
                    </li>
                    <li class="flex justify-between py-2">
                        <span class="text-slate-700">{{ t('finance.cashflow.payroll') }}</span>
                        <span class="font-mono font-semibold text-rose-700">{{ fmtCurrency(dashboard.outflow.payroll) }}</span>
                    </li>
                    <li class="flex justify-between py-2 border-t-2 border-slate-200">
                        <span class="font-semibold text-slate-900">{{ t('finance.cashflow.total') }}</span>
                        <span class="font-mono font-bold text-rose-700">{{ fmtCurrency(dashboard.outflow.total) }}</span>
                    </li>
                </ul>
            </div>
        </section>

        <section v-if="dashboard?.monthly_trend.length" class="card">
            <h3 class="card-title">{{ t('finance.cashflow.monthlyTrend') }}</h3>
            <div class="space-y-2">
                <div v-for="m in dashboard.monthly_trend" :key="m.month" class="grid grid-cols-12 gap-3 items-center">
                    <span class="col-span-1 text-xs text-slate-600 font-medium">{{ monthNames[m.month - 1] }}</span>
                    <div class="col-span-4 flex justify-end">
                        <div class="h-2 bg-rose-100 rounded-full overflow-hidden" :style="{ width: trendPct(m.outflow) + '%' }">
                            <div class="h-full bg-rose-500"></div>
                        </div>
                    </div>
                    <div class="col-span-4">
                        <div class="h-2 bg-emerald-100 rounded-full overflow-hidden" :style="{ width: trendPct(m.inflow) + '%' }">
                            <div class="h-full bg-emerald-500"></div>
                        </div>
                    </div>
                    <span class="col-span-3 text-right text-xs font-mono" :class="m.net >= 0 ? 'text-emerald-700' : 'text-rose-700'">
                        {{ m.net >= 0 ? '+' : '' }}{{ fmtCurrency(m.net) }}
                    </span>
                </div>
            </div>
            <div class="flex items-center gap-4 mt-4 text-xs text-slate-500">
                <span class="inline-flex items-center gap-1.5">
                    <span class="w-3 h-2 rounded-full bg-emerald-500"></span>
                    {{ t('finance.cashflow.inflow') }}
                </span>
                <span class="inline-flex items-center gap-1.5">
                    <span class="w-3 h-2 rounded-full bg-rose-500"></span>
                    {{ t('finance.cashflow.outflow') }}
                </span>
            </div>
        </section>

        <section v-if="dashboard?.branch_breakdown.length" class="card">
            <h3 class="card-title">{{ t('finance.cashflow.byBranch') }}</h3>
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-[11px] text-slate-500 uppercase tracking-wide border-b border-slate-100">
                        <th class="py-1.5">{{ t('finance.cashflow.branch') }}</th>
                        <th class="py-1.5 text-right">{{ t('finance.cashflow.inflow') }}</th>
                        <th class="py-1.5 text-right">{{ t('finance.cashflow.outflow') }}</th>
                        <th class="py-1.5 text-right">{{ t('finance.cashflow.net') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <tr v-for="b in dashboard.branch_breakdown" :key="b.branch_id">
                        <td class="py-1.5 font-medium text-slate-800">{{ b.branch_name }}</td>
                        <td class="py-1.5 text-right font-mono text-emerald-700">{{ fmtCurrency(b.inflow) }}</td>
                        <td class="py-1.5 text-right font-mono text-rose-700">{{ fmtCurrency(b.outflow) }}</td>
                        <td class="py-1.5 text-right font-mono font-bold" :class="b.net >= 0 ? 'text-emerald-700' : 'text-rose-700'">
                            {{ fmtCurrency(b.net) }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>

        <section v-if="forecast" class="card">
            <h3 class="card-title">{{ t('finance.forecast.title') }}</h3>
            <p class="text-xs text-slate-500 mb-3">{{ t('finance.forecast.subtitle', { months: forecast.lookback_months }) }}</p>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                <div class="bg-slate-50/70 rounded-lg p-3">
                    <p class="text-[11px] uppercase tracking-wide text-slate-500">{{ t('finance.forecast.predictedExpenses') }}</p>
                    <p class="text-base font-bold text-rose-700 mt-1 font-mono">{{ fmtCurrency(forecast.predicted_expenses_next_mo) }}</p>
                </div>
                <div class="bg-slate-50/70 rounded-lg p-3">
                    <p class="text-[11px] uppercase tracking-wide text-slate-500">{{ t('finance.forecast.predictedPayroll') }}</p>
                    <p class="text-base font-bold text-rose-700 mt-1 font-mono">{{ fmtCurrency(forecast.predicted_payroll_next_mo) }}</p>
                </div>
                <div class="bg-indigo-50/70 rounded-lg p-3">
                    <p class="text-[11px] uppercase tracking-wide text-indigo-600">{{ t('finance.forecast.predictedTotal') }}</p>
                    <p class="text-base font-bold text-indigo-700 mt-1 font-mono">{{ fmtCurrency(forecast.predicted_total_next_mo) }}</p>
                </div>
            </div>
            <div v-if="forecast.by_category.length" class="space-y-2">
                <h4 class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">{{ t('finance.forecast.byCategory') }}</h4>
                <div v-for="c in forecast.by_category" :key="c.expense_category_id" class="flex items-center justify-between text-xs">
                    <span class="text-slate-700">{{ c.category_name }}</span>
                    <span class="font-mono text-slate-800">{{ fmtCurrency(c.predicted_next_month) }}</span>
                </div>
            </div>
        </section>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, h } from 'vue';
import { useI18n } from 'vue-i18n';
import { cashflowService, financeAlertService } from '@/services/financeService';
import { useCurrency } from '@/composables/useCurrency';
import { ArrowPathIcon, ExclamationTriangleIcon, ExclamationCircleIcon, InformationCircleIcon } from '@heroicons/vue/24/outline';

const { t, locale } = useI18n();
const { fmtCurrency } = useCurrency();

const dashboard = ref(null);
const alerts    = ref([]);
const forecast  = ref(null);
const loading   = ref({ dashboard: false, alerts: false, forecast: false });

const today      = new Date().toISOString().slice(0, 10);
const monthStart = (() => { const d = new Date(); return new Date(d.getFullYear(), d.getMonth(), 1).toISOString().slice(0, 10); })();
const filters    = ref({ from: monthStart, to: today });

const monthNames = computed(() => {
    const fmt = new Intl.DateTimeFormat(locale.value || 'de-DE', { month: 'short' });
    return Array.from({ length: 12 }, (_, i) => fmt.format(new Date(2025, i, 1)));
});

const KpiCard = (props) => {
    const palette = {
        emerald: 'border-emerald-200 bg-emerald-50/40',
        rose:    'border-rose-200 bg-rose-50/40',
        indigo:  'border-indigo-200 bg-indigo-50/40',
        slate:   'border-slate-200 bg-white',
    }[props.tone] ?? 'border-slate-200 bg-white';
    return h('div', { class: `border rounded-xl shadow-sm px-4 py-3 ${palette}` }, [
        h('p', { class: 'text-[11px] uppercase tracking-wide text-slate-500 font-medium' }, props.label),
        h('p', { class: 'text-xl font-bold text-slate-900 mt-1 font-mono' }, String(props.value)),
    ]);
};
KpiCard.props = ['label', 'value', 'tone'];

const AlertCard = (props) => {
    const a = props.alert;
    const palette = {
        critical: { wrap: 'border-rose-200 bg-rose-50',     text: 'text-rose-700',     icon: 'text-rose-600',   iconComp: ExclamationCircleIcon },
        warning:  { wrap: 'border-amber-200 bg-amber-50',   text: 'text-amber-700',    icon: 'text-amber-600',  iconComp: ExclamationTriangleIcon },
        info:     { wrap: 'border-indigo-200 bg-indigo-50', text: 'text-indigo-700',   icon: 'text-indigo-600', iconComp: InformationCircleIcon },
    }[a.severity] ?? { wrap: 'border-slate-200 bg-slate-50', text: 'text-slate-700', icon: 'text-slate-600', iconComp: InformationCircleIcon };

    return h('div', { class: `flex items-start gap-3 px-4 py-3 rounded-lg border ${palette.wrap}` }, [
        h(palette.iconComp, { class: `w-5 h-5 mt-0.5 flex-shrink-0 ${palette.icon}` }),
        h('div', { class: 'flex-1 min-w-0' }, [
            h('p', { class: `text-sm font-medium ${palette.text}` }, a.message),
            a.amount !== null
                ? h('p', { class: 'text-xs text-slate-600 mt-0.5 font-mono' }, fmtCurrency(a.amount))
                : null,
        ]),
    ]);
};
AlertCard.props = ['alert'];

function trendPct(value) {
    if (!dashboard.value) return 0;
    const max = Math.max(
        ...dashboard.value.monthly_trend.flatMap(m => [Number(m.inflow) || 0, Number(m.outflow) || 0])
    );
    if (max <= 0) return 0;
    return Math.max(0, (value / max) * 100);
}

async function loadDashboard() {
    loading.value.dashboard = true;
    try {
        const { data } = await cashflowService.dashboard(filters.value);
        dashboard.value = data.data;
    } finally {
        loading.value.dashboard = false;
    }
}

async function loadAlerts() {
    loading.value.alerts = true;
    try {
        const { data } = await financeAlertService.list();
        alerts.value = data.data ?? [];
    } finally {
        loading.value.alerts = false;
    }
}

async function loadForecast() {
    loading.value.forecast = true;
    try {
        const { data } = await cashflowService.forecast({ lookback_months: 3 });
        forecast.value = data.data;
    } finally {
        loading.value.forecast = false;
    }
}

function refreshAll() {
    loadDashboard();
    loadAlerts();
    loadForecast();
}

onMounted(refreshAll);
</script>

<style scoped>
@reference '../../../css/app.css';
.card       { @apply bg-white border border-slate-200 rounded-xl shadow-sm p-5; }
.card-title { @apply text-xs font-bold text-slate-500 uppercase tracking-wider mb-3; }
.btn-soft   { @apply inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-slate-300 text-slate-700 text-sm hover:bg-slate-50 transition-colors disabled:opacity-50; }
.lbl        { @apply block text-xs font-medium text-slate-600 mb-1; }
.ctrl       { @apply px-3 py-2 text-sm border border-slate-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent; }
</style>
