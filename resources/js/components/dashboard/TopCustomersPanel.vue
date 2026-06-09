<template>
    <!--
        TopCustomersPanel — Phase AC Round 3.

        Replaces the single "Top Customers" list with a 4-tab tier panel:
          VIP / Recent / Highest Outstanding / Biggest This Month

        Each tab fetches its own slice from /api/dashboard/top-customers?tab=…
        Rows feature initial-based avatars, tier badges and a contextual
        right-side metric matching the tab.
    -->
    <section class="card top-customers-panel anim-fade-up">
        <header class="tc-head">
            <div class="min-w-0">
                <p class="t-overline">{{ t('dashboard.topCustomers.title', 'Top Customers') }}</p>
                <p class="t-caption mt-0.5">{{ tabSubtitle }}</p>
            </div>
            <RouterLink :to="{ name: 'customers' }" class="tc-link">
                {{ t('dashboard.viewAll', 'View all') }}
                <ArrowLongRightIcon class="w-3.5 h-3.5" />
            </RouterLink>
        </header>

        <div class="tc-tabs" role="tablist">
            <button
                v-for="t_ in tabs"
                :key="t_.key"
                @click="setTab(t_.key)"
                :class="['tc-tab', tab === t_.key && 'is-active']"
                role="tab"
                :aria-selected="tab === t_.key"
            >
                <component :is="t_.icon" class="w-3.5 h-3.5" />
                {{ t_.label }}
            </button>
        </div>

        <div class="tc-rows">
            <template v-if="loading">
                <Skeleton v-for="i in 3" :key="i" variant="row" />
            </template>

            <EmptyState
                v-else-if="!rows.length"
                size="sm"
                :tone="emptyState.tone"
                :icon="emptyState.icon"
                :title="emptyState.title"
                :description="emptyState.desc"
            />

            <ul v-else class="tc-list">
                <li v-for="(row, i) in rows" :key="row.id" class="tc-row">
                    <span class="tc-rank">#{{ i + 1 }}</span>
                    <span :class="['tc-avatar', `avatar-${avatarToneFor(i)}`]">{{ row.initials || '—' }}</span>
                    <div class="tc-body">
                        <div class="flex items-center gap-2 flex-wrap">
                            <p class="tc-name">{{ row.name }}</p>
                            <span v-if="row.tier" :class="['status-pill', tierToneFor(row.tier)]">{{ tierLabel(row.tier) }}</span>
                        </div>
                        <p class="t-caption font-mono">{{ row.code || row.phone || '—' }}</p>
                    </div>
                    <div class="tc-metric">
                        <span :class="['tc-metric-value', tab === 'outstanding' && 'is-warning']">
                            {{ formatMetric(row) }}
                        </span>
                        <span v-if="row.visits != null" class="t-caption">
                            {{ row.visits }} {{ t('dashboard.topCustomers.visits', 'visits') }}
                        </span>
                        <span v-else-if="row.invoice_count != null" class="t-caption">
                            {{ row.invoice_count }} {{ t('dashboard.topCustomers.invoices', 'invoices') }}
                        </span>
                    </div>
                </li>
            </ul>
        </div>
    </section>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { RouterLink } from 'vue-router';
import { useSettingsStore } from '@/stores/settings';
import { useLocale } from '@/composables/useLocale';
import {
    ArrowLongRightIcon, BanknotesIcon, ClockIcon, ExclamationCircleIcon,
    StarIcon, UsersIcon,
} from '@heroicons/vue/24/outline';
import { dashboardService } from '@/services/dashboardService';
import Skeleton   from '@/components/ui/Skeleton.vue';
import EmptyState from '@/components/ui/EmptyState.vue';

const { t, locale } = useI18n();
const { intlLocale } = useLocale();
const settingsStore = useSettingsStore();

const tab = ref('vip');
const rows = ref([]);
const loading = ref(true);

const tabs = computed(() => [
    { key: 'vip',         label: t('dashboard.topCustomers.vip',         'VIP'),          icon: StarIcon },
    { key: 'recent',      label: t('dashboard.topCustomers.recent',      'Recent'),       icon: ClockIcon },
    { key: 'outstanding', label: t('dashboard.topCustomers.outstanding', 'Highest due'),  icon: ExclamationCircleIcon },
    { key: 'biggest',     label: t('dashboard.topCustomers.biggest',     'Biggest sale'), icon: BanknotesIcon },
]);

