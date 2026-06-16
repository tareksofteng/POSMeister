<template>
    <!--
        Three small forecast tiles sitting under the executive snapshot.
        Each pulls the 7-day projection for one of revenue / profit /
        cash flow from the ForecastService and renders the expected
        total, trend delta and confidence in one glance.

        The widget is read-only and cached server-side; no widget-level
        polling because the cache TTL (30 min) is shorter than how often
        a person actually looks at this in a day.
    -->
    <section class="card forecast-widget anim-fade-up">
        <header class="fw-head">
            <div class="min-w-0">
                <p class="t-overline">{{ t('insights.forecast.title', 'Forecast · next 7 days') }}</p>
                <p class="t-caption mt-0.5">{{ t('insights.forecast.subtitle', 'Moving-average projection from the past 90 days. Deterministic, explainable, refreshed every 30 minutes.') }}</p>
            </div>
            <RouterLink :to="{ name: 'insight-timeline' }" class="fw-link">
                {{ t('insights.forecast.viewAll', 'View insights') }}
                <ArrowLongRightIcon class="w-3.5 h-3.5" />
            </RouterLink>
        </header>

        <div class="fw-tiles">
            <div v-for="m in metrics" :key="m.key" class="fw-tile">
                <div :class="['fw-tile-icon', `fw-icon-${m.tone}`]">
                    <component :is="m.icon" class="w-4 h-4" />
                </div>
                <div class="min-w-0 flex-1">
                    <p class="t-overline">{{ m.label }}</p>
                    <p class="fw-value">
                        <template v-if="loading">—</template>
                        <template v-else>{{ fmt(data?.[m.key]?.forecast_total) }}</template>
                    </p>
                    <p v-if="!loading" class="fw-meta">
                        <span v-if="data?.[m.key]?.trend_delta_pct != null" :class="trendClass(data[m.key].trend_delta_pct)">
                            {{ data[m.key].trend_delta_pct >= 0 ? '+' : '' }}{{ data[m.key].trend_delta_pct }}%
                        </span>
                        <span class="t-caption">· {{ data?.[m.key]?.confidence ?? 0 }}% {{ t('insights.forecast.confidence', 'confidence') }}</span>
                    </p>
                </div>
            </div>
        </div>
    </section>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { RouterLink } from 'vue-router';
import { useSettingsStore } from '@/stores/settings';
import { useLocale } from '@/composables/useLocale';
import {
    ArrowLongRightIcon, ArrowTrendingUpIcon, BanknotesIcon, ChartBarIcon,
} from '@heroicons/vue/24/outline';
import { insightsService } from '@/services/insightsService';

const { t } = useI18n();
const { intlLocale } = useLocale();
const settingsStore = useSettingsStore();

const data    = ref(null);
const loading = ref(true);

const metrics = [
    { key: 'revenue',   label: t('insights.forecast.revenue',  'Revenue'),     icon: ArrowTrendingUpIcon, tone: 'indigo'  },
    { key: 'profit',    label: t('insights.forecast.profit',   'Profit'),      icon: ChartBarIcon,        tone: 'emerald' },
    { key: 'cash_flow', label: t('insights.forecast.cashFlow', 'Net cash'),    icon: BanknotesIcon,       tone: 'amber'   },
];

async function load() {
    loading.value = true;
    try {
        const res = await insightsService.forecastSummary();
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

function trendClass(delta) {
    if (delta >= 0) return 'fw-trend-up';
    return 'fw-trend-down';
}
</script>

<style scoped>
@reference '../../../css/app.css';

.forecast-widget { padding: 1rem 1.125rem; display: flex; flex-direction: column; gap: 0.875rem; }

.fw-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.5rem;
}
.fw-link {
    display: inline-flex; align-items: center; gap: 0.25rem;
    padding: 0.25rem 0.5rem;
    border-radius: 0.375rem;
    font-size: 0.6875rem;
    font-weight: 600;
    color: rgb(67 56 202);
    flex-shrink: 0;
    transition: background-color var(--motion-fast) var(--motion-out);
}
.fw-link:hover { background: rgb(238 242 255); }
html.dark .fw-link { color: rgb(165 180 252); }
html.dark .fw-link:hover { background: rgb(67 56 202 / 0.18); }

.fw-tiles {
    display: grid;
    grid-template-columns: 1fr;
    gap: 0.5rem;
}
@media (min-width: 640px) { .fw-tiles { grid-template-columns: repeat(3, 1fr); } }

.fw-tile {
    display: flex;
    align-items: center;
    gap: 0.625rem;
    padding: 0.75rem 0.875rem;
    background: var(--surface-sunken);
    border: 1px solid var(--border-default);
    border-radius: 0.625rem;
    min-width: 0;
}
.fw-tile-icon {
    width: 32px; height: 32px;
    border-radius: 0.5rem;
    display: grid; place-items: center;
    flex-shrink: 0;
}
.fw-icon-indigo  { background: rgb(238 242 255); color: rgb(67 56 202); }
.fw-icon-emerald { background: rgb(209 250 229); color: rgb(6 95 70); }
.fw-icon-amber   { background: rgb(254 243 199); color: rgb(146 64 14); }
html.dark .fw-icon-indigo  { background: rgb(67 56 202 / 0.25); color: rgb(165 180 252); }
html.dark .fw-icon-emerald { background: rgb(5 150 105 / 0.25); color: rgb(110 231 183); }
html.dark .fw-icon-amber   { background: rgb(180 83 9 / 0.3);   color: rgb(252 211 77); }

.fw-value {
    margin-top: 0.125rem;
    font-size: 1rem;
    font-weight: 700;
    color: var(--text-primary);
    font-variant-numeric: tabular-nums;
    letter-spacing: -0.015em;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.fw-meta {
    margin-top: 0.125rem;
    display: flex;
    align-items: center;
    gap: 0.375rem;
    font-size: 0.6875rem;
}
.fw-trend-up   { color: rgb(4 120 87);  font-weight: 700; }
.fw-trend-down { color: rgb(190 18 60); font-weight: 700; }
html.dark .fw-trend-up   { color: rgb(110 231 183); }
html.dark .fw-trend-down { color: rgb(253 164 175); }
</style>
