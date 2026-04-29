<template>
    <div class="min-h-screen bg-slate-50">

        <!-- Page header -->
        <div class="bg-white border-b border-slate-200 px-6 py-4 print:hidden">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-bold text-slate-800">{{ t('supplierDue.title') }}</h1>
                    <p class="text-sm text-slate-500 mt-0.5">{{ t('supplierDue.subtitle') }}</p>
                </div>
                <button
                    v-if="rows.length"
                    @click="printReport"
                    class="flex items-center gap-2 px-4 py-2 text-sm font-medium text-slate-600 bg-white border border-slate-300 rounded-lg hover:bg-slate-50 transition-colors"
                >
                    <PrinterIcon class="w-4 h-4" />
                    {{ t('supplierDue.print') }}
                </button>
            </div>
        </div>

        <!-- KPI cards -->
        <div class="px-6 py-4 grid grid-cols-4 gap-4 print:hidden">
            <div class="bg-white rounded-xl border border-slate-200 p-4 flex items-center gap-4">
                <div class="w-10 h-10 rounded-lg bg-slate-100 flex items-center justify-center flex-shrink-0">
                    <BuildingStorefrontIcon class="w-5 h-5 text-slate-500" />
                </div>
                <div>
                    <p class="text-xs text-slate-500 font-medium">{{ t('supplierDue.kpiSuppliers') }}</p>
                    <p class="text-lg font-bold text-slate-800">{{ summary.total_suppliers }}</p>
                </div>
            </div>
            <div class="bg-white rounded-xl border border-slate-200 p-4 flex items-center gap-4">
                <div class="w-10 h-10 rounded-lg bg-red-50 flex items-center justify-center flex-shrink-0">
                    <ExclamationCircleIcon class="w-5 h-5 text-red-500" />
                </div>
                <div>
                    <p class="text-xs text-slate-500 font-medium">{{ t('supplierDue.kpiWithDue') }}</p>
                    <p class="text-lg font-bold text-red-600">{{ summary.suppliers_with_due }}</p>
                </div>
            </div>
            <div class="bg-white rounded-xl border border-slate-200 p-4 flex items-center gap-4">
                <div class="w-10 h-10 rounded-lg bg-orange-50 flex items-center justify-center flex-shrink-0">
                    <BanknotesIcon class="w-5 h-5 text-orange-500" />
                </div>
                <div>
                    <p class="text-xs text-slate-500 font-medium">{{ t('supplierDue.kpiTotalBill') }}</p>
                    <p class="text-lg font-bold text-slate-800">{{ fmtCurrency(summary.total_bill) }}</p>
                </div>
            </div>
            <div class="bg-white rounded-xl border border-slate-200 p-4 flex items-center gap-4">
                <div class="w-10 h-10 rounded-lg bg-amber-50 flex items-center justify-center flex-shrink-0">
                    <ArrowTrendingUpIcon class="w-5 h-5 text-amber-600" />
                </div>
                <div>
                    <p class="text-xs text-slate-500 font-medium">{{ t('supplierDue.kpiTotalDue') }}</p>
                    <p class="text-lg font-bold text-red-600">{{ fmtCurrency(summary.total_due) }}</p>
                </div>
            </div>
        </div>

        <!-- Filter + Table -->
        <div class="px-6 pb-6">
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm">

                <!-- Filter bar -->
                <div class="px-5 py-4 border-b border-slate-100 flex flex-wrap gap-3 items-end print:hidden">
                    <!-- Only with due toggle -->
                    <label class="flex items-center gap-2 cursor-pointer select-none">
                        <div
                            @click="filters.onlyWithDue = !filters.onlyWithDue"
                            :class="['relative w-9 h-5 rounded-full transition-colors cursor-pointer', filters.onlyWithDue ? 'bg-red-500' : 'bg-slate-200']"
                        >
                            <div :class="['absolute top-0.5 w-4 h-4 rounded-full bg-white shadow transition-transform', filters.onlyWithDue ? 'translate-x-4' : 'translate-x-0.5']"></div>
                        </div>
                        <span class="text-sm text-slate-600 font-medium">{{ t('supplierDue.onlyWithDue') }}</span>
                    </label>

                    <div class="flex-1 min-w-40">
                        <label class="text-xs font-medium text-slate-500 block mb-1">{{ t('supplierDue.supplier') }}</label>
                        <select v-model="filters.supplier_id" class="form-select text-sm py-1.5">
                            <option value="">{{ t('supplierDue.allSuppliers') }}</option>
                            <option v-for="s in allSuppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
                        </select>
                    </div>

                    <button
                        @click="loadReport"
                        :disabled="loading"
                        class="px-5 py-1.5 bg-orange-600 hover:bg-orange-700 disabled:opacity-60 text-white text-sm font-medium rounded-lg transition-colors"
                    >
                        {{ t('supplierDue.search') }}
                    </button>
                </div>

                <!-- Table -->
                <div class="overflow-x-auto" id="supplier-due-table">

                    <!-- Print header (hidden on screen) -->
                    <div class="hidden print:block px-6 py-4 border-b border-slate-200">
                        <h2 class="text-lg font-bold text-slate-800 text-center">{{ t('supplierDue.printHeader') }}</h2>
                        <p class="text-xs text-slate-500 text-center mt-1">{{ t('supplierDue.printDate') }}: {{ printDate }}</p>
                    </div>

                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-slate-100 bg-slate-50">
                                <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">#</th>
                                <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">{{ t('supplierDue.code') }}</th>
                                <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">{{ t('supplierDue.name') }}</th>
                                <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide hidden lg:table-cell">{{ t('supplierDue.contactPerson') }}</th>
                                <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide hidden lg:table-cell">{{ t('supplierDue.phone') }}</th>
                                <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide hidden xl:table-cell">{{ t('supplierDue.address') }}</th>
                                <th class="text-right px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">{{ t('supplierDue.billAmount') }}</th>
                                <th class="text-right px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">{{ t('supplierDue.paidAmount') }}</th>
                                <th class="text-right px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">{{ t('supplierDue.dueAmount') }}</th>
                                <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide print:hidden">{{ t('supplierDue.status') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template v-if="loading">
                                <tr>
                                    <td colspan="10" class="text-center py-12 text-slate-400">{{ t('common.loading') }}…</td>
                                </tr>
                            </template>
                            <template v-else-if="displayRows.length === 0">
                                <tr>
                                    <td colspan="10" class="text-center py-12 text-slate-400">{{ t('supplierDue.noResults') }}</td>
                                </tr>
                            </template>
                            <template v-else>
                                <tr
                                    v-for="(row, i) in displayRows"
                                    :key="row.id"
                                    :class="['border-b border-slate-50 transition-colors', row.due_amount > 0 ? 'hover:bg-red-50/30' : 'hover:bg-slate-50/40']"
                                >
                                    <td class="px-4 py-3 text-slate-400 text-xs">{{ i + 1 }}</td>
                                    <td class="px-4 py-3 font-mono text-xs text-slate-600">{{ row.code }}</td>
                                    <td class="px-4 py-3">
                                        <span class="font-semibold text-slate-800">{{ row.name }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-slate-500 hidden lg:table-cell">{{ row.contact_person || '—' }}</td>
                                    <td class="px-4 py-3 text-slate-500 hidden lg:table-cell">{{ row.phone || '—' }}</td>
                                    <td class="px-4 py-3 text-slate-500 text-xs hidden xl:table-cell max-w-xs truncate">{{ row.address || '—' }}</td>
                                    <td class="px-4 py-3 text-right text-slate-700">{{ fmtCurrency(row.bill_amount) }}</td>
                                    <td class="px-4 py-3 text-right text-orange-700 font-medium">{{ fmtCurrency(row.total_paid) }}</td>
                                    <td class="px-4 py-3 text-right font-bold" :class="row.due_amount > 0 ? 'text-red-600' : 'text-emerald-600'">
                                        {{ fmtCurrency(row.due_amount) }}
                                    </td>
                                    <td class="px-4 py-3 print:hidden">
                                        <span
                                            :class="[
                                                'inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium',
                                                row.due_amount > 0 ? 'bg-red-100 text-red-700' : 'bg-emerald-100 text-emerald-700'
                                            ]"
                                        >
                                            {{ row.due_amount > 0 ? t('supplierDue.statusDue') : t('supplierDue.statusClear') }}
                                        </span>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                        <tfoot v-if="displayRows.length">
                            <tr class="bg-slate-800 text-white font-semibold">
                                <td colspan="6" class="px-4 py-3 text-sm">{{ t('supplierDue.grandTotal') }}</td>
                                <td class="px-4 py-3 text-right">{{ fmtCurrency(displayRows.reduce((s, r) => s + r.bill_amount, 0)) }}</td>
                                <td class="px-4 py-3 text-right text-orange-300">{{ fmtCurrency(displayRows.reduce((s, r) => s + r.total_paid, 0)) }}</td>
                                <td class="px-4 py-3 text-right text-red-300">{{ fmtCurrency(displayRows.reduce((s, r) => s + r.due_amount, 0)) }}</td>
                                <td class="print:hidden"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import {
    PrinterIcon, BanknotesIcon, BuildingStorefrontIcon,
    ExclamationCircleIcon, ArrowTrendingUpIcon,
} from '@heroicons/vue/24/outline';
import { supplierService } from '@/services/supplierService';
import { useSettingsStore } from '@/stores/settings';
import { useCurrency } from '@/composables/useCurrency';

