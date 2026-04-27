<template>
    <div class="p-6 lg:p-8 space-y-5 print:p-0 print:space-y-3">

        <!-- ── Page header ──────────────────────────────────────────────── -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 print:hidden">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">{{ t('saleRecord.title') }}</h1>
                <p class="mt-0.5 text-sm text-gray-500">{{ t('saleRecord.subtitle') }}</p>
            </div>
            <button
                v-if="sales.length"
                @click="printReport"
                class="inline-flex items-center gap-2 px-4 py-2 bg-gray-800 hover:bg-gray-700 text-white text-sm font-medium rounded-lg transition-colors shadow-sm"
            >
                <PrinterIcon class="w-4 h-4" />
                {{ t('saleRecord.print') }}
            </button>
        </div>

        <!-- ── Filter bar ────────────────────────────────────────────────── -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 print:hidden">
            <div class="flex flex-wrap items-end gap-3">

                <!-- Von -->
                <div>
                    <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-1">
                        {{ t('saleRecord.from') }}
                    </label>
                    <input v-model="filters.date_from" type="date"
                        class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white" />
                </div>

                <!-- Bis -->
                <div>
                    <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-1">
                        {{ t('saleRecord.to') }}
                    </label>
                    <input v-model="filters.date_to" type="date"
                        class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white" />
                </div>

                <!-- Kunde -->
                <div>
                    <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-1">
                        {{ t('saleRecord.customer') }}
                    </label>
                    <select v-model="filters.customer_id"
                        class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white min-w-[180px]">
                        <option value="">{{ t('saleRecord.allCustomers') }}</option>
                        <option v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                </div>

                <!-- Verkaufstyp -->
                <div>
                    <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-1">
                        {{ t('saleRecord.saleType') }}
                    </label>
                    <select v-model="filters.sale_type"
                        class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white">
                        <option value="">{{ t('saleRecord.allTypes') }}</option>
                        <option value="retail">{{ t('saleRecord.retail') }}</option>
                        <option value="wholesale">{{ t('saleRecord.wholesale') }}</option>
                    </select>
                </div>

                <!-- Status -->
                <div>
                    <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-1">
                        {{ t('common.status') }}
                    </label>
                    <select v-model="filters.status"
                        class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white">
                        <option value="">{{ t('common.allStatuses') }}</option>
                        <option value="active">{{ t('sales.statusActive') }}</option>
                        <option value="cancelled">{{ t('sales.statusCancelled') }}</option>
                    </select>
                </div>

                <!-- Search button -->
                <button @click="loadRecord"
                    :disabled="loading"
                    class="inline-flex items-center gap-2 px-5 py-1.5 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white text-sm font-semibold rounded-lg transition-colors shadow-sm">
                    <svg v-if="loading" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                    <MagnifyingGlassIcon v-else class="w-3.5 h-3.5" />
                    {{ t('saleRecord.search') }}
                </button>

                <!-- Details toggle -->
                <label v-if="sales.length" class="ml-auto inline-flex items-center gap-2 cursor-pointer select-none">
                    <div class="relative">
                        <input type="checkbox" v-model="showAllDetails" class="sr-only peer" />
                        <div class="w-9 h-5 bg-gray-200 peer-checked:bg-indigo-600 rounded-full transition-colors"></div>
                        <div class="absolute top-0.5 left-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform peer-checked:translate-x-4"></div>
                    </div>
                    <span class="text-sm text-gray-600">{{ t('saleRecord.withDetails') }}</span>
                </label>

            </div>
        </div>

        <!-- ── KPI cards ─────────────────────────────────────────────────── -->
        <div v-if="summary" class="grid grid-cols-2 lg:grid-cols-4 gap-3">
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-4 py-3">
                <p class="text-xs text-gray-500 font-medium">{{ t('saleRecord.totalOrders') }}</p>
                <p class="text-2xl font-bold text-gray-900 mt-0.5">{{ summary.count }}</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-4 py-3">
                <p class="text-xs text-gray-500 font-medium">{{ t('sales.vat') }}</p>
                <p class="text-2xl font-bold text-gray-900 mt-0.5 tabular-nums">{{ fmt(summary.vat_amount) }}</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-4 py-3">
                <p class="text-xs text-gray-500 font-medium">{{ t('saleRecord.totalPaid') }}</p>
                <p class="text-2xl font-bold text-emerald-600 mt-0.5 tabular-nums">{{ fmt(summary.total_paid) }}</p>
            </div>
            <div class="bg-indigo-600 rounded-xl shadow-sm px-4 py-3">
                <p class="text-xs text-indigo-200 font-medium">{{ t('sales.grandTotal') }}</p>
                <p class="text-2xl font-bold text-white mt-0.5 tabular-nums">{{ fmt(summary.grand_total) }}</p>
            </div>
        </div>

        <!-- ── Print header (hidden on screen) ───────────────────────────── -->
        <div class="hidden print:block mb-4">
            <h2 class="text-xl font-bold text-gray-900">{{ t('saleRecord.title') }}</h2>
            <p class="text-sm text-gray-600">
                {{ t('saleRecord.from') }}: {{ filters.date_from }} &nbsp;·&nbsp;
                {{ t('saleRecord.to') }}: {{ filters.date_to }}
                <span v-if="customerName"> &nbsp;·&nbsp; {{ customerName }}</span>
            </p>
        </div>

        <!-- ── Empty / loading state ─────────────────────────────────────── -->
        <div v-if="!sales.length && !loading && searched"
            class="bg-white rounded-xl border border-gray-200 shadow-sm py-14 text-center">
            <ClipboardDocumentListIcon class="w-12 h-12 text-gray-200 mx-auto mb-3" />
            <p class="text-gray-500 text-sm">{{ t('saleRecord.noResults') }}</p>
        </div>

        <!-- ── Main table ─────────────────────────────────────────────────── -->
        <div v-if="sales.length" class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden" id="printArea">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">

                    <!-- Table head -->
                    <thead>
                        <tr class="bg-indigo-600 text-white text-xs uppercase tracking-wide">
                            <th class="px-3 py-2.5 w-8 print:hidden"></th>
                            <th class="px-3 py-2.5 text-left">{{ t('sales.number') }}</th>
                            <th class="px-3 py-2.5 text-left">{{ t('sales.date') }}</th>
                            <th class="px-3 py-2.5 text-left">{{ t('customers.title') }}</th>
                            <th class="px-3 py-2.5 text-center">{{ t('saleRecord.saleType') }}</th>
                            <th class="px-3 py-2.5 text-right w-16">{{ t('saleRecord.items') }}</th>
                            <th class="px-3 py-2.5 text-right">{{ t('sales.subtotal') }}</th>
                            <th class="px-3 py-2.5 text-right">{{ t('sales.vat') }}</th>
                            <th class="px-3 py-2.5 text-right">{{ t('sales.discount') }}</th>
                            <th class="px-3 py-2.5 text-right font-bold">{{ t('sales.grandTotal') }}</th>
                            <th class="px-3 py-2.5 text-right">{{ t('saleRecord.totalPaid') }}</th>
                            <th class="px-3 py-2.5 text-right">{{ t('saleRecord.due') }}</th>
                            <th class="px-3 py-2.5 text-center w-24">{{ t('common.status') }}</th>
                            <th class="px-3 py-2.5 text-center w-16 print:hidden">{{ t('common.actions') }}</th>
                        </tr>
                    </thead>

                    <tbody>
                        <template v-for="(s, idx) in sales" :key="s.id">

                            <!-- ── Master row ── -->
                            <tr
                                @click="toggle(s.id)"
                                :class="[
                                    'border-b border-gray-100 cursor-pointer transition-colors',
                                    isExpanded(s.id) ? 'bg-indigo-50/60' : 'hover:bg-gray-50/70',
                                    idx % 2 === 1 && !isExpanded(s.id) ? 'bg-gray-50/40' : '',
                                ]"
                            >
                                <!-- Expand chevron -->
                                <td class="px-3 py-2.5 text-center print:hidden">
                                    <ChevronDownIcon
                                        :class="['w-3.5 h-3.5 text-gray-400 transition-transform duration-200', isExpanded(s.id) ? 'rotate-180 text-indigo-500' : '']"
                                    />
                                </td>
                                <td class="px-3 py-2.5 font-mono font-semibold text-indigo-700 whitespace-nowrap">
                                    {{ s.sale_number }}
                                </td>
                                <td class="px-3 py-2.5 text-gray-600 whitespace-nowrap">{{ fmtDate(s.sale_date) }}</td>
                                <td class="px-3 py-2.5 text-gray-800 font-medium">{{ s.customer_name ?? '—' }}</td>
                                <td class="px-3 py-2.5 text-center">
                                    <span :class="[
                                        'inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium',
                                        s.sale_type === 'wholesale'
                                            ? 'bg-violet-100 text-violet-700'
                                            : 'bg-sky-100 text-sky-700',
                                    ]">
                                        {{ s.sale_type === 'wholesale' ? t('saleRecord.wholesale') : t('saleRecord.retail') }}
                                    </span>
                                </td>
                                <td class="px-3 py-2.5 text-right text-gray-500">{{ s.items?.length ?? 0 }}</td>
                                <td class="px-3 py-2.5 text-right tabular-nums text-gray-700">{{ fmt(s.subtotal) }}</td>
                                <td class="px-3 py-2.5 text-right tabular-nums text-gray-700">{{ fmt(s.vat_amount) }}</td>
                                <td class="px-3 py-2.5 text-right tabular-nums text-gray-500">{{ s.discount_amount > 0 ? fmt(s.discount_amount) : '—' }}</td>
                                <td class="px-3 py-2.5 text-right tabular-nums font-bold text-gray-900">{{ fmt(s.grand_total) }}</td>
                                <td class="px-3 py-2.5 text-right tabular-nums text-emerald-600 font-medium">{{ fmt(s.total_paid) }}</td>
                                <td class="px-3 py-2.5 text-right tabular-nums"
                                    :class="s.due_amount > 0 ? 'text-red-500 font-medium' : 'text-gray-400'">
                                    {{ s.due_amount > 0 ? fmt(s.due_amount) : '—' }}
                                </td>
                                <td class="px-3 py-2.5 text-center">
                                    <span :class="[
                                        'inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold',
                                        s.status === 'active'
                                            ? 'bg-emerald-100 text-emerald-700'
                                            : 'bg-red-100 text-red-600',
                                    ]">
                                        {{ s.status === 'active' ? t('sales.statusActive') : t('sales.statusCancelled') }}
                                    </span>
                                </td>
                                <td class="px-3 py-2.5 text-center print:hidden" @click.stop>
                                    <a :href="`/sales/${s.id}/invoice`" target="_blank"
                                        class="inline-flex items-center justify-center w-7 h-7 rounded-lg text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 transition-colors"
                                        :title="t('sales.viewInvoice')"
                                    >
                                        <DocumentTextIcon class="w-4 h-4" />
                                    </a>
                                </td>
                            </tr>

                            <!-- ── Detail rows (items) ── -->
                            <tr v-if="isExpanded(s.id) && s.items?.length" class="border-b border-indigo-100/60">
                                <td colspan="14" class="p-0">
                                    <div class="bg-indigo-50/40 border-t border-indigo-100/80">
                                        <table class="w-full text-xs">
                                            <thead>
                                                <tr class="bg-indigo-100/60 text-indigo-700 uppercase tracking-wide">
                                                    <th class="px-6 py-1.5 text-left w-8 print:hidden">#</th>
                                                    <th class="px-4 py-1.5 text-left">{{ t('saleRecord.product') }}</th>
                                                    <th class="px-4 py-1.5 text-center w-20">{{ t('sales.unit') }}</th>
                                                    <th class="px-4 py-1.5 text-right w-20">{{ t('sales.qty') }}</th>
                                                    <th class="px-4 py-1.5 text-right w-28">{{ t('sales.unitPrice') }}</th>
                                                    <th class="px-4 py-1.5 text-right w-16">{{ t('sales.vatRate') }}</th>
                                                    <th class="px-4 py-1.5 text-right w-28">{{ t('sales.vat') }}</th>
                                                    <th class="px-4 py-1.5 text-right w-28 font-bold">{{ t('sales.lineTotal') }}</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-indigo-100/50">
                                                <tr v-for="(item, i) in s.items" :key="item.id"
                                                    class="hover:bg-indigo-100/30 transition-colors">
                                                    <td class="px-6 py-1.5 text-gray-400 print:hidden">{{ i + 1 }}</td>
                                                    <td class="px-4 py-1.5">
                                                        <p class="font-semibold text-gray-800">{{ item.name }}</p>
                                                        <p class="text-gray-400 font-mono text-[11px]">{{ item.sku }}</p>
                                                    </td>
                                                    <td class="px-4 py-1.5 text-center text-gray-600">{{ item.unit_symbol ?? '—' }}</td>
                                                    <td class="px-4 py-1.5 text-right tabular-nums text-gray-700 font-medium">{{ item.quantity }}</td>
                                                    <td class="px-4 py-1.5 text-right tabular-nums text-gray-700">{{ fmt(item.unit_price) }}</td>
                                                    <td class="px-4 py-1.5 text-right tabular-nums text-gray-500">{{ item.tax_rate }}%</td>
                                                    <td class="px-4 py-1.5 text-right tabular-nums text-gray-500">{{ fmt(item.vat_amount) }}</td>
                                                    <td class="px-4 py-1.5 text-right tabular-nums font-bold text-gray-900">{{ fmt(item.line_total) }}</td>
                                                </tr>
                                            </tbody>
                                            <!-- Item subtotal -->
                                            <tfoot>
                                                <tr class="bg-indigo-100/40 font-semibold text-indigo-800">
                                                    <td colspan="3" class="px-4 py-1.5 text-right text-xs uppercase tracking-wide">
                                                        {{ t('sales.subtotal') }}
                                                    </td>
                                                    <td class="px-4 py-1.5 text-right tabular-nums">
                                                        {{ s.items.reduce((acc, i) => acc + i.quantity, 0) }}
                                                    </td>
                                                    <td class="px-4 py-1.5 text-right tabular-nums">{{ fmt(s.subtotal) }}</td>
                                                    <td></td>
                                                    <td class="px-4 py-1.5 text-right tabular-nums">{{ fmt(s.vat_amount) }}</td>
                                                    <td class="px-4 py-1.5 text-right tabular-nums">{{ fmt(s.grand_total) }}</td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </td>
                            </tr>

                        </template>
                    </tbody>

                    <!-- ── Footer totals row ── -->
                    <tfoot v-if="summary">
                        <tr class="bg-gray-800 text-white font-bold text-sm border-t-2 border-gray-700">
                            <td class="px-3 py-3 print:hidden"></td>
                            <td colspan="3" class="px-3 py-3 text-right text-xs uppercase tracking-widest text-gray-300">
                                {{ t('saleRecord.grandTotalRow') }} ({{ summary.count }})
                            </td>
                            <td class="px-3 py-3"></td>
                            <td class="px-3 py-3 text-right tabular-nums">
                                {{ sales.reduce((acc, s) => acc + (s.items?.length ?? 0), 0) }}
                            </td>
                            <td class="px-3 py-3 text-right tabular-nums">{{ fmt(summary.subtotal) }}</td>
                            <td class="px-3 py-3 text-right tabular-nums">{{ fmt(summary.vat_amount) }}</td>
                            <td class="px-3 py-3 text-right tabular-nums">{{ fmt(summary.discount_amount) }}</td>
                            <td class="px-3 py-3 text-right tabular-nums text-indigo-300 text-base">{{ fmt(summary.grand_total) }}</td>
                            <td class="px-3 py-3 text-right tabular-nums text-emerald-300">{{ fmt(summary.total_paid) }}</td>
                            <td class="px-3 py-3 text-right tabular-nums text-red-300">{{ fmt(summary.due_amount) }}</td>
                            <td colspan="2" class="print:hidden"></td>
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

