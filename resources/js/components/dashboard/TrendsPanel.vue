<template>
    <!--
        TrendsPanel — Phase AC Round 2.

        Replaces the static 14-day "Sales last 14 days" mini-chart on
        the dashboard with a full-period trend panel:

          – 4 metric tabs (Revenue / Profit / Purchase / Cash Flow)
          – 3 period tabs (7d / 30d / 90d)
          – smooth SVG line/area chart with axis ticks, hover dot
          – KPI summary: total, daily average, latest day, MoM delta
          – Skeleton loader on tab switch — no layout jump

        Pure CSS + inline SVG, no chart library. Branch-aware via the
        backend service.
    -->
    <section class="card trends-panel anim-fade-up">
        <!-- Header — metric tabs (left) + period chips (right). -->
        <header class="trends-head">
            <div class="trends-metrics">
                <button
                    v-for="m in metrics"
                    :key="m.key"
                    @click="setMetric(m.key)"
                    :class="['metric-tab', metric === m.key && 'is-active']"
                >
                    <component :is="m.icon" class="w-4 h-4" />
                    <span>{{ m.label }}</span>
                </button>
            </div>
            <div class="trends-periods">
                <button
                    v-for="p in periods"
                    :key="p.days"
                    @click="setDays(p.days)"
                    :class="['period-chip', days === p.days && 'is-active']"
                >
                    {{ p.label }}
                </button>
            </div>
        </header>

        <!-- KPI summary -->
        <div class="trends-kpis">
            <div class="trends-kpi">
                <p class="t-overline">{{ t('dashboard.trends.total', 'Total') }}</p>
                <p class="trends-kpi-value">{{ fmt(stats.total) }}</p>
            </div>
            <div class="trends-kpi">
                <p class="t-overline">{{ t('dashboard.trends.avgPerDay', 'Avg / day') }}</p>
                <p class="trends-kpi-value">{{ fmt(stats.avg) }}</p>
            </div>
            <div class="trends-kpi">
                <p class="t-overline">{{ t('dashboard.trends.latest', 'Latest') }}</p>
                <p class="trends-kpi-value">{{ fmt(stats.latest) }}</p>
            </div>
            <div class="trends-kpi">
                <p class="t-overline">{{ t('dashboard.trends.delta', 'vs prior') }}</p>
                <p :class="['trends-kpi-value', deltaToneClass]">
                    {{ stats.delta != null ? (stats.delta >= 0 ? '+' : '') + stats.delta + '%' : '—' }}
                </p>
            </div>
        </div>

        <!-- Chart canvas -->
        <div class="trends-canvas">
            <Skeleton v-if="loading" variant="chart" />
            <template v-else-if="series.length > 1">
                <svg :viewBox="`0 0 ${chartW} ${chartH}`" preserveAspectRatio="none" class="trends-svg" @mousemove="onHover" @mouseleave="hoverIdx = null">
                    <!-- Y-axis gridlines -->
                    <line v-for="g in gridY" :key="g.y" :x1="0" :x2="chartW" :y1="g.y" :y2="g.y" class="trends-grid" />

                    <!-- Filled area below the line -->
                    <path :d="areaPath" class="trends-area" />

                    <!-- The line itself -->
                    <path :d="linePath" class="trends-line" />

                    <!-- Hover marker -->
                    <template v-if="hoverIdx != null && series[hoverIdx]">
                        <line :x1="pointX(hoverIdx)" :x2="pointX(hoverIdx)" :y1="0" :y2="chartH" class="trends-hover-line" />
                        <circle :cx="pointX(hoverIdx)" :cy="pointY(series[hoverIdx].value)" r="4" class="trends-hover-dot" />
                    </template>
                </svg>

                <!-- Hover tooltip -->
                <div v-if="hoverIdx != null && series[hoverIdx]" class="trends-tooltip" :style="tooltipStyle">
                    <p class="trends-tooltip-date">{{ formatTooltipDate(series[hoverIdx].date) }}</p>
                    <p class="trends-tooltip-value">{{ fmt(series[hoverIdx].value) }}</p>
                </div>

                <!-- Axis labels -->
                <div class="trends-xaxis">
                    <span v-for="(t, i) in xTicks" :key="i" class="trends-xtick" :style="{ left: xtickPct(t.idx) + '%' }">
                        {{ formatXTick(t.date) }}
                    </span>
                </div>
            </template>
            <EmptyState
                v-else
                size="sm"
                tone="indigo"
                :icon="ChartBarIcon"
                :title="t('dashboard.trends.emptyTitle', 'Not enough data yet')"
                :description="t('dashboard.trends.emptyDesc', 'Trend lights up once there are at least two days of activity in the selected window.')"
            />
        </div>
    </section>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useSettingsStore } from '@/stores/settings';
