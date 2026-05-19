<template>
    <div class="p-6 lg:p-8 space-y-6 max-w-7xl mx-auto">

        <header class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
            <div>
                <p class="text-xs font-medium text-indigo-600 uppercase tracking-wider mb-1">
                    {{ greetingLabel }}, {{ userName }}
                </p>
                <h1 class="text-2xl font-bold text-slate-900 tracking-tight">
                    {{ t('finance.dashboard.title') }}
                </h1>
                <p class="mt-1 text-sm text-slate-500">{{ t('finance.dashboard.subtitle') }}</p>
            </div>
            <div class="flex flex-wrap items-end gap-2">
                <div>
                    <label class="lbl">{{ t('common.dateFrom') }}</label>
                    <input v-model="filters.from" @change="reload" type="date" class="ctrl w-40" />
                </div>
                <div>
                    <label class="lbl">{{ t('common.dateTo') }}</label>
                    <input v-model="filters.to" @change="reload" type="date" class="ctrl w-40" />
                </div>
                <div>
                    <label class="lbl">{{ t('finance.dashboard.preset') }}</label>
                    <select v-model="preset" @change="applyPreset" class="ctrl w-40">
                        <option value="this_month">{{ t('finance.dashboard.preset_thisMonth') }}</option>
                        <option value="last_month">{{ t('finance.dashboard.preset_lastMonth') }}</option>
                        <option value="this_quarter">{{ t('finance.dashboard.preset_thisQuarter') }}</option>
                        <option value="ytd">{{ t('finance.dashboard.preset_ytd') }}</option>
                        <option value="custom">{{ t('finance.dashboard.preset_custom') }}</option>
                    </select>
                </div>
                <button @click="reload" :disabled="anyLoading" class="btn-soft">
                    <ArrowPathIcon :class="['w-4 h-4', anyLoading && 'animate-spin']" />
                    {{ t('finance.dashboard.refresh') }}
                </button>
            </div>
        </header>

        <section v-if="insights.length" class="space-y-2">
            <InsightCard v-for="(it, i) in insights" :key="i" :item="it" />
        </section>

        <section class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-5 gap-3">
            <KpiCard
                :label="t('finance.dashboard.kpi.totalSales')"
                :value="kpis ? fmtCurrency(kpis.total_sales) : '—'"
                :sub="growthLabel(kpis?.revenue_growth_percent)"
                :subTone="growthTone(kpis?.revenue_growth_percent)"
                tone="indigo"
                :icon="BanknotesIcon"
            />
            <KpiCard
                :label="t('finance.dashboard.kpi.grossProfit')"
                :value="kpis ? fmtCurrency(kpis.gross_profit) : '—'"
                :sub="kpis ? kpis.gross_margin_percent + '% ' + t('finance.dashboard.kpi.margin') : ''"
                tone="emerald"
                :icon="ArrowTrendingUpIcon"
            />
            <KpiCard
                :label="t('finance.dashboard.kpi.netProfit')"
                :value="kpis ? fmtCurrency(kpis.net_profit) : '—'"
                :sub="kpis ? kpis.net_margin_percent + '% ' + t('finance.dashboard.kpi.margin') : ''"
                :tone="(kpis?.net_profit ?? 0) >= 0 ? 'emerald' : 'rose'"
                :icon="ChartBarIcon"
            />
            <KpiCard
                :label="t('finance.dashboard.kpi.totalExpenses')"
                :value="kpis ? fmtCurrency(kpis.total_expenses) : '—'"
                tone="rose"
                :icon="ReceiptPercentIcon"
            />
            <KpiCard
                :label="t('finance.dashboard.kpi.payroll')"
                :value="kpis ? fmtCurrency(kpis.payroll_expenses) : '—'"
                tone="amber"
                :icon="UserGroupIcon"
            />
            <KpiCard
                :label="t('finance.dashboard.kpi.totalPurchases')"
                :value="kpis ? fmtCurrency(kpis.total_purchases) : '—'"
                tone="slate"
                :icon="ShoppingCartIcon"
            />
            <KpiCard
                :label="t('finance.dashboard.kpi.inventoryValue')"
                :value="kpis ? fmtCurrency(kpis.inventory_value) : '—'"
                tone="slate"
                :icon="CubeIcon"
            />
            <KpiCard
                :label="t('finance.dashboard.kpi.receivables')"
                :value="kpis ? fmtCurrency(kpis.outstanding_receivables) : '—'"
                :sub="t('finance.dashboard.kpi.unpaidInvoices')"
                tone="amber"
                :icon="DocumentTextIcon"
            />
            <KpiCard
                :label="t('finance.dashboard.kpi.payables')"
                :value="kpis ? fmtCurrency(kpis.outstanding_payables) : '—'"
                :sub="t('finance.dashboard.kpi.unpaidSuppliers')"
                tone="rose"
                :icon="BuildingStorefrontIcon"
            />
            <KpiCard
                :label="t('finance.dashboard.kpi.revenueGrowth')"
                :value="kpis ? (kpis.revenue_growth_percent + '%') : '—'"
                :sub="t('finance.dashboard.kpi.vsPrevious')"
                :tone="(kpis?.revenue_growth_percent ?? 0) >= 0 ? 'emerald' : 'rose'"
                :icon="ArrowTrendingUpIcon"
            />
        </section>

        <section v-if="salesTrend" class="card">
            <div class="flex items-start justify-between mb-4">
                <div>
                    <h3 class="card-title mb-0">{{ t('finance.dashboard.salesTrend.title') }}</h3>
                    <p class="text-xs text-slate-500 mt-1">{{ t('finance.dashboard.salesTrend.subtitle', { year: salesTrend.year }) }}</p>
                </div>
                <div class="flex items-center gap-3 text-xs text-slate-500">
                    <span class="inline-flex items-center gap-1.5">
                        <span class="w-3 h-2 rounded-sm bg-indigo-500"></span>
                        {{ t('finance.dashboard.kpi.totalSales') }}
                    </span>
                    <span class="inline-flex items-center gap-1.5">
                        <span class="w-3 h-2 rounded-sm bg-rose-400"></span>
                        {{ t('finance.dashboard.salesTrend.expenses') }}
                    </span>
                </div>
            </div>
            <div class="flex items-end gap-2 h-48">
                <div v-for="m in salesTrend.data" :key="m.month" class="flex-1 flex flex-col items-center gap-1">
                    <div class="w-full flex items-end justify-center gap-0.5 h-44">
                        <div class="w-3 bg-indigo-500 rounded-t hover:bg-indigo-600 transition-colors"
                             :style="{ height: trendBar(m.sales) + '%' }"
                             :title="t('finance.dashboard.kpi.totalSales') + ': ' + fmtCurrency(m.sales)"></div>
                        <div class="w-3 bg-rose-400 rounded-t hover:bg-rose-500 transition-colors"
                             :style="{ height: trendBar(m.expenses) + '%' }"
                             :title="t('finance.dashboard.salesTrend.expenses') + ': ' + fmtCurrency(m.expenses)"></div>
                    </div>
                    <span class="text-[10px] text-slate-500 font-medium">{{ monthNames[m.month - 1] }}</span>
                </div>
            </div>
        </section>

        <section v-if="profitAnalysis" class="card">
            <h3 class="card-title">{{ t('finance.dashboard.profit.title') }}</h3>
            <div class="grid grid-cols-1 md:grid-cols-6 gap-2 mb-5">
                <WaterfallStep v-for="s in profitAnalysis.waterfall" :key="s.label"
                    :label="t('finance.dashboard.profit.' + s.label)"
                    :value="s.value"
                />
            </div>
            <div>
                <h4 class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-3">
                    {{ t('finance.dashboard.profit.monthly') }}
                </h4>
                <div class="space-y-1.5">
                    <div v-for="m in profitAnalysis.monthly" :key="m.month" class="grid grid-cols-12 gap-3 items-center text-xs">
                        <span class="col-span-1 text-slate-600 font-medium">{{ monthNames[m.month - 1] }}</span>
                        <div class="col-span-9 relative h-3 bg-slate-100 rounded-full overflow-hidden">
                            <div
                                class="absolute top-0 h-full rounded-full"
                                :class="m.profit >= 0 ? 'bg-emerald-500' : 'bg-rose-500'"
                                :style="profitBarStyle(m.profit)"
                            ></div>
                            <span class="absolute inset-0 flex items-center justify-center text-slate-400 text-[9px]">|</span>
                        </div>
                        <span class="col-span-2 text-right font-mono"
                              :class="m.profit >= 0 ? 'text-emerald-700' : 'text-rose-700'">
                            {{ fmtCurrency(m.profit) }}
                        </span>
                    </div>
                </div>
            </div>
        </section>

        <section v-if="branchRows.length" class="card">
            <h3 class="card-title">{{ t('finance.dashboard.branch.title') }}</h3>
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-[11px] text-slate-500 uppercase tracking-wide border-b border-slate-100">
                        <th class="py-2">{{ t('finance.dashboard.branch.branch') }}</th>
                        <th class="py-2 text-right">{{ t('finance.dashboard.kpi.totalSales') }}</th>
                        <th class="py-2 text-right">{{ t('finance.dashboard.salesTrend.expenses') }}</th>
                        <th class="py-2 text-right">{{ t('finance.dashboard.branch.profit') }}</th>
                        <th class="py-2 text-right w-32">{{ t('finance.dashboard.branch.margin') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <tr v-for="b in branchRows" :key="b.branch_id">
                        <td class="py-2 font-medium text-slate-800">{{ b.branch_name }}</td>
                        <td class="py-2 text-right font-mono text-slate-800">{{ fmtCurrency(b.sales) }}</td>
                        <td class="py-2 text-right font-mono text-rose-700">{{ fmtCurrency(b.expenses) }}</td>
                        <td class="py-2 text-right font-mono font-semibold"
                            :class="b.profit >= 0 ? 'text-emerald-700' : 'text-rose-700'">
                            {{ fmtCurrency(b.profit) }}
                        </td>
                        <td class="py-2 text-right">
                            <div class="inline-flex items-center gap-2">
                                <div class="w-16 h-1.5 bg-slate-100 rounded-full overflow-hidden">
                                    <div class="h-full rounded-full"
                                         :class="b.margin >= 0 ? 'bg-emerald-500' : 'bg-rose-500'"
                                         :style="{ width: Math.min(100, Math.abs(b.margin)) + '%' }"></div>
                                </div>
                                <span class="font-mono text-xs text-slate-700 w-10 text-right">{{ b.margin }}%</span>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
            <section class="card">
                <h3 class="card-title">{{ t('finance.dashboard.topProducts.title') }}</h3>
                <div v-if="topProducts.length" class="space-y-1.5">
                    <div v-for="(p, i) in topProducts" :key="p.product_id" class="flex items-center justify-between text-sm py-1.5 border-b border-slate-50 last:border-0">
                        <div class="min-w-0 flex-1 flex items-center gap-2">
                            <span class="text-xs text-slate-400 font-mono w-6">#{{ i + 1 }}</span>
                            <div class="min-w-0">
                                <p class="font-medium text-slate-800 truncate">{{ p.name }}</p>
                                <p class="text-[11px] text-slate-500">{{ p.sku }} · {{ p.qty_sold }} {{ t('finance.dashboard.topProducts.sold') }}</p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="font-mono text-sm text-slate-800">{{ fmtCurrency(p.revenue) }}</p>
                            <p class="text-[11px] text-emerald-700 font-mono">+{{ fmtCurrency(p.profit) }} · {{ p.margin }}%</p>
                        </div>
                    </div>
                </div>
                <p v-else class="text-center text-sm text-slate-400 py-6">{{ t('finance.dashboard.empty') }}</p>
            </section>

            <section class="card">
                <h3 class="card-title">{{ t('finance.dashboard.topCustomers.title') }}</h3>
                <div v-if="topCustomers.length" class="space-y-1.5">
                    <div v-for="(c, i) in topCustomers" :key="c.customer_id" class="flex items-center justify-between text-sm py-1.5 border-b border-slate-50 last:border-0">
                        <div class="min-w-0 flex-1 flex items-center gap-2">
                            <span class="text-xs text-slate-400 font-mono w-6">#{{ i + 1 }}</span>
                            <div class="min-w-0">
                                <p class="font-medium text-slate-800 truncate">{{ c.name }}</p>
                                <p class="text-[11px] text-slate-500">{{ c.invoice_count }} {{ t('finance.dashboard.topCustomers.invoices') }}</p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="font-mono text-sm text-slate-800">{{ fmtCurrency(c.revenue) }}</p>
                            <p v-if="c.outstanding > 0" class="text-[11px] text-amber-700 font-mono">
                                {{ t('finance.dashboard.topCustomers.open') }}: {{ fmtCurrency(c.outstanding) }}
                            </p>
                        </div>
                    </div>
                </div>
                <p v-else class="text-center text-sm text-slate-400 py-6">{{ t('finance.dashboard.empty') }}</p>
            </section>
        </div>

        <section v-if="expenseBreakdown.length" class="card">
            <h3 class="card-title">{{ t('finance.dashboard.expenseBreakdown.title') }}</h3>
            <div class="space-y-2.5">
                <div v-for="row in expenseBreakdown" :key="row.category_id" class="grid grid-cols-12 gap-3 items-center">
                    <span class="col-span-3 text-sm font-medium text-slate-700 truncate">{{ row.name }}</span>
                    <div class="col-span-7 h-3 bg-slate-100 rounded-full overflow-hidden">
                        <div class="h-full bg-gradient-to-r from-rose-400 to-rose-600 rounded-full"
                             :style="{ width: row.percent + '%' }"></div>
                    </div>
                    <span class="col-span-2 text-right text-xs font-mono text-slate-700">
                        {{ fmtCurrency(row.amount) }} · {{ row.percent }}%
                    </span>
                </div>
            </div>
        </section>

        <section v-if="inventory" class="card">
            <h3 class="card-title">{{ t('finance.dashboard.inventory.title') }}</h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
                <div class="bg-slate-50/70 rounded-lg p-3">
                    <p class="text-[11px] uppercase tracking-wide text-slate-500">{{ t('finance.dashboard.inventory.stockValue') }}</p>
                    <p class="text-base font-bold text-slate-900 mt-1 font-mono">{{ fmtCurrency(inventory.stock_value) }}</p>
                </div>
                <div class="bg-slate-50/70 rounded-lg p-3">
                    <p class="text-[11px] uppercase tracking-wide text-slate-500">{{ t('finance.dashboard.inventory.stockUnits') }}</p>
                    <p class="text-base font-bold text-slate-900 mt-1 font-mono">{{ inventory.stock_units }}</p>
                </div>
                <div class="bg-amber-50/70 rounded-lg p-3">
                    <p class="text-[11px] uppercase tracking-wide text-amber-600">{{ t('finance.dashboard.inventory.lowStock') }}</p>
                    <p class="text-base font-bold text-amber-700 mt-1 font-mono">{{ inventory.low_stock_count }}</p>
                </div>
                <div class="bg-rose-50/70 rounded-lg p-3">
                    <p class="text-[11px] uppercase tracking-wide text-rose-600">{{ t('finance.dashboard.inventory.outOfStock') }}</p>
                    <p class="text-base font-bold text-rose-700 mt-1 font-mono">{{ inventory.out_of_stock_count }}</p>
                </div>
            </div>
            <div v-if="inventory.low_stock_items.length" class="text-sm">
                <h4 class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-2">
                    {{ t('finance.dashboard.inventory.lowStockList') }}
                </h4>
                <ul class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-1">
                    <li v-for="p in inventory.low_stock_items" :key="p.product_id"
                        class="flex justify-between border-b border-slate-50 py-1">
                        <span class="text-slate-700 truncate">{{ p.name }} <span class="text-slate-400 text-[11px]">({{ p.sku }})</span></span>
                        <span class="font-mono text-xs text-amber-700 whitespace-nowrap">
                            {{ p.quantity }} / {{ p.reorder_level }}
                        </span>
                    </li>
                </ul>
            </div>
        </section>

        <section class="card">
            <h3 class="card-title">{{ t('finance.dashboard.quickActions.title') }}</h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <RouterLink :to="{ name: 'finance-budgets' }" class="quick-link">
                    <ClipboardDocumentListIcon class="w-5 h-5 text-indigo-500" />
                    <span>{{ t('finance.dashboard.quickActions.budgets') }}</span>
                </RouterLink>
                <RouterLink :to="{ name: 'finance-cashflow' }" class="quick-link">
                    <ChartBarIcon class="w-5 h-5 text-emerald-500" />
                    <span>{{ t('finance.dashboard.quickActions.cashflow') }}</span>
                </RouterLink>
                <RouterLink :to="{ name: 'finance-calendar' }" class="quick-link">
                    <CalendarDaysIcon class="w-5 h-5 text-amber-500" />
                    <span>{{ t('finance.dashboard.quickActions.calendar') }}</span>
                </RouterLink>
                <RouterLink :to="{ name: 'expense-reports' }" class="quick-link">
                    <DocumentChartBarIcon class="w-5 h-5 text-rose-500" />
                    <span>{{ t('finance.dashboard.quickActions.expenseReports') }}</span>
                </RouterLink>
            </div>
        </section>

    </div>
</template>

<script setup>
import { ref, computed, onMounted, h } from 'vue';
import { useI18n } from 'vue-i18n';
import { RouterLink } from 'vue-router';
import { financialDashboardService } from '@/services/financeService';
import { useCurrency } from '@/composables/useCurrency';
import { useAuthStore } from '@/stores/auth';
import {
    ArrowPathIcon, BanknotesIcon, ArrowTrendingUpIcon, ChartBarIcon,
    ReceiptPercentIcon, UserGroupIcon, ShoppingCartIcon, CubeIcon,
    DocumentTextIcon, BuildingStorefrontIcon, ClipboardDocumentListIcon,
    CalendarDaysIcon, DocumentChartBarIcon,
    ExclamationTriangleIcon, ExclamationCircleIcon, InformationCircleIcon, CheckCircleIcon,
} from '@heroicons/vue/24/outline';

const { t, locale } = useI18n();
const { fmtCurrency } = useCurrency();
const auth = useAuthStore();

const today = () => new Date().toISOString().slice(0, 10);
const monthStart = () => { const d = new Date(); return new Date(d.getFullYear(), d.getMonth(), 1).toISOString().slice(0, 10); };

const filters = ref({ from: monthStart(), to: today() });
const preset  = ref('this_month');

const kpis             = ref(null);
const insights         = ref([]);
const salesTrend       = ref(null);
const profitAnalysis   = ref(null);
const branchRows       = ref([]);
const topProducts      = ref([]);
const topCustomers     = ref([]);
const expenseBreakdown = ref([]);
const inventory        = ref(null);

const loading = ref({
    dashboard: false, trend: false, profit: false,
    branch: false, products: false, customers: false,
    expense: false, inventory: false,
});
const anyLoading = computed(() => Object.values(loading.value).some(Boolean));

const userName = computed(() => auth.user?.name?.split(' ')[0] ?? '');
const greetingLabel = computed(() => {
    const h = new Date().getHours();
    if (h < 11) return t('finance.dashboard.greetingMorning');
    if (h < 18) return t('finance.dashboard.greetingDay');
    return t('finance.dashboard.greetingEvening');
});

const monthNames = computed(() => {
    const fmt = new Intl.DateTimeFormat(locale.value || 'de-DE', { month: 'short' });
    return Array.from({ length: 12 }, (_, i) => fmt.format(new Date(2025, i, 1)));
});

function applyPreset() {
    const now = new Date();
    if (preset.value === 'this_month') {
        filters.value.from = new Date(now.getFullYear(), now.getMonth(), 1).toISOString().slice(0, 10);
        filters.value.to   = new Date(now.getFullYear(), now.getMonth() + 1, 0).toISOString().slice(0, 10);
    } else if (preset.value === 'last_month') {
        filters.value.from = new Date(now.getFullYear(), now.getMonth() - 1, 1).toISOString().slice(0, 10);
        filters.value.to   = new Date(now.getFullYear(), now.getMonth(), 0).toISOString().slice(0, 10);
    } else if (preset.value === 'this_quarter') {
        const q = Math.floor(now.getMonth() / 3);
        filters.value.from = new Date(now.getFullYear(), q * 3, 1).toISOString().slice(0, 10);
        filters.value.to   = new Date(now.getFullYear(), q * 3 + 3, 0).toISOString().slice(0, 10);
    } else if (preset.value === 'ytd') {
        filters.value.from = new Date(now.getFullYear(), 0, 1).toISOString().slice(0, 10);
        filters.value.to   = today();
    }
    if (preset.value !== 'custom') reload();
}

function growthLabel(pct) {
    if (pct === null || pct === undefined) return '';
    const sign = pct > 0 ? '+' : '';
    return sign + pct + '% ' + t('finance.dashboard.kpi.vsPrevious');
}
function growthTone(pct) {
    if (pct === null || pct === undefined) return 'slate';
    return pct >= 0 ? 'emerald' : 'rose';
}

function trendBar(value) {
    if (!salesTrend.value) return 0;
    const max = Math.max(...salesTrend.value.data.flatMap(m => [m.sales, m.expenses]), 1);
    return Math.max(2, (value / max) * 100);
}

function profitBarStyle(profit) {
    if (!profitAnalysis.value) return {};
    const max = Math.max(...profitAnalysis.value.monthly.map(m => Math.abs(m.profit)), 1);
    const widthPct = Math.min(50, Math.abs(profit) / max * 50);
    if (profit >= 0) {
        return { left: '50%', width: widthPct + '%' };
    }
    return { right: '50%', width: widthPct + '%' };
}

const KpiCard = (props) => {
    const palette = {
        emerald: 'border-emerald-200',
        rose:    'border-rose-200',
        indigo:  'border-indigo-200',
        amber:   'border-amber-200',
        slate:   'border-slate-200',
    }[props.tone] ?? 'border-slate-200';
    const iconColor = {
        emerald: 'text-emerald-500',
        rose:    'text-rose-500',
        indigo:  'text-indigo-500',
        amber:   'text-amber-500',
        slate:   'text-slate-400',
    }[props.tone] ?? 'text-slate-400';

    return h('div', { class: `bg-white border ${palette} rounded-xl shadow-sm px-4 py-3 hover:shadow-md transition-shadow` }, [
        h('div', { class: 'flex items-start justify-between gap-2' }, [
            h('p', { class: 'text-[11px] uppercase tracking-wide text-slate-500 font-medium' }, props.label),
            props.icon ? h(props.icon, { class: `w-4 h-4 ${iconColor}` }) : null,
        ]),
        h('p', { class: 'text-xl font-bold text-slate-900 mt-1 font-mono' }, String(props.value)),
        props.sub
            ? h('p', { class: 'text-[11px] mt-1 font-medium ' + (
                props.subTone === 'emerald' ? 'text-emerald-700' :
                props.subTone === 'rose'    ? 'text-rose-700' :
                'text-slate-500') }, props.sub)
            : null,
    ]);
};
KpiCard.props = ['label', 'value', 'tone', 'icon', 'sub', 'subTone'];

const WaterfallStep = (props) => {
    const isNeg = props.value < 0;
    const isTotal = props.label.toLowerCase().includes('profit')
                 || props.label.toLowerCase().includes('gewinn')
                 || props.label.toLowerCase().includes('umsatz')
                 || props.label.toLowerCase().includes('revenue');
    const tone = isTotal ? 'bg-indigo-50 border-indigo-200' : (isNeg ? 'bg-rose-50 border-rose-200' : 'bg-slate-50 border-slate-200');
    const valueColor = isNeg ? 'text-rose-700' : (isTotal ? 'text-indigo-700' : 'text-slate-800');
    return h('div', { class: `rounded-lg border ${tone} px-3 py-2.5` }, [
        h('p', { class: 'text-[10px] uppercase tracking-wide text-slate-500 font-medium' }, props.label),
        h('p', { class: `text-sm font-bold mt-1 font-mono ${valueColor}` }, fmtCurrency(props.value)),
    ]);
};
WaterfallStep.props = ['label', 'value'];

const InsightCard = (props) => {
    const it = props.item;
    const palette = {
        positive: { wrap: 'border-emerald-200 bg-emerald-50',  text: 'text-emerald-800',  icon: 'text-emerald-600', iconComp: CheckCircleIcon },
        critical: { wrap: 'border-rose-200 bg-rose-50',        text: 'text-rose-800',     icon: 'text-rose-600',    iconComp: ExclamationCircleIcon },
        warning:  { wrap: 'border-amber-200 bg-amber-50',      text: 'text-amber-800',    icon: 'text-amber-600',   iconComp: ExclamationTriangleIcon },
        info:     { wrap: 'border-indigo-200 bg-indigo-50',    text: 'text-indigo-800',   icon: 'text-indigo-600',  iconComp: InformationCircleIcon },
    }[it.tone] ?? { wrap: 'border-slate-200 bg-slate-50', text: 'text-slate-700', icon: 'text-slate-500', iconComp: InformationCircleIcon };

    return h('div', { class: `flex items-start gap-3 px-4 py-3 rounded-lg border ${palette.wrap}` }, [
        h(palette.iconComp, { class: `w-5 h-5 mt-0.5 flex-shrink-0 ${palette.icon}` }),
        h('p', { class: `text-sm font-medium ${palette.text}` }, it.text),
    ]);
};
InsightCard.props = ['item'];

async function loadDashboard() {
    loading.value.dashboard = true;
    try {
        const { data } = await financialDashboardService.dashboard(filters.value);
        kpis.value     = data.data.kpis;
        insights.value = data.data.insights ?? [];
    } finally {
        loading.value.dashboard = false;
    }
}

async function loadTrend() {
    loading.value.trend = true;
    try {
        const year = new Date(filters.value.to).getFullYear();
        const { data } = await financialDashboardService.salesTrend({ year });
        salesTrend.value = data.data;
    } finally {
        loading.value.trend = false;
    }
}

async function loadProfit() {
    loading.value.profit = true;
    try {
        const year = new Date(filters.value.to).getFullYear();
        const { data } = await financialDashboardService.profitAnalysis({ year });
        profitAnalysis.value = data.data;
    } finally {
        loading.value.profit = false;
    }
}

async function loadBranch() {
    loading.value.branch = true;
    try {
        const { data } = await financialDashboardService.branchPerformance(filters.value);
        branchRows.value = data.data.rows ?? [];
    } finally {
        loading.value.branch = false;
    }
}

async function loadTopProducts() {
    loading.value.products = true;
    try {
        const { data } = await financialDashboardService.topProducts({ ...filters.value, limit: 10 });
        topProducts.value = data.data.rows ?? [];
    } finally {
        loading.value.products = false;
    }
}

async function loadTopCustomers() {
    loading.value.customers = true;
    try {
        const { data } = await financialDashboardService.topCustomers({ ...filters.value, limit: 10 });
        topCustomers.value = data.data.rows ?? [];
    } finally {
        loading.value.customers = false;
    }
}

async function loadExpenseBreakdown() {
    loading.value.expense = true;
    try {
        const { data } = await financialDashboardService.expenseBreakdown(filters.value);
        expenseBreakdown.value = data.data.rows ?? [];
    } finally {
        loading.value.expense = false;
    }
}

async function loadInventory() {
    loading.value.inventory = true;
    try {
        const { data } = await financialDashboardService.inventoryInsights();
        inventory.value = data.data;
    } finally {
        loading.value.inventory = false;
    }
}

function reload() {
    preset.value = matchPreset();
    loadDashboard();
    loadTrend();
    loadProfit();
    loadBranch();
    loadTopProducts();
    loadTopCustomers();
    loadExpenseBreakdown();
    loadInventory();
}

function matchPreset() {
    const now = new Date();
    const tm = new Date(now.getFullYear(), now.getMonth(), 1).toISOString().slice(0, 10);
    const tme = new Date(now.getFullYear(), now.getMonth() + 1, 0).toISOString().slice(0, 10);
    if (filters.value.from === tm && filters.value.to === tme) return 'this_month';
    return 'custom';
}

onMounted(reload);
</script>

<style scoped>
@reference '../../../css/app.css';
.card        { @apply bg-white border border-slate-200 rounded-xl shadow-sm p-5; }
.card-title  { @apply text-xs font-bold text-slate-500 uppercase tracking-wider mb-3; }
.btn-soft    { @apply inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-slate-300 text-slate-700 text-sm hover:bg-slate-50 transition-colors disabled:opacity-50; }
.lbl         { @apply block text-xs font-medium text-slate-600 mb-1; }
.ctrl        { @apply px-3 py-2 text-sm border border-slate-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent; }
.quick-link  { @apply flex items-center gap-2 px-4 py-3 rounded-lg border border-slate-200 bg-white hover:bg-indigo-50 hover:border-indigo-200 text-sm font-medium text-slate-700 transition-colors; }
</style>
