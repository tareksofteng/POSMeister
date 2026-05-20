<template>
    <div class="p-6 lg:p-8 space-y-6 max-w-7xl mx-auto">

        <header class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
            <div>
                <p class="text-xs font-medium text-indigo-600 uppercase tracking-wider mb-1">
                    {{ t('inventory.module') }}
                </p>
                <h1 class="text-2xl font-bold text-slate-900 tracking-tight">
                    {{ t('inventory.intelligence.title') }}
                </h1>
                <p class="mt-1 text-sm text-slate-500">{{ t('inventory.intelligence.subtitle') }}</p>
            </div>
            <div class="flex items-end gap-2">
                <div>
                    <label class="lbl">{{ t('inventory.intelligence.lookback') }}</label>
                    <select v-model.number="lookback" @change="load" class="ctrl w-40">
                        <option :value="7">{{ t('inventory.intelligence.last7') }}</option>
                        <option :value="30">{{ t('inventory.intelligence.last30') }}</option>
                        <option :value="90">{{ t('inventory.intelligence.last90') }}</option>
                        <option :value="180">{{ t('inventory.intelligence.last180') }}</option>
                    </select>
                </div>
                <button @click="load" :disabled="loading" class="btn-soft">
                    <ArrowPathIcon :class="['w-4 h-4', loading && 'animate-spin']" />
                    {{ t('common.refresh') }}
                </button>
            </div>
        </header>

        <section class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-5 gap-3">
            <KpiCard :label="t('inventory.kpi.value')"        :value="d ? fmtCurrency(d.inventory_value) : '—'" tone="indigo" :icon="ArchiveBoxIcon" />
            <KpiCard :label="t('inventory.kpi.products')"     :value="d ? d.distinct_products : '—'" tone="slate" :icon="CubeIcon" />
            <KpiCard :label="t('inventory.kpi.turnover')"     :value="d ? d.turnover_ratio + 'x' : '—'" :sub="t('inventory.kpi.turnoverSub')" tone="emerald" :icon="ArrowPathIcon" />
            <KpiCard :label="t('inventory.kpi.coverage')"     :value="d?.avg_coverage_days !== null ? (d.avg_coverage_days + ' ' + t('common.days')) : '—'" tone="indigo" :icon="ClockIcon" />
            <KpiCard :label="t('inventory.kpi.lowStock')"     :value="d ? d.low_stock_count : '—'"  tone="amber" :icon="ExclamationTriangleIcon" />
            <KpiCard :label="t('inventory.kpi.overstock')"    :value="d ? d.overstock_count : '—'" tone="amber" :icon="ArchiveBoxArrowDownIcon" />
            <KpiCard :label="t('inventory.kpi.dead')"         :value="d ? d.dead_stock_count : '—'" tone="rose"  :icon="ExclamationCircleIcon" />
            <KpiCard :label="t('inventory.kpi.fast')"         :value="d ? d.fast_moving_count : '—'" tone="emerald" />
            <KpiCard :label="t('inventory.kpi.medium')"       :value="d ? d.medium_moving_count : '—'" tone="indigo" />
            <KpiCard :label="t('inventory.kpi.slow')"         :value="d ? d.slow_moving_count : '—'"   tone="amber" />
        </section>

        <section v-if="branchHealth.length > 1" class="card">
            <h3 class="card-title">{{ t('inventory.intelligence.branchHealth') }}</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3">
                <div v-for="b in branchHealth" :key="b.branch_id"
                     class="border border-slate-200 rounded-lg p-3 hover:shadow-sm transition-shadow">
                    <div class="flex items-center justify-between mb-2">
                        <p class="font-semibold text-slate-900 truncate">{{ b.branch_name }}</p>
                        <span :class="['text-lg font-bold font-mono', healthColor(b.health_score)]">
                            {{ b.health_score }}
                        </span>
                    </div>
                    <div class="h-2 bg-slate-100 rounded-full overflow-hidden mb-2">
                        <div class="h-full rounded-full transition-all"
                             :class="healthBar(b.health_score)"
                             :style="{ width: b.health_score + '%' }"></div>
                    </div>
                    <div class="text-[11px] text-slate-500 space-y-0.5">
                        <p><span class="font-medium">{{ t('inventory.kpi.value') }}:</span> {{ fmtCurrency(b.inventory_value) }}</p>
                        <p><span class="font-medium">{{ t('inventory.kpi.lowStock') }}:</span> {{ b.low_stock_count }}</p>
                        <p><span class="font-medium">{{ t('inventory.kpi.dead') }}:</span> {{ b.dead_stock_count }}</p>
                        <p><span class="font-medium">{{ t('inventory.kpi.turnover') }}:</span> {{ b.turnover_ratio }}x</p>
                    </div>
                </div>
            </div>
        </section>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
            <section class="card">
                <h3 class="card-title">{{ t('inventory.intelligence.topProfitable') }}</h3>
                <div v-if="d?.top_profitable?.length" class="space-y-1.5">
                    <div v-for="(p, i) in d.top_profitable" :key="p.product_id"
                         class="flex items-center justify-between text-sm py-1.5 border-b border-slate-50 last:border-0">
                        <div class="min-w-0 flex items-center gap-2">
                            <span class="text-xs text-slate-400 font-mono w-6">#{{ i + 1 }}</span>
                            <div class="min-w-0">
                                <p class="font-medium text-slate-800 truncate">{{ p.name }}</p>
                                <p class="text-[11px] text-slate-500">{{ p.sku }} · {{ p.sold_qty }} {{ t('inventory.intelligence.sold') }}</p>
                            </div>
                        </div>
                        <p class="font-mono text-sm text-emerald-700 font-semibold">+{{ fmtCurrency(p.profit) }}</p>
                    </div>
                </div>
                <p v-else class="text-center text-sm text-slate-400 py-6">{{ t('inventory.intelligence.empty') }}</p>
            </section>

            <section class="card">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="card-title mb-0">{{ t('inventory.intelligence.topLossRisk') }}</h3>
                    <RouterLink :to="{ name: 'inventory-dead-stock' }" class="text-xs text-indigo-600 hover:underline">
                        {{ t('inventory.intelligence.viewDeadStock') }} →
                    </RouterLink>
                </div>
                <div v-if="d?.top_loss_risk?.length" class="space-y-1.5">
                    <div v-for="(p, i) in d.top_loss_risk" :key="p.product_id"
                         class="flex items-center justify-between text-sm py-1.5 border-b border-slate-50 last:border-0">
                        <div class="min-w-0 flex items-center gap-2">
                            <span class="text-xs text-slate-400 font-mono w-6">#{{ i + 1 }}</span>
                            <div class="min-w-0">
                                <p class="font-medium text-slate-800 truncate">{{ p.name }}</p>
                                <p class="text-[11px] text-slate-500">
                                    {{ p.sku }} ·
                                    <span v-if="p.days_since_sale !== null">{{ p.days_since_sale }} {{ t('inventory.intelligence.daysIdle') }}</span>
                                    <span v-else class="text-rose-600">{{ t('inventory.intelligence.neverSold') }}</span>
                                </p>
                            </div>
                        </div>
                        <p class="font-mono text-sm text-rose-700 font-semibold">{{ fmtCurrency(p.stock_value) }}</p>
                    </div>
                </div>
                <p v-else class="text-center text-sm text-slate-400 py-6">{{ t('inventory.intelligence.empty') }}</p>
            </section>
        </div>

        <section class="card">
            <h3 class="card-title">{{ t('inventory.intelligence.quickActions') }}</h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <RouterLink :to="{ name: 'inventory-reorder' }" class="quick-link">
                    <ClipboardDocumentListIcon class="w-5 h-5 text-indigo-500" />
                    <span>{{ t('inventory.reorder.title') }}</span>
                </RouterLink>
                <RouterLink :to="{ name: 'inventory-dead-stock' }" class="quick-link">
                    <ExclamationCircleIcon class="w-5 h-5 text-rose-500" />
                    <span>{{ t('inventory.deadStock.title') }}</span>
                </RouterLink>
                <RouterLink :to="{ name: 'inventory-analytics' }" class="quick-link">
                    <ChartBarIcon class="w-5 h-5 text-emerald-500" />
                    <span>{{ t('inventory.analytics.title') }}</span>
                </RouterLink>
                <RouterLink :to="{ name: 'inventory' }" class="quick-link">
                    <ArchiveBoxIcon class="w-5 h-5 text-slate-500" />
                    <span>{{ t('inventory.intelligence.viewStock') }}</span>
                </RouterLink>
            </div>
        </section>

    </div>
