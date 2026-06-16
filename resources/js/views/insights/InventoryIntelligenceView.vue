<template>
    <!--
        Inventory intelligence — the four questions every owner asks
        about their stock room, answered side by side: turnover ratio,
        aging buckets, expected stockouts, and 30-day velocity leaders.
    -->
    <div class="p-3 sm:p-6 lg:p-8 space-y-4 sm:space-y-6 max-w-6xl mx-auto anim-fade-in">

        <header class="anim-fade-up">
            <p class="t-overline text-indigo-500 mb-1.5">{{ t('insights.module', 'Business Insights') }}</p>
            <h1 class="h1-display">{{ t('insights.inventory.title', 'Inventory intelligence') }}</h1>
            <p class="mt-1.5 t-body">{{ t('insights.inventory.subtitle', 'How fast is stock moving, what is aging on the shelves, and which products are about to run out. All sourced from your existing inventory and sales data — no assumptions, no AI.') }}</p>
        </header>

        <div v-if="loading && !data" class="space-y-4">
            <Skeleton variant="kpi-card" />
            <Skeleton variant="row" />
            <Skeleton variant="row" />
        </div>

        <template v-else-if="data">
            <!-- Turnover headline -->
            <section class="card turnover-card anim-fade-up" :class="`verdict-${data.turnover.verdict}`">
                <div class="t-head">
                    <div>
                        <p class="t-overline">{{ t('insights.inventory.turnover.title', 'Inventory turnover · last 90 days') }}</p>
                        <p class="t-caption mt-0.5">{{ t('insights.inventory.turnover.subtitle', 'COGS over the period divided by current inventory value. Higher means stock is moving; lower means capital trapped on shelves.') }}</p>
                    </div>
                    <span :class="['status-pill', verdictTone(data.turnover.verdict)]">
                        {{ verdictLabel(data.turnover.verdict) }}
                    </span>
                </div>
                <div class="t-grid">
                    <div>
                        <p class="t-overline">{{ t('insights.inventory.turnover.ratio', 'Ratio') }}</p>
                        <p class="t-kpi mt-1">{{ data.turnover.ratio.toFixed(2) }}×</p>
                    </div>
                    <div>
                        <p class="t-overline">{{ t('insights.inventory.turnover.cogs', 'COGS · 90d') }}</p>
                        <p class="t-kpi mt-1">{{ fmt(data.turnover.cogs_90d) }}</p>
                    </div>
                    <div>
                        <p class="t-overline">{{ t('insights.inventory.turnover.invValue', 'Stock value now') }}</p>
                        <p class="t-kpi mt-1">{{ fmt(data.turnover.inv_value_now) }}</p>
                    </div>
                    <div>
                        <p class="t-overline">{{ t('insights.inventory.turnover.daysOnHand', 'Days on hand') }}</p>
                        <p class="t-kpi mt-1">
                            <template v-if="data.turnover.days_on_hand != null">{{ data.turnover.days_on_hand }}</template>
                            <template v-else>—</template>
                        </p>
                    </div>
                </div>
            </section>

            <!-- Aging buckets -->
            <section class="card aging-card anim-fade-up">
                <header class="dash-list-head">
                    <div>
                        <p class="t-overline">{{ t('insights.inventory.aging.title', 'Stock aging') }}</p>
                        <p class="t-caption mt-0.5">{{ t('insights.inventory.aging.subtitle', 'Inventory value grouped by days since the product last sold. Older buckets = capital not turning.') }}</p>
                    </div>
                    <span class="t-caption">{{ t('insights.inventory.aging.total', 'Total') }}: {{ fmt(data.aging.total_value) }}</span>
                </header>
                <div class="aging-stack">
                    <div v-for="b in data.aging.buckets" :key="b.label" class="aging-row">
                        <div class="aging-head">
                            <span class="aging-label">{{ b.label }} {{ t('insights.inventory.aging.days', 'days') }}</span>
                            <span class="aging-meta">
                                <span class="font-mono">{{ b.count }}</span>
                                {{ t('insights.inventory.aging.skus', 'SKUs') }}
                                ·
                                <span class="font-mono font-semibold">{{ fmt(b.value) }}</span>
                            </span>
                        </div>
                        <div class="aging-bar">
                            <div :class="['aging-bar-fill', `aging-${b.label.replace(/[^0-9+]/g, '')}`]" :style="{ width: b.pct + '%' }" />
                        </div>
                        <span class="t-caption">{{ b.pct }}%</span>
                    </div>
                </div>
            </section>

            <!-- Two-column: stockouts + velocity -->
            <section class="grid grid-cols-1 lg:grid-cols-2 gap-4 anim-fade-up">
                <!-- Stockout forecast -->
                <div class="card overflow-hidden">
                    <header class="dash-list-head">
                        <div>
                            <p class="t-overline">{{ t('insights.inventory.stockout.title', 'Expected stockouts') }}</p>
                            <p class="t-caption mt-0.5">{{ t('insights.inventory.stockout.subtitle', 'Days = on-hand / mean daily sale rate over 30 days.') }}</p>
                        </div>
                    </header>
                    <EmptyState
                        v-if="!data.stockout.length"
                        size="sm" tone="emerald"
                        :icon="CheckCircleIcon"
                        :title="t('insights.inventory.stockout.empty', 'No imminent stockouts')"
                    />
                    <ul v-else class="divide-y divide-slate-100 dark:divide-slate-800">
                        <li v-for="r in data.stockout" :key="r.product_id" class="stk-row">
                            <span :class="['stk-rail', `stk-${r.urgency}`]" />
                            <div class="min-w-0 flex-1">
                                <p class="stk-name">{{ r.name }}</p>
                                <p class="t-caption">
                                    <span class="font-mono">{{ r.sku }}</span> · {{ t('insights.inventory.stockout.onHand', 'on hand') }} {{ r.on_hand }}
                                </p>
                            </div>
                            <div class="text-right flex-shrink-0">
                                <p class="stk-days">{{ r.days_to_stockout }}d</p>
                                <p class="t-caption">{{ r.stockout_date }}</p>
                            </div>
                        </li>
                    </ul>
                </div>

                <!-- Velocity leaders -->
                <div class="card overflow-hidden">
                    <header class="dash-list-head">
                        <div>
                            <p class="t-overline">{{ t('insights.inventory.velocity.title', 'Velocity leaders · 30 days') }}</p>
                            <p class="t-caption mt-0.5">{{ t('insights.inventory.velocity.subtitle', 'Units per day. Cross-reference with the stockout list — high velocity + few days left = act today.') }}</p>
                        </div>
                    </header>
                    <EmptyState
                        v-if="!data.velocity.length"
                        size="sm" tone="indigo"
                        :icon="BoltIcon"
                        :title="t('insights.inventory.velocity.empty', 'No sales in the past 30 days')"
                    />
                    <ul v-else class="divide-y divide-slate-100 dark:divide-slate-800">
                        <li v-for="(r, i) in data.velocity" :key="r.product_id" class="vel-row">
                            <span class="vel-rank">#{{ i + 1 }}</span>
                            <div class="min-w-0 flex-1">
                                <p class="vel-name">{{ r.name }}</p>
                                <p class="t-caption font-mono">{{ r.sku }}</p>
                            </div>
                            <div class="text-right flex-shrink-0">
                                <p class="vel-velocity">{{ r.velocity }}<span class="t-caption">/d</span></p>
                                <p class="t-caption">{{ fmt(r.revenue_30d) }}</p>
                            </div>
                        </li>
                    </ul>
                </div>
            </section>
        </template>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { BoltIcon, CheckCircleIcon } from '@heroicons/vue/24/outline';
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
        const res = await insightsService.inventoryIntelligence();
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

