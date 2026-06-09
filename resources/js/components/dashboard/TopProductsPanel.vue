<template>
    <!--
        TopProductsPanel — Phase AC Round 3.

        Replaces the single "Top Products" list with a 4-tab tier panel:
          Best Sellers / Slow Movers / Dead Stock / Reorder Needed

        Each tab fetches its own slice from /api/dashboard/top-products?tab=…
        and presents a premium row with rank, image, name, SKU and a
        contextual right-side metric (qty, days idle, shortfall, etc.).

        Tabs are kept lightweight — no SWR cache, just a 120ms debounce
        on switch so quick toggling doesn't hammer the backend.
    -->
    <section class="card top-products-panel anim-fade-up">
        <header class="tp-head">
            <div class="min-w-0">
                <p class="t-overline">{{ t('dashboard.topProducts.title', 'Top Products') }}</p>
                <p class="t-caption mt-0.5">{{ tabSubtitle }}</p>
            </div>
            <RouterLink :to="{ name: 'products' }" class="tp-link">
                {{ t('dashboard.viewAll', 'View all') }}
                <ArrowLongRightIcon class="w-3.5 h-3.5" />
            </RouterLink>
        </header>

        <!-- Tabs -->
        <div class="tp-tabs" role="tablist">
            <button
                v-for="t_ in tabs"
                :key="t_.key"
                @click="setTab(t_.key)"
                :class="['tp-tab', tab === t_.key && 'is-active']"
                role="tab"
                :aria-selected="tab === t_.key"
            >
                <component :is="t_.icon" class="w-3.5 h-3.5" />
                {{ t_.label }}
            </button>
        </div>

        <!-- Rows -->
        <div class="tp-rows">
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

            <ul v-else class="tp-list">
                <li v-for="(row, i) in rows" :key="row.id" class="tp-row">
                    <span class="tp-rank">#{{ i + 1 }}</span>
                    <div class="tp-img">
                        <img v-if="row.image" :src="resolveImage(row.image)" :alt="row.name" />
                        <CubeIcon v-else class="w-4 h-4 text-slate-400" />
                    </div>
                    <div class="tp-body">
                        <p class="tp-name">{{ row.name }}</p>
                        <p class="t-caption font-mono">{{ row.sku }}</p>
                    </div>
                    <div class="tp-metric">
                        <span :class="['status-pill', metricTone]">{{ formatMetric(row) }}</span>
                        <span v-if="row.revenue != null" class="tp-revenue">{{ fmt(row.revenue) }}</span>
                    </div>
                </li>
            </ul>
        </div>
    </section>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { RouterLink } from 'vue-router';
import { useSettingsStore } from '@/stores/settings';
import { useLocale } from '@/composables/useLocale';
import {
    ArrowLongRightIcon, BoltIcon, ClockIcon, ArchiveBoxIcon, FireIcon, CubeIcon,
} from '@heroicons/vue/24/outline';
import { dashboardService } from '@/services/dashboardService';
import Skeleton   from '@/components/ui/Skeleton.vue';
import EmptyState from '@/components/ui/EmptyState.vue';

const { t } = useI18n();
const { intlLocale } = useLocale();
const settingsStore = useSettingsStore();

const tab = ref('best');
const rows = ref([]);
const loading = ref(true);

const tabs = computed(() => [
    { key: 'best',    label: t('dashboard.topProducts.best',    'Best sellers'),   icon: BoltIcon },
    { key: 'slow',    label: t('dashboard.topProducts.slow',    'Slow movers'),    icon: ClockIcon },
    { key: 'dead',    label: t('dashboard.topProducts.dead',    'Dead stock'),     icon: ArchiveBoxIcon },
    { key: 'reorder', label: t('dashboard.topProducts.reorder', 'Reorder needed'), icon: FireIcon },
]);

const tabSubtitle = computed(() => ({
    best:    t('dashboard.topProducts.bestSubtitle',    'Highest revenue products this month.'),
    slow:    t('dashboard.topProducts.slowSubtitle',    'Sold but moved 5 units or fewer this month.'),
    dead:    t('dashboard.topProducts.deadSubtitle',    'Active products with no sale in 90+ days.'),
    reorder: t('dashboard.topProducts.reorderSubtitle', 'At or below reorder level — restock soon.'),
})[tab.value]);

const emptyState = computed(() => ({
    best: {
        tone: 'emerald', icon: BoltIcon,
        title: t('dashboard.topProducts.bestEmpty', 'No sales yet this month'),
        desc:  t('dashboard.topProducts.bestEmptyDesc', 'Top sellers will appear after the first sales are recorded.'),
    },
    slow: {
        tone: 'amber', icon: ClockIcon,
        title: t('dashboard.topProducts.slowEmpty', 'No slow movers detected'),
        desc:  t('dashboard.topProducts.slowEmptyDesc', 'Every selling product moved more than 5 units this month.'),
    },
    dead: {
        tone: 'slate', icon: ArchiveBoxIcon,
        title: t('dashboard.topProducts.deadEmpty', 'No dead stock'),
        desc:  t('dashboard.topProducts.deadEmptyDesc', 'Every active product had at least one sale in the last 90 days.'),
    },
    reorder: {
        tone: 'emerald', icon: FireIcon,
        title: t('dashboard.topProducts.reorderEmpty', 'Stock levels healthy'),
        desc:  t('dashboard.topProducts.reorderEmptyDesc', 'No active products are below their reorder level.'),
    },
})[tab.value]);

