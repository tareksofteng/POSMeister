<template>
    <div class="p-6 lg:p-8 space-y-6 max-w-7xl mx-auto">

        <!-- Hero -->
        <header class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-indigo-500 mb-1">{{ dateString }}</p>
                <h1 class="text-2xl lg:text-3xl font-bold text-slate-900 tracking-tight">
                    {{ greeting }}, <span class="text-indigo-600">{{ auth.userName }}</span>
                </h1>
                <p class="mt-1 text-sm text-slate-500">{{ t('dashboard.heroSubtitle') }}</p>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <span :class="roleBadgeClass">{{ roleBadgeLabel }}</span>
                <span class="inline-flex items-center gap-1.5 text-xs text-slate-500 bg-white border border-slate-200 rounded-lg px-3 py-2">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                    {{ t('dashboard.systemActive') }}
                </span>
                <button @click="load" :disabled="loading"
                        class="inline-flex items-center gap-1.5 text-xs text-slate-600 bg-white border border-slate-200 rounded-lg px-3 py-2 hover:bg-slate-50 disabled:opacity-50">
                    <ArrowPathIcon :class="['w-3.5 h-3.5', loading && 'animate-spin']" />
                    {{ t('dashboard.refresh') }}
                </button>
            </div>
        </header>

        <!-- Smart alerts banner -->
        <section v-if="alerts.length" class="flex flex-wrap gap-2">
            <div v-for="(a, i) in alerts" :key="i"
                 :class="['inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border text-xs font-medium', alertClass(a.severity)]">
                <ExclamationTriangleIcon v-if="a.severity !== 'info'" class="w-3.5 h-3.5" />
                <InformationCircleIcon v-else class="w-3.5 h-3.5" />
                {{ t('dashboard.alerts.' + a.kind, { count: a.count }) }}
            </div>
        </section>

        <!-- KPI strip -->
        <section class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3">
            <KpiCard :label="t('dashboard.kpi.todayRevenue')"
                     :value="fmt(d?.sales?.today_revenue)"
                     :sub="(d?.sales?.today_sales_count ?? 0) + ' ' + t('dashboard.kpi.salesUnit')"
                     :delta="d?.sales?.delta_vs_yesterday"
                     tone="indigo" :icon="BanknotesIcon" />
            <KpiCard :label="t('dashboard.kpi.monthRevenue')"
                     :value="fmt(d?.sales?.month_revenue)"
                     :sub="(d?.sales?.month_sales_count ?? 0) + ' ' + t('dashboard.kpi.salesUnit')"
                     tone="emerald" :icon="ChartBarIcon" />
            <KpiCard :label="t('dashboard.kpi.netProfit')"
                     :value="fmt(d?.finance?.net_profit_month)"
                     :sub="(d?.finance?.gross_margin_pct ?? 0) + '% ' + t('dashboard.kpi.grossMargin')"
                     :tone="(d?.finance?.net_profit_month ?? 0) >= 0 ? 'emerald' : 'rose'"
                     :icon="ArrowTrendingUpIcon" />
            <KpiCard :label="t('dashboard.kpi.cashAndBank')"
                     :value="fmt((d?.finance?.cash_balance ?? 0) + (d?.finance?.bank_balance ?? 0))"
                     :sub="t('dashboard.kpi.cashBankSub')"
                     tone="indigo" :icon="BuildingLibraryIcon" />
            <KpiCard :label="t('dashboard.kpi.receivables')"
                     :value="fmt(d?.sales?.month_outstanding)"
                     :sub="t('dashboard.kpi.outstandingSub')"
                     tone="amber" :icon="DocumentTextIcon" />
            <KpiCard :label="t('dashboard.kpi.activeCustomers')"
                     :value="d?.customers?.active_count ?? '—'"
                     :sub="(d?.customers?.new_this_month ?? 0) + ' ' + t('dashboard.kpi.newThisMonth')"
                     tone="indigo" :icon="UsersIcon" />
        </section>

        <!-- Secondary insights -->
        <section class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <InsightTile :label="t('dashboard.insights.stockValue')"   :value="fmt(d?.inventory?.stock_value)"        :icon="ArchiveBoxIcon" tone="indigo" />
            <InsightTile :label="t('dashboard.insights.openOrders')"   :value="String(d?.orders?.open ?? 0)"          :icon="ShoppingBagIcon" tone="amber" />
            <InsightTile :label="t('dashboard.insights.loyalty')"      :value="fmt(d?.customers?.loyalty_liability)"  :icon="GiftIcon" tone="rose" />
            <InsightTile :label="t('dashboard.insights.activeStaff')"  :value="String(d?.hrm?.active_employees ?? 0)" :icon="UserGroupIcon" tone="slate" />
        </section>

        <!-- Sales trend mini-chart + Quick access -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
            <section class="lg:col-span-2 bg-white border border-slate-200 rounded-xl shadow-sm p-5">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-xs font-bold text-slate-500 uppercase tracking-wider">{{ t('dashboard.trend.title') }}</h3>
                        <p class="text-xs text-slate-400 mt-0.5">{{ t('dashboard.trend.subtitle') }}</p>
                    </div>
                    <p class="text-sm text-slate-700 font-mono">Ø {{ fmt(trendAvg) }}</p>
                </div>
                <div class="flex items-end gap-1.5 h-32">
                    <div v-for="day in d?.sales_trend ?? []" :key="day.date"
                         class="flex-1 flex flex-col items-center gap-1 group">
                        <div class="w-full bg-indigo-100 rounded-t-md overflow-hidden flex flex-col-reverse h-full">
                            <div class="w-full bg-gradient-to-t from-indigo-600 to-indigo-400 rounded-t-md transition-all group-hover:from-indigo-700"
                                 :style="{ height: trendBar(day.revenue) + '%' }"
                                 :title="day.date + ': ' + fmt(day.revenue)"></div>
                        </div>
                        <span class="text-[10px] text-slate-400 font-mono">{{ formatDay(day.date) }}</span>
                    </div>
                </div>
            </section>

            <section class="bg-white border border-slate-200 rounded-xl shadow-sm p-5">
                <h3 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-3">{{ t('dashboard.quickAccess.title') }}</h3>
                <div class="space-y-2">
                    <RouterLink v-for="link in quickLinks" :key="link.label"
                                :to="link.to"
                                class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-indigo-50 transition-colors group">
                        <div :class="['w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0', link.iconBg]">
                            <component :is="link.icon" :class="['w-4 h-4', link.iconColor]" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-slate-800 group-hover:text-indigo-700">{{ link.label }}</p>
                            <p class="text-xs text-slate-400 truncate">{{ link.desc }}</p>
                        </div>
                        <ChevronRightIcon class="w-4 h-4 text-slate-300 group-hover:text-indigo-400 transition-colors" />
                    </RouterLink>
                </div>
            </section>
        </div>

        <!-- Top products + Top customers -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
            <section class="bg-white border border-slate-200 rounded-xl shadow-sm">
                <div class="px-5 py-3 border-b border-slate-100 flex items-center justify-between">
                    <h3 class="text-xs font-bold text-slate-500 uppercase tracking-wider">{{ t('dashboard.topProducts.title') }}</h3>
                    <RouterLink :to="{ name: 'products' }" class="text-xs text-indigo-600 hover:underline">{{ t('dashboard.viewAll') }} →</RouterLink>
                </div>
                <ul class="divide-y divide-slate-50">
                    <li v-for="(p, i) in d?.top_products ?? []" :key="p.product_id"
                        class="flex items-center justify-between px-5 py-2.5 hover:bg-slate-50/60">
                        <div class="flex items-center gap-2 min-w-0">
                            <span class="text-xs text-slate-400 font-mono w-6">#{{ i + 1 }}</span>
                            <div class="min-w-0">
                                <p class="font-medium text-slate-800 truncate">{{ p.name }}</p>
                                <p class="text-[11px] text-slate-500 font-mono">{{ p.sku }} · {{ p.qty_sold }} {{ t('dashboard.topProducts.sold') }}</p>
                            </div>
                        </div>
                        <p class="font-mono text-sm text-slate-800 font-semibold">{{ fmt(p.revenue) }}</p>
                    </li>
                    <li v-if="(d?.top_products ?? []).length === 0" class="px-5 py-8 text-center text-sm text-slate-400">
                        {{ t('dashboard.topProducts.empty') }}
                    </li>
                </ul>
            </section>

            <section class="bg-white border border-slate-200 rounded-xl shadow-sm">
                <div class="px-5 py-3 border-b border-slate-100 flex items-center justify-between">
                    <h3 class="text-xs font-bold text-slate-500 uppercase tracking-wider">{{ t('dashboard.topCustomers.title') }}</h3>
                    <RouterLink :to="{ name: 'customers' }" class="text-xs text-indigo-600 hover:underline">{{ t('dashboard.viewAll') }} →</RouterLink>
                </div>
                <ul class="divide-y divide-slate-50">
                    <li v-for="(c, i) in d?.top_customers ?? []" :key="c.customer_id"
                        class="flex items-center justify-between px-5 py-2.5 hover:bg-slate-50/60">
                        <div class="flex items-center gap-2 min-w-0">
                            <span class="text-xs text-slate-400 font-mono w-6">#{{ i + 1 }}</span>
                            <div class="min-w-0">
                                <p class="font-medium text-slate-800 truncate">{{ c.name }}</p>
                                <p class="text-[11px] text-slate-500">{{ c.visits }} {{ t('dashboard.topCustomers.visits') }}</p>
                            </div>
                        </div>
                        <p class="font-mono text-sm text-slate-800 font-semibold">{{ fmt(c.revenue) }}</p>
                    </li>
                    <li v-if="(d?.top_customers ?? []).length === 0" class="px-5 py-8 text-center text-sm text-slate-400">
                        {{ t('dashboard.topCustomers.empty') }}
                    </li>
                </ul>
            </section>
        </div>

        <!-- Recent sales + Activity feed -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
            <section class="lg:col-span-2 bg-white border border-slate-200 rounded-xl shadow-sm">
                <div class="px-5 py-3 border-b border-slate-100 flex items-center justify-between">
                    <div>
                        <h3 class="text-xs font-bold text-slate-500 uppercase tracking-wider">{{ t('dashboard.recentSales.title') }}</h3>
                        <p class="text-xs text-slate-400 mt-0.5">{{ t('dashboard.recentSales.subtitle') }}</p>
                    </div>
                    <RouterLink :to="{ name: 'sales' }" class="text-xs text-indigo-600 hover:underline">{{ t('dashboard.viewAll') }} →</RouterLink>
                </div>
                <ul v-if="(d?.recent_sales ?? []).length" class="divide-y divide-slate-50">
                    <li v-for="s in d.recent_sales" :key="s.id"
                        class="flex items-center gap-3 px-5 py-3 hover:bg-slate-50/60">
                        <div class="w-9 h-9 rounded-lg bg-emerald-50 flex items-center justify-center flex-shrink-0">
                            <ShoppingCartIcon class="w-4 h-4 text-emerald-600" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-slate-900 font-mono">{{ s.sale_number }}</p>
                            <p class="text-xs text-slate-500 truncate">{{ s.customer_name }} · {{ s.sale_date }}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-semibold text-slate-900 font-mono">{{ fmt(s.grand_total) }}</p>
                            <p v-if="s.due_amount > 0" class="text-[11px] text-amber-700 font-medium">
                                {{ t('dashboard.recentSales.due') }}: {{ fmt(s.due_amount) }}
                            </p>
                            <p v-else class="text-[11px] text-emerald-600 font-medium">{{ t('dashboard.recentSales.paid') }}</p>
                        </div>
                    </li>
                </ul>
                <div v-else class="px-5 py-12 text-center">
                    <ShoppingCartIcon class="w-10 h-10 text-slate-200 mx-auto mb-3" />
                    <p class="text-sm text-slate-400">{{ t('dashboard.recentSales.empty') }}</p>
                </div>
            </section>

            <section class="bg-white border border-slate-200 rounded-xl shadow-sm">
                <div class="px-5 py-3 border-b border-slate-100">
                    <h3 class="text-xs font-bold text-slate-500 uppercase tracking-wider">{{ t('dashboard.activity.title') }}</h3>
                </div>
                <ol class="px-5 py-3 space-y-2 max-h-96 overflow-y-auto">
                    <li v-for="(a, i) in d?.activity ?? []" :key="i"
                        class="flex items-start gap-3 py-1.5 border-b border-slate-50 last:border-0">
                        <span :class="['mt-1.5 w-2 h-2 rounded-full flex-shrink-0', kindDot(a.kind)]"></span>
                        <div class="min-w-0 flex-1">
                            <p class="text-xs font-medium text-slate-800 truncate">{{ a.title }}</p>
                            <p class="text-[10px] text-slate-400">{{ relativeTime(a.at) }}</p>
                        </div>
                        <p class="text-xs font-mono text-slate-700 whitespace-nowrap">{{ fmt(a.amount) }}</p>
                    </li>
                    <li v-if="(d?.activity ?? []).length === 0" class="text-center text-sm text-slate-400 py-6">
                        {{ t('dashboard.activity.empty') }}
                    </li>
                </ol>
            </section>
        </div>

        <!-- System info + Module status -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
            <section class="bg-white border border-slate-200 rounded-xl shadow-sm p-5">
                <h3 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-3">{{ t('dashboard.systemInfo.title') }}</h3>
                <dl class="space-y-2.5 text-sm">
                    <div v-for="item in systemInfo" :key="item.label" class="flex items-center justify-between">
                        <dt class="text-xs text-slate-500">{{ item.label }}</dt>
                        <dd class="text-xs font-semibold text-slate-700">{{ item.value }}</dd>
                    </div>
                </dl>
            </section>

            <section class="lg:col-span-2 bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
                <div class="px-5 py-3 border-b border-slate-100">
                    <h3 class="text-xs font-bold text-slate-500 uppercase tracking-wider">{{ t('dashboard.moduleStatus.title') }}</h3>
                    <p class="text-xs text-slate-400 mt-0.5">{{ t('dashboard.moduleStatus.subtitle') }}</p>
                </div>
                <div class="grid grid-cols-3 md:grid-cols-4 lg:grid-cols-5 divide-x divide-y divide-slate-100">
                    <div v-for="m in moduleStatusList" :key="m.key"
                         class="flex flex-col items-center gap-2 px-3 py-4 text-center">
                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                        <p class="text-[11px] font-semibold text-slate-700">{{ m.label }}</p>
                        <span class="text-[9px] uppercase tracking-wider px-1.5 py-0.5 rounded bg-emerald-50 text-emerald-700 font-bold">
                            {{ m.phase }}
                        </span>
                    </div>
                </div>
            </section>
        </div>

    </div>
