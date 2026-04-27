<template>
    <div class="p-6 lg:p-8 space-y-5 print:p-0 print:space-y-3">

        <!-- ── Page header ──────────────────────────────────────────────── -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 print:hidden">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">{{ t('purchaseReturnRecord.title') }}</h1>
                <p class="mt-0.5 text-sm text-gray-500">{{ t('purchaseReturnRecord.subtitle') }}</p>
            </div>
            <button v-if="returns.length" @click="printReport"
                class="inline-flex items-center gap-2 px-4 py-2 bg-gray-800 hover:bg-gray-700 text-white text-sm font-medium rounded-lg transition-colors shadow-sm">
                <PrinterIcon class="w-4 h-4" />
                {{ t('purchaseReturnRecord.print') }}
            </button>
        </div>

        <!-- ── Filter bar ────────────────────────────────────────────────── -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 print:hidden">
            <div class="flex flex-wrap items-end gap-3">
                <div>
                    <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-1">
                        {{ t('purchaseReturnRecord.from') }}
                    </label>
                    <input v-model="filters.date_from" type="date"
                        class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500 bg-white" />
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-1">
                        {{ t('purchaseReturnRecord.to') }}
                    </label>
                    <input v-model="filters.date_to" type="date"
                        class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500 bg-white" />
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-1">
                        {{ t('purchaseReturnRecord.supplier') }}
                    </label>
                    <select v-model="filters.supplier_id"
                        class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500 bg-white min-w-[180px]">
                        <option value="">{{ t('purchaseReturnRecord.allSuppliers') }}</option>
                        <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                </div>
                <button @click="loadRecord" :disabled="loading"
                    class="inline-flex items-center gap-2 px-5 py-1.5 bg-orange-600 hover:bg-orange-700 disabled:opacity-50 text-white text-sm font-semibold rounded-lg transition-colors shadow-sm">
                    <svg v-if="loading" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                    <MagnifyingGlassIcon v-else class="w-3.5 h-3.5" />
                    {{ t('purchaseReturnRecord.search') }}
                </button>
                <label v-if="returns.length" class="ml-auto inline-flex items-center gap-2 cursor-pointer select-none">
                    <div class="relative">
                        <input type="checkbox" v-model="showAllDetails" class="sr-only peer" />
                        <div class="w-9 h-5 bg-gray-200 peer-checked:bg-orange-600 rounded-full transition-colors"></div>
                        <div class="absolute top-0.5 left-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform peer-checked:translate-x-4"></div>
                    </div>
                    <span class="text-sm text-gray-600">{{ t('purchaseReturnRecord.withDetails') }}</span>
                </label>
            </div>
        </div>

        <!-- ── KPI cards ─────────────────────────────────────────────────── -->
        <div v-if="summary" class="grid grid-cols-2 lg:grid-cols-3 gap-3">
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-4 py-3">
                <p class="text-xs text-gray-500 font-medium">{{ t('purchaseReturnRecord.totalReturns') }}</p>
                <p class="text-2xl font-bold text-gray-900 mt-0.5">{{ summary.count }}</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-4 py-3">
                <p class="text-xs text-gray-500 font-medium">{{ t('purchaseReturnRecord.items') }}</p>
                <p class="text-2xl font-bold text-gray-900 mt-0.5">
                    {{ returns.reduce((s, r) => s + (r.items?.length ?? 0), 0) }}
                </p>
            </div>
            <div class="bg-orange-600 rounded-xl shadow-sm px-4 py-3">
                <p class="text-xs text-orange-200 font-medium">{{ t('purchaseReturns.totalReturn') }}</p>
                <p class="text-2xl font-bold text-white mt-0.5 tabular-nums">{{ fmt(summary.total_amount) }}</p>
            </div>
        </div>

        <!-- ── Print header ───────────────────────────────────────────────── -->
        <div class="hidden print:block mb-4">
            <h2 class="text-xl font-bold text-gray-900">{{ t('purchaseReturnRecord.title') }}</h2>
            <p class="text-sm text-gray-600">
                {{ t('purchaseReturnRecord.from') }}: {{ filters.date_from }} &nbsp;·&nbsp;
                {{ t('purchaseReturnRecord.to') }}: {{ filters.date_to }}
                <span v-if="supplierName"> &nbsp;·&nbsp; {{ supplierName }}</span>
            </p>
        </div>

        <!-- ── Empty state ────────────────────────────────────────────────── -->
        <div v-if="!returns.length && !loading && searched"
            class="bg-white rounded-xl border border-gray-200 shadow-sm py-14 text-center">
            <ClipboardDocumentListIcon class="w-12 h-12 text-gray-200 mx-auto mb-3" />
            <p class="text-gray-500 text-sm">{{ t('purchaseReturnRecord.noResults') }}</p>
        </div>

        <!-- ── Main table ─────────────────────────────────────────────────── -->
        <div v-if="returns.length" class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden" id="printArea">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-orange-600 text-white text-xs uppercase tracking-wide">
                            <th class="px-3 py-2.5 w-8 print:hidden"></th>
                            <th class="px-3 py-2.5 text-left">{{ t('purchaseReturns.returnNumber') }}</th>
                            <th class="px-3 py-2.5 text-left">{{ t('purchaseReturns.returnDate') }}</th>
                            <th class="px-3 py-2.5 text-left">{{ t('suppliers.title') }}</th>
                            <th class="px-3 py-2.5 text-left">{{ t('purchaseReturnRecord.originalPurchase') }}</th>
                            <th class="px-3 py-2.5 text-right w-16">{{ t('purchaseReturnRecord.items') }}</th>
                            <th class="px-3 py-2.5 text-right font-bold">{{ t('purchaseReturns.totalReturn') }}</th>
                            <th class="px-3 py-2.5 text-center w-16 print:hidden">{{ t('common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="(r, idx) in returns" :key="r.id">
                            <tr
                                @click="toggle(r.id)"
                                :class="[
                                    'border-b border-gray-100 cursor-pointer transition-colors',
                                    isExpanded(r.id) ? 'bg-orange-50/60' : 'hover:bg-gray-50/70',
                                    idx % 2 === 1 && !isExpanded(r.id) ? 'bg-gray-50/40' : '',
                                ]"
                            >
                                <td class="px-3 py-2.5 text-center print:hidden">
                                    <ChevronDownIcon :class="['w-3.5 h-3.5 text-gray-400 transition-transform duration-200', isExpanded(r.id) ? 'rotate-180 text-orange-500' : '']" />
                                </td>
                                <td class="px-3 py-2.5 font-mono font-semibold text-orange-700 whitespace-nowrap">{{ r.return_number }}</td>
                                <td class="px-3 py-2.5 text-gray-600 whitespace-nowrap">{{ fmtDate(r.return_date) }}</td>
                                <td class="px-3 py-2.5 text-gray-800 font-medium">{{ r.supplier?.name ?? '—' }}</td>
                                <td class="px-3 py-2.5 text-gray-500 font-mono text-xs">{{ r.purchase?.purchase_number ?? '—' }}</td>
                                <td class="px-3 py-2.5 text-right text-gray-500">{{ r.items?.length ?? 0 }}</td>
                                <td class="px-3 py-2.5 text-right tabular-nums font-bold text-gray-900">{{ fmt(r.total_amount) }}</td>
                                <td class="px-3 py-2.5 text-center print:hidden" @click.stop>
                                    <a :href="`/purchase-returns/${r.id}/invoice`" target="_blank"
                                        class="inline-flex items-center justify-center w-7 h-7 rounded-lg text-gray-400 hover:text-orange-600 hover:bg-orange-50 transition-colors"
                                        :title="t('purchaseReturnRecord.viewReturn')">
                                        <DocumentTextIcon class="w-4 h-4" />
                                    </a>
                                </td>
                            </tr>
                            <!-- Detail rows -->
                            <tr v-if="isExpanded(r.id) && r.items?.length" class="border-b border-orange-100/60">
                                <td colspan="8" class="p-0">
                                    <div class="bg-orange-50/40 border-t border-orange-100/80">
                                        <table class="w-full text-xs">
                                            <thead>
                                                <tr class="bg-orange-100/60 text-orange-700 uppercase tracking-wide">
                                                    <th class="px-6 py-1.5 text-left w-8 print:hidden">#</th>
                                                    <th class="px-4 py-1.5 text-left">{{ t('purchaseReturnRecord.product') }}</th>
                                                    <th class="px-4 py-1.5 text-center w-20">{{ t('purchases.unit') }}</th>
                                                    <th class="px-4 py-1.5 text-right w-20">{{ t('purchases.qty') }}</th>
                                                    <th class="px-4 py-1.5 text-right w-28">{{ t('purchaseReturns.unitCost') }}</th>
                                                    <th class="px-4 py-1.5 text-right w-28 font-bold">{{ t('purchaseReturns.amount') }}</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-orange-100/50">
                                                <tr v-for="(item, i) in r.items" :key="item.id"
                                                    class="hover:bg-orange-100/30 transition-colors">
                                                    <td class="px-6 py-1.5 text-gray-400 print:hidden">{{ i + 1 }}</td>
                                                    <td class="px-4 py-1.5">
                                                        <p class="font-semibold text-gray-800">{{ item.product_name }}</p>
                                                        <p class="text-gray-400 font-mono text-[11px]">{{ item.product_sku }}</p>
                                                    </td>
                                                    <td class="px-4 py-1.5 text-center text-gray-600">{{ item.unit_name ?? '—' }}</td>
                                                    <td class="px-4 py-1.5 text-right tabular-nums text-gray-700 font-medium">{{ item.quantity }}</td>
                                                    <td class="px-4 py-1.5 text-right tabular-nums text-gray-700">{{ fmt(item.unit_cost) }}</td>
                                                    <td class="px-4 py-1.5 text-right tabular-nums font-bold text-gray-900">{{ fmt(item.line_total) }}</td>
                                                </tr>
                                            </tbody>
                                            <tfoot>
                                                <tr class="bg-orange-100/40 font-semibold text-orange-800">
                                                    <td colspan="3" class="px-4 py-1.5 text-right text-xs uppercase tracking-wide">
                                                        {{ t('purchaseReturns.totalReturn') }}
                                                    </td>
                                                    <td class="px-4 py-1.5 text-right tabular-nums">
                                                        {{ r.items.reduce((s, i) => s + i.quantity, 0) }}
                                                    </td>
                                                    <td></td>
                                                    <td class="px-4 py-1.5 text-right tabular-nums">{{ fmt(r.total_amount) }}</td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                    <tfoot v-if="summary">
                        <tr class="bg-gray-800 text-white font-bold text-sm border-t-2 border-gray-700">
                            <td class="px-3 py-3 print:hidden"></td>
                            <td colspan="3" class="px-3 py-3 text-right text-xs uppercase tracking-widest text-gray-300">
                                {{ t('purchaseReturnRecord.grandTotalRow') }} ({{ summary.count }})
                            </td>
                            <td class="px-3 py-3"></td>
                            <td class="px-3 py-3 text-right tabular-nums">
                                {{ returns.reduce((s, r) => s + (r.items?.length ?? 0), 0) }}
                            </td>
                            <td class="px-3 py-3 text-right tabular-nums text-orange-300 text-base">{{ fmt(summary.total_amount) }}</td>
                            <td class="print:hidden"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useSettingsStore } from '@/stores/settings';
import { purchaseService } from '@/services/purchaseService';
import { supplierService } from '@/services/supplierService';
import {
    MagnifyingGlassIcon, PrinterIcon, ChevronDownIcon,
    DocumentTextIcon, ClipboardDocumentListIcon,
} from '@heroicons/vue/24/outline';

const { t }         = useI18n();
const settingsStore = useSettingsStore();

const today        = new Date().toISOString().substring(0, 10);
const firstOfMonth = new Date(new Date().getFullYear(), new Date().getMonth(), 1)
    .toISOString().substring(0, 10);

const filters = ref({ date_from: firstOfMonth, date_to: today, supplier_id: '' });

const suppliers = ref([]);
onMounted(async () => {
    try {
        const { data } = await supplierService.all();
        suppliers.value = data.data ?? [];
    } catch { /* non-critical */ }
    loadRecord();
});

const returns       = ref([]);
const summary       = ref(null);
const loading       = ref(false);
const searched      = ref(false);
const showAllDetails = ref(false);
const expandedIds   = ref(new Set());

const supplierName = computed(() => {
    if (!filters.value.supplier_id) return '';
    return suppliers.value.find(x => x.id === filters.value.supplier_id)?.name ?? '';
});

async function loadRecord() {
    loading.value  = true;
    searched.value = true;
    try {
        const { data } = await purchaseService.returnRecord(filters.value);
        returns.value     = data.data    ?? [];
        summary.value     = data.summary ?? null;
        expandedIds.value = new Set();
        if (showAllDetails.value) expandAll();
    } catch (err) {
        console.error(err);
    } finally {
        loading.value = false;
    }
}

function isExpanded(id) { return expandedIds.value.has(id); }
function toggle(id) {
    const s = new Set(expandedIds.value);
    s.has(id) ? s.delete(id) : s.add(id);
    expandedIds.value = s;
}
function expandAll()   { expandedIds.value = new Set(returns.value.map(r => r.id)); }
function collapseAll() { expandedIds.value = new Set(); }
watch(showAllDetails, v => v ? expandAll() : collapseAll());

function fmt(value) {
    if (value == null) return '—';
    const code = settingsStore.settings?.currency_code ?? 'EUR';
    return new Intl.NumberFormat('de-DE', { style: 'currency', currency: code }).format(value);
}
function fmtDate(dateStr) {
    if (!dateStr) return '—';
    return new Date(dateStr + 'T00:00:00').toLocaleDateString('de-DE', {
        day: '2-digit', month: '2-digit', year: 'numeric',
    });
}
function printReport() {
    expandAll();
    setTimeout(() => window.print(), 150);
}
</script>

<style scoped>
@reference '../../../css/app.css';
@media print {
    @page { size: A4 landscape; margin: 12mm; }
    .print\:hidden { display: none !important; }
    .print\:block  { display: block !important; }
    body { font-size: 11px; }
}
</style>
