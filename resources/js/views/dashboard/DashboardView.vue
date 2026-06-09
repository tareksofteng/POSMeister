<template>
    <div class="p-3 sm:p-6 lg:p-8 space-y-4 sm:space-y-6 max-w-7xl mx-auto anim-fade-in">

        <!-- ── Hero ──
            Phase AA polish: typography uses the new scale (.t-overline / .h1-display),
            the action cluster swaps inline classes for the unified <Button> primitive.
            Same content; reads commercial. -->
        <header class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-3 sm:gap-4 anim-fade-up">
            <div>
                <p class="t-overline text-indigo-500 mb-1.5">{{ dateString }}</p>
                <h1 class="h1-display">
                    {{ greeting }}, <span class="text-indigo-600">{{ auth.userName }}</span>
                </h1>
                <p class="mt-1.5 t-body">{{ t('dashboard.heroSubtitle') }}</p>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <span :class="roleBadgeClass">{{ roleBadgeLabel }}</span>
                <span class="hidden sm:inline-flex items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg px-3 py-2">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                    {{ t('dashboard.systemActive') }}
                </span>
                <Button
                    variant="secondary"
                    size="sm"
                    :leading-icon="ArrowPathIcon"
                    :loading="loading"
                    @click="load"
                >
                    {{ t('dashboard.refresh') }}
                </Button>
            </div>
        </header>

        <!-- ── EXECUTIVE KPI MARQUEE — phones already see the same numbers in
             the 2-col snapshot grid below, and the auto-scrolling strip eats
             vertical real estate they don't have. Show it from md up only. -->
        <KpiMarquee v-if="d" :items="marqueeItems" class="hidden md:block" />

        <!-- ── Smart alerts banner ──
            Phase AA: replaces 4 inline tone classes with the unified
            .card-alert-* system. Same data; reads as one design language
            with notifications, status pills, and serial-warranty chips. -->
        <section v-if="alerts.length" class="flex flex-wrap gap-2 anim-fade-up">
            <div v-for="(a, i) in alerts" :key="i"
                 :class="['card card-alert', cardAlertToneClass(a.severity), 'inline-flex items-center gap-2 text-xs font-medium']">
                <ExclamationTriangleIcon v-if="a.severity !== 'info'" class="w-3.5 h-3.5 flex-shrink-0" />
                <InformationCircleIcon v-else class="w-3.5 h-3.5 flex-shrink-0" />
                {{ t('dashboard.alerts.' + a.kind, { count: a.count }) }}
            </div>
        </section>

        <!-- Health snapshot — score card spans full width, with the four
             ring widgets stacked below. Gives every metric room to breathe
             and keeps the visual rhythm consistent on every viewport. -->
        <section v-if="d?.health" class="space-y-3 sm:space-y-4 anim-fade-up">
            <BusinessHealthCard :health="d.health" />
            <HealthRingsRow :health="d.health" />
        </section>

        <!-- Phase AC — rule-based insights row. Horizontally scrolls on
             phones, fills the page on desktop. Hidden when the engine
             returns no insights (clean baseline). -->
        <InsightsCarousel v-if="d?.insights?.length" :insights="d.insights" />

        <!-- ── Phase AB-2: Business Alerts widget ──
             Sits between the smart-alerts banner and the executive KPI
             cards so it's the LOUDEST signal on the dashboard once data
             loads. Pure read; auto-refreshes every 2 minutes. -->
        <BusinessAlertsWidget />

        <!-- Phase AC — multi-branch leaderboard. Self-hides when the
             user is in a single-branch workspace (backend returns []). -->
        <BranchLeaderboard v-if="d?.branch_leaderboard?.length >= 2" :branches="d.branch_leaderboard" />

        <!-- ── EXECUTIVE SNAPSHOT — premium KPI cards with sparklines.
             While loading the first payload, show 6 shape-aware skeleton
             tiles so the layout doesn't jump when data lands. -->
        <section v-if="!d" class="grid grid-cols-2 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6 gap-2.5 sm:gap-4 anim-stagger">
            <Skeleton v-for="i in 6" :key="i" variant="kpi-card" />
        </section>
        <section v-else class="grid grid-cols-2 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6 gap-2.5 sm:gap-4 anim-fade-up anim-stagger">
            <SparklineCard
                :label="t('dashboard.kpi.todayRevenue')"
                :value="+d?.sales?.today_revenue || 0"
                :prefix="currencyPrefix"
                :delta="d?.sales?.delta_vs_yesterday"
                :deltaLabel="t('dashboard.kpi.vsYesterday')"
                :sub="(d?.sales?.today_sales_count ?? 0) + ' ' + t('dashboard.kpi.salesUnit')"
                :sparkline="trendValues"
                tone="indigo"
            ><template #icon><BanknotesIcon class="w-5 h-5" /></template></SparklineCard>

            <SparklineCard
                :label="t('dashboard.kpi.monthRevenue')"
                :value="+d?.sales?.month_revenue || 0"
                :prefix="currencyPrefix"
                :sub="(d?.sales?.month_sales_count ?? 0) + ' ' + t('dashboard.kpi.salesUnit')"
                :sparkline="trendValues"
                tone="emerald"
            ><template #icon><ChartBarIcon class="w-5 h-5" /></template></SparklineCard>

            <SparklineCard
                :label="t('dashboard.kpi.netProfit')"
                :value="+d?.finance?.net_profit_month || 0"
                :prefix="currencyPrefix"
                :sub="(d?.finance?.gross_margin_pct ?? 0) + '% ' + t('dashboard.kpi.grossMargin')"
                :tone="(d?.finance?.net_profit_month ?? 0) >= 0 ? 'emerald' : 'rose'"
            ><template #icon><ArrowTrendingUpIcon class="w-5 h-5" /></template></SparklineCard>

            <SparklineCard
                :label="t('dashboard.kpi.cashAndBank')"
                :value="(+d?.finance?.cash_balance || 0) + (+d?.finance?.bank_balance || 0)"
                :prefix="currencyPrefix"
                :sub="t('dashboard.kpi.cashBankSub')"
                tone="indigo"
            ><template #icon><BuildingLibraryIcon class="w-5 h-5" /></template></SparklineCard>

            <SparklineCard
                :label="t('dashboard.kpi.receivables')"
                :value="+d?.sales?.month_outstanding || 0"
                :prefix="currencyPrefix"
                :sub="t('dashboard.kpi.outstandingSub')"
                tone="amber"
            ><template #icon><DocumentTextIcon class="w-5 h-5" /></template></SparklineCard>

            <SparklineCard
                :label="t('dashboard.kpi.activeCustomers')"
                :value="+d?.customers?.active_count || 0"
                :sub="(d?.customers?.new_this_month ?? 0) + ' ' + t('dashboard.kpi.newThisMonth')"
                tone="violet"
            ><template #icon><UsersIcon class="w-5 h-5" /></template></SparklineCard>
        </section>

        <!-- Secondary insights -->
        <section class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <InsightTile :label="t('dashboard.insights.stockValue')"   :value="fmt(d?.inventory?.stock_value)"        :icon="ArchiveBoxIcon" tone="indigo" />
            <InsightTile :label="t('dashboard.insights.openOrders')"   :value="String(d?.orders?.open ?? 0)"          :icon="ShoppingBagIcon" tone="amber" />
            <InsightTile :label="t('dashboard.insights.loyalty')"      :value="fmt(d?.customers?.loyalty_liability)"  :icon="GiftIcon" tone="rose" />
            <InsightTile :label="t('dashboard.insights.activeStaff')"  :value="String(d?.hrm?.active_employees ?? 0)" :icon="UserGroupIcon" tone="slate" />
        </section>

        <!-- Phase AC Round 2 — TrendsPanel replaces the static 14-day bar
             chart with a full period+metric switcher (revenue / profit /
             purchase / cash flow × 7d / 30d / 90d). Self-fetches on tab
             switch and uses the design-system Skeleton on transitions. -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
            <div class="lg:col-span-2">
                <TrendsPanel />
            </div>

            <QuickActionsPanel />
        </div>

        <!-- Phase AC Round 3 — Top Products 2.0 + Top Customers 2.0. The
             old single-list sections are replaced by 4-tab tier panels
             that fetch their own slices on demand. Empty states are
             tier-specific so the user always sees a useful message. -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
            <TopProductsPanel />
            <TopCustomersPanel />
        </div>

        <!-- Recent sales + Activity feed -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
            <section class="card lg:col-span-2 overflow-hidden">
                <div class="dash-list-head">
                    <div>
                        <p class="t-overline">{{ t('dashboard.recentSales.title') }}</p>
                        <p class="t-caption mt-0.5">{{ t('dashboard.recentSales.subtitle') }}</p>
                    </div>
                    <RouterLink :to="{ name: 'sales' }" class="dash-list-link">{{ t('dashboard.viewAll') }} →</RouterLink>
                </div>
                <div v-if="!d" class="px-5 py-3 space-y-2">
                    <Skeleton variant="row" />
                    <Skeleton variant="row" />
                    <Skeleton variant="row" />
                </div>
                <ul v-else-if="(d.recent_sales ?? []).length" class="divide-y divide-slate-100 dark:divide-slate-800">
                    <li v-for="s in d.recent_sales" :key="s.id" class="flex items-center gap-3 px-5 py-3 hover:bg-slate-50/60 dark:hover:bg-slate-800/40 transition-colors">
                        <div class="w-9 h-9 rounded-lg bg-emerald-50 dark:bg-emerald-900/30 flex items-center justify-center flex-shrink-0">
                            <ShoppingCartIcon class="w-4 h-4 text-emerald-600 dark:text-emerald-400" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-slate-900 dark:text-slate-100 font-mono">{{ s.sale_number }}</p>
                            <p class="text-xs text-slate-500 truncate">{{ s.customer_name }} · {{ s.sale_date }}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-semibold text-slate-900 dark:text-slate-100 font-mono">{{ fmt(s.grand_total) }}</p>
                            <p v-if="s.due_amount > 0" class="text-[11px] text-amber-700 dark:text-amber-400 font-medium">
                                {{ t('dashboard.recentSales.due') }}: {{ fmt(s.due_amount) }}
                            </p>
                            <p v-else class="text-[11px] text-emerald-600 dark:text-emerald-400 font-medium">{{ t('dashboard.recentSales.paid') }}</p>
                        </div>
                    </li>
                </ul>
                <EmptyState
                    v-else
                    size="sm"
                    tone="emerald"
                    :icon="ShoppingCartIcon"
                    :title="t('dashboard.recentSales.empty')"
                    :description="t('dashboard.recentSales.emptyDesc', 'Open the POS or record a new sale to see it appear here in real time.')"
                >
                    <template #action>
                        <RouterLink :to="{ name: 'pos' }" class="rs-cta">
                            <ShoppingCartIcon class="w-4 h-4" />
                            {{ t('menu.cashTerminal', 'Open POS') }}
                        </RouterLink>
                    </template>
                </EmptyState>
            </section>

            <section class="card overflow-hidden">
                <div class="dash-list-head">
                    <p class="t-overline">{{ t('dashboard.activity.title') }}</p>
                </div>
                <div class="px-2 py-2 max-h-96 overflow-y-auto">
                    <BusinessActivityFeed :items="feedItems" :limit="10" />
                </div>
            </section>
        </div>

        <!-- A compact bottom strip showing the things the operator needs to
             trust: connectivity, install state, sync queue, alerts. -->
        <SystemHealthFooter />

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
    CubeIcon, ReceiptPercentIcon, CurrencyDollarIcon, FireIcon,
} from '@heroicons/vue/24/outline';