function verdictTone(v) {
    return ({
        fast:     'status-pill-success',
        healthy:  'status-pill-success',
        slow:     'status-pill-warning',
        stagnant: 'status-pill-danger',
        no_data:  'status-pill-neutral',
    })[v] || 'status-pill-neutral';
}

function verdictLabel(v) {
    return ({
        fast:     t('insights.inventory.turnover.verdict.fast',     'Fast'),
        healthy:  t('insights.inventory.turnover.verdict.healthy',  'Healthy'),
        slow:     t('insights.inventory.turnover.verdict.slow',     'Slow'),
        stagnant: t('insights.inventory.turnover.verdict.stagnant', 'Stagnant'),
        no_data:  t('insights.inventory.turnover.verdict.noData',   'No data'),
    })[v] || v;
}
</script>

<style scoped>
@reference '../../../css/app.css';

.turnover-card {
    padding: 1rem 1.125rem 1.125rem;
    display: flex; flex-direction: column;
    gap: 0.875rem;
    border-left: 3px solid var(--border-default);
}
.turnover-card.verdict-fast,
.turnover-card.verdict-healthy { border-left-color: rgb(16 185 129); }
.turnover-card.verdict-slow    { border-left-color: rgb(245 158 11); }
.turnover-card.verdict-stagnant{ border-left-color: rgb(244 63 94); }