</template>

<script setup>
import { ref, h, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { RouterLink } from 'vue-router';
import { inventoryIntelligenceService } from '@/services/inventoryIntelligenceService';
import { useCurrency } from '@/composables/useCurrency';
import {
    ArrowPathIcon, ArchiveBoxIcon, ArchiveBoxArrowDownIcon, CubeIcon, ClockIcon,
    ExclamationTriangleIcon, ExclamationCircleIcon,
    ClipboardDocumentListIcon, ChartBarIcon,
} from '@heroicons/vue/24/outline';

const { t } = useI18n();
const { fmtCurrency } = useCurrency();

const d = ref(null);
const branchHealth = ref([]);
const loading = ref(false);
const lookback = ref(30);

const KpiCard = (props) => {
    const palette = {
        emerald: 'border-emerald-200',
        rose:    'border-rose-200',
        indigo:  'border-indigo-200',
        amber:   'border-amber-200',
    }[props.tone] ?? 'border-slate-200';
    const iconColor = {
        emerald: 'text-emerald-500',
        rose:    'text-rose-500',
        indigo:  'text-indigo-500',
        amber:   'text-amber-500',
    }[props.tone] ?? 'text-slate-400';
    return h('div', { class: `bg-white border ${palette} rounded-xl shadow-sm px-4 py-3 hover:shadow-md transition-shadow` }, [
        h('div', { class: 'flex items-start justify-between gap-2' }, [
            h('p', { class: 'text-[11px] uppercase tracking-wide text-slate-500 font-medium' }, props.label),
            props.icon ? h(props.icon, { class: `w-4 h-4 ${iconColor}` }) : null,
        ]),
        h('p', { class: 'text-xl font-bold text-slate-900 mt-1 font-mono' }, String(props.value)),
        props.sub ? h('p', { class: 'text-[11px] mt-1 text-slate-500' }, props.sub) : null,
    ]);
};
KpiCard.props = ['label', 'value', 'tone', 'icon', 'sub'];

function healthColor(score) {
    if (score >= 75) return 'text-emerald-700';
    if (score >= 50) return 'text-amber-700';
    return 'text-rose-700';
}
function healthBar(score) {
    if (score >= 75) return 'bg-emerald-500';
    if (score >= 50) return 'bg-amber-500';
    return 'bg-rose-500';
}

async function load() {
    loading.value = true;
    try {
        const [{ data: dash }, { data: bh }] = await Promise.all([
            inventoryIntelligenceService.dashboard({ lookback_days: lookback.value }),
            inventoryIntelligenceService.branchHealth(),
        ]);
        d.value = dash.data;
        branchHealth.value = bh.data ?? [];
    } finally {
        loading.value = false;
    }
}

onMounted(load);
</script>

<style scoped>
@reference '../../../css/app.css';
.card        { @apply bg-white border border-slate-200 rounded-xl shadow-sm p-5; }
.card-title  { @apply text-xs font-bold text-slate-500 uppercase tracking-wider mb-3; }
.btn-soft    { @apply inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-slate-300 text-slate-700 text-sm hover:bg-slate-50 transition-colors disabled:opacity-50; }
.lbl         { @apply block text-xs font-medium text-slate-600 mb-1; }
.ctrl        { @apply px-3 py-2 text-sm border border-slate-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent; }
.quick-link  { @apply flex items-center gap-2 px-4 py-3 rounded-lg border border-slate-200 bg-white hover:bg-indigo-50 hover:border-indigo-200 text-sm font-medium text-slate-700 transition-colors; }
</style>
