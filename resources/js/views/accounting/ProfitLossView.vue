<template>
    <div class="p-6 lg:p-8 space-y-5 max-w-7xl mx-auto">

        <header class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 tracking-tight">{{ t('accounting.pl.title') }}</h1>
                <p class="mt-1 text-sm text-slate-500">{{ t('accounting.pl.subtitle') }}</p>
            </div>
            <div class="flex items-end gap-2">
                <div>
                    <label class="lbl">{{ t('common.dateFrom') }}</label>
                    <input v-model="from" @change="load" type="date" class="ctrl w-40" />
                </div>
                <div>
                    <label class="lbl">{{ t('common.dateTo') }}</label>
                    <input v-model="to" @change="load" type="date" class="ctrl w-40" />
                </div>
                <button @click="exportCsv" :disabled="!report" class="btn-soft">
                    <ArrowDownTrayIcon class="w-4 h-4" /> CSV
                </button>
                <button @click="print" :disabled="!report" class="btn-soft">
                    <PrinterIcon class="w-4 h-4" /> {{ t('common.print') }}
                </button>
            </div>
        </header>

        <div v-if="report" class="grid grid-cols-1 lg:grid-cols-2 gap-5">

            <div class="card">
                <h3 class="card-title">{{ t('accounting.pl.revenue') }}</h3>
                <table class="w-full text-sm">
                    <tbody class="divide-y divide-slate-50">
                        <tr v-for="r in report.revenue" :key="r.account_id">
                            <td class="py-1.5 font-mono text-xs text-slate-500 w-16">{{ r.account_code }}</td>
                            <td class="py-1.5 text-slate-800">{{ r.account_name }}</td>
                            <td class="py-1.5 text-right font-mono">{{ fmt(r.amount) }}</td>
                        </tr>
                        <tr v-if="report.revenue.length === 0">
                            <td colspan="3" class="py-4 text-center text-xs text-slate-400">{{ t('accounting.pl.noRevenue') }}</td>
                        </tr>
                    </tbody>
                    <tfoot class="border-t-2 border-slate-200">
                        <tr class="font-bold">
                            <td colspan="2" class="py-2 text-emerald-800">{{ t('accounting.pl.revenueTotal') }}</td>
                            <td class="py-2 text-right font-mono text-emerald-700">{{ fmt(report.revenue_total) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="card">
                <h3 class="card-title">{{ t('accounting.pl.expense') }}</h3>
                <table class="w-full text-sm">
                    <tbody class="divide-y divide-slate-50">
                        <tr v-for="r in report.expense" :key="r.account_id">
                            <td class="py-1.5 font-mono text-xs text-slate-500 w-16">{{ r.account_code }}</td>
                            <td class="py-1.5 text-slate-800">{{ r.account_name }}</td>
                            <td class="py-1.5 text-right font-mono">{{ fmt(r.amount) }}</td>
                        </tr>
                        <tr v-if="report.expense.length === 0">
                            <td colspan="3" class="py-4 text-center text-xs text-slate-400">{{ t('accounting.pl.noExpense') }}</td>
                        </tr>
                    </tbody>
                    <tfoot class="border-t-2 border-slate-200">
                        <tr class="font-bold">
                            <td colspan="2" class="py-2 text-rose-800">{{ t('accounting.pl.expenseTotal') }}</td>
                            <td class="py-2 text-right font-mono text-rose-700">{{ fmt(report.expense_total) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

        </div>

        <div v-if="report" class="card flex items-center justify-between">
            <div>
                <p class="text-xs uppercase tracking-wide text-slate-500 font-semibold">{{ t('accounting.pl.netResult') }}</p>
                <p class="text-xs text-slate-500 mt-0.5">
                    {{ formatDate(report.period.from) }} — {{ formatDate(report.period.to) }}
                </p>
            </div>
            <div class="text-right">
                <p class="text-3xl font-bold font-mono"
                   :class="report.net_profit >= 0 ? 'text-emerald-700' : 'text-rose-700'">
                    {{ fmt(report.net_profit) }}
                </p>
                <p class="text-xs text-slate-500 mt-1">
                    {{ report.net_profit >= 0 ? t('accounting.pl.profit') : t('accounting.pl.loss') }}
                </p>
            </div>
        </div>

    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { accountingReportService } from '@/services/accountingService';
import { useCurrency } from '@/composables/useCurrency';
import { ArrowDownTrayIcon, PrinterIcon } from '@heroicons/vue/24/outline';

const { t, locale } = useI18n();
const { fmtCurrency } = useCurrency();

const from = ref(new Date(new Date().getFullYear(), 0, 1).toISOString().slice(0, 10));
const to   = ref(new Date().toISOString().slice(0, 10));
const report = ref(null);

function fmt(v) { return v === null || v === undefined ? '' : fmtCurrency(v); }
function formatDate(d) {
    return new Intl.DateTimeFormat(locale.value || 'de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(new Date(d));
}

async function load() {
    const { data } = await accountingReportService.profitLoss({ from: from.value, to: to.value });
    report.value = data.data;
}

function exportCsv() {
    window.open(`/api/accounting/profit-loss?from=${from.value}&to=${to.value}&format=csv`, '_blank');
}
function print() { window.print(); }

onMounted(load);
</script>

<style scoped>
@reference '../../../css/app.css';
.card       { @apply bg-white border border-slate-200 rounded-xl shadow-sm p-5; }
.card-title { @apply text-xs font-bold text-slate-500 uppercase tracking-wider mb-3; }
.btn-soft   { @apply inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-slate-300 text-slate-700 text-sm hover:bg-slate-50 transition-colors disabled:opacity-50; }
.lbl        { @apply block text-xs font-medium text-slate-600 mb-1; }
.ctrl       { @apply px-3 py-2 text-sm border border-slate-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent; }
@media print {
    .btn-soft, .lbl, input { display: none !important; }
    .card { box-shadow: none; border: none; }
}
</style>
