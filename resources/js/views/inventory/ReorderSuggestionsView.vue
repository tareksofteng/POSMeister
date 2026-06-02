<template>
    <div class="p-3 sm:p-6 lg:p-8 space-y-4 sm:space-y-5 max-w-7xl mx-auto pb-safe">

        <header class="space-y-3 sm:space-y-0 sm:flex sm:items-end sm:justify-between sm:gap-4">
            <div>
                <h1 class="text-lg sm:text-2xl font-bold text-slate-900 dark:text-slate-100 tracking-tight">{{ t('inventory.reorder.title') }}</h1>
                <p class="mt-0.5 text-xs sm:text-sm text-slate-500">{{ t('inventory.reorder.subtitle') }}</p>
            </div>
            <div class="grid grid-cols-2 sm:flex sm:items-end gap-2">
                <div>
                    <label class="block text-[10px] uppercase tracking-wider font-semibold text-slate-500 mb-1">{{ t('inventory.reorder.velocityDays') }}</label>
                    <select v-model.number="velocityDays" @change="load" class="w-full sm:w-32 px-3 py-2 text-sm border border-slate-300 rounded-lg bg-white">
                        <option :value="14">14</option>
                        <option :value="30">30</option>
                        <option :value="60">60</option>
                        <option :value="90">90</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] uppercase tracking-wider font-semibold text-slate-500 mb-1">{{ t('inventory.reorder.safetyDays') }}</label>
                    <select v-model.number="safetyDays" @change="load" class="w-full sm:w-28 px-3 py-2 text-sm border border-slate-300 rounded-lg bg-white">
                        <option :value="3">3</option>
                        <option :value="7">7</option>
                        <option :value="14">14</option>
                        <option :value="21">21</option>
                    </select>
                </div>
                <label class="col-span-2 sm:col-span-1 inline-flex items-center gap-2 text-xs sm:text-sm text-slate-700 sm:pb-2">
                    <input type="checkbox" v-model="urgentOnly" @change="load" class="rounded border-slate-300" />
                    {{ t('inventory.reorder.urgentOnly') }}
                </label>
                <div class="col-span-2 sm:col-span-1 inline-flex rounded-lg border border-slate-300 overflow-hidden self-end">
                    <button @click="view = 'list'"
                        :class="['flex-1 px-3 py-2 text-xs font-medium', view === 'list' ? 'bg-indigo-600 text-white' : 'bg-white text-slate-700']">
                        {{ t('inventory.reorder.listView') }}
                    </button>
                    <button @click="view = 'supplier'"
                        :class="['flex-1 px-3 py-2 text-xs font-medium', view === 'supplier' ? 'bg-indigo-600 text-white' : 'bg-white text-slate-700']">
                        {{ t('inventory.reorder.supplierView') }}
                    </button>
                </div>
            </div>
        </header>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-2 sm:gap-3">
            <SumBox :label="t('inventory.reorder.totalItems')" :value="String(rows.length)" />
            <SumBox :label="t('inventory.reorder.critical')" :value="String(urgencyCount('critical'))" tone="rose" />
            <SumBox :label="t('inventory.reorder.high')" :value="String(urgencyCount('high'))" tone="amber" />
            <SumBox :label="t('inventory.reorder.estimatedSpend')" :value="fmtCurrency(totalSpend)" tone="indigo" />
        </div>

        <!-- ── MOBILE STACKED CARDS (< sm) — list view ─────────────── -->
        <div v-if="view === 'list'" class="sm:hidden space-y-2">
            <div v-for="r in rows" :key="`m-${r.product_id}`" class="bg-white rounded-xl border border-slate-200 p-3 shadow-sm">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="font-semibold text-slate-900 truncate">{{ r.name }}</p>
                        <p class="text-[10px] text-slate-500 font-mono">{{ r.sku }}</p>
                    </div>
                    <span :class="urgencyClass(r.urgency)" class="text-[10px] uppercase tracking-wider px-2 py-0.5 rounded-md font-bold flex-shrink-0">
                        {{ t('inventory.urgency.' + r.urgency) }}
                    </span>
                </div>
                <div class="grid grid-cols-3 gap-2 mt-3 pt-2 border-t border-slate-100 text-xs">
                    <div>
                        <p class="text-[9px] uppercase tracking-wider text-slate-400 font-semibold">{{ t('inventory.fields.stock') }}</p>
                        <p class="font-bold text-slate-800 font-mono">{{ r.current_stock }}<span v-if="r.reorder_level > 0" class="text-[10px] text-slate-400"> / {{ r.reorder_level }}</span></p>
                    </div>
                    <div>
                        <p class="text-[9px] uppercase tracking-wider text-slate-400 font-semibold">{{ t('inventory.fields.avgDaily') }}</p>
                        <p class="font-bold text-slate-800 font-mono">{{ r.avg_daily_sales }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-[9px] uppercase tracking-wider text-slate-400 font-semibold">{{ t('inventory.fields.recommendedQty') }}</p>
                        <p class="font-bold text-indigo-700 font-mono text-sm">{{ r.recommended_qty }}</p>
                    </div>
                </div>
                <div class="mt-2 pt-2 border-t border-slate-100 flex items-center justify-between gap-2 text-xs">
                    <div class="min-w-0">
                        <p class="text-[9px] uppercase tracking-wider text-slate-400 font-semibold">{{ t('inventory.fields.supplier') }}</p>
                        <p class="text-slate-700 truncate">
                            <span v-if="r.preferred_supplier">{{ r.preferred_supplier.name }}</span>
                            <span v-else class="text-slate-400 italic">{{ t('inventory.reorder.noSupplier') }}</span>
                        </p>
                    </div>
                    <div class="text-right">
                        <p class="text-[9px] uppercase tracking-wider text-slate-400 font-semibold">{{ t('inventory.fields.estCost') }}</p>
                        <p class="font-bold text-slate-900 font-mono">{{ fmtCurrency(r.estimated_cost) }}</p>
                    </div>
                </div>
                <p v-if="r.predicted_stockout" class="mt-2 text-[10px] text-rose-600 font-semibold">
                    ⚠ {{ t('inventory.fields.stockout') }}: {{ formatDate(r.predicted_stockout) }} ({{ r.coverage_days }}d)
                </p>
            </div>
            <p v-if="!loading && rows.length === 0" class="py-10 text-center text-sm text-slate-400">{{ t('inventory.reorder.empty') }}</p>
        </div>

        <!-- ── DESKTOP TABLE (sm+) — list view ─────────────────────── -->
        <div v-if="view === 'list'" class="hidden sm:block card overflow-hidden p-0">
            <table class="w-full text-sm">
                <thead class="bg-slate-50/70">
                    <tr class="text-left text-[11px] text-slate-500 uppercase tracking-wide border-b border-slate-100">
                        <th class="px-4 py-2.5">{{ t('inventory.fields.product') }}</th>
                        <th class="px-4 py-2.5 text-right">{{ t('inventory.fields.stock') }}</th>
                        <th class="px-4 py-2.5 text-right">{{ t('inventory.fields.avgDaily') }}</th>
                        <th class="px-4 py-2.5">{{ t('inventory.fields.stockout') }}</th>
                        <th class="px-4 py-2.5 text-right">{{ t('inventory.fields.recommendedQty') }}</th>
                        <th class="px-4 py-2.5">{{ t('inventory.fields.supplier') }}</th>
                        <th class="px-4 py-2.5 text-right">{{ t('inventory.fields.estCost') }}</th>
                        <th class="px-4 py-2.5 text-center">{{ t('inventory.fields.urgency') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <tr v-for="r in rows" :key="r.product_id" class="hover:bg-slate-50/60">
                        <td class="px-4 py-2">
                            <p class="font-medium text-slate-800">{{ r.name }}</p>
                            <p class="text-[11px] text-slate-500 font-mono">{{ r.sku }}</p>
                        </td>
                        <td class="px-4 py-2 text-right font-mono">
                            {{ r.current_stock }}
                            <span v-if="r.reorder_level > 0" class="text-[10px] text-slate-400 block">/ {{
                                r.reorder_level }}</span>
                        </td>
                        <td class="px-4 py-2 text-right font-mono">{{ r.avg_daily_sales }}</td>
                        <td class="px-4 py-2 text-xs">
                            <span v-if="r.predicted_stockout" :class="stockoutClass(r.coverage_days)">
                                {{ formatDate(r.predicted_stockout) }}
                                <span class="text-slate-400 ml-1">({{ r.coverage_days }}d)</span>
                            </span>
                            <span v-else class="text-slate-400">—</span>
                        </td>
                        <td class="px-4 py-2 text-right font-mono font-semibold">{{ r.recommended_qty }}</td>
                        <td class="px-4 py-2 text-xs text-slate-700">
                            <span v-if="r.preferred_supplier">{{ r.preferred_supplier.name }}</span>
                            <span v-else class="text-slate-400 italic">{{ t('inventory.reorder.noSupplier') }}</span>
                        </td>
                        <td class="px-4 py-2 text-right font-mono">{{ fmtCurrency(r.estimated_cost) }}</td>
                        <td class="px-4 py-2 text-center">
                            <span :class="urgencyClass(r.urgency)"
                                class="text-[10px] uppercase tracking-wider px-2 py-0.5 rounded-md font-bold">
                                {{ t('inventory.urgency.' + r.urgency) }}
                            </span>
                        </td>
                    </tr>
                    <tr v-if="!loading && rows.length === 0">
                        <td colspan="8" class="py-10 text-center text-sm text-slate-400">{{ t('inventory.reorder.empty')
                            }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="view === 'supplier'" class="space-y-3 sm:space-y-4">
            <div v-for="g in supplierGroups" :key="g.supplier_id ?? 'none'" class="card overflow-hidden p-0">
                <div class="flex items-center justify-between px-3 sm:px-5 py-2.5 sm:py-3 border-b border-slate-100 bg-slate-50/60 gap-2">
                    <div class="min-w-0">
                        <p class="font-semibold text-slate-900 truncate">{{ g.supplier_name }}</p>
                        <p class="text-[10px] sm:text-xs text-slate-500 mt-0.5">
                            {{ g.item_count }} {{ t('inventory.reorder.items') }} ·
                            {{ g.total_qty }} {{ t('inventory.reorder.units') }}
                        </p>
                    </div>
                    <p class="font-mono font-bold text-indigo-700 text-sm sm:text-base flex-shrink-0">{{ fmtCurrency(g.estimated_total) }}</p>
                </div>

                <!-- Mobile cards per item -->
                <div class="sm:hidden divide-y divide-slate-100">
                    <div v-for="r in g.items" :key="`sm-${r.product_id}`" class="p-3">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="font-medium text-slate-800 truncate">{{ r.name }}</p>
                                <p class="text-[10px] text-slate-500 font-mono">{{ r.sku }}</p>
                            </div>
                            <span :class="urgencyClass(r.urgency)" class="text-[10px] uppercase tracking-wider px-1.5 py-0.5 rounded-md font-bold flex-shrink-0">
                                {{ t('inventory.urgency.' + r.urgency) }}
                            </span>
                        </div>
                        <div class="grid grid-cols-3 gap-2 mt-2 text-xs">
                            <div>
                                <p class="text-[9px] uppercase tracking-wider text-slate-400">{{ t('inventory.fields.stock') }}</p>
                                <p class="font-mono text-slate-700">{{ r.current_stock }}</p>
                            </div>
                            <div>
                                <p class="text-[9px] uppercase tracking-wider text-slate-400">{{ t('inventory.reorder.order') }}</p>
                                <p class="font-mono font-bold text-slate-900">{{ r.recommended_qty }}</p>
                            </div>
                            <div class="text-right">
                                <p class="text-[9px] uppercase tracking-wider text-slate-400">{{ t('inventory.fields.estCost') }}</p>
                                <p class="font-mono font-bold text-indigo-700">{{ fmtCurrency(r.estimated_cost) }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Desktop table -->
                <table class="hidden sm:table w-full text-sm">
                    <tbody class="divide-y divide-slate-50">
                        <tr v-for="r in g.items" :key="r.product_id">
                            <td class="px-5 py-2">
                                <p class="font-medium text-slate-800">{{ r.name }}</p>
                                <p class="text-[11px] text-slate-500 font-mono">{{ r.sku }}</p>
                            </td>
                            <td class="px-5 py-2 text-right text-xs text-slate-600">
                                {{ t('inventory.fields.stock') }}: <span class="font-mono">{{ r.current_stock }}</span>
                            </td>
                            <td class="px-5 py-2 text-right text-xs text-slate-600">
                                {{ t('inventory.reorder.order') }}: <span class="font-mono font-bold">{{
                                    r.recommended_qty }}</span>
                            </td>
                            <td class="px-5 py-2 text-right font-mono">{{ fmtCurrency(r.estimated_cost) }}</td>
                            <td class="px-5 py-2 text-center">
                                <span :class="urgencyClass(r.urgency)"
                                    class="text-[10px] uppercase tracking-wider px-2 py-0.5 rounded-md font-bold">
                                    {{ t('inventory.urgency.' + r.urgency) }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p v-if="!loading && supplierGroups.length === 0" class="text-center text-sm text-slate-400 py-10">
                {{ t('inventory.reorder.empty') }}
            </p>
        </div>

    </div>
</template>

<script setup>
import { ref, computed, h, onMounted, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { procurementService } from '@/services/inventoryIntelligenceService';
import { useCurrency } from '@/composables/useCurrency';

const { t, locale } = useI18n();
const { fmtCurrency } = useCurrency();

const rows = ref([]);
const supplierGroups = ref([]);
const loading = ref(false);
const view = ref('list');

const velocityDays = ref(30);
const safetyDays = ref(7);
const urgentOnly = ref(false);

const totalSpend = computed(() => rows.value.reduce((s, r) => s + (r.estimated_cost || 0), 0));
function urgencyCount(u) { return rows.value.filter(r => r.urgency === u).length; }

function urgencyClass(u) {
    return {
        critical: 'bg-rose-100 text-rose-800',
        high: 'bg-amber-100 text-amber-800',
        medium: 'bg-indigo-100 text-indigo-800',
        low: 'bg-slate-100 text-slate-600',
    }[u] ?? 'bg-slate-100 text-slate-600';
}
function stockoutClass(days) {
    if (days === null || days === undefined) return 'text-slate-400';
    if (days <= 3) return 'text-rose-700 font-semibold';
    if (days <= 7) return 'text-amber-700';
    return 'text-slate-600';
}
function formatDate(d) {
    return new Intl.DateTimeFormat(locale.value || 'en-US', { day: '2-digit', month: '2-digit' }).format(new Date(d));
}

const SumBox = (props) => {
    const palette = {
        rose: 'border-rose-200 bg-rose-50/40 text-rose-700',
        amber: 'border-amber-200 bg-amber-50/40 text-amber-700',
        indigo: 'border-indigo-200 bg-indigo-50/40 text-indigo-700',
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
        const params = {
            velocity_days: velocityDays.value,
            safety_days: safetyDays.value,
            urgent_only: urgentOnly.value ? 1 : 0,
        };
        if (view.value === 'supplier') {
            const { data } = await procurementService.suggestionsBySupplier(params);
            supplierGroups.value = data.data ?? [];
            // Flatten for KPI totals
            rows.value = supplierGroups.value.flatMap(g => g.items);
        } else {
            const { data } = await procurementService.suggestions(params);
            rows.value = data.data ?? [];
        }
    } finally {
        loading.value = false;
    }
}

watch(view, load);
onMounted(load);
</script>

<style scoped>
@reference '../../../css/app.css';

.card {
    @apply bg-white border border-slate-200 rounded-xl shadow-sm;
}

.lbl {
    @apply block text-xs font-medium text-slate-600 mb-1;
}

.ctrl {
    @apply px-3 py-2 text-sm border border-slate-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent;
}
</style>