// ── Filters ───────────────────────────────────────────────────────────────
const today        = new Date().toISOString().substring(0, 10);
const firstOfMonth = new Date(new Date().getFullYear(), new Date().getMonth(), 1)
    .toISOString().substring(0, 10);

const filters = ref({
    date_from:   firstOfMonth,
    date_to:     today,
    customer_id: '',
    sale_type:   '',
    status:      '',
});

const customers = ref([]);
onMounted(async () => {
    try {
        const { data } = await customerService.all();
        customers.value = data.data ?? [];
    } catch { /* non-critical */ }

    loadRecord();
});

// ── Data ──────────────────────────────────────────────────────────────────
const sales         = ref([]);
const summary       = ref(null);
const loading       = ref(false);
const searched      = ref(false);
const showAllDetails = ref(false);
const expandedIds   = ref(new Set());

const customerName = computed(() => {
    if (!filters.value.customer_id) return '';
    const c = customers.value.find(x => x.id === filters.value.customer_id);
    return c ? c.name : '';
});

async function loadRecord() {
    loading.value  = true;
    searched.value = true;
    try {
        const { data } = await saleService.record(filters.value);
        sales.value         = data.data    ?? [];
        summary.value       = data.summary ?? null;
        expandedIds.value   = new Set();
        if (showAllDetails.value) expandAll();
    } catch (err) {
        console.error(err);
    } finally {
        loading.value = false;
    }
}

// ── Expand / collapse ─────────────────────────────────────────────────────
function isExpanded(id) { return expandedIds.value.has(id); }
function toggle(id) {
    const s = new Set(expandedIds.value);
    s.has(id) ? s.delete(id) : s.add(id);
    expandedIds.value = s;
}
function expandAll()   { expandedIds.value = new Set(sales.value.map(s => s.id)); }
function collapseAll() { expandedIds.value = new Set(); }

watch(showAllDetails, (val) => { val ? expandAll() : collapseAll(); });

// ── Formatting ────────────────────────────────────────────────────────────
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

// ── Print ─────────────────────────────────────────────────────────────────
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