// Executive dashboard upgrade
import KpiMarquee     from '@/components/dashboard/KpiMarquee.vue';
import SparklineCard  from '@/components/dashboard/SparklineCard.vue';
import BusinessActivityFeed from '@/components/dashboard/BusinessActivityFeed.vue';
// Phase AB-2 — proactive business alerts surface
import BusinessAlertsWidget from '@/components/dashboard/BusinessAlertsWidget.vue';
// Phase AC — Executive Dashboard 2.0 additions
import BusinessHealthCard   from '@/components/dashboard/BusinessHealthCard.vue';
import HealthRingsRow       from '@/components/dashboard/HealthRingsRow.vue';
import InsightsCarousel     from '@/components/dashboard/InsightsCarousel.vue';
import BranchLeaderboard    from '@/components/dashboard/BranchLeaderboard.vue';
// Phase AC Round 2 — full-period trend switcher
import TrendsPanel          from '@/components/dashboard/TrendsPanel.vue';
// Phase AC Round 3 — Top Products + Top Customers 2.0
import TopProductsPanel     from '@/components/dashboard/TopProductsPanel.vue';
import TopCustomersPanel    from '@/components/dashboard/TopCustomersPanel.vue';
// Phase AC Round 4 — premium quick actions + system health footer
import QuickActionsPanel    from '@/components/dashboard/QuickActionsPanel.vue';
import SystemHealthFooter   from '@/components/dashboard/SystemHealthFooter.vue';