</template>

<script setup>
import { ref, computed, h, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { RouterLink } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useSettingsStore } from '@/stores/settings';
import { useLocale } from '@/composables/useLocale';
import { dashboardService } from '@/services/dashboardService';
import {
    BanknotesIcon, ChartBarIcon, ArrowTrendingUpIcon, BuildingLibraryIcon,
    DocumentTextIcon, UsersIcon, ArchiveBoxIcon, ShoppingBagIcon, GiftIcon,
    UserGroupIcon, ShoppingCartIcon, ChevronRightIcon, ArrowPathIcon,
    ExclamationTriangleIcon, InformationCircleIcon,
    TagIcon, TruckIcon, BookOpenIcon, ClipboardDocumentListIcon,
} from '@heroicons/vue/24/outline';

const { t } = useI18n();
const { intlLocale } = useLocale();
const auth = useAuthStore();
const settingsStore = useSettingsStore();

const d = ref(null);
const loading = ref(false);

function fmt(value) {
    if (value === null || value === undefined) return '—';
    const code = settingsStore.settings?.currency_code ?? 'EUR';
    return new Intl.NumberFormat(intlLocale.value || 'de-DE',
        { style: 'currency', currency: code, maximumFractionDigits: 2 }
    ).format(Number(value) || 0);
}

