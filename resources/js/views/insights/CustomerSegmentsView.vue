<template>
    <!--
        Customer RFM dashboard. Five tier cards across the top show the
        headcount per segment with a top-3 leaderboard underneath. The
        drill-down panel at the bottom swaps in the full customer list
        for whichever segment the owner picked.
    -->
    <div class="p-3 sm:p-6 lg:p-8 space-y-4 sm:space-y-6 max-w-6xl mx-auto anim-fade-in">

        <header class="anim-fade-up">
            <p class="t-overline text-indigo-500 mb-1.5">{{ t('insights.module', 'Business Insights') }}</p>
            <h1 class="h1-display">{{ t('insights.segments.title', 'Customer segments') }}</h1>
            <p class="mt-1.5 t-body">{{ t('insights.segments.subtitle', 'Customers ranked by Recency, Frequency and Monetary value over the past 365 days. Use the tiers to focus calls, campaigns and retention.') }}</p>
        </header>

        <!-- Loading -->
        <div v-if="loading && !summary" class="grid grid-cols-2 sm:grid-cols-5 gap-3">
            <Skeleton v-for="i in 5" :key="i" variant="kpi-card" />
        </div>

        <template v-else-if="summary">
            <!-- Top-line totals -->
            <section class="grid grid-cols-3 gap-3 anim-fade-up">
                <div class="card card-kpi">
                    <p class="t-overline">{{ t('insights.segments.totalCustomers', 'Customers') }}</p>
                    <p class="t-kpi mt-1">{{ summary.totals.customers }}</p>
                </div>
                <div class="card card-kpi">
                    <p class="t-overline">{{ t('insights.segments.totalRevenue', 'Lifetime revenue · 365d') }}</p>
                    <p class="t-kpi mt-1">{{ fmt(summary.totals.revenue) }}</p>
                </div>
                <div class="card card-kpi">
                    <p class="t-overline">{{ t('insights.segments.avgBasket', 'Avg basket') }}</p>
                    <p class="t-kpi mt-1">{{ fmt(summary.totals.avg_basket) }}</p>
                </div>
            </section>

            <!-- Segment tier cards -->
            <section class="grid grid-cols-2 sm:grid-cols-5 gap-3 anim-fade-up">
                <button
                    v-for="seg in segments"
                    :key="seg.key"
                    :class="['card seg-card', `seg-${seg.key.toLowerCase()}`, activeSegment === seg.key && 'is-active']"
                    @click="loadSegment(seg.key)"
                >
                    <div class="seg-head">
                        <span :class="['seg-badge', `seg-badge-${seg.key.toLowerCase()}`]"></span>
                        <p class="seg-name">{{ t(`insights.segments.tier.${seg.key.toLowerCase()}`, seg.key) }}</p>
                    </div>
                    <p class="seg-count">{{ summary.counts[seg.key] || 0 }}</p>
                    <p class="t-caption">{{ t('insights.segments.customers', 'customers') }}</p>

                    <!-- Top 3 by spend -->
                    <ul v-if="(summary.top[seg.key] || []).length" class="seg-top">
                        <li v-for="(c, i) in summary.top[seg.key]" :key="c.customer_id">
                            <span class="seg-top-rank">{{ i + 1 }}</span>
                            <span class="seg-top-name">{{ c.name }}</span>
                            <span class="seg-top-spend">{{ fmt(c.monetary_value) }}</span>
                        </li>
                    </ul>
                </button>
            </section>

            <!-- Drill-down list -->
            <section v-if="activeSegment" class="card overflow-hidden anim-fade-up">
                <header class="dash-list-head">
                    <div>
                        <p class="t-overline">{{ t('insights.segments.drilldown', 'Customers in tier') }}: {{ t(`insights.segments.tier.${activeSegment.toLowerCase()}`, activeSegment) }}</p>
                        <p class="t-caption mt-0.5">{{ tierHint(activeSegment) }}</p>
                    </div>
                    <button class="t-caption underline" @click="activeSegment = null">{{ t('common.close', 'Close') }}</button>
                </header>

                <div v-if="loadingList" class="p-4 space-y-2">
                    <Skeleton v-for="i in 4" :key="i" variant="row" />
                </div>

                <EmptyState
                    v-else-if="!segmentList.length"
                    size="sm"
                    tone="slate"
                    :icon="UsersIcon"
                    :title="t('insights.segments.emptyTier', 'No customers in this tier yet')"
                />

                <div v-else class="responsive-table">
                    <table class="data-table table-premium">
                        <thead>
                            <tr>
                                <th>{{ t('common.name', 'Name') }}</th>
                                <th>{{ t('insights.segments.cols.lastSale', 'Last sale') }}</th>
                                <th class="text-right">{{ t('insights.segments.cols.frequency', 'Visits') }}</th>
                                <th class="text-right">{{ t('insights.segments.cols.monetary', 'Spend · 365d') }}</th>
                                <th class="text-right">{{ t('insights.segments.cols.rfm', 'RFM') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="c in segmentList" :key="c.customer_id">
                                <td>
                                    <p class="font-semibold text-slate-900 dark:text-slate-100">{{ c.name }}</p>
                                    <p class="t-caption font-mono">{{ c.code || c.phone || '—' }}</p>
                                </td>
                                <td>
                                    <span class="t-caption">{{ c.last_sale }} · {{ c.days_since }}d</span>
                                </td>
                                <td class="text-right font-mono">{{ c.frequency_count }}</td>
                                <td class="text-right font-mono font-semibold">{{ fmt(c.monetary_value) }}</td>
                                <td class="text-right">
                                    <span class="rfm-cell">{{ c.r_score }}-{{ c.f_score }}-{{ c.m_score }}</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </template>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { UsersIcon } from '@heroicons/vue/24/outline';
import { useSettingsStore } from '@/stores/settings';
import { useLocale } from '@/composables/useLocale';
import { insightsService } from '@/services/insightsService';
import Skeleton   from '@/components/ui/Skeleton.vue';
import EmptyState from '@/components/ui/EmptyState.vue';

const { t } = useI18n();
const { intlLocale } = useLocale();
const settingsStore = useSettingsStore();

const summary = ref(null);
const loading = ref(true);
const activeSegment = ref(null);
const segmentList = ref([]);
const loadingList = ref(false);

const segments = [
    { key: 'Platinum' },
    { key: 'Gold' },
    { key: 'Silver' },
    { key: 'Bronze' },
    { key: 'Dormant' },
];

async function load() {
    loading.value = true;
    try {
        const res = await insightsService.customerSegments();
        summary.value = res.data?.data ?? null;
    } finally {
        loading.value = false;
    }
}

async function loadSegment(key) {
    activeSegment.value = key;
    loadingList.value = true;
    try {
        const res = await insightsService.customersInSegment(key);
        segmentList.value = res.data?.data?.customers ?? [];
    } finally {
        loadingList.value = false;
    }
}

function fmt(value) {
    if (value == null) return '—';
    const code = settingsStore.settings?.currency_code ?? 'EUR';
    return new Intl.NumberFormat(intlLocale.value || 'en-US', { style: 'currency', currency: code, maximumFractionDigits: 0 })
        .format(Number(value) || 0);
}

function tierHint(tier) {
    return ({
        Platinum: t('insights.segments.tierHint.platinum', 'Recent, frequent, top spenders. Protect this relationship — they fund the business.'),
        Gold:     t('insights.segments.tierHint.gold',     'Broadly healthy across all three axes. One nudge away from Platinum.'),
        Silver:   t('insights.segments.tierHint.silver',   'Engaged but not consistent. Targeted promotions move them up.'),
        Bronze:   t('insights.segments.tierHint.bronze',   'Single-purchase or low-frequency. Re-engagement opportunity.'),
        Dormant:  t('insights.segments.tierHint.dormant',  'No purchase in 180+ days. Win-back campaign candidate.'),
    })[tier] || '';
}

onMounted(load);
</script>

<style scoped>
@reference '../../../css/app.css';

.seg-card {
    padding: 0.875rem 1rem;
    text-align: left;
    transition: transform var(--motion-fast) var(--motion-out), box-shadow var(--motion-fast) var(--motion-out), border-color var(--motion-fast) var(--motion-out);
    cursor: pointer;
}
.seg-card:hover { transform: translateY(-2px); box-shadow: var(--elev-2); border-color: var(--border-strong); }
.seg-card.is-active {
    border-color: rgb(79 70 229);
    box-shadow: 0 0 0 1px rgb(79 70 229), var(--elev-2);
}

.seg-head { display: flex; align-items: center; gap: 0.5rem; }
.seg-badge {
    width: 10px; height: 10px;
    border-radius: 999px;
    flex-shrink: 0;
}
.seg-badge-platinum { background: linear-gradient(135deg, rgb(165 180 252), rgb(99 102 241)); box-shadow: 0 0 0 3px rgba(99,102,241,0.18); }
.seg-badge-gold     { background: linear-gradient(135deg, rgb(252 211 77), rgb(245 158 11)); box-shadow: 0 0 0 3px rgba(245,158,11,0.18); }
.seg-badge-silver   { background: linear-gradient(135deg, rgb(203 213 225), rgb(148 163 184)); box-shadow: 0 0 0 3px rgba(148,163,184,0.18); }
.seg-badge-bronze   { background: linear-gradient(135deg, rgb(214 162 122), rgb(180 122 92)); box-shadow: 0 0 0 3px rgba(180,122,92,0.18); }
.seg-badge-dormant  { background: linear-gradient(135deg, rgb(244 63 94), rgb(190 18 60)); box-shadow: 0 0 0 3px rgba(244,63,94,0.18); }

.seg-name {
    font-size: 0.875rem;
    font-weight: 700;
    color: var(--text-primary);
}
.seg-count {
    margin-top: 0.5rem;
    font-size: 1.5rem;
    font-weight: 800;
    color: var(--text-primary);
    font-variant-numeric: tabular-nums;
    letter-spacing: -0.02em;
    line-height: 1;
}

.seg-top {
    margin-top: 0.75rem;
    border-top: 1px solid var(--border-subtle);
    padding-top: 0.5rem;
    list-style: none;
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}
.seg-top li {
    display: grid;
    grid-template-columns: auto 1fr auto;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.6875rem;
}
.seg-top-rank {
    color: var(--text-tertiary);
    font-weight: 700;
    font-variant-numeric: tabular-nums;
}
.seg-top-name {
    color: var(--text-primary);
    font-weight: 500;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.seg-top-spend {
    color: var(--text-secondary);
    font-weight: 700;
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
}

.rfm-cell {
    font-family: ui-monospace, SF Mono, Menlo, monospace;
    font-weight: 700;
    color: var(--text-primary);
    background: var(--surface-sunken);
    padding: 0.125rem 0.5rem;
    border-radius: 999px;
}
</style>
