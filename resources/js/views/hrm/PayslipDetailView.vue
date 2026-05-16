<template>
    <div class="p-6 lg:p-8 max-w-5xl mx-auto space-y-6">

        <div class="flex items-center gap-3 print:hidden">
            <RouterLink :to="backRoute" class="p-2 text-slate-400 hover:text-slate-700 hover:bg-slate-100 rounded-lg">
                <ArrowLeftIcon class="w-5 h-5" />
            </RouterLink>
            <div class="flex-1 min-w-0">
                <h1 class="text-xl font-bold text-slate-900 tracking-tight">
                    {{ t('hrm.payroll.payslip') }}
                    <span class="text-indigo-600 font-mono ml-2">{{ payslip?.payslip_number }}</span>
                </h1>
                <p class="text-sm text-slate-500">{{ payslip?.period_label }}</p>
            </div>
            <div class="flex items-center gap-2">
                <button @click="printPayslip" class="btn-soft">
                    <PrinterIcon class="w-4 h-4" />
                    {{ t('common.print') }}
                </button>
                <button
                    v-if="payslip && payslip.status !== 'paid'"
                    @click="payModalOpen = true"
                    class="btn-primary"
                >
                    <BanknotesIcon class="w-4 h-4" />
                    {{ t('hrm.payroll.markPaid') }}
                </button>
            </div>
        </div>

        <div v-if="loading" class="text-center py-16 text-slate-400">
            <div class="w-8 h-8 border-2 border-indigo-500 border-t-transparent rounded-full animate-spin mx-auto mb-3"></div>
            <p class="text-sm">{{ t('common.loading') }}</p>
        </div>

        <article v-else-if="payslip" id="payslip-doc" class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">

            <div class="bg-gradient-to-r from-indigo-50 to-slate-50 px-6 py-6 border-b border-slate-100">
                <div class="flex flex-col sm:flex-row items-start sm:items-center gap-5">
                    <EmployeeAvatar :src="payslip.employee?.photo_url" :name="payslip.employee?.full_name" size="lg" />
                    <div class="flex-1 min-w-0">
                        <h2 class="text-xl font-bold text-slate-900">{{ payslip.employee?.full_name }}</h2>
                        <p class="text-sm text-slate-600">
                            {{ payslip.employee?.designation ?? '—' }}
                            <span v-if="payslip.employee?.department"> · {{ payslip.employee.department }}</span>
                        </p>
                        <p class="text-xs text-slate-500 font-mono mt-1">{{ payslip.employee?.employee_id }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-[10px] uppercase tracking-wider text-indigo-600 font-semibold mb-1">{{ t('hrm.payroll.period') }}</p>
                        <p class="text-sm font-medium text-slate-800">
                            {{ formatDate(payslip.period_start) }} - {{ formatDate(payslip.period_end) }}
                        </p>
                        <p class="text-xs text-slate-500 mt-1" v-if="payslip.branch_name">{{ payslip.branch_name }}</p>
                    </div>
                </div>
            </div>

            <div class="px-6 py-4 border-b border-slate-100 grid grid-cols-2 md:grid-cols-6 gap-4 text-center">
                <DayBox :label="t('hrm.payroll.daysWorked')"  :value="payslip.days_worked" color="emerald" />
                <DayBox :label="t('hrm.payroll.daysAbsent')"  :value="payslip.days_absent" color="rose" />
                <DayBox :label="t('hrm.payroll.daysLeave')"   :value="payslip.days_leave" color="amber" />
                <DayBox :label="t('hrm.payroll.daysLate')"    :value="payslip.days_late" color="orange" />
                <DayBox :label="t('hrm.payroll.daysHalf')"    :value="payslip.days_half" color="indigo" />
                <DayBox :label="t('hrm.payroll.totalDays')"   :value="payslip.days_in_period" color="slate" />
            </div>

            <div class="px-6 py-6 space-y-5">
                <ItemBlock
                    :title="t('hrm.payroll.allowances')"
                    type="allowance"
                    :items="itemsByType.allowance"
                    :total="payslip.total_allowances"
                    color-tag="text-emerald-700"
                    @add="openAddItem('allowance')"
                    @remove="removeItem"
                />
                <ItemBlock
                    :title="t('hrm.payroll.bonuses')"
                    type="bonus"
                    :items="itemsByType.bonus"
                    :total="payslip.total_bonuses"
                    color-tag="text-emerald-700"
                    @add="openAddItem('bonus')"
                    @remove="removeItem"
                />
                <ItemBlock
                    :title="t('hrm.payroll.overtime')"
                    type="overtime"
                    :items="itemsByType.overtime"
                    :total="payslip.total_overtime"
                    color-tag="text-emerald-700"
                    @add="openAddItem('overtime')"
                    @remove="removeItem"
                />
                <ItemBlock
                    :title="t('hrm.payroll.deductions')"
                    type="deduction"
                    :items="itemsByType.deduction"
                    :total="payslip.total_deductions"
                    color-tag="text-rose-700"
                    @add="openAddItem('deduction')"
                    @remove="removeItem"
                />
                <ItemBlock
                    :title="t('hrm.payroll.tax')"
                    type="tax"
                    :items="itemsByType.tax"
                    :total="payslip.tax_amount"
                    color-tag="text-rose-700"
                    @add="openAddItem('tax')"
                    @remove="removeItem"
                />
            </div>

            <div class="px-6 py-5 bg-slate-50/70 border-t border-slate-100">
                <dl class="space-y-2 max-w-md ml-auto text-sm">
                    <div class="flex justify-between text-slate-700">
                        <dt>{{ t('hrm.payroll.basicSalary') }}</dt>
                        <dd class="font-mono">{{ fmtCurrency(payslip.basic_salary) }}</dd>
                    </div>
                    <div class="flex justify-between text-emerald-700">
                        <dt>+ {{ t('hrm.payroll.totalEarnings') }}</dt>
                        <dd class="font-mono">{{ fmtCurrency(payslip.total_allowances + payslip.total_bonuses + payslip.total_overtime) }}</dd>
                    </div>
                    <div class="flex justify-between text-slate-700 pt-2 border-t border-slate-200">
                        <dt class="font-semibold">{{ t('hrm.payroll.gross') }}</dt>
                        <dd class="font-mono font-semibold">{{ fmtCurrency(payslip.gross_salary) }}</dd>
                    </div>
                    <div class="flex justify-between text-rose-700">
                        <dt>- {{ t('hrm.payroll.deductions') }}</dt>
                        <dd class="font-mono">{{ fmtCurrency(payslip.total_deductions) }}</dd>
                    </div>
                    <div class="flex justify-between text-rose-700">
                        <dt>- {{ t('hrm.payroll.tax') }}</dt>
                        <dd class="font-mono">{{ fmtCurrency(payslip.tax_amount) }}</dd>
                    </div>
                    <div class="flex justify-between items-center pt-3 mt-1 border-t-2 border-indigo-300">
                        <dt class="font-bold text-slate-900 text-base">{{ t('hrm.payroll.net') }}</dt>
                        <dd class="font-mono font-bold text-indigo-700 text-lg">{{ fmtCurrency(payslip.net_salary) }}</dd>
                    </div>
                </dl>
            </div>

            <div v-if="payslip.payment_date" class="px-6 py-4 border-t border-slate-100 bg-emerald-50/40 text-sm flex items-center justify-between flex-wrap gap-2">
                <span class="text-emerald-700">
                    <span class="font-semibold">{{ t('hrm.payroll.paidOn') }}:</span> {{ formatDate(payslip.payment_date) }}
                    <span v-if="payslip.payment_method"> · {{ t('paymentMethod.' + payslip.payment_method) }}</span>
                    <span v-if="payslip.payment_reference"> · {{ payslip.payment_reference }}</span>
                </span>
                <span class="text-emerald-800 font-semibold font-mono">{{ fmtCurrency(payslip.paid_amount) }}</span>
            </div>
        </article>

        <Modal v-model="itemModalOpen" :title="t('hrm.payroll.addItem')" size="md">
            <form @submit.prevent="addItem" id="item-form" class="space-y-4">
                <div>
                    <label class="lbl">{{ t('hrm.payroll.itemType') }}</label>
                    <select v-model="itemForm.type" class="ctrl" disabled>
                        <option value="allowance">{{ t('hrm.payroll.allowance') }}</option>
                        <option value="bonus">{{ t('hrm.payroll.bonus') }}</option>
                        <option value="overtime">{{ t('hrm.payroll.overtime_one') }}</option>
                        <option value="deduction">{{ t('hrm.payroll.deduction') }}</option>
                        <option value="tax">{{ t('hrm.payroll.tax_one') }}</option>
                    </select>
                </div>
                <div>
                    <label class="lbl">{{ t('hrm.payroll.itemName') }} <span class="text-rose-500">*</span></label>
                    <input v-model="itemForm.name" type="text" class="ctrl" required />
                </div>
                <div>
                    <label class="lbl">{{ t('hrm.payroll.itemAmount') }} <span class="text-rose-500">*</span></label>
                    <input v-model.number="itemForm.amount" type="number" min="0" step="0.01" class="ctrl text-right font-mono" required />
                </div>
                <div>
                    <label class="lbl">{{ t('hrm.payroll.itemNotes') }}</label>
                    <input v-model="itemForm.notes" type="text" class="ctrl" />
                </div>
            </form>
            <template #footer>
                <button @click="itemModalOpen = false" class="btn-soft">{{ t('common.cancel') }}</button>
                <button type="submit" form="item-form" :disabled="itemSaving" class="btn-primary">
                    {{ itemSaving ? t('common.saving') : t('common.save') }}
                </button>
            </template>
        </Modal>

        <Modal v-model="payModalOpen" :title="t('hrm.payroll.markPaidTitle')" size="md">
            <form @submit.prevent="pay" id="pay-form" class="space-y-4">
                <div>
                    <label class="lbl">{{ t('hrm.payroll.paidAmount') }}</label>
                    <input v-model.number="payForm.paid_amount" type="number" min="0" step="0.01" class="ctrl text-right font-mono" />
                    <p class="text-[11px] text-slate-400 mt-1">{{ t('hrm.payroll.paidAmountHint', { amount: fmtCurrency(payslip?.net_salary ?? 0) }) }}</p>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="lbl">{{ t('hrm.payroll.paymentDate') }}</label>
                        <input v-model="payForm.payment_date" type="date" class="ctrl" />
                    </div>
                    <div>
                        <label class="lbl">{{ t('hrm.payroll.paymentMethod') }}</label>
                        <select v-model="payForm.payment_method" class="ctrl">
                            <option value="bank_transfer">{{ t('paymentMethod.bank_transfer') }}</option>
                            <option value="cash">{{ t('paymentMethod.cash') }}</option>
                            <option value="card">{{ t('paymentMethod.card') }}</option>
                            <option value="other">{{ t('paymentMethod.other') }}</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="lbl">{{ t('hrm.payroll.paymentReference') }}</label>
                    <input v-model="payForm.payment_reference" type="text" class="ctrl" />
                </div>
            </form>
            <template #footer>
                <button @click="payModalOpen = false" class="btn-soft">{{ t('common.cancel') }}</button>
                <button type="submit" form="pay-form" :disabled="paying" class="btn-primary">
                    {{ paying ? t('common.saving') : t('hrm.payroll.confirmPayment') }}
                </button>
            </template>
        </Modal>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, h } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute, RouterLink } from 'vue-router';
