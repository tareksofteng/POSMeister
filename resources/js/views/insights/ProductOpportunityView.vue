<template>
    <!--
        Product opportunity engine. Three lenses on what to push next:
        the strongest product associations (market basket), category
        growth period-over-period, and the margin mix of last month's
        revenue.
    -->
    <div class="p-3 sm:p-6 lg:p-8 space-y-4 sm:space-y-6 max-w-6xl mx-auto anim-fade-in">

        <header class="anim-fade-up">
            <p class="t-overline text-indigo-500 mb-1.5">{{ t('insights.module', 'Business Insights') }}</p>
            <h1 class="h1-display">{{ t('insights.opportunities.title', 'Product opportunities') }}</h1>
            <p class="mt-1.5 t-body">{{ t('insights.opportunities.subtitle', 'What sells together, which categories are growing, and where your margin actually comes from. All sourced from sale_items — no third-party tracker, no AI.') }}</p>
        </header>

        <div v-if="loading && !data" class="space-y-4">
            <Skeleton variant="kpi-card" />
            <Skeleton variant="row" />
            <Skeleton variant="row" />
        </div>

        <template v-else-if="data">
            <!-- Margin mix strip -->
            <section class="card margin-card anim-fade-up">
                <header class="margin-head">
                    <div>
                        <p class="t-overline">{{ t('insights.opportunities.margin.title', 'Margin mix · last 30 days') }}</p>
                        <p class="t-caption mt-0.5">{{ t('insights.opportunities.margin.subtitle', 'Share of revenue by gross margin band — protect the high band, fix the loss band.') }}</p>
                    </div>
                </header>
                <div class="margin-bar">
                    <div
                        v-for="b in data.margin_mix"
                        :key="b.band"
                        :class="['margin-seg', `margin-${b.band}`]"
                        :style="{ width: b.pct + '%' }"
                        :title="`${marginLabel(b.band)} · ${b.pct}%`"
                    />
                </div>
                <div class="margin-legend">
                    <div v-for="b in data.margin_mix" :key="b.band" class="margin-cell">
                        <span :class="['margin-swatch', `margin-${b.band}`]" />
                        <div class="min-w-0">
                            <p class="margin-band">{{ marginLabel(b.band) }}</p>
                            <p class="t-caption">{{ fmt(b.revenue) }} · {{ b.products }} {{ t('insights.opportunities.margin.products', 'products') }}</p>
                        </div>
                        <p class="margin-pct">{{ b.pct }}%</p>
                    </div>
                </div>
            </section>

            <!-- Frequently bought together -->
            <section class="card overflow-hidden anim-fade-up">
                <header class="dash-list-head">
                    <div>
                        <p class="t-overline">{{ t('insights.opportunities.fbt.title', 'Frequently bought together') }}</p>
                        <p class="t-caption mt-0.5">{{ t('insights.opportunities.fbt.subtitle', 'Product pairs that show up in the same basket. Lift > 1 means the pair occurs more often than chance.') }}</p>
                    </div>
                </header>
                <EmptyState
                    v-if="!data.fbt.length"
                    size="sm" tone="indigo"
                    :icon="LinkIcon"
                    :title="t('insights.opportunities.fbt.empty', 'Not enough multi-item baskets yet')"
                    :description="t('insights.opportunities.fbt.emptyDesc', 'Pairs appear after at least three baskets contain the same two products in the last 180 days.')"
                />
                <ul v-else class="divide-y divide-slate-100 dark:divide-slate-800">
                    <li v-for="(pair, i) in data.fbt" :key="`${pair.product_a.id}-${pair.product_b.id}`" class="fbt-row">
                        <span class="fbt-rank">#{{ i + 1 }}</span>
                        <div class="fbt-products min-w-0">
                            <div class="fbt-product">
                                <span class="fbt-bullet" />
                                <div class="min-w-0">
                                    <p class="fbt-name">{{ pair.product_a.name }}</p>
                                    <p class="t-caption font-mono">{{ pair.product_a.sku || '—' }}</p>
                                </div>
                            </div>
                            <div class="fbt-plus">+</div>
                            <div class="fbt-product">
                                <span class="fbt-bullet" />
                                <div class="min-w-0">
                                    <p class="fbt-name">{{ pair.product_b.name }}</p>
                                    <p class="t-caption font-mono">{{ pair.product_b.sku || '—' }}</p>
                                </div>
                            </div>
                        </div>
                        <div class="fbt-stats flex-shrink-0">
                            <div class="text-right">
                                <p class="fbt-stat-value">{{ pair.together_count }}</p>
                                <p class="t-caption">{{ t('insights.opportunities.fbt.baskets', 'baskets') }}</p>
                            </div>
                            <div class="text-right">
                                <p class="fbt-stat-value">{{ pair.lift }}×</p>
                                <p class="t-caption">{{ t('insights.opportunities.fbt.lift', 'lift') }}</p>
                            </div>
                            <span :class="['status-pill', liftTone(pair.verdict)]">{{ liftLabel(pair.verdict) }}</span>
                        </div>
                    </li>
                </ul>
            </section>

            <!-- Category growth -->
            <section class="card overflow-hidden anim-fade-up">
                <header class="dash-list-head">
                    <div>
                        <p class="t-overline">{{ t('insights.opportunities.growth.title', 'Category growth · 30d vs prior 30d') }}</p>
                        <p class="t-caption mt-0.5">{{ t('insights.opportunities.growth.subtitle', 'Where to add SKUs, where to thin the shelf.') }}</p>
                    </div>
                </header>
                <EmptyState
                    v-if="!data.category_growth.length"
                    size="sm" tone="slate"
                    :icon="TagIcon"
                    :title="t('insights.opportunities.growth.empty', 'Not enough category-tagged sales yet')"
                />
                <ul v-else class="divide-y divide-slate-100 dark:divide-slate-800">
                    <li v-for="c in data.category_growth" :key="c.category_id" class="cat-row">
                        <div class="min-w-0 flex-1">
                            <p class="cat-name">{{ c.name }}</p>
                            <p class="t-caption">{{ fmt(c.now) }} {{ t('insights.opportunities.growth.now', 'now') }} · {{ fmt(c.was) }} {{ t('insights.opportunities.growth.prior', 'prior') }}</p>
                        </div>
                        <div class="text-right flex-shrink-0">
                            <p :class="['cat-delta', growthClass(c.delta_pct)]">
                                <template v-if="c.delta_pct == null">—</template>
                                <template v-else>{{ c.delta_pct >= 0 ? '+' : '' }}{{ c.delta_pct }}%</template>
                            </p>
                            <span :class="['status-pill', growthTone(c.verdict)]">{{ growthLabel(c.verdict) }}</span>
                        </div>
                    </li>
                </ul>
            </section>
        </template>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { LinkIcon, TagIcon } from '@heroicons/vue/24/outline';