const now = new Date();
const greeting = computed(() => {
    const h = now.getHours();
    const key = h < 12 ? 'morning' : h < 17 ? 'afternoon' : 'evening';
    return t(`dashboard.greeting.${key}`);
});
const dateString = computed(() =>
    new Intl.DateTimeFormat(intlLocale.value || 'de-DE',
        { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }
    ).format(now)
);
function formatDay(d) {
    return new Intl.DateTimeFormat(intlLocale.value || 'de-DE', { day: '2-digit', month: '2-digit' }).format(new Date(d));
}
function relativeTime(input) {
    if (!input) return '';
    const time = new Date(input);
    const diff = Math.floor((Date.now() - time.getTime()) / 1000);
    if (diff < 60)    return diff + 's';
    if (diff < 3600)  return Math.floor(diff / 60) + 'm';
    if (diff < 86400) return Math.floor(diff / 3600) + 'h';
    return Math.floor(diff / 86400) + 'd';
}

const ROLE_CLASSES = {
    admin:   'inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-semibold bg-indigo-600 text-white shadow-sm',
    manager: 'inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-semibold bg-violet-100 text-violet-700',
    cashier: 'inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-semibold bg-slate-100 text-slate-600',
};
const roleBadgeClass = computed(() => ROLE_CLASSES[auth.userRole] ?? ROLE_CLASSES.cashier);
const roleBadgeLabel = computed(() => t(`dashboard.roleBadges.${auth.userRole ?? 'cashier'}`));