// Phase AA design-system primitives
import Button     from '@/components/ui/Button.vue';
import Skeleton   from '@/components/ui/Skeleton.vue';
import EmptyState from '@/components/ui/EmptyState.vue';

const { t } = useI18n();
const { intlLocale } = useLocale();
const auth = useAuthStore();
const settingsStore = useSettingsStore();

const d = ref(null);
const loading = ref(false);

function fmt(value) {
    if (value === null || value === undefined) return '—';
    const code = settingsStore.settings?.currency_code ?? 'EUR';
    return new Intl.NumberFormat(intlLocale.value || 'en-US',
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
    new Intl.DateTimeFormat(intlLocale.value || 'en-US',
        { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }
    ).format(now)
);
function formatDay(d) {
    return new Intl.DateTimeFormat(intlLocale.value || 'en-US', { day: '2-digit', month: '2-digit' }).format(new Date(d));
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

/**
 * Map backend severity → design-system .card-alert-* tone. Single mapping
 * point so the rest of the product can swap the same banner widget in
 * with one class, and dark mode lights up automatically.
 */
function cardAlertToneClass(severity) {
    return {
        critical: 'card-alert-danger',
        warning:  'card-alert-warning',
        info:     'card-alert-info',
        success:  'card-alert-success',
    }[severity] ?? 'card-alert-info';
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

// ── Executive marquee KPIs ────────────────────────────────────────────────
// Compact, glance-friendly metrics that scroll across the top of the dash.
const trendValues = computed(() => (d.value?.sales_trend ?? []).map(p => p.revenue || 0));

const marqueeItems = computed(() => {
    const s   = d.value?.sales     || {};
    const p   = d.value?.purchases || {};
    const f   = d.value?.finance   || {};
    const inv = d.value?.inventory || {};
    const o   = d.value?.orders    || {};
    return [
        { key: 'sales-today',  label: t('dashboard.kpi.todayRevenue'),    value: +s.today_revenue || 0,    prefix: currencyPrefix.value, icon: BanknotesIcon,         tone: 'indigo',  delta: s.delta_vs_yesterday, sparkline: trendValues.value },
        { key: 'sales-month',  label: t('dashboard.kpi.monthRevenue'),    value: +s.month_revenue || 0,    prefix: currencyPrefix.value, icon: ChartBarIcon,          tone: 'emerald', sparkline: trendValues.value },
        { key: 'purch-today',  label: t('dashboard.kpi.todayPurchase'),   value: +p.today || 0,            prefix: currencyPrefix.value, icon: TruckIcon,             tone: 'sky' },
        { key: 'purch-month',  label: t('dashboard.kpi.monthPurchase'),   value: +p.month || 0,            prefix: currencyPrefix.value, icon: TruckIcon,             tone: 'sky' },
        { key: 'cust-pay',     label: t('dashboard.kpi.customerPayments'),value: +p.customer_paid_month || 0, prefix: currencyPrefix.value, icon: CurrencyDollarIcon, tone: 'emerald' },
        { key: 'sup-pay',      label: t('dashboard.kpi.supplierPayments'),value: +p.supplier_paid_month || 0, prefix: currencyPrefix.value, icon: CurrencyDollarIcon, tone: 'violet' },
        { key: 'profit',       label: t('dashboard.kpi.netProfit'),       value: +f.net_profit_month || 0, prefix: currencyPrefix.value, icon: ArrowTrendingUpIcon,   tone: (+f.net_profit_month || 0) >= 0 ? 'emerald' : 'rose' },
        { key: 'cash',         label: t('dashboard.kpi.cashAndBank'),     value: (+f.cash_balance || 0) + (+f.bank_balance || 0), prefix: currencyPrefix.value, icon: BuildingLibraryIcon, tone: 'indigo' },
        { key: 'receivables',  label: t('dashboard.kpi.receivables'),     value: +s.month_outstanding || 0,prefix: currencyPrefix.value, icon: DocumentTextIcon,      tone: 'amber',  urgent: (+s.month_outstanding || 0) > 0 },
        { key: 'stock-value',  label: t('dashboard.insights.stockValue'), value: +inv.stock_value || 0,    prefix: currencyPrefix.value, icon: CubeIcon,              tone: 'slate' },
        { key: 'open-orders',  label: t('dashboard.insights.openOrders'), value: +o.open || 0,                                          icon: ShoppingBagIcon,       tone: 'amber',  urgent: (+o.open || 0) > 0 },
        { key: 'reorder',      label: t('dashboard.kpi.reorderAlerts'),   value: +inv.low_stock_count || 0,                             icon: FireIcon,              tone: 'rose',   urgent: (+inv.low_stock_count || 0) > 0 },
        { key: 'dead-stock',   label: t('dashboard.kpi.deadStock'),       value: +inv.dead_stock_count || 0,                            icon: ArchiveBoxIcon,        tone: 'slate' },
    ];
});

const currencyPrefix = computed(() => settingsStore.settings?.currency_symbol ? (settingsStore.settings.currency_symbol + ' ') : '');

// Activity feed mapping — translate backend kinds → semantic types for the timeline.
const feedItems = computed(() => (d.value?.activity ?? []).map(a => ({
    id:       a.id,
    type:     ({ sale: 'sale', journal: 'payment_in' })[a.kind] || 'sale',
    title:    a.title,
    subtitle: a.amount ? (currencyPrefix.value + Number(a.amount).toFixed(2)) : '',
    at:       a.at,
})));

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
    { label: t('dashboard.systemInfo.version'),     value: 'v2.0.0 — Phase X' },
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
    { key: 'platform',    label: t('dashboard.moduleStatus.modules.platform'),    phase: 'X' },
]);

async function load() {
    loading.value = true;
    try {
        if (navigator.onLine === false) {
            await loadFromCache();
            return;
        }
        const { data } = await dashboardService.stats();
        d.value = data;
        // Mirror the fresh stats into IndexedDB so the next offline boot
        // already has yesterday's numbers in front of the cashier.
        try {
            const { put } = await import('@/offline/db');
            await put('settings', { k: 'dashboard_stats', v: data });
        } catch { /* best-effort */ }
    } catch (err) {
        const swOffline = err.response?.headers?.['x-posmeister-offline'] === '1';
        if (!err.response || swOffline) {
            await loadFromCache();
        }
    } finally {
        loading.value = false;
    }
}

async function loadFromCache() {
    try {
        const { get } = await import('@/offline/db');
        const row = await get('settings', 'dashboard_stats');
        if (row?.v) d.value = row.v;
    } catch { /* nothing cached yet */ }
}

onMounted(load);
</script>

<style scoped>
@reference '../../../css/app.css';

/* Quick-access link — premium hover lift + indigo wash. The icon disc
   stays bright (semantic) while the surrounding tile picks up an indigo
   tint so the whole card becomes a single "press-able" affordance. */
.quick-link {
    @apply flex items-center gap-3 px-3 py-2.5 rounded-lg;
    transition:
        background-color var(--motion-fast) var(--motion-out),
        transform        var(--motion-fast) var(--motion-out);
}
.quick-link:hover {
    @apply bg-indigo-50 dark:bg-indigo-900/20;
}
.quick-link-icon {
    @apply w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04), 0 4px 10px -6px rgba(99, 102, 241, 0.18);
}

