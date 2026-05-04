<template>
    <div class="ledger-page">
        <header class="ledger-header print:hidden">
            <div>
                <h1>{{ t('supplierLedger.title') }}</h1>
                <p class="text-sm text-slate-500">{{ t('supplierLedger.subtitle') }}</p>
            </div>
            <button v-if="report" @click="printPage" class="btn-ghost">
                <PrinterIcon class="w-4 h-4" />
                {{ t('common.print') }}
            </button>
        </header>

        <section class="filter-bar print:hidden">
            <div class="field">
                <label>{{ t('supplierLedger.supplier') }}</label>
                <select v-model.number="form.supplier_id" class="control">
                    <option :value="null">{{ t('supplierLedger.selectSupplier') }}</option>
                    <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}<span v-if="s.code"> ({{ s.code }})</span></option>
                </select>
            </div>
            <div class="field">
                <label>{{ t('common.dateFrom') }}</label>
                <input v-model="form.from" type="date" class="control" />
            </div>
            <div class="field">
                <label>{{ t('common.dateTo') }}</label>
                <input v-model="form.to" type="date" class="control" />
            </div>
            <button @click="generate" :disabled="loading || !form.supplier_id" class="btn-primary self-end">
                <ArrowPathIcon v-if="loading" class="w-4 h-4 animate-spin" />
                <DocumentMagnifyingGlassIcon v-else class="w-4 h-4" />
                {{ loading ? t('common.loading') : t('supplierLedger.generate') }}
            </button>
        </section>

        <div v-if="error" class="alert-error print:hidden">{{ error }}</div>

        <div v-if="!report && !loading" class="empty-state print:hidden">
            <BuildingStorefrontIcon class="w-10 h-10 text-slate-300" />
            <p class="text-sm text-slate-500">{{ t('supplierLedger.empty') }}</p>
        </div>

        <article v-if="report" class="ledger-paper" id="ledger-paper">
            <div class="paper-head">
                <div>
                    <p class="paper-eyebrow">{{ t('supplierLedger.documentTitle') }}</p>
                    <h2>{{ report.supplier.name }}</h2>
                    <p class="text-xs text-slate-500" v-if="report.supplier.code">{{ report.supplier.code }}</p>
                    <p class="text-xs text-slate-500 mt-2" v-if="report.supplier.contact_person">{{ report.supplier.contact_person }}</p>
                    <p class="text-xs text-slate-500" v-if="report.supplier.address">{{ report.supplier.address }}</p>
                    <p class="text-xs text-slate-500" v-if="report.supplier.phone">{{ report.supplier.phone }}</p>
                </div>
                <div class="text-right">
                    <p class="paper-eyebrow">{{ t('supplierLedger.period') }}</p>
                    <p class="text-sm font-medium text-slate-700">{{ formatPeriod(report.period) }}</p>
                    <p class="text-xs text-slate-400 mt-2">{{ t('common.printDate') }}: {{ printedAt }}</p>
                    <p class="text-xs text-slate-400" v-if="settings?.company_name">{{ settings.company_name }}</p>
                </div>
            </div>

            <div class="summary-grid">
                <div class="summary-card">
                    <span class="label">{{ t('supplierLedger.opening') }}</span>
                    <span class="value">{{ fmtCurrency(report.opening) }}</span>
                </div>
                <div class="summary-card">
                    <span class="label">{{ t('supplierLedger.totalDebit') }}</span>
                    <span class="value text-emerald-700">{{ fmtCurrency(report.totals.debit) }}</span>
                </div>
                <div class="summary-card">
                    <span class="label">{{ t('supplierLedger.totalCredit') }}</span>
                    <span class="value text-slate-700">{{ fmtCurrency(report.totals.credit) }}</span>
                </div>
                <div class="summary-card highlight">
                    <span class="label">{{ t('supplierLedger.closing') }}</span>
                    <span class="value" :class="report.closing > 0 ? 'text-rose-700' : 'text-emerald-700'">{{ fmtCurrency(report.closing) }}</span>
                </div>
            </div>

            <table class="ledger-table">
                <thead>
                    <tr>
                        <th class="w-24">{{ t('common.date') }}</th>
                        <th class="w-28">{{ t('supplierLedger.type') }}</th>
                        <th class="w-36">{{ t('supplierLedger.reference') }}</th>
                        <th>{{ t('supplierLedger.description') }}</th>
                        <th class="num">{{ t('supplierLedger.debit') }}</th>
                        <th class="num">{{ t('supplierLedger.credit') }}</th>
                        <th class="num">{{ t('supplierLedger.balance') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="opening-row">
                        <td colspan="6">{{ t('supplierLedger.openingBalance') }}</td>
                        <td class="num">{{ fmtCurrency(report.opening) }}</td>
                    </tr>
                    <tr v-for="(row, i) in report.rows" :key="i">
                        <td>{{ formatDate(row.date) }}</td>
                        <td>
                            <span class="type-pill" :class="typeClass(row.type)">{{ t('supplierLedger.type_' + row.type) }}</span>
                        </td>
                        <td class="font-mono text-xs">{{ row.reference || '-' }}</td>
                        <td class="text-slate-600">{{ describe(row) }}</td>
                        <td class="num text-emerald-700">{{ row.debit ? fmtCurrency(row.debit) : '' }}</td>
                        <td class="num">{{ row.credit ? fmtCurrency(row.credit) : '' }}</td>
                        <td class="num font-medium">{{ fmtCurrency(row.balance) }}</td>
                    </tr>
                    <tr v-if="report.rows.length === 0">
                        <td colspan="7" class="py-8 text-center text-slate-400">{{ t('supplierLedger.noMovements') }}</td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4">{{ t('supplierLedger.totals') }}</td>
                        <td class="num text-emerald-700">{{ fmtCurrency(report.totals.debit) }}</td>
                        <td class="num">{{ fmtCurrency(report.totals.credit) }}</td>
                        <td class="num font-bold" :class="report.closing > 0 ? 'text-rose-700' : 'text-emerald-700'">{{ fmtCurrency(report.closing) }}</td>
                    </tr>
                </tfoot>
            </table>
        </article>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { reportService } from '@/services/reportService';
import { supplierService } from '@/services/supplierService';
import { useSettingsStore } from '@/stores/settings';
import { useCurrency } from '@/composables/useCurrency';
import {
    PrinterIcon, ArrowPathIcon, DocumentMagnifyingGlassIcon, BuildingStorefrontIcon,
} from '@heroicons/vue/24/outline';

const { t } = useI18n();
const { fmtCurrency } = useCurrency();
const settingsStore = useSettingsStore();
const settings = computed(() => settingsStore.settings);

const suppliers = ref([]);
const report    = ref(null);
const loading   = ref(false);
const error     = ref('');

const form = ref({
    supplier_id: null,
    from: defaultFrom(),
    to:   new Date().toISOString().slice(0, 10),
});

function defaultFrom() {
    const d = new Date();
    d.setMonth(d.getMonth() - 1);
    return d.toISOString().slice(0, 10);
}

const printedAt = computed(() => new Date().toLocaleString('de-DE', {
    day: '2-digit', month: '2-digit', year: 'numeric',
    hour: '2-digit', minute: '2-digit',
}));

function formatDate(s) {
    return s ? new Date(s + 'T00:00:00').toLocaleDateString('de-DE') : '';
}

function formatPeriod({ from, to }) {
    if (!from && !to) return t('common.allTime');
    if (!from) return `${t('common.until')} ${formatDate(to)}`;
    if (!to)   return `${t('common.from')} ${formatDate(from)}`;
    return `${formatDate(from)} – ${formatDate(to)}`;
}

function describe(row) {
    if (row.note)   return row.note;
    if (row.method) return t('paymentMethod.' + row.method);
    return '';
}

function typeClass(type) {
    return {
        purchase: 'bg-slate-100 text-slate-700',
        payment:  'bg-emerald-100 text-emerald-700',
        return:   'bg-amber-100 text-amber-700',
    }[type] || 'bg-slate-100 text-slate-600';
}

function printPage() {
    window.print();
}

async function generate() {
    if (!form.value.supplier_id) return;
    loading.value = true;
    error.value = '';
    try {
        const { data } = await reportService.supplierLedger({
            supplier_id: form.value.supplier_id,
            from: form.value.from || undefined,
            to:   form.value.to   || undefined,
        });
        report.value = data;
    } catch (err) {
        error.value = err.response?.data?.message || t('common.unexpectedError');
    } finally {
        loading.value = false;
    }
}

onMounted(async () => {
    try {
        const { data } = await supplierService.all();
        suppliers.value = data.data ?? [];
    } catch {
        suppliers.value = [];
    }
});
</script>

<style scoped>
@reference '../../../css/app.css';

.ledger-page { @apply p-6 lg:p-8 space-y-6 max-w-7xl mx-auto; }
.ledger-header { @apply flex items-start justify-between gap-4; }
.ledger-header h1 { @apply text-2xl font-bold text-slate-900 tracking-tight; }

.filter-bar { @apply flex flex-wrap gap-3 items-end bg-white border border-slate-200 rounded-xl p-4 shadow-sm; }
.field { @apply flex flex-col gap-1.5 flex-1 min-w-44; }
.field label { @apply text-xs font-medium text-slate-600; }
.control { @apply rounded-lg border border-slate-300 px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent; }

.btn-primary { @apply inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold disabled:opacity-50 disabled:cursor-not-allowed transition-colors; }
.btn-ghost   { @apply inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-slate-300 text-slate-700 text-sm hover:bg-slate-50 transition-colors; }

.alert-error { @apply rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700; }
.empty-state { @apply flex flex-col items-center justify-center gap-3 py-16 bg-white border border-dashed border-slate-200 rounded-xl; }

.ledger-paper { @apply bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden; }
.paper-head { @apply flex items-start justify-between gap-6 px-8 py-6 border-b border-slate-100; }
.paper-head h2 { @apply text-xl font-semibold text-slate-900 mt-1; }
.paper-eyebrow { @apply text-[11px] font-semibold uppercase tracking-wider text-indigo-600; }

.summary-grid { @apply grid grid-cols-2 lg:grid-cols-4 gap-3 px-8 py-5 bg-slate-50/60 border-b border-slate-100 print:bg-white; }
.summary-card { @apply flex flex-col gap-1 bg-white rounded-lg border border-slate-200 px-4 py-3; }
.summary-card.highlight { @apply border-indigo-200 bg-indigo-50/50; }
.summary-card .label { @apply text-[11px] uppercase tracking-wide text-slate-500 font-medium; }
.summary-card .value { @apply text-base font-bold text-slate-900 font-mono tabular-nums; }

.ledger-table { @apply w-full text-sm; }
.ledger-table thead { @apply bg-slate-50; }
.ledger-table th { @apply px-4 py-2.5 text-left text-[11px] font-semibold text-slate-500 uppercase tracking-wide; }
.ledger-table th.num { @apply text-right; }
.ledger-table tbody td { @apply px-4 py-2.5 border-t border-slate-100; }
.ledger-table td.num { @apply text-right font-mono tabular-nums; }
.ledger-table tr.opening-row td { @apply bg-slate-50/70 text-xs font-semibold text-slate-600 italic; }
.ledger-table tfoot td { @apply px-4 py-3 border-t-2 border-slate-300 bg-slate-50 text-sm font-semibold text-slate-800; }
.ledger-table tfoot td.num { @apply text-right font-mono; }

.type-pill { @apply inline-block px-2 py-0.5 rounded-full text-[11px] font-medium; }

@media print {
    @page { size: A4 landscape; margin: 10mm; }
    body { background: white !important; }
    .ledger-page { @apply p-0 max-w-none; }
    .ledger-paper { @apply shadow-none border-0 rounded-none; }
}
</style>
