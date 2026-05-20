<template>
    <div class="p-6 lg:p-8 space-y-6 max-w-7xl mx-auto">

        <header class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
            <div>
                <p class="text-xs font-medium text-indigo-600 uppercase tracking-wider mb-1">
                    {{ t('accounting.module') }}
                </p>
                <h1 class="text-2xl font-bold text-slate-900 tracking-tight">{{ t('accounting.dashboard.title') }}</h1>
                <p class="mt-1 text-sm text-slate-500">{{ t('accounting.dashboard.subtitle') }}</p>
            </div>
            <div class="flex items-end gap-2">
                <div>
                    <label class="lbl">{{ t('accounting.dashboard.asOf') }}</label>
                    <input v-model="asOf" @change="load" type="date" class="ctrl w-40" />
                </div>
                <button @click="load" :disabled="loading" class="btn-soft">
                    <ArrowPathIcon :class="['w-4 h-4', loading && 'animate-spin']" />
                    {{ t('accounting.dashboard.refresh') }}
                </button>
            </div>
        </header>

        <section class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
            <KpiCard :label="t('accounting.dashboard.cashBalance')"   :value="fmt(d?.cash_balance)"   tone="emerald" :icon="BanknotesIcon" />
            <KpiCard :label="t('accounting.dashboard.bankBalance')"   :value="fmt(d?.bank_balance)"   tone="indigo"  :icon="BuildingLibraryIcon" />
            <KpiCard :label="t('accounting.dashboard.receivables')"   :value="fmt(d?.receivables)"    tone="amber"   :icon="DocumentTextIcon" />
            <KpiCard :label="t('accounting.dashboard.payables')"      :value="fmt(d?.payables)"       tone="rose"    :icon="ClipboardDocumentListIcon" />
            <KpiCard :label="t('accounting.dashboard.monthRevenue')"  :value="fmt(d?.monthly_revenue)" tone="emerald" :icon="ArrowTrendingUpIcon" />
            <KpiCard :label="t('accounting.dashboard.monthExpense')"  :value="fmt(d?.monthly_expense)" tone="rose"   :icon="ReceiptPercentIcon" />
        </section>

        <section class="card">
            <div class="flex items-center justify-between mb-4">
                <h3 class="card-title mb-0">{{ t('accounting.dashboard.ytdProfit') }}</h3>
                <span class="text-2xl font-bold font-mono"
                      :class="(d?.ytd_profit ?? 0) >= 0 ? 'text-emerald-700' : 'text-rose-700'">
                    {{ fmt(d?.ytd_profit) }}
                </span>
            </div>
            <p class="text-xs text-slate-500">{{ t('accounting.dashboard.ytdProfitHint') }}</p>
        </section>

        <section class="card">
            <div class="flex items-center justify-between mb-3">
                <h3 class="card-title mb-0">{{ t('accounting.dashboard.recentEntries') }}</h3>
                <RouterLink :to="{ name: 'accounting-journal' }" class="text-xs text-indigo-600 hover:underline">
                    {{ t('accounting.dashboard.viewAll') }} →
                </RouterLink>
            </div>
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-[11px] text-slate-500 uppercase tracking-wide border-b border-slate-100">
                        <th class="py-2">{{ t('accounting.fields.entryDate') }}</th>
                        <th class="py-2">{{ t('accounting.fields.entryNumber') }}</th>
                        <th class="py-2">{{ t('accounting.fields.reference') }}</th>
                        <th class="py-2">{{ t('accounting.fields.narration') }}</th>
                        <th class="py-2 text-right">{{ t('accounting.fields.amount') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <tr v-for="e in d?.recent_entries ?? []" :key="e.id" class="hover:bg-slate-50/60">
                        <td class="py-2 text-slate-600 font-mono text-xs">{{ formatDate(e.entry_date) }}</td>
                        <td class="py-2 font-mono text-xs text-slate-800">{{ e.entry_number }}</td>
                        <td class="py-2">
                            <span class="inline-block px-2 py-0.5 rounded-md text-[10px] uppercase tracking-wider font-semibold"
                                  :class="refClass(e.reference_type)">
                                {{ t('accounting.refType.' + (e.reference_type ?? 'manual')) }}
                            </span>
                        </td>
                        <td class="py-2 text-slate-700 truncate max-w-md">{{ e.narration }}</td>
                        <td class="py-2 text-right font-mono">{{ fmt(e.amount) }}</td>
                    </tr>
                    <tr v-if="!loading && (d?.recent_entries ?? []).length === 0">
                        <td colspan="5" class="py-8 text-center text-sm text-slate-400">{{ t('accounting.dashboard.empty') }}</td>
                    </tr>
                </tbody>
            </table>
        </section>

        <section class="card">
            <h3 class="card-title">{{ t('accounting.dashboard.quickActions') }}</h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <RouterLink :to="{ name: 'accounting-coa' }" class="quick-link">
                    <BookOpenIcon class="w-5 h-5 text-indigo-500" />
                    <span>{{ t('accounting.nav.coa') }}</span>
                </RouterLink>
                <RouterLink :to="{ name: 'accounting-journal' }" class="quick-link">
                    <ClipboardDocumentListIcon class="w-5 h-5 text-emerald-500" />
                    <span>{{ t('accounting.nav.journal') }}</span>
                </RouterLink>
                <RouterLink :to="{ name: 'accounting-trial-balance' }" class="quick-link">
                    <CalculatorIcon class="w-5 h-5 text-amber-500" />
                    <span>{{ t('accounting.nav.trialBalance') }}</span>
                </RouterLink>
                <RouterLink :to="{ name: 'accounting-balance-sheet' }" class="quick-link">
                    <ScaleIcon class="w-5 h-5 text-rose-500" />
                    <span>{{ t('accounting.nav.balanceSheet') }}</span>
                </RouterLink>
            </div>
        </section>

    </div>
</template>

<script setup>
import { ref, h, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { RouterLink } from 'vue-router';
import { accountingReportService } from '@/services/accountingService';
import { useCurrency } from '@/composables/useCurrency';
import {
    ArrowPathIcon, BanknotesIcon, BuildingLibraryIcon, DocumentTextIcon,
    ClipboardDocumentListIcon, ArrowTrendingUpIcon, ReceiptPercentIcon,
    BookOpenIcon, CalculatorIcon, ScaleIcon,
} from '@heroicons/vue/24/outline';

const { t, locale } = useI18n();
const { fmtCurrency } = useCurrency();

const d = ref(null);
const loading = ref(false);
const asOf = ref(new Date().toISOString().slice(0, 10));

function fmt(v) {
    return v === null || v === undefined ? '—' : fmtCurrency(v);
}

function formatDate(d) {
    if (!d) return '';
    return new Intl.DateTimeFormat(locale.value || 'de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(new Date(d));
}

function refClass(type) {
    const tones = {
        sale:             'bg-emerald-50 text-emerald-700',
        purchase:         'bg-indigo-50 text-indigo-700',
        expense:          'bg-rose-50 text-rose-700',
        payslip:          'bg-amber-50 text-amber-700',
        customer_payment: 'bg-emerald-50 text-emerald-700',
        supplier_payment: 'bg-rose-50 text-rose-700',
        manual:           'bg-slate-100 text-slate-700',
        reversal:         'bg-slate-100 text-slate-700',
    };
    return tones[type] ?? 'bg-slate-100 text-slate-700';
}

const KpiCard = (props) => {
    const palette = {
        emerald: 'border-emerald-200',
        rose:    'border-rose-200',
        indigo:  'border-indigo-200',
        amber:   'border-amber-200',
    }[props.tone] ?? 'border-slate-200';
    const iconColor = {
        emerald: 'text-emerald-500',
        rose:    'text-rose-500',
        indigo:  'text-indigo-500',
        amber:   'text-amber-500',
    }[props.tone] ?? 'text-slate-400';
    return h('div', { class: `bg-white border ${palette} rounded-xl shadow-sm px-4 py-3 hover:shadow-md transition-shadow` }, [
        h('div', { class: 'flex items-start justify-between gap-2' }, [
            h('p', { class: 'text-[11px] uppercase tracking-wide text-slate-500 font-medium' }, props.label),
            props.icon ? h(props.icon, { class: `w-4 h-4 ${iconColor}` }) : null,
        ]),
        h('p', { class: 'text-lg font-bold text-slate-900 mt-1 font-mono' }, String(props.value)),
    ]);
};
KpiCard.props = ['label', 'value', 'tone', 'icon'];

async function load() {
    loading.value = true;
    try {
        const { data } = await accountingReportService.dashboard({ as_of: asOf.value });
        d.value = data.data;
    } finally {
        loading.value = false;
    }
}

onMounted(load);
</script>

<style scoped>
@reference '../../../css/app.css';
.card        { @apply bg-white border border-slate-200 rounded-xl shadow-sm p-5; }
.card-title  { @apply text-xs font-bold text-slate-500 uppercase tracking-wider mb-3; }
.btn-soft    { @apply inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-slate-300 text-slate-700 text-sm hover:bg-slate-50 transition-colors disabled:opacity-50; }
.lbl         { @apply block text-xs font-medium text-slate-600 mb-1; }
.ctrl        { @apply px-3 py-2 text-sm border border-slate-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent; }
.quick-link  { @apply flex items-center gap-2 px-4 py-3 rounded-lg border border-slate-200 bg-white hover:bg-indigo-50 hover:border-indigo-200 text-sm font-medium text-slate-700 transition-colors; }
</style>