const { t }           = useI18n();
const settingsStore   = useSettingsStore();
const { fmtCurrency } = useCurrency();

// ── State ────────────────────────────────────────────────────────────────────
const rows         = ref([]);
const summary      = ref({ total_suppliers: 0, suppliers_with_due: 0, total_bill: 0, total_paid: 0, total_due: 0 });
const allSuppliers = ref([]);
const loading      = ref(false);

const filters = ref({
    supplier_id: '',
    onlyWithDue: true,
});

const printDate = new Date().toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' });

// ── Computed rows (apply onlyWithDue filter client-side) ─────────────────────
const displayRows = computed(() =>
    filters.value.onlyWithDue
        ? rows.value.filter(r => r.due_amount > 0)
        : rows.value
);

// ── Data loading ──────────────────────────────────────────────────────────────
async function loadSuppliers() {
    const { data } = await supplierService.all();
    allSuppliers.value = data.data ?? [];
}

async function loadReport() {
    loading.value = true;
    try {
        const params = {};
        if (filters.value.supplier_id) params.supplier_id = filters.value.supplier_id;
        const { data } = await supplierService.dueReport(params);
        rows.value    = data.data ?? [];
        summary.value = data.summary ?? {};
    } finally {
        loading.value = false;
    }
}

// ── Print ─────────────────────────────────────────────────────────────────────
function printReport() {
    const tableHtml = document.getElementById('supplier-due-table').innerHTML;
    const company   = settingsStore.settings?.company_name ?? 'POSmeister';
    const win       = window.open('', '_blank', 'width=1100,height=750');
    win.document.write(`<!DOCTYPE html>
<html>
<head>
<title>${t('supplierDue.title')} — ${company}</title>
<style>
  * { box-sizing: border-box; }
  body { font-family: Arial, sans-serif; font-size: 11px; color: #222; margin: 0; padding: 8px; }
  h2 { text-align: center; margin: 0 0 4px; font-size: 14px; }
  p  { margin: 0; text-align: center; color: #666; font-size: 10px; }
  table { width: 100%; border-collapse: collapse; margin-top: 10px; }
  th { background: #1e293b; color: #fff; font-size: 10px; text-transform: uppercase; padding: 6px 8px; }
  td { padding: 5px 8px; border-bottom: 1px solid #e2e8f0; font-size: 11px; }
  tfoot td { background: #1e293b; color: #fff; font-weight: bold; }
  .text-right { text-align: right; }
  .print\\:hidden { display: none; }
  @page { size: A4 landscape; margin: 8mm; }
</style>
</head>
<body>
${tableHtml}
<script>window.onload=()=>{window.print();window.close();}<\/script>
</body>
</html>`);
    win.document.close();
}

onMounted(async () => {
    await loadSuppliers();
    await loadReport();
});
</script>

<style scoped>
@reference '../../../css/app.css';
.form-select { @apply w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-orange-400 focus:border-orange-400 transition; }
@media print { .print\:hidden { display: none !important; } }
</style>