import { useLocale } from '@/composables/useLocale';
import {
    BanknotesIcon, ChartBarIcon, CurrencyDollarIcon, TruckIcon, ArrowTrendingUpIcon,
} from '@heroicons/vue/24/outline';
import { dashboardService } from '@/services/dashboardService';
import Skeleton   from '@/components/ui/Skeleton.vue';
import EmptyState from '@/components/ui/EmptyState.vue';

const { t, locale } = useI18n();
const { intlLocale } = useLocale();
const settingsStore = useSettingsStore();

// Default to revenue / 30 days — most useful combination at a glance.
const metric = ref('revenue');
const days   = ref(30);
const series = ref([]);
const loading = ref(true);
const hoverIdx = ref(null);

const metrics = computed(() => [
    { key: 'revenue',   label: t('dashboard.trends.metric.revenue',  'Revenue'),   icon: ArrowTrendingUpIcon },
    { key: 'profit',    label: t('dashboard.trends.metric.profit',   'Profit'),    icon: ChartBarIcon },
    { key: 'purchase',  label: t('dashboard.trends.metric.purchase', 'Purchase'),  icon: TruckIcon },
    { key: 'cash_flow', label: t('dashboard.trends.metric.cashFlow', 'Cash Flow'), icon: BanknotesIcon },
]);
const periods = [
    { days:  7, label: '7d' },
    { days: 30, label: '30d' },
    { days: 90, label: '90d' },
];

// ── Data load — debounced reload on tab switch ───────────────────────────

let reloadTimer = null;
async function load() {
    loading.value = true;
    try {
        const res = await dashboardService.trends(metric.value, days.value);
        series.value = res.data?.data?.series ?? [];
        hoverIdx.value = null;
    } finally {
        loading.value = false;
    }
}
function setMetric(key) { if (key === metric.value) return; metric.value = key; reload(); }
function setDays(d)     { if (d === days.value)     return; days.value   = d;   reload(); }
function reload() {
    clearTimeout(reloadTimer);
    reloadTimer = setTimeout(load, 120);
}
onMounted(load);
onUnmounted(() => clearTimeout(reloadTimer));

// ── Chart geometry — pure math, no library ────────────────────────────────

// viewBox dimensions — kept arbitrary; SVG scales to the container.
const chartW = 800;
const chartH = 220;
const PAD_L = 0;
const PAD_R = 0;
const PAD_T = 14;
const PAD_B = 14;

const valueRange = computed(() => {
    if (!series.value.length) return { min: 0, max: 1 };
    const vals = series.value.map(p => Number(p.value) || 0);
    let min = Math.min(...vals);
    let max = Math.max(...vals);
    if (min === max) { min -= 1; max += 1; }
    // Pad 10% top + bottom so the line never kisses the chart edge.
    const span = max - min;
    return { min: min - span * 0.08, max: max + span * 0.08 };
});

function pointX(i) {
    const n = series.value.length;
    if (n <= 1) return PAD_L;
    return PAD_L + (i / (n - 1)) * (chartW - PAD_L - PAD_R);
}
function pointY(value) {
    const { min, max } = valueRange.value;
    if (max === min) return PAD_T + (chartH - PAD_T - PAD_B) / 2;
    const ratio = ((+value) - min) / (max - min);
    return chartH - PAD_B - ratio * (chartH - PAD_T - PAD_B);
}

const linePath = computed(() => {
    if (series.value.length < 2) return '';
    const pts = series.value.map((p, i) => `${pointX(i).toFixed(1)},${pointY(p.value).toFixed(1)}`);
    return 'M ' + pts.join(' L ');
});

const areaPath = computed(() => {
    if (series.value.length < 2) return '';
    const pts = series.value.map((p, i) => `${pointX(i).toFixed(1)},${pointY(p.value).toFixed(1)}`);
    const first = pointX(0).toFixed(1);
    const last  = pointX(series.value.length - 1).toFixed(1);
    return `M ${first},${chartH - PAD_B} L ${pts.join(' L ')} L ${last},${chartH - PAD_B} Z`;
});