const tabSubtitle = computed(() => ({
    vip:         t('dashboard.topCustomers.vipSubtitle',         'Lifetime top spenders — protect this relationship.'),
    recent:      t('dashboard.topCustomers.recentSubtitle',      'Customers with the most recent purchase.'),
    outstanding: t('dashboard.topCustomers.outstandingSubtitle', 'Largest outstanding receivable balances.'),
    biggest:     t('dashboard.topCustomers.biggestSubtitle',     'Highest single invoice this month.'),
})[tab.value]);

const emptyState = computed(() => ({
    vip: {
        tone: 'amber', icon: StarIcon,
        title: t('dashboard.topCustomers.vipEmpty', 'No VIP data yet'),
        desc:  t('dashboard.topCustomers.vipEmptyDesc', 'Customer rankings will populate as sales build up history.'),
    },
    recent: {
        tone: 'emerald', icon: UsersIcon,
        title: t('dashboard.topCustomers.recentEmpty', 'No recent activity'),
        desc:  t('dashboard.topCustomers.recentEmptyDesc', 'New sales will populate this list immediately.'),
    },
    outstanding: {
        tone: 'emerald', icon: ExclamationCircleIcon,
        title: t('dashboard.topCustomers.outstandingEmpty', 'All clear — no outstanding receivables'),
        desc:  t('dashboard.topCustomers.outstandingEmptyDesc', 'Every customer invoice has been settled.'),
    },
    biggest: {
        tone: 'indigo', icon: BanknotesIcon,
        title: t('dashboard.topCustomers.biggestEmpty', 'No sales yet this month'),
        desc:  t('dashboard.topCustomers.biggestEmptyDesc', 'Once invoices are issued the largest will appear here.'),
    },
})[tab.value]);

// ── Load + debounce switch ────────────────────────────────────────────────

let reloadTimer = null;
async function load() {
    loading.value = true;
    try {
        const res = await dashboardService.topCustomers(tab.value);
        rows.value = res.data?.data?.rows ?? [];
    } finally {
        loading.value = false;
    }
}
function setTab(k) {
    if (k === tab.value) return;
    tab.value = k;
    clearTimeout(reloadTimer);
    reloadTimer = setTimeout(load, 120);
}
onMounted(load);
onUnmounted(() => clearTimeout(reloadTimer));

// ── Display ──────────────────────────────────────────────────────────────

function tierLabel(t_) {
    return ({
        vip:     t('dashboard.topCustomers.tier.vip',      'VIP'),
        regular: t('dashboard.topCustomers.tier.regular',  'Regular'),
        recent:  t('dashboard.topCustomers.tier.recent',   'Recent'),
        overdue: t('dashboard.topCustomers.tier.overdue',  'Overdue'),
        biggest: t('dashboard.topCustomers.tier.biggest',  'Top buyer'),
    })[t_] || t_;
}

function tierToneFor(t_) {
    return ({
        vip:     'status-pill-warning',
        regular: 'status-pill-neutral',
        recent:  'status-pill-success',
        overdue: 'status-pill-danger',
        biggest: 'status-pill-info',
    })[t_] || 'status-pill-neutral';
}

// Deterministic avatar tone so the same customer always gets the same colour.
function avatarToneFor(idx) {
    return ['indigo', 'emerald', 'amber', 'rose', 'sky'][idx % 5];
}

function formatMetric(row) {
    switch (row.metric_label) {
        case 'lifetime_revenue':
        case 'outstanding':
        case 'biggest_sale':
            return fmt(row.metric_value);
        case 'last_sale':
            return formatRelativeDate(row.metric_value);
        default:
            return String(row.metric_value || '—');
    }
}

function fmt(value) {
    if (value == null) return '—';
    const code = settingsStore.settings?.currency_code ?? 'EUR';
    return new Intl.NumberFormat(intlLocale.value || 'en-US', { style: 'currency', currency: code, maximumFractionDigits: 0 })
        .format(Number(value) || 0);
}