import { useSettingsStore } from '@/stores/settings';
import { useLocale } from '@/composables/useLocale';
import { insightsService } from '@/services/insightsService';
import Skeleton   from '@/components/ui/Skeleton.vue';
import EmptyState from '@/components/ui/EmptyState.vue';

const { t } = useI18n();
const { intlLocale } = useLocale();
const settingsStore = useSettingsStore();

const data = ref(null);
const loading = ref(true);

async function load() {
    loading.value = true;
    try {
        const res = await insightsService.productOpportunities();
        data.value = res.data?.data ?? null;
    } finally {
        loading.value = false;
    }
}
onMounted(load);

function fmt(value) {
    if (value == null) return '—';
    const code = settingsStore.settings?.currency_code ?? 'EUR';
    return new Intl.NumberFormat(intlLocale.value || 'en-US', { style: 'currency', currency: code, maximumFractionDigits: 0 })
        .format(Number(value) || 0);
}

function liftTone(v) {
    return ({
        strong:     'status-pill-success',
        meaningful: 'status-pill-info',
        mild:       'status-pill-neutral',
        weak:       'status-pill-neutral',
    })[v] || 'status-pill-neutral';
}
function liftLabel(v) {
    return ({
        strong:     t('insights.opportunities.fbt.verdict.strong',     'Strong'),
        meaningful: t('insights.opportunities.fbt.verdict.meaningful', 'Meaningful'),
        mild:       t('insights.opportunities.fbt.verdict.mild',       'Mild'),
        weak:       t('insights.opportunities.fbt.verdict.weak',       'Weak'),
    })[v] || v;
}

function growthTone(v) {
    return ({
        growing:    'status-pill-success',
        steady:     'status-pill-info',
        softening:  'status-pill-warning',
        declining:  'status-pill-danger',
        new:        'status-pill-neutral',
    })[v] || 'status-pill-neutral';
}
function growthLabel(v) {
    return ({
        growing:   t('insights.opportunities.growth.verdict.growing',   'Growing'),
        steady:    t('insights.opportunities.growth.verdict.steady',    'Steady'),
        softening: t('insights.opportunities.growth.verdict.softening', 'Softening'),
        declining: t('insights.opportunities.growth.verdict.declining', 'Declining'),
        new:       t('insights.opportunities.growth.verdict.new',       'New'),
    })[v] || v;
}
function growthClass(delta) {
    if (delta == null) return '';
    if (delta >=  5)  return 'delta-up';
    if (delta <= -5)  return 'delta-down';
    return '';
}

