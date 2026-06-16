<template>
    <!--
        Supplier intelligence — concentration risk up top, spend leaders
        and lead-time / payment performance side by side, and a quiet-
        supplier list at the bottom. Designed to be read in three glances
        rather than scrolled through.
    -->
    <div class="p-3 sm:p-6 lg:p-8 space-y-4 sm:space-y-6 max-w-6xl mx-auto anim-fade-in">

        <header class="anim-fade-up">
            <p class="t-overline text-indigo-500 mb-1.5">{{ t('insights.module', 'Business Insights') }}</p>
            <h1 class="h1-display">{{ t('insights.suppliers.title', 'Supplier intelligence') }}</h1>
            <p class="mt-1.5 t-body">{{ t('insights.suppliers.subtitle', 'Who you depend on, how reliably they ship, how well you pay them. Pure SQL — no opaque scores.') }}</p>
        </header>

        <div v-if="loading && !data" class="space-y-4">
            <Skeleton variant="kpi-card" />
            <Skeleton variant="row" />
            <Skeleton variant="row" />
        </div>

        <template v-else-if="data">
            <!-- Concentration / totals -->
            <section class="grid grid-cols-1 lg:grid-cols-3 gap-3 anim-fade-up">
                <div class="card card-kpi lg:col-span-1">
                    <p class="t-overline">{{ t('insights.suppliers.totals.active', 'Active · 365d') }}</p>
                    <p class="t-kpi mt-1">{{ data.totals.active_365d }}</p>
                    <p class="t-caption">{{ t('insights.suppliers.totals.outOf', 'of') }} {{ data.totals.suppliers_total }} {{ t('insights.suppliers.totals.registered', 'registered') }}</p>
                </div>
                <div class="card card-kpi lg:col-span-1">
                    <p class="t-overline">{{ t('insights.suppliers.totals.spend', 'Spend · 365d') }}</p>
                    <p class="t-kpi mt-1">{{ fmt(data.totals.spend_365d) }}</p>
                </div>
                <div class="card concentration-card lg:col-span-1" :class="`risk-${data.concentration.verdict}`">
                    <div class="flex items-center justify-between gap-2">
                        <p class="t-overline">{{ t('insights.suppliers.concentration.title', 'Concentration risk') }}</p>
                        <span :class="['status-pill', riskTone(data.concentration.verdict)]">
                            {{ riskLabel(data.concentration.verdict) }}
                        </span>
                    </div>
                    <p class="conc-headline mt-1">{{ data.concentration.top_share_pct }}%</p>
                    <p class="t-caption">
                        <template v-if="data.concentration.top_supplier">
                            {{ t('insights.suppliers.concentration.fromTop', 'from') }}
                            <span class="font-semibold text-slate-900 dark:text-slate-100">{{ data.concentration.top_supplier }}</span>
                            ·
                        </template>
                        {{ t('insights.suppliers.concentration.top3', 'top 3') }}: {{ data.concentration.top3_share_pct }}%
                    </p>
                </div>
            </section>

            <!-- Top suppliers -->
            <section class="card overflow-hidden anim-fade-up">
                <header class="dash-list-head">
                    <div>
                        <p class="t-overline">{{ t('insights.suppliers.top.title', 'Spend leaders · 365 days') }}</p>
                        <p class="t-caption mt-0.5">{{ t('insights.suppliers.top.subtitle', 'Where your procurement budget actually went.') }}</p>
                    </div>
                </header>
                <EmptyState
                    v-if="!data.top.length"
                    size="sm" tone="slate"
                    :icon="BuildingStorefrontIcon"
                    :title="t('insights.suppliers.top.empty', 'No supplier activity yet')"
                />
                <ul v-else class="divide-y divide-slate-100 dark:divide-slate-800">
                    <li v-for="(s, i) in data.top" :key="s.supplier_id" class="sup-row">
                        <span class="sup-rank">#{{ i + 1 }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="sup-name">{{ s.name }}</p>
                            <p class="t-caption">
                                <span class="font-mono">{{ s.code || '—' }}</span>
                                · {{ s.purchases_365d }} {{ t('insights.suppliers.top.purchases', 'purchases') }}
                                · {{ t('insights.suppliers.top.lastBuy', 'last') }} {{ s.last_purchase }}
                            </p>
                        </div>
                        <p class="sup-spend">{{ fmt(s.spend_365d) }}</p>
                    </li>
                </ul>
            </section>

            <!-- Lead times + Payment performance -->
            <section class="grid grid-cols-1 lg:grid-cols-2 gap-4 anim-fade-up">
                <div class="card overflow-hidden">
                    <header class="dash-list-head">
                        <div>
                            <p class="t-overline">{{ t('insights.suppliers.lead.title', 'Fastest lead times') }}</p>
                            <p class="t-caption mt-0.5">{{ t('insights.suppliers.lead.subtitle', 'Average days from order to received status, last 365 days.') }}</p>
                        </div>
                    </header>
                    <EmptyState
                        v-if="!data.lead_times.length"
                        size="sm" tone="slate"
                        :icon="ClockIcon"
                        :title="t('insights.suppliers.lead.empty', 'Not enough received orders yet')"
                    />
                    <ul v-else class="divide-y divide-slate-100 dark:divide-slate-800">
                        <li v-for="l in data.lead_times" :key="l.supplier_id" class="lead-row">
                            <span :class="['lead-rail', `lead-${l.verdict}`]" />
                            <div class="min-w-0 flex-1">
                                <p class="lead-name">{{ l.name }}</p>
                                <p class="t-caption">{{ l.receipts }} {{ t('insights.suppliers.lead.receipts', 'receipts') }}</p>
                            </div>
                            <div class="text-right flex-shrink-0">
                                <p class="lead-days">{{ l.avg_lead_days }}d</p>
                                <span :class="['status-pill', leadTone(l.verdict)]">{{ leadLabel(l.verdict) }}</span>
                            </div>
                        </li>
                    </ul>
                </div>

                <div class="card overflow-hidden">
                    <header class="dash-list-head">
                        <div>
                            <p class="t-overline">{{ t('insights.suppliers.payment.title', 'Payment laggards') }}</p>
                            <p class="t-caption mt-0.5">{{ t('insights.suppliers.payment.subtitle', 'Suppliers where the most invoices remain unpaid — act here this week.') }}</p>
                        </div>
                    </header>
                    <EmptyState
                        v-if="!data.payment.length"
                        size="sm" tone="emerald"
                        :icon="CheckCircleIcon"
                        :title="t('insights.suppliers.payment.empty', 'All suppliers paid on track')"
                    />
                    <ul v-else class="divide-y divide-slate-100 dark:divide-slate-800">
                        <li v-for="p in data.payment" :key="p.supplier_id" class="pay-row">
                            <div class="min-w-0 flex-1">
                                <p class="pay-name">{{ p.name }}</p>
                                <p class="t-caption">
                                    {{ p.paid_in_full }} / {{ p.invoices }} {{ t('insights.suppliers.payment.paid', 'paid in full') }}
                                    <span v-if="p.outstanding > 0">· {{ t('insights.suppliers.payment.outstanding', 'outstanding') }} {{ fmt(p.outstanding) }}</span>
                                </p>
                            </div>
                            <div class="text-right flex-shrink-0">
                                <p :class="['pay-pct', `pay-${p.verdict}`]">{{ p.paid_pct }}%</p>
                                <span :class="['status-pill', payTone(p.verdict)]">{{ payLabel(p.verdict) }}</span>
                            </div>
                        </li>
                    </ul>
                </div>
            </section>

            <!-- Inactive suppliers -->
            <section v-if="data.inactive.length" class="card overflow-hidden anim-fade-up">
                <header class="dash-list-head">
                    <div>
                        <p class="t-overline">{{ t('insights.suppliers.inactive.title', 'Quiet suppliers') }}</p>
                        <p class="t-caption mt-0.5">{{ t('insights.suppliers.inactive.subtitle', 'Regulars (5+ historical purchases) with no order in the last 90 days.') }}</p>
                    </div>
                </header>
                <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                    <li v-for="i in data.inactive" :key="i.supplier_id" class="inact-row">
                        <ExclamationCircleIcon class="w-4 h-4 text-amber-600 dark:text-amber-400 flex-shrink-0" />
                        <div class="min-w-0 flex-1">
                            <p class="inact-name">{{ i.name }}</p>
                            <p class="t-caption">{{ i.total_purchases }} {{ t('insights.suppliers.inactive.historicalPurchases', 'historical purchases') }}</p>
                        </div>
                        <div class="text-right flex-shrink-0">
                            <p class="inact-days">{{ i.days_since }}d</p>
                            <p class="t-caption">{{ i.last_purchase }}</p>
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
import {
    BuildingStorefrontIcon, CheckCircleIcon, ClockIcon, ExclamationCircleIcon,
} from '@heroicons/vue/24/outline';
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
        const res = await insightsService.supplierIntelligence();
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