// 4 evenly-spaced horizontal gridlines.
const gridY = computed(() =>
    [0.25, 0.5, 0.75].map(t => ({ y: PAD_T + t * (chartH - PAD_T - PAD_B) }))
);

// X-axis labels — at most 5 ticks (first, last, ~equidistant in the middle).
const xTicks = computed(() => {
    const n = series.value.length;
    if (n === 0) return [];
    const count = Math.min(5, n);
    const out = [];
    for (let i = 0; i < count; i++) {
        const idx = Math.round((i / (count - 1 || 1)) * (n - 1));
        out.push({ idx, date: series.value[idx]?.date });
    }
    return out;
});
function xtickPct(idx) {
    const n = series.value.length;
    return n <= 1 ? 0 : (idx / (n - 1)) * 100;
}

// ── KPI summary ──────────────────────────────────────────────────────────

const stats = computed(() => {
    const arr = series.value;
    if (!arr.length) return { total: 0, avg: 0, latest: 0, delta: null };
    const total  = arr.reduce((s, p) => s + (Number(p.value) || 0), 0);
    const avg    = total / arr.length;
    const latest = Number(arr[arr.length - 1].value || 0);

    // "vs prior": compare the second half of the window with the first half.
    const mid = Math.floor(arr.length / 2);
    const earlier = arr.slice(0, mid).reduce((s, p) => s + (Number(p.value) || 0), 0);
    const later   = arr.slice(mid).reduce((s, p) => s + (Number(p.value) || 0), 0);
    const delta = earlier > 0 ? Math.round(((later - earlier) / earlier) * 100) : null;

    return { total, avg, latest, delta };
});
const deltaToneClass = computed(() => {
    if (stats.value.delta == null) return '';
    return stats.value.delta >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400';
});

// ── Hover ────────────────────────────────────────────────────────────────

const tooltipStyle = ref({});
function onHover(e) {
    const rect = e.currentTarget.getBoundingClientRect();
    const x = e.clientX - rect.left;
    const n = series.value.length;
    if (n < 2) return;
    const idx = Math.max(0, Math.min(n - 1, Math.round((x / rect.width) * (n - 1))));
    hoverIdx.value = idx;
    // Tooltip placement — keep it inside the canvas.
    const pctX = (idx / (n - 1)) * 100;
    tooltipStyle.value = {
        left: `${pctX}%`,
        transform: pctX < 15 ? 'translateX(0)' : pctX > 85 ? 'translateX(-100%)' : 'translateX(-50%)',
    };
}

// ── Formatting helpers ───────────────────────────────────────────────────

function fmt(value) {
    if (value == null) return '—';
    const isMonetary = metric.value !== 'profit'  // profit can be negative; still currency-shaped
        ? true : true;
    if (isMonetary) {
        const code = settingsStore.settings?.currency_code ?? 'EUR';
        return new Intl.NumberFormat(intlLocale.value || 'en-US', {
            style: 'currency', currency: code, maximumFractionDigits: 0,
        }).format(Number(value) || 0);
    }
    return new Intl.NumberFormat(intlLocale.value || 'en-US').format(Number(value) || 0);
}

function formatXTick(iso) {
    if (!iso) return '';
    const d = new Date(iso);
    return new Intl.DateTimeFormat(intlLocale.value || 'en-US', { day: '2-digit', month: 'short' }).format(d);
}
function formatTooltipDate(iso) {
    if (!iso) return '';
    return new Intl.DateTimeFormat(intlLocale.value || 'en-US', { weekday: 'short', day: '2-digit', month: 'short' }).format(new Date(iso));
}
</script>

<style scoped>
@reference '../../../css/app.css';

.trends-panel {
    padding: 1rem 1.125rem 1.25rem;
    display: flex;
    flex-direction: column;
    gap: 0.875rem;
}

/* Header layout */
.trends-head {
    display: flex;
    flex-direction: column;
    gap: 0.625rem;
}
@media (min-width: 768px) {
    .trends-head { flex-direction: row; align-items: center; justify-content: space-between; }
}