function formatRelativeDate(iso) {
    if (!iso) return '—';
    const today = new Date();
    const d = new Date(iso);
    const days = Math.floor((today - d) / 86400000);
    if (days === 0) return t('dashboard.topCustomers.today', 'Today');
    if (days === 1) return t('dashboard.topCustomers.yesterday', 'Yesterday');
    if (days < 7)   return `${days}d ${t('dashboard.topCustomers.ago', 'ago')}`;
    return new Intl.DateTimeFormat(intlLocale.value || 'en-US', { day: '2-digit', month: 'short' }).format(d);
}
</script>

<style scoped>
@reference '../../../css/app.css';

.top-customers-panel { padding: 1rem 1.125rem 1.125rem; display: flex; flex-direction: column; gap: 0.75rem; }
.tc-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 0.5rem; }
.tc-link {
    display: inline-flex; align-items: center; gap: 0.25rem;
    padding: 0.25rem 0.5rem; border-radius: 0.375rem;
    font-size: 0.6875rem; font-weight: 600;
    color: rgb(67 56 202);
    transition: background-color var(--motion-fast) var(--motion-out);
}
.tc-link:hover { background: rgb(238 242 255); }
html.dark .tc-link { color: rgb(165 180 252); }
html.dark .tc-link:hover { background: rgb(67 56 202 / 0.18); }

.tc-tabs {
    display: flex;
    gap: 0.25rem;
    overflow-x: auto;
    scrollbar-width: none;
    padding-bottom: 0.125rem;
}
.tc-tabs::-webkit-scrollbar { display: none; }
.tc-tab {
    display: inline-flex; align-items: center; gap: 0.375rem;
    padding: 0.375rem 0.625rem;
    border-radius: 0.5rem;
    font-size: 0.75rem; font-weight: 600;
    color: var(--text-secondary);
    background: transparent;
    transition: background-color var(--motion-fast) var(--motion-out), color var(--motion-fast) var(--motion-out);
    white-space: nowrap;
}
.tc-tab:hover { background: rgb(241 245 249); color: var(--text-primary); }
html.dark .tc-tab:hover { background: rgb(30 41 59 / 0.6); }
.tc-tab.is-active {
    background: rgb(238 242 255);
    color: rgb(67 56 202);
}
html.dark .tc-tab.is-active { background: rgb(67 56 202 / 0.25); color: rgb(165 180 252); }

.tc-rows { display: flex; flex-direction: column; gap: 0.5rem; min-height: 60px; }
.tc-list { list-style: none; margin: 0; padding: 0; }
.tc-row {
    display: grid;
    grid-template-columns: auto auto 1fr auto;
    align-items: center;
    gap: 0.625rem;
    padding: 0.625rem 0;
    border-top: 1px solid var(--border-subtle);
}
.tc-row:first-child { border-top: 0; }

.tc-rank {
    display: inline-flex; align-items: center; justify-content: center;
    min-width: 26px; height: 26px;
    padding: 0 0.375rem;
    border-radius: 0.375rem;
    background: var(--surface-sunken);
    color: var(--text-secondary);
    font-size: 0.6875rem; font-weight: 700;
    font-variant-numeric: tabular-nums;
}

.tc-avatar {
    width: 36px; height: 36px;
    border-radius: 999px;
    display: grid; place-items: center;
    font-size: 0.75rem;
    font-weight: 700;
    color: white;
    flex-shrink: 0;
    letter-spacing: 0.02em;
}
.avatar-indigo  { background: linear-gradient(135deg, rgb(99 102 241), rgb(79 70 229)); }
.avatar-emerald { background: linear-gradient(135deg, rgb(16 185 129), rgb(5 150 105)); }
.avatar-amber   { background: linear-gradient(135deg, rgb(245 158 11), rgb(217 119 6)); }
.avatar-rose    { background: linear-gradient(135deg, rgb(244 63 94),  rgb(225 29 72)); }
.avatar-sky     { background: linear-gradient(135deg, rgb(14 165 233), rgb(2 132 199)); }

.tc-body { min-width: 0; }
.tc-name {
    font-size: 0.8125rem;
    font-weight: 600;
    color: var(--text-primary);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.tc-metric {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 0.125rem;
}
.tc-metric-value {
    font-size: 0.8125rem;
    font-weight: 700;
    color: var(--text-primary);
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
}
.tc-metric-value.is-warning { color: rgb(190 18 60); }
html.dark .tc-metric-value.is-warning { color: rgb(253 164 175); }
</style>
