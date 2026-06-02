<template>
    <div class="p-6 lg:p-8 space-y-5 max-w-7xl mx-auto">

        <header class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 tracking-tight">{{ t('accounting.ledger.title') }}</h1>
                <p class="mt-1 text-sm text-slate-500">{{ t('accounting.ledger.subtitle') }}</p>
            </div>
            <div class="flex items-end gap-2">
                <button @click="exportCsv" :disabled="!report" class="btn-soft">
                    <ArrowDownTrayIcon class="w-4 h-4" /> CSV
                </button>
                <button @click="print" :disabled="!report" class="btn-soft">
                    <PrinterIcon class="w-4 h-4" /> {{ t('common.print') }}
                </button>
            </div>
        </header>

        <div class="card grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
            <div class="md:col-span-2">
                <label class="lbl">{{ t('accounting.fields.account') }}</label>
                <select v-model="accountId" @change="load" class="ctrl w-full">
                    <option :value="null">{{ t('accounting.ledger.selectAccount') }}</option>
                    <option v-for="a in accounts" :key="a.id" :value="a.id">
                        {{ a.account_code }} — {{ a.account_name }}
                    </option>
                </select>
            </div>
            <div>
                <label class="lbl">{{ t('common.dateFrom') }}</label>
                <input v-model="from" @change="load" type="date" class="ctrl w-full" />
            </div>
            <div>
                <label class="lbl">{{ t('common.dateTo') }}</label>
                <input v-model="to" @change="load" type="date" class="ctrl w-full" />
            </div>
        </div>

        <div v-if="report" class="card grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
            <SumBox :label="t('accounting.ledger.opening')" :value="fmt(report.opening)" />
            <SumBox :label="t('accounting.fields.debit')"   :value="fmt(report.debit_total)"  tone="indigo" />
            <SumBox :label="t('accounting.fields.credit')"  :value="fmt(report.credit_total)" tone="rose" />
            <SumBox :label="t('accounting.ledger.closing')" :value="fmt(report.closing)"
                    :tone="(report.closing ?? 0) >= 0 ? 'emerald' : 'rose'" />
        </div>

        <div v-if="report" class="card overflow-hidden p-0">
            <table class="w-full text-sm">
                <thead class="bg-slate-50/70">
                    <tr class="text-left text-[11px] text-slate-500 uppercase tracking-wide border-b border-slate-100">
                        <th class="px-4 py-2.5">{{ t('accounting.fields.entryDate') }}</th>
                        <th class="px-4 py-2.5">{{ t('accounting.fields.entryNumber') }}</th>
                        <th class="px-4 py-2.5">{{ t('accounting.fields.narration') }}</th>
                        <th class="px-4 py-2.5 text-right">{{ t('accounting.fields.debit') }}</th>
                        <th class="px-4 py-2.5 text-right">{{ t('accounting.fields.credit') }}</th>
                        <th class="px-4 py-2.5 text-right">{{ t('accounting.fields.balance') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <tr v-for="l in report.lines" :key="l.id" class="hover:bg-slate-50/60">
                        <td class="px-4 py-2 font-mono text-xs text-slate-600">{{ formatDate(l.entry_date) }}</td>
                        <td class="px-4 py-2 font-mono text-xs text-slate-800">{{ l.entry_number }}</td>
                        <td class="px-4 py-2 text-slate-700">{{ l.narration }}</td>
                        <td class="px-4 py-2 text-right font-mono">{{ l.debit > 0 ? fmt(l.debit) : '' }}</td>
                        <td class="px-4 py-2 text-right font-mono">{{ l.credit > 0 ? fmt(l.credit) : '' }}</td>
                        <td class="px-4 py-2 text-right font-mono font-semibold">{{ fmt(l.running_balance) }}</td>
                    </tr>
                    <tr v-if="report.lines.length === 0">
                        <td colspan="6" class="py-10 text-center text-sm text-slate-400">{{ t('accounting.ledger.empty') }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<script setup>
import { ref, h, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { accountingReportService, chartOfAccountsService } from '@/services/accountingService';
import { useCurrency } from '@/composables/useCurrency';
import { ArrowDownTrayIcon, PrinterIcon } from '@heroicons/vue/24/outline';

const { t, locale } = useI18n();
const { fmtCurrency } = useCurrency();

const accounts = ref([]);
const accountId = ref(null);
const from = ref(new Date(new Date().getFullYear(), 0, 1).toISOString().slice(0, 10));
const to   = ref(new Date().toISOString().slice(0, 10));
const report = ref(null);

function fmt(v) { return v === null || v === undefined ? '' : fmtCurrency(v); }
function formatDate(d) {
    return new Intl.DateTimeFormat(locale.value || 'en-US', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(new Date(d));
}

const SumBox = (props) => {
    const palette = {
        emerald: 'border-emerald-200 bg-emerald-50/40',
        rose:    'border-rose-200 bg-rose-50/40',
        indigo:  'border-indigo-200 bg-indigo-50/40',
    }[props.tone] ?? 'border-slate-200 bg-slate-50/40';
    return h('div', { class: `border ${palette} rounded-lg px-3 py-2.5` }, [
        h('p', { class: 'text-[10px] uppercase tracking-wide text-slate-500 font-medium' }, props.label),
        h('p', { class: 'text-base font-bold text-slate-900 mt-1 font-mono' }, String(props.value)),
    ]);
};
SumBox.props = ['label', 'value', 'tone'];

async function load() {
    if (!accountId.value) { report.value = null; return; }
    const { data } = await accountingReportService.ledger(accountId.value, { from: from.value, to: to.value });
    report.value = data.data;
}

function exportCsv() {
    if (!accountId.value) return;
    const url = `/api/accounting/ledger/${accountId.value}?from=${from.value}&to=${to.value}&format=csv`;
    window.open(url, '_blank');
}

function print() { window.print(); }

onMounted(async () => {
    const { data } = await chartOfAccountsService.index({ active_only: 1 });
    accounts.value = data.data ?? [];
});
</script>

<style scoped>
@reference '../../../css/app.css';
.card     { @apply bg-white border border-slate-200 rounded-xl shadow-sm p-4; }
.btn-soft { @apply inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-slate-300 text-slate-700 text-sm hover:bg-slate-50 transition-colors disabled:opacity-50; }
.lbl      { @apply block text-xs font-medium text-slate-600 mb-1; }
.ctrl     { @apply px-3 py-2 text-sm border border-slate-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent; }
@media print {
    .btn-soft, .lbl, select, input { display: none !important; }
    .card { box-shadow: none; border: none; }
}
</style>