function marginLabel(band) {
    return ({
        high:   t('insights.opportunities.margin.bands.high',   'High margin (≥35%)'),
        medium: t('insights.opportunities.margin.bands.medium', 'Medium margin (15–35%)'),
        low:    t('insights.opportunities.margin.bands.low',    'Low margin (0–15%)'),
        loss:   t('insights.opportunities.margin.bands.loss',   'Loss (<0%)'),
    })[band] || band;
}
</script>

<style scoped>
@reference '../../../css/app.css';

/* ── Margin mix ──────────────────────────────────────────────────────── */
.margin-card { padding: 1rem 1.125rem; display: flex; flex-direction: column; gap: 0.875rem; }
.margin-head { display: flex; align-items: flex-start; justify-content: space-between; }
.margin-bar {
    display: flex;
    width: 100%;
    height: 14px;
    border-radius: 999px;
    overflow: hidden;
    background: var(--surface-sunken);
    border: 1px solid var(--border-default);
}
.margin-seg { height: 100%; transition: width 600ms var(--motion-spring); }
.margin-seg.margin-high   { background: linear-gradient(90deg, rgb(16 185 129), rgb(5 150 105)); }
.margin-seg.margin-medium { background: linear-gradient(90deg, rgb(99 102 241), rgb(79 70 229)); }
.margin-seg.margin-low    { background: linear-gradient(90deg, rgb(245 158 11), rgb(217 119 6)); }
.margin-seg.margin-loss   { background: linear-gradient(90deg, rgb(244 63 94),  rgb(225 29 72)); }

.margin-legend {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.5rem;
}
@media (min-width: 768px) { .margin-legend { grid-template-columns: repeat(4, 1fr); } }
.margin-cell {
    display: grid;
    grid-template-columns: auto 1fr auto;
    align-items: center;
    gap: 0.5rem;
    padding: 0.625rem 0.75rem;
    background: var(--surface-sunken);
    border: 1px solid var(--border-default);
    border-radius: 0.5rem;
}
.margin-swatch {
    width: 10px; height: 10px;
    border-radius: 999px;
    flex-shrink: 0;
}
.margin-swatch.margin-high   { background: rgb(16 185 129); }
.margin-swatch.margin-medium { background: rgb(99 102 241); }
.margin-swatch.margin-low    { background: rgb(245 158 11); }
.margin-swatch.margin-loss   { background: rgb(244 63 94); }
.margin-band {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--text-primary);
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.margin-pct {
    font-size: 0.875rem;
    font-weight: 800;
    color: var(--text-primary);
    font-variant-numeric: tabular-nums;
}

/* ── Frequently bought together ───────────────────────────────────── */
.fbt-row {
    display: grid;
    grid-template-columns: auto 1fr auto;
    gap: 0.875rem;
    align-items: center;
    padding: 0.875rem 1.125rem;
}
.fbt-rank {
    display: inline-flex; align-items: center; justify-content: center;
    min-width: 26px; height: 26px;
    padding: 0 0.375rem;
    border-radius: 0.375rem;
    background: var(--surface-sunken);
    color: var(--text-secondary);
    font-size: 0.6875rem;
    font-weight: 700;
    font-variant-numeric: tabular-nums;
}
.fbt-products {
    display: grid;
    grid-template-columns: 1fr auto 1fr;
    align-items: center;
    gap: 0.625rem;
}
.fbt-product {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    min-width: 0;
}
.fbt-bullet {
    width: 8px; height: 8px;
    border-radius: 999px;
    background: rgb(99 102 241);
    flex-shrink: 0;
}
.fbt-name {
    font-size: 0.8125rem;
    font-weight: 600;
    color: var(--text-primary);
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.fbt-plus {
    font-size: 0.875rem;
    font-weight: 800;
    color: var(--text-tertiary);
}
.fbt-stats {
    display: flex;
    align-items: center;
    gap: 0.875rem;
}
.fbt-stat-value {
    font-size: 0.875rem;
    font-weight: 800;
    color: var(--text-primary);
    font-variant-numeric: tabular-nums;
}

@media (max-width: 640px) {
    .fbt-row { grid-template-columns: 1fr; }
    .fbt-products { grid-template-columns: 1fr; }
    .fbt-plus { display: none; }
    .fbt-stats { justify-content: flex-end; }
}

/* ── Category growth ──────────────────────────────────────────────── */
.cat-row {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1.125rem;
}
.cat-name {
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--text-primary);
}
.cat-delta {
    font-size: 1.125rem;
    font-weight: 800;
    color: var(--text-primary);
    font-variant-numeric: tabular-nums;
    line-height: 1;
}
.cat-delta.delta-up   { color: rgb(4 120 87); }
.cat-delta.delta-down { color: rgb(190 18 60); }
html.dark .cat-delta.delta-up   { color: rgb(110 231 183); }
html.dark .cat-delta.delta-down { color: rgb(253 164 175); }
</style>