/* List sections (Top Products / Customers / Recent Sales / Activity) —
   single visual rhythm. Header row, hover row, ranked chip. */
.dash-list-head {
    @apply px-5 py-3 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between gap-3;
}
.dash-list-link {
    @apply text-xs text-indigo-600 dark:text-indigo-400 font-semibold;
    transition: color var(--motion-fast) var(--motion-out);
}
.dash-list-link:hover { @apply text-indigo-700 dark:text-indigo-300 underline; }
.dash-list-row {
    @apply flex items-center justify-between gap-3 px-5 py-2.5;
    transition: background-color var(--motion-fast) var(--motion-out);
}
.dash-list-row:hover { @apply bg-slate-50/60 dark:bg-slate-800/40; }
.dash-list-rank {
    @apply text-xs text-slate-400 dark:text-slate-500 font-mono w-6 flex-shrink-0;
}

/* Open POS shortcut surfaced inside the empty Recent Sales card. */
.rs-cta {
    @apply inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold
           bg-emerald-600 text-white hover:bg-emerald-700 transition-colors;
    box-shadow: 0 1px 0 rgba(255,255,255,0.18) inset, var(--elev-1);
}
.rs-cta:hover { box-shadow: 0 1px 0 rgba(255,255,255,0.2) inset, var(--elev-2); }
</style>