const alerts = computed(() => d.value?.alerts ?? []);
function alertClass(severity) {
    return {
        critical: 'bg-rose-50 border-rose-200 text-rose-800',
        warning:  'bg-amber-50 border-amber-200 text-amber-800',
        info:     'bg-indigo-50 border-indigo-200 text-indigo-800',
    }[severity] ?? 'bg-slate-50 border-slate-200 text-slate-700';
}

const trendAvg = computed(() => {
    const arr = d.value?.sales_trend ?? [];
    if (!arr.length) return 0;
    return Math.round(arr.reduce((s, x) => s + (x.revenue || 0), 0) / arr.length);
});
function trendBar(value) {
    const arr = d.value?.sales_trend ?? [];
    if (!arr.length) return 0;
    const max = Math.max(...arr.map(x => x.revenue || 0), 1);
    return Math.max(4, (value / max) * 100);
}
function kindDot(kind) {
    return {
        sale:    'bg-emerald-500',
        journal: 'bg-indigo-500',
    }[kind] ?? 'bg-slate-400';
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
    }[props.tone] ?? 'text-slate-400';

    const hasDelta = props.delta !== null && props.delta !== undefined;
    const deltaPos = hasDelta && props.delta >= 0;

    return h('div', { class: `bg-white border ${palette} rounded-xl shadow-sm px-4 py-3 hover:shadow-md transition-shadow` }, [
        h('div', { class: 'flex items-start justify-between gap-2 mb-1' }, [
            h('p', { class: 'text-[11px] uppercase tracking-wide text-slate-500 font-medium' }, props.label),
            props.icon ? h(props.icon, { class: `w-4 h-4 ${iconColor}` }) : null,
        ]),
        h('p', { class: 'text-xl font-bold text-slate-900 font-mono' }, String(props.value)),
        h('div', { class: 'flex items-center justify-between mt-1 gap-2' }, [
            h('p', { class: 'text-[11px] text-slate-500 truncate' }, props.sub || ''),
            hasDelta
                ? h('span', {
                    class: 'text-[10px] font-mono font-semibold ' + (deltaPos ? 'text-emerald-700' : 'text-rose-700'),
                  }, (deltaPos ? '+' : '') + props.delta + '%')
                : null,
        ]),
    ]);
};
KpiCard.props = ['label', 'value', 'sub', 'tone', 'icon', 'delta'];

