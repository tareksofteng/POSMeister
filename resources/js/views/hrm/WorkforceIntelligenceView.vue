<template>
    <div class="p-6 lg:p-8 space-y-6 max-w-7xl mx-auto">

        <header>
            <p class="text-xs font-medium text-indigo-600 uppercase tracking-wider mb-1">{{ t('hrm.workforce.module') }}</p>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">{{ t('hrm.workforce.title') }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ t('hrm.workforce.subtitle') }}</p>
        </header>

        <section class="grid grid-cols-2 md:grid-cols-5 gap-3">
            <KpiCard :label="t('hrm.workforce.kpi.headcount')"     :value="d?.active_employees ?? '—'"      tone="indigo" />
            <KpiCard :label="t('hrm.workforce.kpi.revenue')"       :value="d ? fmtCurrency(d.revenue_month) : '—'" tone="emerald" />
            <KpiCard :label="t('hrm.workforce.kpi.labourCost')"    :value="d ? fmtCurrency(d.labour_cost_month) : '—'" tone="rose" />
            <KpiCard :label="t('hrm.workforce.kpi.labourPct')"     :value="d ? d.labour_cost_pct + '%' : '—'" :tone="(d?.labour_cost_pct ?? 0) < 30 ? 'emerald' : 'amber'" />
            <KpiCard :label="t('hrm.workforce.kpi.revenuePerHead')" :value="d ? fmtCurrency(d.revenue_per_head) : '—'" tone="indigo" />
        </section>

        <section v-if="branchRows.length" class="card">
            <h3 class="card-title">{{ t('hrm.workforce.branchEfficiency') }}</h3>
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-[11px] text-slate-500 uppercase tracking-wide border-b border-slate-100">
                        <th class="py-2">{{ t('hrm.workforce.branch') }}</th>
                        <th class="py-2 text-right">{{ t('hrm.workforce.headcount') }}</th>
                        <th class="py-2 text-right">{{ t('hrm.workforce.kpi.revenue') }}</th>
                        <th class="py-2 text-right">{{ t('hrm.workforce.kpi.labourCost') }}</th>
                        <th class="py-2 text-right">{{ t('hrm.workforce.kpi.labourPct') }}</th>
                        <th class="py-2 text-right">{{ t('hrm.workforce.revPerHead') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <tr v-for="b in branchRows" :key="b.branch_id" class="hover:bg-slate-50/60">
                        <td class="py-2 font-medium text-slate-800">{{ b.name }}</td>
                        <td class="py-2 text-right font-mono">{{ b.headcount }}</td>
                        <td class="py-2 text-right font-mono">{{ fmtCurrency(b.revenue) }}</td>
                        <td class="py-2 text-right font-mono text-rose-700">{{ fmtCurrency(b.labour_cost) }}</td>
                        <td class="py-2 text-right font-mono" :class="b.labour_pct < 30 ? 'text-emerald-700' : 'text-amber-700'">{{ b.labour_pct }}%</td>
                        <td class="py-2 text-right font-mono">{{ fmtCurrency(b.revenue_per_head) }}</td>
                    </tr>
                </tbody>
            </table>
        </section>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
            <section class="card">
                <h3 class="card-title">{{ t('hrm.workforce.topCashiers') }}</h3>
                <table class="w-full text-sm">
                    <tbody class="divide-y divide-slate-50">
                        <tr v-for="(c, i) in d?.top_cashiers ?? []" :key="c.user_id">
                            <td class="py-1.5 w-6 text-xs text-slate-400 font-mono">#{{ i + 1 }}</td>
                            <td class="py-1.5">
                                <p class="font-medium text-slate-800">{{ c.name }}</p>
                                <p class="text-[11px] text-slate-500">{{ c.sales_count }} {{ t('hrm.workforce.sales') }} · {{ t('hrm.workforce.refundRate') }}: {{ c.refund_rate }}%</p>
                            </td>
                            <td class="py-1.5 text-right font-mono">{{ fmtCurrency(c.revenue) }}</td>
                        </tr>
                    </tbody>
                </table>
                <p v-if="(d?.top_cashiers ?? []).length === 0" class="text-center text-sm text-slate-400 py-6">{{ t('hrm.workforce.empty') }}</p>
            </section>

            <section class="card">
                <h3 class="card-title">{{ t('hrm.workforce.refundRiskTitle') }}</h3>
                <table class="w-full text-sm">
                    <tbody class="divide-y divide-slate-50">
                        <tr v-for="r in d?.refund_risk ?? []" :key="r.user_id">
                            <td class="py-1.5">
                                <p class="font-medium text-slate-800">{{ r.name }}</p>
                                <p class="text-[11px] text-slate-500">{{ r.refund_count }} {{ t('hrm.workforce.refunds') }}</p>
                            </td>
                            <td class="py-1.5 text-right font-mono text-rose-700 font-semibold">{{ r.refund_rate }}%</td>
                            <td class="py-1.5 text-right font-mono">{{ fmtCurrency(r.refund_amount) }}</td>
                        </tr>
                    </tbody>
                </table>
                <p v-if="(d?.refund_risk ?? []).length === 0" class="text-center text-sm text-slate-400 py-6">{{ t('hrm.workforce.refundEmpty') }}</p>
            </section>
        </div>

        <section v-if="(d?.top_performers ?? []).length" class="card">
            <h3 class="card-title">{{ t('hrm.workforce.topPerformers') }}</h3>
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-[11px] text-slate-500 uppercase tracking-wide border-b border-slate-100">
                        <th class="py-2">{{ t('hrm.approval.employee') }}</th>
                        <th class="py-2 text-right">{{ t('hrm.workforce.kpi.revenue') }}</th>
                        <th class="py-2 text-right">{{ t('hrm.workforce.refundRate') }}</th>
                        <th class="py-2 text-right">{{ t('hrm.workforce.attendancePct') }}</th>
                        <th class="py-2 text-right">{{ t('hrm.workforce.hours') }}</th>
                        <th class="py-2 text-right w-24">{{ t('hrm.workforce.score') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <tr v-for="p in d.top_performers" :key="p.user_id" class="hover:bg-slate-50/60">
                        <td class="py-2 text-slate-800">{{ p.name }}</td>
                        <td class="py-2 text-right font-mono">{{ fmtCurrency(p.revenue) }}</td>
                        <td class="py-2 text-right font-mono">{{ p.refund_rate }}%</td>
                        <td class="py-2 text-right font-mono">{{ p.attendance_pct }}%</td>
                        <td class="py-2 text-right font-mono">{{ p.worked_hours }}</td>
                        <td class="py-2 text-right font-mono font-bold text-indigo-700">{{ p.score }}</td>
                    </tr>
                </tbody>
            </table>
        </section>

    </div>
</template>

<script setup>
import { ref, h, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { workforceAnalyticsService } from '@/services/hrmService';
import { useCurrency } from '@/composables/useCurrency';

const { t } = useI18n();
const { fmtCurrency } = useCurrency();

const d = ref(null);
const branchRows = ref([]);

const KpiCard = (props) => {
    const palette = {
        emerald: 'border-emerald-200',
        rose:    'border-rose-200',
        indigo:  'border-indigo-200',
        amber:   'border-amber-200',
    }[props.tone] ?? 'border-slate-200';
    return h('div', { class: `bg-white border ${palette} rounded-xl shadow-sm px-4 py-3 hover:shadow-md transition-shadow` }, [
        h('p', { class: 'text-[11px] uppercase tracking-wide text-slate-500 font-medium' }, props.label),
        h('p', { class: 'text-xl font-bold text-slate-900 mt-1 font-mono' }, String(props.value)),
    ]);
};
KpiCard.props = ['label', 'value', 'tone'];

async function load() {
    const [{ data: dash }, { data: br }] = await Promise.all([
        workforceAnalyticsService.dashboard(),
        workforceAnalyticsService.branchEfficiency(),
    ]);
    d.value = dash.data;
    branchRows.value = br.data ?? [];
}

onMounted(load);
</script>

<style scoped>
@reference '../../../css/app.css';
.card        { @apply bg-white border border-slate-200 rounded-xl shadow-sm p-5; }
.card-title  { @apply text-xs font-bold text-slate-500 uppercase tracking-wider mb-3; }
</style>
