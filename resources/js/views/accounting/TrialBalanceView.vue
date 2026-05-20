<template>
    <div class="p-6 lg:p-8 space-y-5 max-w-7xl mx-auto">

        <header class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 tracking-tight">{{ t('accounting.trialBalance.title') }}</h1>
                <p class="mt-1 text-sm text-slate-500">{{ t('accounting.trialBalance.subtitle') }}</p>
            </div>
            <div class="flex items-end gap-2">
                <div>
                    <label class="lbl">{{ t('accounting.trialBalance.asOf') }}</label>
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

        <div v-if="report && !report.balanced" class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 flex items-start gap-3">
            <ExclamationTriangleIcon class="w-5 h-5 text-rose-600 mt-0.5" />
            <div>
                <p class="text-sm font-semibold text-rose-800">{{ t('accounting.trialBalance.imbalanceTitle') }}</p>
                <p class="text-xs text-rose-700 mt-0.5">
                    {{ t('accounting.trialBalance.imbalanceHint',
                        { diff: Math.abs(report.total_debit - report.total_credit).toFixed(2) }) }}
                </p>
            </div>
        </div>

        <div v-if="report" class="card overflow-hidden p-0">
            <table class="w-full text-sm">
                <thead class="bg-slate-50/70">
                    <tr class="text-left text-[11px] text-slate-500 uppercase tracking-wide border-b border-slate-100">
                        <th class="px-4 py-2.5">{{ t('accounting.fields.accountCode') }}</th>
                        <th class="px-4 py-2.5">{{ t('accounting.fields.accountName') }}</th>
                        <th class="px-4 py-2.5">{{ t('accounting.fields.accountType') }}</th>
                        <th class="px-4 py-2.5 text-right">{{ t('accounting.trialBalance.opening') }}</th>
                        <th class="px-4 py-2.5 text-right">{{ t('accounting.fields.debit') }}</th>
                        <th class="px-4 py-2.5 text-right">{{ t('accounting.fields.credit') }}</th>
                        <th class="px-4 py-2.5 text-right">{{ t('accounting.fields.balance') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <tr v-for="r in report.rows" :key="r.account_id" class="hover:bg-slate-50/60">
                        <td class="px-4 py-1.5 font-mono text-xs text-slate-700 font-semibold">{{ r.account_code }}</td>
                        <td class="px-4 py-1.5 text-slate-800">{{ r.account_name }}</td>
                        <td class="px-4 py-1.5">
                            <span class="text-[10px] uppercase tracking-wider px-2 py-0.5 rounded-md" :class="typeClass(r.account_type)">
                                {{ t('accounting.types.' + r.account_type) }}
                            </span>
                        </td>
                        <td class="px-4 py-1.5 text-right font-mono text-slate-600">{{ fmt(r.opening_balance) }}</td>
                        <td class="px-4 py-1.5 text-right font-mono">{{ r.period_debit > 0 ? fmt(r.period_debit) : '' }}</td>
                        <td class="px-4 py-1.5 text-right font-mono">{{ r.period_credit > 0 ? fmt(r.period_credit) : '' }}</td>
                        <td class="px-4 py-1.5 text-right font-mono font-semibold">{{ fmt(r.closing_balance) }}</td>
                    </tr>
                </tbody>
                <tfoot class="border-t-2 border-slate-200">
                    <tr class="font-bold bg-slate-50/50">
                        <td colspan="4" class="px-4 py-2 text-slate-700">{{ t('accounting.fields.totals') }}</td>
                        <td class="px-4 py-2 text-right font-mono">{{ fmt(report.total_debit) }}</td>
                        <td class="px-4 py-2 text-right font-mono">{{ fmt(report.total_credit) }}</td>
                        <td class="px-4 py-2 text-right">
                            <span :class="report.balanced ? 'text-emerald-700' : 'text-rose-700'">
                                {{ report.balanced ? t('accounting.trialBalance.balanced') : t('accounting.trialBalance.imbalanced') }}
                            </span>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div v-else-if="!loading" class="text-center text-slate-400 py-12 text-sm">
            {{ t('accounting.trialBalance.noData') }}
        </div>

    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { accountingReportService } from '@/services/accountingService';
import { useCurrency } from '@/composables/useCurrency';
import { ArrowDownTrayIcon, PrinterIcon, ExclamationTriangleIcon } from '@heroicons/vue/24/outline';

const { t } = useI18n();
const { fmtCurrency } = useCurrency();

const asOf = ref(new Date().toISOString().slice(0, 10));
const report = ref(null);
const loading = ref(false);

function fmt(v) {
    if (v === null || v === undefined || Math.abs(v) < 0.005) return '';
    return fmtCurrency(v);
}

function typeClass(type) {
    return {
        asset:     'bg-indigo-50 text-indigo-700',
        liability: 'bg-rose-50 text-rose-700',
        equity:    'bg-amber-50 text-amber-700',
        revenue:   'bg-emerald-50 text-emerald-700',
        expense:   'bg-slate-100 text-slate-700',
    }[type] ?? 'bg-slate-100 text-slate-700';
}

async function load() {
    loading.value = true;
    try {
        const { data } = await accountingReportService.trialBalance({ as_of: asOf.value });
        report.value = data.data;
    } finally {
        loading.value = false;
    }
}

function exportCsv() {
    window.open(`/api/accounting/trial-balance?as_of=${asOf.value}&format=csv`, '_blank');
}
function print() { window.print(); }

onMounted(load);
</script>

<style scoped>
@reference '../../../css/app.css';
.card     { @apply bg-white border border-slate-200 rounded-xl shadow-sm; }
.btn-soft { @apply inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-slate-300 text-slate-700 text-sm hover:bg-slate-50 transition-colors disabled:opacity-50; }
.lbl      { @apply block text-xs font-medium text-slate-600 mb-1; }
.ctrl     { @apply px-3 py-2 text-sm border border-slate-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent; }
@media print {
    .btn-soft, .lbl, header > div:last-child { display: none !important; }
    .card { box-shadow: none; border: none; }
}
</style>