const metricTone = computed(() => ({
    best:    'status-pill-success',
    slow:    'status-pill-warning',
    dead:    'status-pill-neutral',
    reorder: 'status-pill-danger',
})[tab.value]);

// ── Load + debounce switch ────────────────────────────────────────────────

let reloadTimer = null;
async function load() {
    loading.value = true;
    try {
        const res = await dashboardService.topProducts(tab.value);
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

function formatMetric(row) {
    switch (row.metric_label) {
        case 'qty_sold':
            return `${formatNumber(row.metric_value)} ${t('dashboard.topProducts.units', 'units')}`;
        case 'days_idle':
            return `${row.metric_value}+ ${t('dashboard.topProducts.days', 'd idle')}`;
        case 'shortfall':
            return `${t('dashboard.topProducts.short', 'short')} ${formatNumber(row.metric_value)}`;
        default:
            return formatNumber(row.metric_value);
    }
}

function formatNumber(value) {
    return new Intl.NumberFormat(intlLocale.value || 'en-US').format(Number(value) || 0);
}

function fmt(value) {
    if (value == null) return '—';
    const code = settingsStore.settings?.currency_code ?? 'EUR';
    return new Intl.NumberFormat(intlLocale.value || 'en-US', { style: 'currency', currency: code, maximumFractionDigits: 0 })
        .format(Number(value) || 0);
}

function resolveImage(src) {
    if (!src) return '';
    if (src.startsWith('http') || src.startsWith('/')) return src;
    return '/storage/' + src;
}
</script>

<style scoped>
@reference '../../../css/app.css';

.top-products-panel { padding: 1rem 1.125rem 1.125rem; display: flex; flex-direction: column; gap: 0.75rem; }
.tp-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 0.5rem; }
.tp-link {
    display: inline-flex; align-items: center; gap: 0.25rem;
    padding: 0.25rem 0.5rem; border-radius: 0.375rem;
    font-size: 0.6875rem; font-weight: 600;
    color: rgb(67 56 202);
    transition: background-color var(--motion-fast) var(--motion-out);
}
.tp-link:hover { background: rgb(238 242 255); }
html.dark .tp-link { color: rgb(165 180 252); }
html.dark .tp-link:hover { background: rgb(67 56 202 / 0.18); }

/* Tabs */
.tp-tabs {
    display: flex;
    gap: 0.25rem;
    overflow-x: auto;
    scrollbar-width: none;
    padding-bottom: 0.125rem;
}
.tp-tabs::-webkit-scrollbar { display: none; }
.tp-tab {
    display: inline-flex; align-items: center; gap: 0.375rem;
    padding: 0.375rem 0.625rem;
    border-radius: 0.5rem;
    font-size: 0.75rem; font-weight: 600;
    color: var(--text-secondary);
    background: transparent;
    transition: background-color var(--motion-fast) var(--motion-out), color var(--motion-fast) var(--motion-out);
    white-space: nowrap;
}
.tp-tab:hover { background: rgb(241 245 249); color: var(--text-primary); }
html.dark .tp-tab:hover { background: rgb(30 41 59 / 0.6); }
.tp-tab.is-active {
    background: rgb(238 242 255);
    color: rgb(67 56 202);
}
html.dark .tp-tab.is-active { background: rgb(67 56 202 / 0.25); color: rgb(165 180 252); }

/* Rows */
.tp-rows { display: flex; flex-direction: column; gap: 0.5rem; min-height: 60px; }
.tp-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; }
.tp-row {
    display: grid;
    grid-template-columns: auto auto 1fr auto;
    align-items: center;
    gap: 0.625rem;
    padding: 0.625rem 0;
    border-top: 1px solid var(--border-subtle);
}
.tp-row:first-child { border-top: 0; }

.tp-rank {
    display: inline-flex; align-items: center; justify-content: center;
    min-width: 26px; height: 26px;
    padding: 0 0.375rem;
    border-radius: 0.375rem;
    background: var(--surface-sunken);
    color: var(--text-secondary);
    font-size: 0.6875rem; font-weight: 700;
    font-variant-numeric: tabular-nums;
}
.tp-img {
    width: 36px; height: 36px;
    border-radius: 0.5rem;
    background: var(--surface-sunken);
    display: grid; place-items: center;
    overflow: hidden;
    flex-shrink: 0;
}
.tp-img img { width: 100%; height: 100%; object-fit: cover; }

.tp-body { min-width: 0; }
.tp-name {
    font-size: 0.8125rem;
    font-weight: 600;
    color: var(--text-primary);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.tp-metric {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 0.125rem;
}
.tp-revenue {
    font-size: 0.6875rem;
    font-weight: 700;
    color: var(--text-tertiary);
    font-variant-numeric: tabular-nums;
}
</style>
