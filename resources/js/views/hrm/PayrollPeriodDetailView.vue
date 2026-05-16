<template>
    <div class="p-6 lg:p-8 space-y-6">

        <div class="flex items-center gap-3">
            <RouterLink :to="{ name: 'hrm-payroll' }" class="p-2 text-slate-400 hover:text-slate-700 hover:bg-slate-100 rounded-lg">
                <ArrowLeftIcon class="w-5 h-5" />
            </RouterLink>
            <div class="flex-1 min-w-0">
                <h1 class="text-2xl font-bold text-slate-900 tracking-tight">
                    {{ period?.label ?? t('hrm.payroll.title') }}
                </h1>
                <p v-if="period" class="text-sm text-slate-500">
                    {{ formatDate(period.period_start) }} - {{ formatDate(period.period_end) }}
                    <span v-if="period.branch_name"> · {{ period.branch_name }}</span>
                </p>
            </div>
            <div class="flex items-center gap-2">
                <button
                    v-if="period && ['draft', 'generated'].includes(period.status)"
                    @click="confirmGenerate"
                    :disabled="generating"
                    class="btn-soft"
                >
                    <ArrowPathIcon v-if="generating" class="w-4 h-4 animate-spin" />
                    <SparklesIcon v-else class="w-4 h-4" />
                    {{ generating ? t('common.loading') : t('hrm.payroll.generate') }}
                </button>
                <button
                    v-if="period && period.status === 'generated'"
                    @click="finalize"
                    class="btn-primary"
                >
                    <LockClosedIcon class="w-4 h-4" />
                    {{ t('hrm.payroll.finalize') }}
                </button>
            </div>
        </div>

        <div v-if="period" class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <StatCard :label="t('hrm.payroll.payslips')" :value="String(payslips.length)" dot="bg-indigo-400" />
            <StatCard :label="t('hrm.payroll.pending')"  :value="String(counts.pending)" dot="bg-slate-400" />
            <StatCard :label="t('hrm.payroll.paid')"     :value="String(counts.paid)"    dot="bg-emerald-500" />
            <StatCard :label="t('hrm.payroll.netTotal')" :value="fmtCurrency(totals.net)" dot="bg-indigo-500" />
        </div>

        <div v-if="errorMsg" class="rounded-lg bg-rose-50 border border-rose-200 px-4 py-3 text-sm text-rose-700">{{ errorMsg }}</div>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
            <div v-if="loading" class="py-16 text-center text-slate-400">
                <div class="w-6 h-6 border-2 border-indigo-500 border-t-transparent rounded-full animate-spin mx-auto mb-2"></div>
                {{ t('common.loading') }}
            </div>

            <div v-else-if="payslips.length === 0" class="py-16 text-center">
                <DocumentTextIcon class="w-10 h-10 text-slate-300 mx-auto mb-2" />
                <p class="text-sm text-slate-500">{{ t('hrm.payroll.noPayslips') }}</p>
                <button v-if="period && ['draft', 'generated'].includes(period.status)" @click="confirmGenerate" class="btn-primary mt-4 inline-flex">
                    <SparklesIcon class="w-4 h-4" />
                    {{ t('hrm.payroll.generate') }}
                </button>
            </div>

            <div v-else class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 border-b border-slate-100">
                        <tr>
                            <th class="th">{{ t('hrm.payroll.payslipNumber') }}</th>
                            <th class="th">{{ t('hrm.employees.name') }}</th>
                            <th class="th text-right">{{ t('hrm.payroll.daysWorked') }}</th>
                            <th class="th text-right">{{ t('hrm.payroll.basicSalary') }}</th>
                            <th class="th text-right">{{ t('hrm.payroll.gross') }}</th>
                            <th class="th text-right">{{ t('hrm.payroll.deductionsAndTax') }}</th>
                            <th class="th text-right">{{ t('hrm.payroll.net') }}</th>
                            <th class="th">{{ t('common.status') }}</th>
                            <th class="th w-24 text-right">{{ t('common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="p in payslips" :key="p.id" class="hover:bg-slate-50/60">
                            <td class="td font-mono text-xs text-slate-500">{{ p.payslip_number }}</td>
                            <td class="td">
                                <div class="flex items-center gap-2.5">
                                    <EmployeeAvatar :src="p.employee?.photo_url" :name="p.employee?.full_name" size="sm" />
                                    <div class="min-w-0">
                                        <p class="font-medium text-slate-900 truncate">{{ p.employee?.full_name }}</p>
                                        <p class="text-xs text-slate-500 font-mono">{{ p.employee?.employee_id }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="td text-right font-mono">
                                {{ p.days_worked }} / {{ p.days_in_period }}
                            </td>
                            <td class="td text-right font-mono text-slate-700">{{ fmtCurrency(p.basic_salary) }}</td>
                            <td class="td text-right font-mono text-slate-800">{{ fmtCurrency(p.gross_salary) }}</td>
                            <td class="td text-right font-mono text-rose-600">{{ fmtCurrency(p.total_deductions + p.tax_amount) }}</td>
                            <td class="td text-right font-mono font-semibold text-indigo-700">{{ fmtCurrency(p.net_salary) }}</td>
                            <td class="td"><PayslipStatusBadge :status="p.status" /></td>
                            <td class="td">
                                <div class="flex items-center justify-end gap-1">
                                    <RouterLink :to="{ name: 'hrm-payslip', params: { id: p.id } }" class="action-btn" :title="t('common.view')">
                                        <EyeIcon class="w-4 h-4" />
                                    </RouterLink>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, h } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter, RouterLink } from 'vue-router';
import { payrollPeriodService, payslipService } from '@/services/hrmService';
import { useAlert } from '@/composables/useAlert';
import { useCurrency } from '@/composables/useCurrency';
import EmployeeAvatar from '@/components/hrm/EmployeeAvatar.vue';
import {
    ArrowLeftIcon, ArrowPathIcon, SparklesIcon, LockClosedIcon, DocumentTextIcon, EyeIcon,
} from '@heroicons/vue/24/outline';

const { t } = useI18n();
const route = useRoute();
const router = useRouter();
const { toast, confirm } = useAlert();
const { fmtCurrency } = useCurrency();

const period = ref(null);
const payslips = ref([]);
const loading = ref(false);
const generating = ref(false);
const errorMsg = ref('');

const counts = computed(() => {
    let pending = 0, paid = 0;
    for (const p of payslips.value) {
        if (p.status === 'paid') paid++;
        else if (p.status === 'pending') pending++;
    }
    return { pending, paid };
});

const totals = computed(() => ({
    net: payslips.value.reduce((s, p) => s + (Number(p.net_salary) || 0), 0),
}));

const StatCard = (props) => h('div', { class: 'bg-white border border-slate-200 rounded-xl px-4 py-3 shadow-sm flex items-center gap-3' }, [
    h('span', { class: `w-2 h-2 rounded-full ${props.dot}` }),
    h('div', {}, [
        h('p', { class: 'text-[11px] uppercase tracking-wide text-slate-500 font-medium' }, props.label),
        h('p', { class: 'text-lg font-bold text-slate-900 mt-0.5 font-mono' }, props.value),
    ]),
]);
StatCard.props = ['label', 'value', 'dot'];

const PayslipStatusBadge = (props) => {
    const palette = {
        pending:        { tone: 'bg-slate-100 text-slate-700',     dot: 'bg-slate-400' },
        paid:           { tone: 'bg-emerald-100 text-emerald-700', dot: 'bg-emerald-500' },
        partially_paid: { tone: 'bg-amber-100 text-amber-700',     dot: 'bg-amber-500' },
        cancelled:      { tone: 'bg-rose-100 text-rose-700',       dot: 'bg-rose-500' },
    }[props.status] ?? { tone: 'bg-slate-100 text-slate-700', dot: 'bg-slate-400' };

    return h('span', { class: `inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[11px] font-medium ${palette.tone}` }, [
        h('span', { class: `w-1.5 h-1.5 rounded-full ${palette.dot}` }),
        t('hrm.payroll.payslip_' + props.status),
    ]);
};
PayslipStatusBadge.props = ['status'];

function formatDate(s) {
    return s ? new Date(s + 'T00:00:00').toLocaleDateString('de-DE') : '';
}

async function fetchPeriod() {
    try {
        const { data } = await payrollPeriodService.show(route.params.id);
        period.value = data.data ?? data;
    } catch (err) {
        errorMsg.value = err.response?.data?.message ?? t('common.unexpectedError');
    }
}

async function fetchPayslips() {
    loading.value = true;
    try {
        const { data } = await payslipService.index({ payroll_period_id: route.params.id, per_page: 500 });
        payslips.value = data.data ?? [];
    } catch (err) {
        errorMsg.value = err.response?.data?.message ?? t('common.unexpectedError');
    } finally {
        loading.value = false;
    }
}

async function confirmGenerate() {
    const ok = await confirm({
        title: t('hrm.payroll.generateTitle'),
        text:  t('hrm.payroll.generateMessage'),
        confirmText: t('hrm.payroll.generate'),
    });
    if (!ok) return;
    generating.value = true;
    try {
        const { data } = await payrollPeriodService.generate(route.params.id);
        toast('success', data.message ?? t('hrm.payroll.generatedSuccess'));
        await Promise.all([fetchPeriod(), fetchPayslips()]);
    } catch (err) {
        toast('error', err.response?.data?.message ?? t('common.unexpectedError'));
    } finally {
        generating.value = false;
    }
}

async function finalize() {
    const ok = await confirm({
        title: t('hrm.payroll.finalizeTitle'),
        text:  t('hrm.payroll.finalizeMessage'),
        confirmText: t('hrm.payroll.finalize'),
    });
    if (!ok) return;
    try {
        await payrollPeriodService.finalize(route.params.id);
        toast('success', t('hrm.payroll.finalizedSuccess'));
        fetchPeriod();
    } catch (err) {
        toast('error', err.response?.data?.message ?? t('common.unexpectedError'));
    }
}

onMounted(async () => {
    await Promise.all([fetchPeriod(), fetchPayslips()]);
});
</script>

<style scoped>
@reference '../../../css/app.css';
.btn-primary { @apply inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg transition-colors shadow-sm disabled:opacity-50; }
.btn-soft    { @apply inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-slate-300 text-slate-700 text-sm hover:bg-slate-50 transition-colors disabled:opacity-50; }
.action-btn  { @apply p-1.5 text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors inline-flex; }
.th          { @apply px-4 py-2.5 text-left text-[11px] font-semibold text-slate-500 uppercase tracking-wide; }
.td          { @apply px-4 py-2.5 align-middle; }
</style>
