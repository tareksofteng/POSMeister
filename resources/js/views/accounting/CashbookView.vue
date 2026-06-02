<template>
    <div class="p-6 lg:p-8 space-y-5 max-w-7xl mx-auto">

        <header class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 tracking-tight">{{ t('accounting.cashbook.title') }}</h1>
                <p class="mt-1 text-sm text-slate-500">{{ t('accounting.cashbook.subtitle') }}</p>
            </div>
            <div class="flex items-end gap-2">
                <button @click="print" :disabled="!report" class="btn-soft">
                    <PrinterIcon class="w-4 h-4" /> {{ t('common.print') }}
                </button>
            </div>
        </header>

        <div class="card grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
            <div class="md:col-span-2">
                <label class="lbl">{{ t('accounting.cashbook.account') }}</label>
                <select v-model="accountId" @change="load" class="ctrl w-full">
                    <option :value="null">{{ t('accounting.cashbook.selectAccount') }}</option>
                    <option v-for="a in cashAccounts" :key="a.id" :value="a.id">
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

        <div v-if="report" class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <div class="card !p-3">
                <p class="text-[10px] uppercase tracking-wide text-slate-500">{{ t('accounting.cashbook.opening') }}</p>
                <p class="text-base font-bold font-mono text-slate-900 mt-1">{{ fmt(report.opening) }}</p>
            </div>
            <div class="card !p-3">
                <p class="text-[10px] uppercase tracking-wide text-emerald-600">{{ t('accounting.cashbook.cashIn') }}</p>
                <p class="text-base font-bold font-mono text-emerald-700 mt-1">{{ fmt(totalIn) }}</p>
            </div>
            <div class="card !p-3">
                <p class="text-[10px] uppercase tracking-wide text-rose-600">{{ t('accounting.cashbook.cashOut') }}</p>
                <p class="text-base font-bold font-mono text-rose-700 mt-1">{{ fmt(totalOut) }}</p>
            </div>
            <div class="card !p-3">
                <p class="text-[10px] uppercase tracking-wide text-slate-500">{{ t('accounting.cashbook.closing') }}</p>
                <p class="text-base font-bold font-mono text-slate-900 mt-1">{{ fmt(report.closing) }}</p>
            </div>
        </div>

        <div v-if="report" class="card overflow-hidden p-0">
            <table class="w-full text-sm">
                <thead class="bg-slate-50/70">
                    <tr class="text-left text-[11px] text-slate-500 uppercase tracking-wide border-b border-slate-100">
                        <th class="px-4 py-2.5">{{ t('accounting.fields.entryDate') }}</th>
                        <th class="px-4 py-2.5 text-right">{{ t('accounting.cashbook.cashIn') }}</th>
                        <th class="px-4 py-2.5 text-right">{{ t('accounting.cashbook.cashOut') }}</th>
                        <th class="px-4 py-2.5 text-right">{{ t('accounting.cashbook.closing') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <tr v-for="d in report.days" :key="d.date" class="hover:bg-slate-50/60">
                        <td class="px-4 py-2 font-mono text-xs text-slate-700">{{ formatDate(d.date) }}</td>
                        <td class="px-4 py-2 text-right font-mono text-emerald-700">{{ d.cash_in > 0 ? fmt(d.cash_in) : '' }}</td>
                        <td class="px-4 py-2 text-right font-mono text-rose-700">{{ d.cash_out > 0 ? fmt(d.cash_out) : '' }}</td>
                        <td class="px-4 py-2 text-right font-mono font-semibold">{{ fmt(d.closing) }}</td>
                    </tr>
                    <tr v-if="report.days.length === 0">
                        <td colspan="4" class="py-10 text-center text-sm text-slate-400">{{ t('accounting.cashbook.empty') }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { accountingReportService, chartOfAccountsService } from '@/services/accountingService';
import { useCurrency } from '@/composables/useCurrency';
import { PrinterIcon } from '@heroicons/vue/24/outline';

const { t, locale } = useI18n();
const { fmtCurrency } = useCurrency();

const cashAccounts = ref([]);
const accountId = ref(null);
const from = ref(new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().slice(0, 10));
const to   = ref(new Date().toISOString().slice(0, 10));
const report = ref(null);

const totalIn  = computed(() => (report.value?.days ?? []).reduce((s, d) => s + (d.cash_in  || 0), 0));
const totalOut = computed(() => (report.value?.days ?? []).reduce((s, d) => s + (d.cash_out || 0), 0));

function fmt(v) { return v === null || v === undefined ? '' : fmtCurrency(v); }
function formatDate(d) {
    return new Intl.DateTimeFormat(locale.value || 'en-US', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(new Date(d));
}

async function load() {
    if (!accountId.value) { report.value = null; return; }
    const { data } = await accountingReportService.cashbook(accountId.value, { from: from.value, to: to.value });
    report.value = data.data;
}

function print() { window.print(); }

onMounted(async () => {
    const { data } = await chartOfAccountsService.index({ active_only: 1, type: 'asset' });
    // Cash + bank accounts only (1xxx range starting with 10/11)
    cashAccounts.value = (data.data ?? []).filter(a =>
        a.account_code.startsWith('10') || a.account_code.startsWith('11')
    );
    if (cashAccounts.value.length > 0) {
        accountId.value = cashAccounts.value[0].id;
        load();
    }
});
</script>

<style scoped>
@reference '../../../css/app.css';
.card     { @apply bg-white border border-slate-200 rounded-xl shadow-sm p-4; }
.btn-soft { @apply inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-slate-300 text-slate-700 text-sm hover:bg-slate-50 transition-colors disabled:opacity-50; }
.lbl      { @apply block text-xs font-medium text-slate-600 mb-1; }
.ctrl     { @apply px-3 py-2 text-sm border border-slate-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent; }
@media print {
    .btn-soft, .lbl, input, select { display: none !important; }
    .card { box-shadow: none; border: none; }
}
</style>