const InsightTile = (props) => {
    const palette = {
        emerald: 'border-emerald-200 bg-emerald-50/40',
        rose:    'border-rose-200 bg-rose-50/40',
        indigo:  'border-indigo-200 bg-indigo-50/40',
        amber:   'border-amber-200 bg-amber-50/40',
        slate:   'border-slate-200 bg-white',
    }[props.tone] ?? 'border-slate-200 bg-white';
    const iconColor = {
        emerald: 'text-emerald-500',
        rose:    'text-rose-500',
        indigo:  'text-indigo-500',
        amber:   'text-amber-500',
    }[props.tone] ?? 'text-slate-400';
    return h('div', { class: `border ${palette} rounded-xl shadow-sm px-4 py-3 flex items-center gap-3` }, [
        h(props.icon, { class: `w-7 h-7 ${iconColor}` }),
        h('div', null, [
            h('p', { class: 'text-[10px] uppercase tracking-wide text-slate-500 font-medium' }, props.label),
            h('p', { class: 'text-base font-bold text-slate-900 font-mono mt-0.5' }, String(props.value)),
        ]),
    ]);
};
InsightTile.props = ['label', 'value', 'icon', 'tone'];

const quickLinks = computed(() => [
    { label: t('dashboard.quickAccess.openPOS'),       desc: t('dashboard.quickAccess.openPOSDesc'),       to: { name: 'pos' },               icon: ShoppingCartIcon,         iconBg: 'bg-emerald-50', iconColor: 'text-emerald-600' },
    { label: t('dashboard.quickAccess.newSale'),       desc: t('dashboard.quickAccess.newSaleDesc'),       to: { name: 'sales-new' },         icon: TagIcon,                  iconBg: 'bg-indigo-50',  iconColor: 'text-indigo-600' },
    { label: t('dashboard.quickAccess.newPurchase'),   desc: t('dashboard.quickAccess.newPurchaseDesc'),   to: { name: 'purchase-create' },   icon: TruckIcon,                iconBg: 'bg-violet-50',  iconColor: 'text-violet-600' },
    { label: t('dashboard.quickAccess.accounting'),    desc: t('dashboard.quickAccess.accountingDesc'),    to: { name: 'accounting-dashboard' }, icon: BookOpenIcon,         iconBg: 'bg-amber-50',   iconColor: 'text-amber-600' },
    { label: t('dashboard.quickAccess.reorder'),       desc: t('dashboard.quickAccess.reorderDesc'),       to: { name: 'inventory-reorder' }, icon: ClipboardDocumentListIcon, iconBg: 'bg-rose-50',   iconColor: 'text-rose-600' },
]);