.t-head {
    display: flex; align-items: flex-start; justify-content: space-between;
    gap: 0.5rem;
}
.t-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0.625rem;
}
@media (min-width: 640px) { .t-grid { grid-template-columns: repeat(4, 1fr); } }
.t-grid > div {
    padding: 0.625rem 0.75rem;
    background: var(--surface-sunken);
    border: 1px solid var(--border-default);
    border-radius: 0.5rem;
}

/* Aging */
.aging-card { padding: 1rem 1.125rem; }
.aging-stack { display: flex; flex-direction: column; gap: 0.625rem; margin-top: 0.5rem; }
.aging-row {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 0.25rem 0.625rem;
}
.aging-head {
    grid-column: 1 / -1;
    display: flex; align-items: center; justify-content: space-between;
    gap: 0.5rem;
}
.aging-label {
    font-size: 0.8125rem;
    font-weight: 600;
    color: var(--text-primary);
}
.aging-meta {
    font-size: 0.75rem;
    color: var(--text-secondary);
}
.aging-bar {
    grid-column: 1;
    height: 6px;
    background: var(--surface-sunken);
    border-radius: 999px;
    overflow: hidden;
}
.aging-bar-fill {
    height: 100%;
    border-radius: 999px;
    transition: width 600ms var(--motion-spring);
    background: rgb(99 102 241);
}
.aging-bar-fill.aging-030  { background: linear-gradient(90deg, rgb(16 185 129), rgb(5 150 105)); }
.aging-bar-fill.aging-3160 { background: linear-gradient(90deg, rgb(99 102 241), rgb(79 70 229)); }
.aging-bar-fill.aging-6190 { background: linear-gradient(90deg, rgb(245 158 11), rgb(217 119 6)); }
.aging-bar-fill.aging-91180{ background: linear-gradient(90deg, rgb(244 63 94), rgb(225 29 72)); }
.aging-bar-fill.aging-180  { background: linear-gradient(90deg, rgb(190 18 60), rgb(159 18 57)); }
.aging-row > .t-caption {
    grid-column: 2;
    font-weight: 700;
    color: var(--text-primary);
}

/* Stockout list */
.stk-row {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1.125rem;
}
.stk-rail {
    width: 3px;
    align-self: stretch;
    min-height: 32px;
    border-radius: 999px;
}
.stk-critical { background: rgb(225 29 72); box-shadow: 0 0 0 1px rgba(225,29,72,0.18); }
.stk-high     { background: rgb(244 63 94); }
.stk-medium   { background: rgb(245 158 11); }
.stk-low      { background: rgb(148 163 184); }

.stk-name {
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--text-primary);
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.stk-days {
    font-size: 1rem;
    font-weight: 800;
    color: rgb(190 18 60);
    font-variant-numeric: tabular-nums;
    letter-spacing: -0.015em;
}
html.dark .stk-days { color: rgb(253 164 175); }

/* Velocity list */
.vel-row {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1.125rem;
}
.vel-rank {
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
.vel-name {
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--text-primary);
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.vel-velocity {
    font-size: 1rem;
    font-weight: 800;
    color: var(--text-primary);
    font-variant-numeric: tabular-nums;
    letter-spacing: -0.015em;
}
</style>