function riskTone(v) {
    return ({
        critical: 'status-pill-danger',
        high:     'status-pill-warning',
        medium:   'status-pill-info',
        low:      'status-pill-success',
        no_data:  'status-pill-neutral',
    })[v] || 'status-pill-neutral';
}
function riskLabel(v) {
    return ({
        critical: t('insights.suppliers.concentration.verdict.critical', 'Critical'),
        high:     t('insights.suppliers.concentration.verdict.high',     'High'),
        medium:   t('insights.suppliers.concentration.verdict.medium',   'Medium'),
        low:      t('insights.suppliers.concentration.verdict.low',      'Low'),
        no_data:  t('insights.suppliers.concentration.verdict.noData',   'No data'),
    })[v] || v;
}

function leadTone(v) {
    return ({
        fast:    'status-pill-success',
        on_time: 'status-pill-info',
        slow:    'status-pill-warning',
        late:    'status-pill-danger',
    })[v] || 'status-pill-neutral';
}
function leadLabel(v) {
    return ({
        fast:    t('insights.suppliers.lead.verdict.fast',    'Fast'),
        on_time: t('insights.suppliers.lead.verdict.onTime',  'On time'),
        slow:    t('insights.suppliers.lead.verdict.slow',    'Slow'),
        late:    t('insights.suppliers.lead.verdict.late',    'Late'),
    })[v] || v;
}

