<template>
    <div class="p-3 sm:p-6 lg:p-8 space-y-4 sm:space-y-5 max-w-7xl mx-auto pb-safe">

        <header class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 tracking-tight">{{ t('inventory.analytics.title') }}</h1>
                <p class="mt-1 text-sm text-slate-500">{{ t('inventory.analytics.subtitle') }}</p>
            </div>
            <div class="inline-flex rounded-lg border border-slate-300 overflow-hidden">
                <button @click="tab = 'valuation'"    :class="['px-3 py-2 text-xs font-medium', tab === 'valuation'    ? 'bg-indigo-600 text-white' : 'bg-white text-slate-700']">
                    {{ t('inventory.analytics.tabValuation') }}
                </button>
                <button @click="tab = 'profitability'":class="['px-3 py-2 text-xs font-medium', tab === 'profitability'? 'bg-indigo-600 text-white' : 'bg-white text-slate-700']">
                    {{ t('inventory.analytics.tabProfitability') }}
                </button>
                <button @click="tab = 'movement'"     :class="['px-3 py-2 text-xs font-medium', tab === 'movement'     ? 'bg-indigo-600 text-white' : 'bg-white text-slate-700']">
                    {{ t('inventory.analytics.tabMovement') }}
                </button>
                <button @click="tab = 'suppliers'"    :class="['px-3 py-2 text-xs font-medium', tab === 'suppliers'    ? 'bg-indigo-600 text-white' : 'bg-white text-slate-700']">
                    {{ t('inventory.analytics.tabSuppliers') }}
                </button>
            </div>
        </header>

        <div v-if="tab !== 'valuation' && tab !== 'suppliers'" class="card flex flex-wrap items-end gap-3">
            <div>
                <label class="lbl">{{ t('common.dateFrom') }}</label>
                <input v-model="from" @change="load" type="date" class="ctrl w-40" />
            </div>
            <div>
                <label class="lbl">{{ t('common.dateTo') }}</label>
                <input v-model="to" @change="load" type="date" class="ctrl w-40" />
            </div>
        </div>

        <!-- Valuation -->
        <section v-if="tab === 'valuation' && valuation" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <SumBox :label="t('inventory.analytics.costValue')"     :value="fmtCurrency(valuation.total_cost_value)"   tone="slate" />
                <SumBox :label="t('inventory.analytics.retailValue')"   :value="fmtCurrency(valuation.total_retail_value)" tone="indigo" />
                <SumBox :label="t('inventory.analytics.unrealisedMargin')" :value="fmtCurrency(valuation.unrealised_margin)" tone="emerald" />
            </div>
            <div class="card overflow-hidden p-0 overflow-x-auto">
                <table class="w-full text-sm min-w-[600px]">
                    <thead class="bg-slate-50/70">
                        <tr class="text-left text-[11px] text-slate-500 uppercase tracking-wide border-b border-slate-100">
                            <th class="px-4 py-2.5">{{ t('inventory.fields.product') }}</th>
                            <th class="px-4 py-2.5 text-right">{{ t('inventory.fields.qty') }}</th>
                            <th class="px-4 py-2.5 text-right">{{ t('inventory.fields.costPrice') }}</th>
                            <th class="px-4 py-2.5 text-right">{{ t('inventory.analytics.costValue') }}</th>
                            <th class="px-4 py-2.5 text-right">{{ t('inventory.analytics.retailValue') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <tr v-for="r in valuation.items" :key="r.product_id">
                            <td class="px-4 py-2">
                                <p class="font-medium text-slate-800">{{ r.name }}</p>
                                <p class="text-[11px] text-slate-500 font-mono">{{ r.sku }}</p>
                            </td>
                            <td class="px-4 py-2 text-right font-mono">{{ r.qty }}</td>
                            <td class="px-4 py-2 text-right font-mono text-slate-600">{{ fmtCurrency(r.cost_price) }}</td>
                            <td class="px-4 py-2 text-right font-mono">{{ fmtCurrency(r.cost_value) }}</td>
                            <td class="px-4 py-2 text-right font-mono">{{ fmtCurrency(r.retail_value) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Profitability -->
        <section v-if="tab === 'profitability'" class="card overflow-hidden p-0 overflow-x-auto">
            <table class="w-full text-sm min-w-[600px]">
                <thead class="bg-slate-50/70">
                    <tr class="text-left text-[11px] text-slate-500 uppercase tracking-wide border-b border-slate-100">
                        <th class="px-4 py-2.5">{{ t('inventory.fields.product') }}</th>
                        <th class="px-4 py-2.5 text-right">{{ t('inventory.fields.qtySold') }}</th>
                        <th class="px-4 py-2.5 text-right">{{ t('inventory.analytics.revenue') }}</th>
                        <th class="px-4 py-2.5 text-right">{{ t('inventory.analytics.cost') }}</th>
                        <th class="px-4 py-2.5 text-right">{{ t('inventory.analytics.profit') }}</th>
                        <th class="px-4 py-2.5 text-right w-32">{{ t('inventory.analytics.margin') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <tr v-for="r in profitability" :key="r.product_id">
                        <td class="px-4 py-2">
                            <p class="font-medium text-slate-800">{{ r.name }}</p>
                            <p class="text-[11px] text-slate-500 font-mono">{{ r.sku }}</p>
                        </td>
                        <td class="px-4 py-2 text-right font-mono">{{ r.qty_sold }}</td>
                        <td class="px-4 py-2 text-right font-mono">{{ fmtCurrency(r.revenue) }}</td>
                        <td class="px-4 py-2 text-right font-mono text-rose-700">{{ fmtCurrency(r.cost) }}</td>
                        <td class="px-4 py-2 text-right font-mono font-semibold"
                            :class="r.profit >= 0 ? 'text-emerald-700' : 'text-rose-700'">{{ fmtCurrency(r.profit) }}</td>
                        <td class="px-4 py-2 text-right">
                            <div class="inline-flex items-center gap-2">
                                <div class="w-16 h-1.5 bg-slate-100 rounded-full overflow-hidden">
                                    <div class="h-full rounded-full"
                                         :class="r.margin_pct >= 0 ? 'bg-emerald-500' : 'bg-rose-500'"
                                         :style="{ width: Math.min(100, Math.abs(r.margin_pct)) + '%' }"></div>
                                </div>
                                <span class="font-mono text-xs w-10 text-right">{{ r.margin_pct }}%</span>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!loading && profitability.length === 0">
                        <td colspan="6" class="py-10 text-center text-sm text-slate-400">{{ t('inventory.analytics.empty') }}</td>
                    </tr>
                </tbody>
            </table>
        </section>

        <!-- Movement -->
        <section v-if="tab === 'movement' && movement" class="card overflow-hidden p-0 overflow-x-auto">
            <table class="w-full text-sm min-w-[600px]">
                <thead class="bg-slate-50/70">
                    <tr class="text-left text-[11px] text-slate-500 uppercase tracking-wide border-b border-slate-100">
                        <th class="px-4 py-2.5">{{ t('inventory.fields.product') }}</th>
                        <th class="px-4 py-2.5 text-right">{{ t('inventory.analytics.qtyIn') }}</th>
                        <th class="px-4 py-2.5 text-right">{{ t('inventory.analytics.qtyOut') }}</th>
                        <th class="px-4 py-2.5 text-right">{{ t('inventory.analytics.net') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <tr v-for="r in movement.rows" :key="r.product_id">
                        <td class="px-4 py-2">
                            <p class="font-medium text-slate-800">{{ r.name }}</p>
                            <p class="text-[11px] text-slate-500 font-mono">{{ r.sku }}</p>
                        </td>
                        <td class="px-4 py-2 text-right font-mono text-emerald-700">{{ r.qty_in }}</td>
                        <td class="px-4 py-2 text-right font-mono text-rose-700">{{ r.qty_out }}</td>
                        <td class="px-4 py-2 text-right font-mono font-semibold"
                            :class="r.net >= 0 ? 'text-emerald-700' : 'text-rose-700'">
                            {{ r.net > 0 ? '+' : '' }}{{ r.net }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>

        <!-- Suppliers -->
        <section v-if="tab === 'suppliers' && suppliers.length" class="card overflow-hidden p-0 overflow-x-auto">
            <table class="w-full text-sm min-w-[600px]">
                <thead class="bg-slate-50/70">
                    <tr class="text-left text-[11px] text-slate-500 uppercase tracking-wide border-b border-slate-100">
                        <th class="px-4 py-2.5">{{ t('inventory.fields.supplier') }}</th>
                        <th class="px-4 py-2.5 text-right">{{ t('inventory.suppliers.purchases') }}</th>
                        <th class="px-4 py-2.5 text-right">{{ t('inventory.suppliers.totalValue') }}</th>
                        <th class="px-4 py-2.5 text-right">{{ t('inventory.suppliers.outstanding') }}</th>
                        <th class="px-4 py-2.5 text-right">{{ t('inventory.suppliers.leadTime') }}</th>
                        <th class="px-4 py-2.5">{{ t('inventory.suppliers.lastPurchase') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <tr v-for="s in suppliers" :key="s.supplier_id" class="hover:bg-slate-50/60">
                        <td class="px-4 py-2 font-medium text-slate-800">{{ s.name }}</td>
                        <td class="px-4 py-2 text-right font-mono">{{ s.purchase_count }}</td>
                        <td class="px-4 py-2 text-right font-mono">{{ fmtCurrency(s.total_purchased) }}</td>
                        <td class="px-4 py-2 text-right font-mono"
                            :class="s.outstanding > 0 ? 'text-amber-700' : 'text-slate-500'">
                            {{ fmtCurrency(s.outstanding) }}
                        </td>
                        <td class="px-4 py-2 text-right font-mono">{{ s.lead_time_days }}d</td>
                        <td class="px-4 py-2 text-xs">
                            <span v-if="s.last_purchase_date">
                                {{ formatDate(s.last_purchase_date) }}
                                <span class="text-slate-400 ml-1">({{ s.days_since_last }}d)</span>
                            </span>
                            <span v-else class="text-slate-400">—</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>

    </div>
</template>

<script setup>
import { ref, h, onMounted, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { inventoryReportService, supplierAnalyticsService } from '@/services/inventoryIntelligenceService';
import { useCurrency } from '@/composables/useCurrency';

const { t, locale } = useI18n();
const { fmtCurrency } = useCurrency();

const tab = ref('valuation');
const loading = ref(false);

const valuation = ref(null);
const profitability = ref([]);
const movement = ref(null);
const suppliers = ref([]);

const from = ref(new Date(new Date().getFullYear(), 0, 1).toISOString().slice(0, 10));
const to   = ref(new Date().toISOString().slice(0, 10));

function formatDate(d) {
    return new Intl.DateTimeFormat(locale.value || 'en-US', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(new Date(d));
}

const SumBox = (props) => {
    const palette = {
        emerald: 'border-emerald-200 bg-emerald-50/40 text-emerald-700',
        indigo:  'border-indigo-200 bg-indigo-50/40 text-indigo-700',
        slate:   'border-slate-200 bg-white text-slate-900',
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
        if (tab.value === 'valuation') {
            const { data } = await inventoryReportService.valuation();
            valuation.value = data.data;
        } else if (tab.value === 'profitability') {
            const { data } = await inventoryReportService.profitability({ from: from.value, to: to.value });
            profitability.value = data.data ?? [];
        } else if (tab.value === 'movement') {
            const { data } = await inventoryReportService.movement({ from: from.value, to: to.value });
            movement.value = data.data;
        } else if (tab.value === 'suppliers') {
            const { data } = await supplierAnalyticsService.leaderboard();
            suppliers.value = data.data ?? [];
        }
    } finally {
        loading.value = false;
    }
}

watch(tab, load);
onMounted(load);
</script>

<style scoped>
@reference '../../../css/app.css';
.card       { @apply bg-white border border-slate-200 rounded-xl shadow-sm; }
.lbl        { @apply block text-xs font-medium text-slate-600 mb-1; }
.ctrl       { @apply px-3 py-2 text-sm border border-slate-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent; }
</style>