import { payslipService } from '@/services/hrmService';
import { useAlert } from '@/composables/useAlert';
import { useCurrency } from '@/composables/useCurrency';
import EmployeeAvatar from '@/components/hrm/EmployeeAvatar.vue';
import Modal from '@/components/ui/Modal.vue';
import {
    ArrowLeftIcon, BanknotesIcon, PrinterIcon, PlusIcon, XMarkIcon,
} from '@heroicons/vue/24/outline';

const { t } = useI18n();
const route = useRoute();
const { toast } = useAlert();
const { fmtCurrency } = useCurrency();

const payslip = ref(null);
const loading = ref(true);

const itemModalOpen = ref(false);
const itemSaving = ref(false);
const itemForm = ref({ type: 'allowance', name: '', amount: 0, notes: '' });

const payModalOpen = ref(false);
const paying = ref(false);
const payForm = ref({
    paid_amount: 0,
    payment_date: new Date().toISOString().slice(0, 10),
    payment_method: 'bank_transfer',
    payment_reference: '',
});

const itemsByType = computed(() => {
    const out = { allowance: [], bonus: [], overtime: [], deduction: [], tax: [] };
    for (const i of payslip.value?.items ?? []) {
        if (out[i.type]) out[i.type].push(i);
    }
    return out;
});