function payTone(v) {
    return ({
        excellent: 'status-pill-success',
        good:      'status-pill-info',
        mixed:     'status-pill-warning',
        poor:      'status-pill-danger',
    })[v] || 'status-pill-neutral';
}
function payLabel(v) {
    return ({
        excellent: t('insights.suppliers.payment.verdict.excellent', 'Excellent'),
        good:      t('insights.suppliers.payment.verdict.good',      'Good'),
        mixed:     t('insights.suppliers.payment.verdict.mixed',     'Mixed'),
        poor:      t('insights.suppliers.payment.verdict.poor',      'Poor'),
    })[v] || v;
}
</script>

<style scoped>
@reference '../../../css/app.css';

.concentration-card {
    padding: 0.875rem 1rem;
    border-left: 3px solid var(--border-default);
}
.concentration-card.risk-critical { border-left-color: rgb(225 29 72); }
.concentration-card.risk-high     { border-left-color: rgb(245 158 11); }
.concentration-card.risk-medium   { border-left-color: rgb(14 165 233); }
.concentration-card.risk-low      { border-left-color: rgb(16 185 129); }
.conc-headline {
    font-size: 1.75rem;
    font-weight: 800;
    color: var(--text-primary);
    font-variant-numeric: tabular-nums;
    letter-spacing: -0.02em;
    line-height: 1;
}

.sup-row {
    display: grid;
    grid-template-columns: auto 1fr auto;
    align-items: center;
    gap: 0.625rem;
    padding: 0.75rem 1.125rem;
}
.sup-rank {
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
.sup-name {
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--text-primary);
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.sup-spend {
    font-size: 0.875rem;
    font-weight: 800;
    color: var(--text-primary);
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
}

.lead-row, .pay-row {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1.125rem;
}
.lead-rail {
    width: 3px;
    align-self: stretch;
    min-height: 28px;
    border-radius: 999px;
}
.lead-fast    { background: rgb(16 185 129); }
.lead-on_time { background: rgb(14 165 233); }
.lead-slow    { background: rgb(245 158 11); }
.lead-late    { background: rgb(244 63 94); }
.lead-name, .pay-name {
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--text-primary);
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.lead-days, .pay-pct {
    font-size: 1rem;
    font-weight: 800;
    color: var(--text-primary);
    font-variant-numeric: tabular-nums;
    line-height: 1;
}
.pay-poor { color: rgb(190 18 60); }
html.dark .pay-poor { color: rgb(253 164 175); }

.inact-row {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1.125rem;
}
.inact-name {
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--text-primary);
}
.inact-days {
    font-size: 0.9375rem;
    font-weight: 800;
    color: var(--text-primary);
    font-variant-numeric: tabular-nums;
}
</style>
