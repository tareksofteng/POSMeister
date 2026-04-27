<template>
    <div class="p-6 lg:p-8 space-y-5 print:p-0 print:space-y-3">

        <!-- ── Page header ──────────────────────────────────────────────── -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 print:hidden">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">{{ t('saleReturnRecord.title') }}</h1>
                <p class="mt-0.5 text-sm text-gray-500">{{ t('saleReturnRecord.subtitle') }}</p>
            </div>
            <button v-if="returns.length" @click="printReport"
                class="inline-flex items-center gap-2 px-4 py-2 bg-gray-800 hover:bg-gray-700 text-white text-sm font-medium rounded-lg transition-colors shadow-sm">
                <PrinterIcon class="w-4 h-4" />
                {{ t('saleReturnRecord.print') }}
            </button>
        </div>

        <!-- ── Filter bar ────────────────────────────────────────────────── -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 print:hidden">
            <div class="flex flex-wrap items-end gap-3">
                <div>
                    <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-1">
                        {{ t('saleReturnRecord.from') }}
                    </label>
                    <input v-model="filters.date_from" type="date"
                        class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-500 bg-white" />
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-1">
                        {{ t('saleReturnRecord.to') }}
                    </label>
                    <input v-model="filters.date_to" type="date"
                        class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-500 bg-white" />
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-1">
                        {{ t('saleReturnRecord.customer') }}
                    </label>
                    <select v-model="filters.customer_id"
                        class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-500 bg-white min-w-[180px]">
                        <option value="">{{ t('saleReturnRecord.allCustomers') }}</option>
                        <option v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                </div>
                <button @click="loadRecord" :disabled="loading"
                    class="inline-flex items-center gap-2 px-5 py-1.5 bg-teal-600 hover:bg-teal-700 disabled:opacity-50 text-white text-sm font-semibold rounded-lg transition-colors shadow-sm">
                    <svg v-if="loading" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                    <MagnifyingGlassIcon v-else class="w-3.5 h-3.5" />
                    {{ t('saleReturnRecord.search') }}
                </button>
                <label v-if="returns.length" class="ml-auto inline-flex items-center gap-2 cursor-pointer select-none">
                    <div class="relative">
                        <input type="checkbox" v-model="showAllDetails" class="sr-only peer" />
                        <div class="w-9 h-5 bg-gray-200 peer-checked:bg-teal-600 rounded-full transition-colors"></div>
                        <div class="absolute top-0.5 left-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform peer-checked:translate-x-4"></div>
                    </div>
                    <span class="text-sm text-gray-600">{{ t('saleReturnRecord.withDetails') }}</span>
                </label>
            </div>
        </div>

        <!-- ── KPI cards ─────────────────────────────────────────────────── -->
        <div v-if="summary" class="grid grid-cols-2 lg:grid-cols-3 gap-3">
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-4 py-3">
                <p class="text-xs text-gray-500 font-medium">{{ t('saleReturnRecord.totalReturns') }}</p>
                <p class="text-2xl font-bold text-gray-900 mt-0.5">{{ summary.count }}</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-4 py-3">
                <p class="text-xs text-gray-500 font-medium">{{ t('saleReturnRecord.items') }}</p>
                <p class="text-2xl font-bold text-gray-900 mt-0.5">
                    {{ returns.reduce((s, r) => s + (r.items?.length ?? 0), 0) }}
                </p>
            </div>
            <div class="bg-teal-600 rounded-xl shadow-sm px-4 py-3">
                <p class="text-xs text-teal-200 font-medium">{{ t('saleReturns.totalReturn') }}</p>
                <p class="text-2xl font-bold text-white mt-0.5 tabular-nums">{{ fmt(summary.total_amount) }}</p>
            </div>
        </div>

        <!-- ── Print header ───────────────────────────────────────────────── -->
        <div class="hidden print:block mb-4">
            <h2 class="text-xl font-bold text-gray-900">{{ t('saleReturnRecord.title') }}</h2>
            <p class="text-sm text-gray-600">
                {{ t('saleReturnRecord.from') }}: {{ filters.date_from }} &nbsp;·&nbsp;
                {{ t('saleReturnRecord.to') }}: {{ filters.date_to }}
                <span v-if="customerName"> &nbsp;·&nbsp; {{ customerName }}</span>
            </p>
        </div>

        <!-- ── Empty state ────────────────────────────────────────────────── -->
        <div v-if="!returns.length && !loading && searched"
            class="bg-white rounded-xl border border-gray-200 shadow-sm py-14 text-center">
            <ClipboardDocumentListIcon class="w-12 h-12 text-gray-200 mx-auto mb-3" />
            <p class="text-gray-500 text-sm">{{ t('saleReturnRecord.noResults') }}</p>
        </div>

        <!-- ── Main table ─────────────────────────────────────────────────── -->
        <div v-if="returns.length" class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden" id="printArea">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-teal-600 text-white text-xs uppercase tracking-wide">
                            <th class="px-3 py-2.5 w-8 print:hidden"></th>
                            <th class="px-3 py-2.5 text-left">{{ t('saleReturns.returnNumber') }}</th>
                            <th class="px-3 py-2.5 text-left">{{ t('saleReturns.returnDate') }}</th>
                            <th class="px-3 py-2.5 text-left">{{ t('customers.title') }}</th>
                            <th class="px-3 py-2.5 text-left">{{ t('saleReturnRecord.originalSale') }}</th>
                            <th class="px-3 py-2.5 text-right w-16">{{ t('saleReturnRecord.items') }}</th>
                            <th class="px-3 py-2.5 text-right font-bold">{{ t('saleReturns.totalReturn') }}</th>
                            <th class="px-3 py-2.5 text-center w-16 print:hidden">{{ t('common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="(r, idx) in returns" :key="r.id">
                            <tr
                                @click="toggle(r.id)"
                                :class="[
                                    'border-b border-gray-100 cursor-pointer transition-colors',
                                    isExpanded(r.id) ? 'bg-teal-50/60' : 'hover:bg-gray-50/70',
                                    idx % 2 === 1 && !isExpanded(r.id) ? 'bg-gray-50/40' : '',
                                ]"
                            >
                                <td class="px-3 py-2.5 text-center print:hidden">
                                    <ChevronDownIcon :class="['w-3.5 h-3.5 text-gray-400 transition-transform duration-200', isExpanded(r.id) ? 'rotate-180 text-teal-500' : '']" />
                                </td>
                                <td class="px-3 py-2.5 font-mono font-semibold text-teal-700 whitespace-nowrap">{{ r.return_number }}</td>
                                <td class="px-3 py-2.5 text-gray-600 whitespace-nowrap">{{ fmtDate(r.return_date) }}</td>
                                <td class="px-3 py-2.5 text-gray-800 font-medium">{{ r.customer?.name ?? '—' }}</td>
                                <td class="px-3 py-2.5 text-gray-500 font-mono text-xs">{{ r.sale?.sale_number ?? '—' }}</td>
                                <td class="px-3 py-2.5 text-right text-gray-500">{{ r.items?.length ?? 0 }}</td>
                                <td class="px-3 py-2.5 text-right tabular-nums font-bold text-gray-900">{{ fmt(r.total_amount) }}</td>
                                <td class="px-3 py-2.5 text-center print:hidden" @click.stop>
                                    <a :href="`/sale-returns/${r.id}/invoice`" target="_blank"
                                        class="inline-flex items-center justify-center w-7 h-7 rounded-lg text-gray-400 hover:text-teal-600 hover:bg-teal-50 transition-colors"
                                        :title="t('saleReturnRecord.viewReturn')">
                                        <DocumentTextIcon class="w-4 h-4" />
                                    </a>
                                </td>
                            </tr>
                            <!-- Detail rows -->
                            <tr v-if="isExpanded(r.id) && r.items?.length" class="border-b border-teal-100/60">
                                <td colspan="8" class="p-0">
                                    <div class="bg-teal-50/40 border-t border-teal-100/80">
                                        <table class="w-full text-xs">
                                            <thead>
                                                <tr class="bg-teal-100/60 text-teal-700 uppercase tracking-wide">
                                                    <th class="px-6 py-1.5 text-left w-8 print:hidden">#</th>
                                                    <th class="px-4 py-1.5 text-left">{{ t('saleReturnRecord.product') }}</th>
                                                    <th class="px-4 py-1.5 text-center w-20">{{ t('sales.unit') }}</th>
                                                    <th class="px-4 py-1.5 text-right w-20">{{ t('sales.qty') }}</th>
                                                    <th class="px-4 py-1.5 text-right w-28">{{ t('saleReturns.unitPrice') }}</th>
                                                    <th class="px-4 py-1.5 text-right w-28 font-bold">{{ t('saleReturns.amount') }}</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-teal-100/50">
                                                <tr v-for="(item, i) in r.items" :key="item.id"
                                                    class="hover:bg-teal-100/30 transition-colors">
                                                    <td class="px-6 py-1.5 text-gray-400 print:hidden">{{ i + 1 }}</td>
                                                    <td class="px-4 py-1.5">
                                                        <p class="font-semibold text-gray-800">{{ item.product_name }}</p>
                                                        <p class="text-gray-400 font-mono text-[11px]">{{ item.product_sku }}</p>
                                                    </td>
                                                    <td class="px-4 py-1.5 text-center text-gray-600">{{ item.unit_name ?? '—' }}</td>
                                                    <td class="px-4 py-1.5 text-right tabular-nums text-gray-700 font-medium">{{ item.quantity }}</td>
                                                    <td class="px-4 py-1.5 text-right tabular-nums text-gray-700">{{ fmt(item.unit_price) }}</td>
                                                    <td class="px-4 py-1.5 text-right tabular-nums font-bold text-gray-900">{{ fmt(item.line_total) }}</td>
                                                </tr>
                                            </tbody>
                                            <tfoot>
                                                <tr class="bg-teal-100/40 font-semibold text-teal-800">
                                                    <td colspan="3" class="px-4 py-1.5 text-right text-xs uppercase tracking-wide">
                                                        {{ t('saleReturns.totalReturn') }}
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
                                {{ t('saleReturnRecord.grandTotalRow') }} ({{ summary.count }})
                            </td>
                            <td class="px-3 py-3"></td>
                            <td class="px-3 py-3 text-right tabular-nums">
                                {{ returns.reduce((s, r) => s + (r.items?.length ?? 0), 0) }}
                            </td>
                            <td class="px-3 py-3 text-right tabular-nums text-teal-300 text-base">{{ fmt(summary.total_amount) }}</td>
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
import { saleService } from '@/services/saleService';
import { customerService } from '@/services/customerService';
import {
    MagnifyingGlassIcon, PrinterIcon, ChevronDownIcon,
    DocumentTextIcon, ClipboardDocumentListIcon,
} from '@heroicons/vue/24/outline';

const { t }         = useI18n();
const settingsStore = useSettingsStore();

const today        = new Date().toISOString().substring(0, 10);
const firstOfMonth = new Date(new Date().getFullYear(), new Date().getMonth(), 1)
    .toISOString().substring(0, 10);

const filters = ref({ date_from: firstOfMonth, date_to: today, customer_id: '' });

const customers = ref([]);
onMounted(async () => {
    try {
        const { data } = await customerService.all();
        customers.value = data.data ?? [];
    } catch { /* non-critical */ }
    loadRecord();
});

const returns       = ref([]);
const summary       = ref(null);
const loading       = ref(false);
const searched      = ref(false);
const showAllDetails = ref(false);
const expandedIds   = ref(new Set());

const customerName = computed(() => {
    if (!filters.value.customer_id) return '';
    return customers.value.find(x => x.id === filters.value.customer_id)?.name ?? '';
});

async function loadRecord() {
    loading.value  = true;
    searched.value = true;
    try {
        const { data } = await saleService.returnRecord(filters.value);
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