const backRoute = computed(() => payslip.value?.payroll_period_id
    ? { name: 'hrm-payroll-period', params: { id: payslip.value.payroll_period_id } }
    : { name: 'hrm-payroll' }
);

const ItemBlock = (props, { emit }) => {
    return h('section', {}, [
        h('div', { class: 'flex items-center justify-between mb-2' }, [
            h('h3', { class: 'text-xs font-bold text-slate-500 uppercase tracking-wider' }, [
                props.title,
                ' ',
                h('span', { class: `font-mono ml-2 ${props.colorTag}` }, fmtCurrency(props.total || 0)),
            ]),
            h('button', {
                onClick: () => emit('add'),
                class: 'inline-flex items-center gap-1 px-2 py-1 text-[11px] font-semibold text-indigo-700 hover:bg-indigo-50 rounded print:hidden',
            }, [h(PlusIcon, { class: 'w-3.5 h-3.5' }), t('hrm.payroll.addLine')]),
        ]),
        props.items.length === 0
            ? h('p', { class: 'text-xs text-slate-400 italic' }, t('hrm.payroll.noItems'))
            : h('div', { class: 'space-y-1' }, props.items.map(i =>
                h('div', { key: i.id, class: 'flex items-center justify-between bg-slate-50/60 rounded px-3 py-1.5 text-sm' }, [
                    h('div', { class: 'flex-1 min-w-0' }, [
                        h('p', { class: 'text-slate-800 font-medium' }, i.name),
                        i.notes ? h('p', { class: 'text-xs text-slate-500' }, i.notes) : null,
                    ]),
                    h('span', { class: `font-mono font-medium ${props.colorTag}` }, fmtCurrency(i.amount)),
                    h('button', {
                        onClick: () => emit('remove', i.id),
                        class: 'p-1 ml-2 text-slate-300 hover:text-rose-500 rounded print:hidden',
                    }, h(XMarkIcon, { class: 'w-4 h-4' })),
                ])
            )),
    ]);
};
ItemBlock.props = ['title', 'type', 'items', 'total', 'colorTag'];
ItemBlock.emits = ['add', 'remove'];

