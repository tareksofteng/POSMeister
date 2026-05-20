<template>
    <div class="p-6 lg:p-8 space-y-5 max-w-7xl mx-auto">

        <header class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 tracking-tight">{{ t('accounting.balanceSheet.title') }}</h1>
                <p class="mt-1 text-sm text-slate-500">{{ t('accounting.balanceSheet.subtitle') }}</p>
            </div>
            <div class="flex items-end gap-2">
                <div>
                    <label class="lbl">{{ t('accounting.balanceSheet.asOf') }}</label>
                    <input v-model="asOf" @change="load" type="date" class="ctrl w-40" />
                </div>
                <button @click="exportCsv" :disabled="!report" class="btn-soft">
                    <ArrowDownTrayIcon class="w-4 h-4" /> CSV
                </button>
                <button @click="print" :disabled="!report" class="btn-soft">
                    <PrinterIcon class="w-4 h-4" /> {{ t('common.print') }}
                </button>
            </div>
        </header>

        <div v-if="report && !report.balanced" class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            {{ t('accounting.balanceSheet.imbalanceHint') }}
        </div>

        <div v-if="report" class="grid grid-cols-1 lg:grid-cols-2 gap-5">

            <div class="card">
                <h3 class="card-title">{{ t('accounting.balanceSheet.assets') }}</h3>
                <table class="w-full text-sm">
                    <tbody class="divide-y divide-slate-50">
                        <tr v-for="r in report.assets" :key="r.account_id">
                            <td class="py-1.5 font-mono text-xs text-slate-500 w-16">{{ r.account_code }}</td>
                            <td class="py-1.5 text-slate-800">{{ r.account_name }}</td>
                            <td class="py-1.5 text-right font-mono">{{ fmt(r.amount) }}</td>
                        </tr>
                        <tr v-if="report.assets.length === 0">
                            <td colspan="3" class="py-4 text-center text-xs text-slate-400">—</td>
                        </tr>
                    </tbody>
                    <tfoot class="border-t-2 border-slate-200 font-bold">
                        <tr>
                            <td colspan="2" class="py-2 text-indigo-800">{{ t('accounting.balanceSheet.assetTotal') }}</td>
                            <td class="py-2 text-right font-mono text-indigo-800">{{ fmt(report.asset_total) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="card space-y-5">
                <div>
                    <h3 class="card-title">{{ t('accounting.balanceSheet.liabilities') }}</h3>
                    <table class="w-full text-sm">
                        <tbody class="divide-y divide-slate-50">
                            <tr v-for="r in report.liabilities" :key="r.account_id">
                                <td class="py-1.5 font-mono text-xs text-slate-500 w-16">{{ r.account_code }}</td>
                                <td class="py-1.5 text-slate-800">{{ r.account_name }}</td>
                                <td class="py-1.5 text-right font-mono">{{ fmt(r.amount) }}</td>
                            </tr>
                            <tr v-if="report.liabilities.length === 0">
                                <td colspan="3" class="py-2 text-center text-xs text-slate-400">—</td>
                            </tr>
                        </tbody>
                        <tfoot class="border-t border-slate-200 font-semibold">
                            <tr>
                                <td colspan="2" class="py-1.5 text-rose-800">{{ t('accounting.balanceSheet.liabilityTotal') }}</td>
                                <td class="py-1.5 text-right font-mono text-rose-700">{{ fmt(report.liability_total) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div>
                    <h3 class="card-title">{{ t('accounting.balanceSheet.equity') }}</h3>
                    <table class="w-full text-sm">
                        <tbody class="divide-y divide-slate-50">
                            <tr v-for="r in report.equity" :key="r.account_id">
                                <td class="py-1.5 font-mono text-xs text-slate-500 w-16">{{ r.account_code }}</td>
                                <td class="py-1.5 text-slate-800">{{ r.account_name }}</td>
                                <td class="py-1.5 text-right font-mono">{{ fmt(r.amount) }}</td>
                            </tr>
                            <tr>
                                <td class="py-1.5 font-mono text-xs text-slate-500 w-16"></td>
                                <td class="py-1.5 text-slate-800 italic">{{ t('accounting.balanceSheet.ytdProfit') }}</td>
                                <td class="py-1.5 text-right font-mono">{{ fmt(report.ytd_profit) }}</td>
                            </tr>
                        </tbody>
                        <tfoot class="border-t border-slate-200 font-semibold">
                            <tr>
                                <td colspan="2" class="py-1.5 text-amber-800">{{ t('accounting.balanceSheet.equityTotal') }}</td>
                                <td class="py-1.5 text-right font-mono text-amber-700">{{ fmt(report.equity_total + report.ytd_profit) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="border-t-2 border-slate-200 pt-3 flex justify-between font-bold">
                    <span class="text-slate-800">{{ t('accounting.balanceSheet.passivaTotal') }}</span>
                    <span class="font-mono text-slate-900">
                        {{ fmt(report.liability_total + report.equity_total + report.ytd_profit) }}
                    </span>
                </div>
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

const { t } = useI18n();
const { fmtCurrency } = useCurrency();

const asOf = ref(new Date().toISOString().slice(0, 10));
const report = ref(null);

function fmt(v) { return v === null || v === undefined ? '' : fmtCurrency(v); }

async function load() {
    const { data } = await accountingReportService.balanceSheet({ as_of: asOf.value });
    report.value = data.data;
}

function exportCsv() {
    window.open(`/api/accounting/balance-sheet?as_of=${asOf.value}&format=csv`, '_blank');
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