.trends-metrics {
    display: flex;
    gap: 0.25rem;
    flex-wrap: wrap;
}
.metric-tab {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    padding: 0.375rem 0.75rem;
    border-radius: 0.5rem;
    font-size: 0.8125rem;
    font-weight: 600;
    color: var(--text-secondary);
    background: transparent;
    transition: background-color var(--motion-fast) var(--motion-out), color var(--motion-fast) var(--motion-out);
}
.metric-tab:hover { background: rgb(241 245 249); color: var(--text-primary); }
html.dark .metric-tab:hover { background: rgb(30 41 59 / 0.6); }
.metric-tab.is-active {
    background: rgb(238 242 255);
    color: rgb(67 56 202);
}
html.dark .metric-tab.is-active {
    background: rgb(67 56 202 / 0.25);
    color: rgb(165 180 252);
}

.trends-periods {
    display: inline-flex;
    border-radius: 0.5rem;
    border: 1px solid var(--border-default);
    background: var(--surface-sunken);
    padding: 2px;
}
.period-chip {
    padding: 0.25rem 0.625rem;
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--text-secondary);
    border-radius: 0.375rem;
    transition: background-color var(--motion-fast) var(--motion-out), color var(--motion-fast) var(--motion-out);
}
.period-chip:hover:not(.is-active) {
    color: var(--text-primary);
}
.period-chip.is-active {
    background: var(--surface-raised);
    color: var(--text-primary);
    box-shadow: var(--elev-1);
}

/* KPI strip */
.trends-kpis {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0.5rem;
}
@media (min-width: 640px) {
    .trends-kpis { grid-template-columns: repeat(4, 1fr); }
}
.trends-kpi {
    padding: 0.625rem 0.75rem;
    background: var(--surface-sunken);
    border: 1px solid var(--border-default);
    border-radius: 0.625rem;
}
.trends-kpi-value {
    margin-top: 0.125rem;
    font-size: 0.9375rem;
    font-weight: 700;
    color: var(--text-primary);
    font-variant-numeric: tabular-nums;
    letter-spacing: -0.01em;
}

/* Chart canvas */
.trends-canvas {
    position: relative;
    width: 100%;
    height: 240px;
    display: flex;
    align-items: flex-end;
}
.trends-svg {
    width: 100%; height: 100%;
    display: block;
    cursor: crosshair;
}
.trends-grid {
    stroke: var(--border-default);
    stroke-width: 1;
    stroke-dasharray: 2 4;
}
.trends-line {
    stroke: rgb(79 70 229);
    stroke-width: 2;
    fill: none;
    stroke-linecap: round;
    stroke-linejoin: round;
    filter: drop-shadow(0 1px 1px rgba(79, 70, 229, 0.25));
    transition: d 400ms var(--motion-out);
}
.trends-area {
    fill: url(#trends-gradient);
    opacity: 0.35;
}
html.dark .trends-line {
    stroke: rgb(129 140 248);
    filter: drop-shadow(0 1px 2px rgba(99, 102, 241, 0.35));
}
.trends-area { fill: rgba(99, 102, 241, 0.18); }
html.dark .trends-area { fill: rgba(165, 180, 252, 0.10); }

.trends-hover-line {
    stroke: rgb(99 102 241);
    stroke-width: 1;
    stroke-dasharray: 3 3;
    opacity: 0.6;
}
.trends-hover-dot {
    fill: white;
    stroke: rgb(79 70 229);
    stroke-width: 2.5;
}
html.dark .trends-hover-dot {
    fill: rgb(15 23 42);
    stroke: rgb(129 140 248);
}

/* Hover tooltip */
.trends-tooltip {
    position: absolute;
    bottom: 32px;
    padding: 0.375rem 0.625rem;
    background: var(--text-primary);
    color: var(--text-inverse);
    border-radius: 0.375rem;
    pointer-events: none;
    white-space: nowrap;
    box-shadow: var(--elev-2);
    z-index: 2;
}
html.dark .trends-tooltip {
    background: rgb(241 245 249);
    color: rgb(15 23 42);
}
.trends-tooltip-date { font-size: 0.6875rem; font-weight: 600; opacity: 0.8; }
.trends-tooltip-value { font-size: 0.8125rem; font-weight: 700; font-variant-numeric: tabular-nums; margin-top: 0.125rem; }

/* X axis */
.trends-xaxis {
    position: absolute;
    bottom: -4px;
    left: 0;
    right: 0;
    height: 18px;
    pointer-events: none;
}
.trends-xtick {
    position: absolute;
    transform: translateX(-50%);
    font-size: 0.6875rem;
    color: var(--text-tertiary);
    font-weight: 600;
    white-space: nowrap;
}
</style>