const systemInfo = computed(() => [
    { label: t('dashboard.systemInfo.version'),     value: 'v1.7.0 — Phase G' },
    { label: t('dashboard.systemInfo.framework'),   value: 'Laravel 13 + Vue 3' },
    { label: t('dashboard.systemInfo.auth'),        value: 'Sanctum (Token)' },
    { label: t('dashboard.systemInfo.environment'), value: import.meta.env.MODE === 'production' ? 'Production' : 'Development' },
]);

const moduleStatusList = computed(() => [
    { key: 'pos',         label: t('dashboard.moduleStatus.modules.pos'),         phase: 'A' },
    { key: 'sales',       label: t('dashboard.moduleStatus.modules.sales'),       phase: 'A' },
    { key: 'inventory',   label: t('dashboard.moduleStatus.modules.inventory'),   phase: 'D' },
    { key: 'hrm',         label: t('dashboard.moduleStatus.modules.hrm'),         phase: 'G' },
    { key: 'finance',     label: t('dashboard.moduleStatus.modules.finance'),     phase: 'B' },
    { key: 'accounting',  label: t('dashboard.moduleStatus.modules.accounting'),  phase: 'C' },
    { key: 'crm',         label: t('dashboard.moduleStatus.modules.crm'),         phase: 'E' },
    { key: 'oms',         label: t('dashboard.moduleStatus.modules.oms'),         phase: 'F' },
    { key: 'expenses',    label: t('dashboard.moduleStatus.modules.expenses'),    phase: 'A' },
    { key: 'reports',     label: t('dashboard.moduleStatus.modules.reports'),     phase: 'A' },
]);

async function load() {
    loading.value = true;
    try {
        const { data } = await dashboardService.stats();
        d.value = data;
    } finally {
        loading.value = false;
    }
}

onMounted(load);
</script>
