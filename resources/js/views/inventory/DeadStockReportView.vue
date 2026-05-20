<template>
    <div class="p-6 lg:p-8 space-y-5 max-w-7xl mx-auto">

        <header class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 tracking-tight">{{ t('inventory.deadStock.title') }}</h1>
                <p class="mt-1 text-sm text-slate-500">{{ t('inventory.deadStock.subtitle') }}</p>
            </div>
            <div class="flex items-end gap-2">
                <div>
                    <label class="lbl">{{ t('inventory.deadStock.threshold') }}</label>
                    <select v-model.number="deadDays" @change="load" class="ctrl w-40">
                        <option :value="30">30 {{ t('common.days') }}</option>
                        <option :value="60">60 {{ t('common.days') }}</option>
                        <option :value="90">90 {{ t('common.days') }}</option>
                        <option :value="180">180 {{ t('common.days') }}</option>
                        <option :value="365">365 {{ t('common.days') }}</option>
                    </select>
                </div>
            </div>
        </header>

        <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
            <SumBox :label="t('inventory.deadStock.affectedProducts')" :value="String(rows.length)" tone="rose" />
            <SumBox :label="t('inventory.deadStock.totalUnits')"       :value="String(Math.round(totalUnits))" tone="amber" />
            <SumBox :label="t('inventory.deadStock.capitalTied')"      :value="fmtCurrency(totalValue)" tone="rose" />
        </div>

        <section class="card">
            <h3 class="card-title">{{ t('inventory.deadStock.aging') }}</h3>
            <div v-if="aging" class="space-y-2">
                <div v-for="(b, key) in aging" :key="key" class="grid grid-cols-12 items-center gap-3 text-xs">
                    <span class="col-span-2 text-slate-700 font-medium">
                        {{ key === 'never' ? t('inventory.deadStock.never') : (key + ' ' + t('common.days')) }}
                    </span>
                    <div class="col-span-7 h-2.5 bg-slate-100 rounded-full overflow-hidden">
                        <div class="h-full rounded-full transition-all" :class="agingBarColor(key)"
                             :style="{ width: agingPct(b.value) + '%' }"></div>
                    </div>
                    <span class="col-span-1 text-right text-slate-600">{{ b.count }}</span>
                    <span class="col-span-2 text-right font-mono text-slate-800">{{ fmtCurrency(b.value) }}</span>
                </div>
            </div>
        </section>

        <div class="card overflow-hidden p-0">
            <table class="w-full text-sm">
                <thead class="bg-slate-50/70">
                    <tr class="text-left text-[11px] text-slate-500 uppercase tracking-wide border-b border-slate-100">
                        <th class="px-4 py-2.5">{{ t('inventory.fields.product') }}</th>
                        <th class="px-4 py-2.5 text-right">{{ t('inventory.fields.stock') }}</th>
                        <th class="px-4 py-2.5 text-right">{{ t('inventory.fields.costPrice') }}</th>
                        <th class="px-4 py-2.5 text-right">{{ t('inventory.fields.tiedCapital') }}</th>
                        <th class="px-4 py-2.5">{{ t('inventory.fields.lastSale') }}</th>
                        <th class="px-4 py-2.5 text-center">{{ t('inventory.fields.idle') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <tr v-for="r in rows" :key="r.product_id" class="hover:bg-slate-50/60">
                        <td class="px-4 py-2">
                            <p class="font-medium text-slate-800">{{ r.name }}</p>
                            <p class="text-[11px] text-slate-500 font-mono">{{ r.sku }}</p>
                        </td>
                        <td class="px-4 py-2 text-right font-mono">{{ r.stock_qty }}</td>
                        <td class="px-4 py-2 text-right font-mono text-slate-600">{{ fmtCurrency(r.cost_price) }}</td>
                        <td class="px-4 py-2 text-right font-mono font-semibold text-rose-700">{{ fmtCurrency(r.stock_value) }}</td>
                        <td class="px-4 py-2 text-xs">
                            <span v-if="r.last_sale_date">{{ formatDate(r.last_sale_date) }}</span>
                            <span v-else class="text-rose-700 font-medium">{{ t('inventory.deadStock.neverSold') }}</span>
                        </td>
                        <td class="px-4 py-2 text-center">
                            <span :class="idleBadge(r.days_since_sale)" class="text-[10px] uppercase tracking-wider px-2 py-0.5 rounded-md font-bold">
                                {{ r.days_since_sale !== null ? r.days_since_sale + 'd' : '∞' }}
                            </span>
                        </td>
                    </tr>
                    <tr v-if="!loading && rows.length === 0">
                        <td colspan="6" class="py-12 text-center text-sm text-slate-400">
                            {{ t('inventory.deadStock.empty') }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

    </div>
</template>

<script setup>
import { ref, computed, h, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { inventoryIntelligenceService } from '@/services/inventoryIntelligenceService';
import { useCurrency } from '@/composables/useCurrency';

const { t, locale } = useI18n();
const { fmtCurrency } = useCurrency();

const rows = ref([]);
const aging = ref(null);
const loading = ref(false);
const deadDays = ref(90);

const totalUnits = computed(() => rows.value.reduce((s, r) => s + (r.stock_qty || 0), 0));
const totalValue = computed(() => rows.value.reduce((s, r) => s + (r.stock_value || 0), 0));

function formatDate(d) {
    return new Intl.DateTimeFormat(locale.value || 'de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(new Date(d));
}

function idleBadge(days) {
    if (days === null) return 'bg-rose-100 text-rose-800';
    if (days >= 180)   return 'bg-rose-100 text-rose-800';
    if (days >= 90)    return 'bg-amber-100 text-amber-800';
    return 'bg-slate-100 text-slate-700';
}

function agingBarColor(key) {
    return {
        '0-30':   'bg-emerald-500',
        '31-60':  'bg-emerald-400',
        '61-90':  'bg-amber-400',
        '91-180': 'bg-rose-400',
        '180+':   'bg-rose-600',
        'never':  'bg-slate-400',
    }[key] ?? 'bg-slate-300';
}

function agingPct(value) {
    if (!aging.value) return 0;
    const max = Math.max(...Object.values(aging.value).map(b => b.value), 1);
    return Math.max(2, (value / max) * 100);
}

const SumBox = (props) => {
    const palette = {
        rose:    'border-rose-200 bg-rose-50/40 text-rose-700',
        amber:   'border-amber-200 bg-amber-50/40 text-amber-700',
        indigo:  'border-indigo-200 bg-indigo-50/40 text-indigo-700',
    }[props.tone] ?? 'border-slate-200 bg-white text-slate-900';
    return h('div', { class: `border rounded-xl shadow-sm px-4 py-3 ${palette}` }, [
        h('p', { class: 'text-[11px] uppercase tracking-wide opacity-75 font-medium' }, props.label),
        h('p', { class: 'text-xl font-bold mt-1 font-mono' }, String(props.value)),
    ]);
};
SumBox.props = ['label', 'value', 'tone'];

async function load() {
    loading.value = true;
    try {
        const [{ data: dead }, { data: agingData }] = await Promise.all([
            inventoryIntelligenceService.deadStock({ dead_days: deadDays.value }),
            inventoryIntelligenceService.aging(),
        ]);
        rows.value = dead.data ?? [];
        aging.value = agingData.data ?? null;
    } finally {
        loading.value = false;
    }
}

onMounted(load);
</script>

<style scoped>
@reference '../../../css/app.css';
.card       { @apply bg-white border border-slate-200 rounded-xl shadow-sm p-5; }
.card-title { @apply text-xs font-bold text-slate-500 uppercase tracking-wider mb-3; }
.lbl        { @apply block text-xs font-medium text-slate-600 mb-1; }
.ctrl       { @apply px-3 py-2 text-sm border border-slate-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent; }
</style>
