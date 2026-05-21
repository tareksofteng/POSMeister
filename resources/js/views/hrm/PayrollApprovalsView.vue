<template>
    <div class="p-6 lg:p-8 space-y-5 max-w-7xl mx-auto">

        <header>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">{{ t('hrm.approval.title') }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ t('hrm.approval.subtitle') }}</p>
        </header>

        <div class="flex flex-wrap gap-2">
            <button v-for="s in statuses" :key="s" @click="activeStatus = s; load()"
                    :class="['px-4 py-2 rounded-lg border text-sm font-medium transition-colors',
                             activeStatus === s
                                ? 'bg-indigo-600 text-white border-indigo-600'
                                : 'bg-white border-slate-300 text-slate-700 hover:bg-slate-50']">
                {{ t('hrm.approval.status.' + s) }}
                <span class="ml-2 text-xs font-mono opacity-75">{{ counts[s] ?? '' }}</span>
            </button>
        </div>

        <div class="card overflow-hidden p-0">
            <table class="w-full text-sm">
                <thead class="bg-slate-50/70">
                    <tr class="text-left text-[11px] text-slate-500 uppercase tracking-wide border-b border-slate-100">
                        <th class="px-4 py-2.5">{{ t('hrm.approval.payslip') }}</th>
                        <th class="px-4 py-2.5">{{ t('hrm.approval.employee') }}</th>
                        <th class="px-4 py-2.5">{{ t('hrm.approval.period') }}</th>
                        <th class="px-4 py-2.5 text-right">{{ t('hrm.approval.net') }}</th>
                        <th class="px-4 py-2.5">{{ t('hrm.approval.payment') }}</th>
                        <th class="px-4 py-2.5">{{ t('hrm.approval.locked') }}</th>
                        <th class="px-4 py-2.5 text-right w-56"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <tr v-for="p in rows" :key="p.id" class="hover:bg-slate-50/60">
                        <td class="px-4 py-2 font-mono text-xs text-indigo-600">{{ p.payslip_number }}</td>
                        <td class="px-4 py-2">
                            <p class="font-medium text-slate-800">{{ p.employee?.first_name }} {{ p.employee?.last_name }}</p>
                            <p class="text-[11px] font-mono text-slate-500">{{ p.employee?.employee_id }}</p>
                        </td>
                        <td class="px-4 py-2 text-xs text-slate-600">{{ formatDate(p.period_start) }} – {{ formatDate(p.period_end) }}</td>
                        <td class="px-4 py-2 text-right font-mono">{{ fmtCurrency(p.net_salary) }}</td>
                        <td class="px-4 py-2">
                            <span :class="paymentBadge(p.status)" class="text-[10px] uppercase tracking-wider px-2 py-0.5 rounded-md font-bold">
                                {{ p.status }}
                            </span>
                        </td>
                        <td class="px-4 py-2 text-center">
                            <span v-if="p.is_locked" class="text-rose-600">🔒</span>
                            <span v-else class="text-slate-300">—</span>
                        </td>
                        <td class="px-4 py-2 text-right text-xs space-x-2">
                            <button v-if="p.approval_status === 'draft'" @click="act(p, 'submit')" class="text-indigo-600 hover:underline">{{ t('hrm.approval.actions.submit') }}</button>
                            <button v-if="p.approval_status === 'submitted'" @click="act(p, 'approve')" class="text-emerald-600 hover:underline">{{ t('hrm.approval.actions.approve') }}</button>
                            <button v-if="p.approval_status === 'submitted'" @click="reject(p)" class="text-rose-600 hover:underline">{{ t('hrm.approval.actions.reject') }}</button>
                            <button v-if="p.approval_status === 'rejected' && !p.is_locked" @click="act(p, 'reopen')" class="text-amber-600 hover:underline">{{ t('hrm.approval.actions.reopen') }}</button>
                        </td>
                    </tr>
                    <tr v-if="!loading && rows.length === 0">
                        <td colspan="7" class="py-12 text-center text-sm text-slate-400">{{ t('hrm.approval.empty') }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { payrollApprovalService } from '@/services/hrmService';
import { useCurrency } from '@/composables/useCurrency';
import { useAlert } from '@/composables/useAlert';

const { t, locale } = useI18n();
const { fmtCurrency } = useCurrency();
const { confirm, toast, prompt } = useAlert();

const statuses = ['draft', 'submitted', 'approved', 'rejected'];
const activeStatus = ref('submitted');
const rows = ref([]);
const counts = ref({});
const loading = ref(false);

function formatDate(d) {
    return new Intl.DateTimeFormat(locale.value || 'de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(new Date(d));
}

function paymentBadge(s) {
    return {
        pending:        'bg-slate-100 text-slate-700',
        paid:           'bg-emerald-100 text-emerald-800',
        partially_paid: 'bg-amber-100 text-amber-800',
        cancelled:      'bg-rose-100 text-rose-800',
    }[s] ?? 'bg-slate-100 text-slate-700';
}

async function load() {
    loading.value = true;
    try {
        const [{ data: list }, { data: c }] = await Promise.all([
            payrollApprovalService.queue({ approval_status: activeStatus.value, per_page: 50 }),
            payrollApprovalService.counts(),
        ]);
        rows.value = list.data ?? [];
        counts.value = c.data ?? {};
    } finally {
        loading.value = false;
    }
}

async function act(p, action) {
    const labelKey = `hrm.approval.confirms.${action}`;
    if (!(await confirm(t(labelKey, { number: p.payslip_number })))) return;
    await payrollApprovalService[action](p.id);
    toast.success(t('common.updated'));
    load();
}

async function reject(p) {
    const reason = window.prompt(t('hrm.approval.actions.rejectReason')); // simple inline prompt
    if (!reason) return;
    await payrollApprovalService.reject(p.id, { reason });
    toast.success(t('common.updated'));
    load();
}

onMounted(load);
</script>

<style scoped>
@reference '../../../css/app.css';
.card { @apply bg-white border border-slate-200 rounded-xl shadow-sm; }
</style>
