<template>
    <Modal v-model="visible" :title="title" size="md" persistent>
        <div v-if="mode === 'reject'" class="space-y-4">
            <p class="text-sm text-slate-600">{{ t('expenses.approval.rejectIntro', { number: expense?.expense_number }) }}</p>
            <div>
                <label class="lbl">{{ t('expenses.approval.reason') }} <span class="text-rose-500">*</span></label>
                <textarea v-model="reason" rows="3" class="ctrl resize-none" :placeholder="t('expenses.approval.reasonPh')" required></textarea>
            </div>
        </div>

        <div v-else-if="mode === 'pay'" class="space-y-4">
            <p class="text-sm text-slate-600">
                {{ t('expenses.approval.payIntro', { number: expense?.expense_number, amount: fmtCurrency(expense?.amount) }) }}
            </p>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="lbl">{{ t('expenses.approval.paidAt') }}</label>
                    <input v-model="payForm.paid_at" type="date" class="ctrl" />
                </div>
                <div>
                    <label class="lbl">{{ t('expenses.fields.payment_method') }}</label>
                    <select v-model="payForm.payment_method" class="ctrl">
                        <option value="cash">{{ t('paymentMethod.cash') }}</option>
                        <option value="card">{{ t('paymentMethod.card') }}</option>
                        <option value="bank_transfer">{{ t('paymentMethod.bank_transfer') }}</option>
                        <option value="cheque">{{ t('expenses.paymentMethod.cheque') }}</option>
                        <option value="other">{{ t('paymentMethod.other') }}</option>
                    </select>
                </div>
            </div>
            <div>
                <label class="lbl">{{ t('expenses.fields.reference_no') }}</label>
                <input v-model="payForm.reference_no" type="text" class="ctrl" />
            </div>
            <div>
                <label class="lbl">{{ t('expenses.approval.notes') }}</label>
                <textarea v-model="payForm.notes" rows="2" class="ctrl resize-none"></textarea>
            </div>
        </div>

        <template #footer>
            <button @click="close" class="btn-soft">{{ t('common.cancel') }}</button>
            <button @click="submit" :disabled="saving || (mode === 'reject' && !reason.trim())"
                    :class="['btn-action', mode === 'reject' ? 'bg-rose-600 hover:bg-rose-700' : 'bg-emerald-600 hover:bg-emerald-700']">
                <CheckIcon v-if="!saving" class="w-4 h-4" />
                <ArrowPathIcon v-else class="w-4 h-4 animate-spin" />
                {{ saving ? t('common.saving') : (mode === 'reject' ? t('expenses.approval.confirmReject') : t('expenses.approval.confirmPay')) }}
            </button>
        </template>
    </Modal>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { expenseService } from '@/services/expenseService';
import { useAlert } from '@/composables/useAlert';
import { useCurrency } from '@/composables/useCurrency';
import Modal from '@/components/ui/Modal.vue';
import { CheckIcon, ArrowPathIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    modelValue: { type: Boolean, required: true },
    mode:       { type: String, default: 'reject' },   // reject | pay
    expense:    { type: Object, default: null },
});
const emit = defineEmits(['update:modelValue', 'done']);

const { t } = useI18n();
const { toast } = useAlert();
const { fmtCurrency } = useCurrency();

const visible = computed({
    get: () => props.modelValue,
    set: (v) => emit('update:modelValue', v),
});

const title = computed(() =>
    props.mode === 'reject' ? t('expenses.approval.rejectTitle') : t('expenses.approval.payTitle')
);

const reason = ref('');
const payForm = ref({
    paid_at: new Date().toISOString().slice(0, 10),
    payment_method: 'bank_transfer',
    reference_no: '',
    notes: '',
});
const saving = ref(false);

watch(() => props.modelValue, (open) => {
    if (!open) return;
    saving.value = false;
    if (props.mode === 'reject') {
        reason.value = '';
    } else {
        payForm.value = {
            paid_at: new Date().toISOString().slice(0, 10),
            payment_method: props.expense?.payment_method || 'bank_transfer',
            reference_no: props.expense?.reference_no || '',
            notes: '',
        };
    }
});

function close() {
    if (saving.value) return;
    visible.value = false;
}

async function submit() {
    if (!props.expense) return;
    saving.value = true;
    try {
        if (props.mode === 'reject') {
            await expenseService.reject(props.expense.id, reason.value.trim());
            toast('success', t('expenses.approval.rejectedSuccess'));
        } else {
            await expenseService.markPaid(props.expense.id, payForm.value);
            toast('success', t('expenses.approval.paidSuccess'));
        }
        emit('done');
        visible.value = false;
    } catch (err) {
        toast('error', err.response?.data?.message ?? t('common.unexpectedError'));
    } finally {
        saving.value = false;
    }
}
</script>

<style scoped>
@reference '../../../css/app.css';
.btn-action  { @apply inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white rounded-lg transition-colors shadow-sm disabled:opacity-50 disabled:cursor-not-allowed; }
.btn-soft    { @apply inline-flex items-center px-4 py-2 rounded-lg border border-slate-300 text-slate-700 text-sm hover:bg-slate-50 transition-colors; }
.lbl         { @apply block text-xs font-medium text-slate-600 mb-1.5; }
.ctrl        { @apply w-full px-3 py-2 text-sm border border-slate-200 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent; }
</style>