const DayBox = (props) => h('div', { class: `rounded-lg p-2 bg-${props.color}-50/50` }, [
    h('p', { class: 'text-[10px] uppercase tracking-wider text-slate-500' }, props.label),
    h('p', { class: `text-lg font-bold text-${props.color}-700 mt-0.5 font-mono` }, String(props.value ?? 0)),
]);
DayBox.props = ['label', 'value', 'color'];

function formatDate(s) {
    return s ? new Date(s + 'T00:00:00').toLocaleDateString('de-DE') : '';
}

function openAddItem(type) {
    itemForm.value = { type, name: '', amount: 0, notes: '' };
    itemModalOpen.value = true;
}

async function addItem() {
    itemSaving.value = true;
    try {
        const { data } = await payslipService.addItem(route.params.id, itemForm.value);
        payslip.value = data.data ?? data;
        itemModalOpen.value = false;
        toast('success', t('common.createdSuccess'));
    } catch (err) {
        toast('error', err.response?.data?.message ?? t('common.unexpectedError'));
    } finally {
        itemSaving.value = false;
    }
}

async function removeItem(itemId) {
    try {
        const { data } = await payslipService.removeItem(route.params.id, itemId);
        payslip.value = data.data ?? data;
    } catch (err) {
        toast('error', err.response?.data?.message ?? t('common.unexpectedError'));
    }
}

async function pay() {
    paying.value = true;
    try {
        const payload = { ...payForm.value };
        if (!payload.paid_amount) payload.paid_amount = payslip.value.net_salary;
        const { data } = await payslipService.pay(route.params.id, payload);
        payslip.value = data.data ?? data;
        payModalOpen.value = false;
        toast('success', t('hrm.payroll.paidSuccess'));
    } catch (err) {
        toast('error', err.response?.data?.message ?? t('common.unexpectedError'));
    } finally {
        paying.value = false;
    }
}

function printPayslip() {
    window.print();
}

async function load() {
    try {
        const { data } = await payslipService.show(route.params.id);
        payslip.value = data.data ?? data;
        payForm.value.paid_amount = Number(payslip.value.net_salary) || 0;
    } catch (err) {
        toast('error', err.response?.data?.message ?? t('common.unexpectedError'));
    } finally {
        loading.value = false;
    }
}

onMounted(load);
</script>

<style scoped>
@reference '../../../css/app.css';
.btn-primary { @apply inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg transition-colors shadow-sm disabled:opacity-50; }
.btn-soft    { @apply inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-slate-300 text-slate-700 text-sm hover:bg-slate-50 transition-colors; }
.lbl         { @apply block text-xs font-medium text-slate-600 mb-1.5; }
.ctrl        { @apply w-full px-3 py-2 text-sm border border-slate-200 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent disabled:bg-slate-50; }
.err         { @apply text-xs text-rose-600 mt-1; }
</style>

<style>
@media print {
    @page { size: A4 portrait; margin: 12mm; }
    body { background: white !important; }
    .print\:hidden { display: none !important; }
    #payslip-doc { box-shadow: none !important; border: none !important; }
}
</style>
