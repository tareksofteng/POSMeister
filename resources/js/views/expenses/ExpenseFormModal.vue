<template>
    <Modal v-model="visible" :title="title" size="lg" persistent>
        <div class="space-y-5">
            <form id="expense-form" @submit.prevent="save" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="lbl">{{ t('expenses.fields.title') }} <span class="text-rose-500">*</span></label>
                        <input v-model="form.title" type="text" class="ctrl" required />
                        <p v-if="errors.title" class="err">{{ errors.title }}</p>
                    </div>
                    <div>
                        <label class="lbl">{{ t('expenses.fields.category') }} <span class="text-rose-500">*</span></label>
                        <select v-model.number="form.expense_category_id" class="ctrl" required>
                            <option :value="null">—</option>
                            <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                        <p v-if="errors.expense_category_id" class="err">{{ errors.expense_category_id }}</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="lbl">{{ t('expenses.fields.amount') }} <span class="text-rose-500">*</span></label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-400">{{ currencySymbol }}</span>
                            <input v-model.number="form.amount" type="number" min="0.01" step="0.01" class="ctrl pl-7 text-right font-mono" required />
                        </div>
                        <p v-if="errors.amount" class="err">{{ errors.amount }}</p>
                    </div>
                    <div>
                        <label class="lbl">{{ t('expenses.fields.date') }} <span class="text-rose-500">*</span></label>
                        <input v-model="form.expense_date" type="date" class="ctrl" required />
                        <p v-if="errors.expense_date" class="err">{{ errors.expense_date }}</p>
                    </div>
                    <div>
                        <label class="lbl">{{ t('expenses.fields.payment_method') }}</label>
                        <select v-model="form.payment_method" class="ctrl">
                            <option value="cash">{{ t('paymentMethod.cash') }}</option>
                            <option value="card">{{ t('paymentMethod.card') }}</option>
                            <option value="bank_transfer">{{ t('paymentMethod.bank_transfer') }}</option>
                            <option value="cheque">{{ t('expenses.paymentMethod.cheque') }}</option>
                            <option value="other">{{ t('paymentMethod.other') }}</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="lbl">{{ t('expenses.fields.reference_no') }}</label>
                        <input v-model="form.reference_no" type="text" class="ctrl" placeholder="Belegnummer, Quittung, ..." />
                    </div>
                    <div v-if="branches.length > 1">
                        <label class="lbl">{{ t('expenses.fields.branch') }}</label>
                        <select v-model.number="form.branch_id" class="ctrl">
                            <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="lbl">{{ t('expenses.fields.description') }}</label>
                    <textarea v-model="form.description" rows="2" class="ctrl resize-none"></textarea>
                </div>

                <div>
                    <label class="lbl">{{ t('expenses.fields.attachment') }}</label>
                    <div class="flex items-center gap-3">
                        <input ref="fileInput" type="file" accept="image/png,image/jpeg,image/webp,application/pdf" @change="onFileChange" class="hidden" />
                        <button type="button" @click="fileInput?.click()" class="btn-soft">
                            <ArrowUpTrayIcon class="w-4 h-4" />
                            {{ attachmentFile || existingAttachment ? t('expenses.changeAttachment') : t('expenses.uploadAttachment') }}
                        </button>
                        <span v-if="attachmentFile" class="text-xs text-slate-600 truncate">{{ attachmentFile.name }}</span>
                        <a v-else-if="existingAttachment" :href="existingAttachment" target="_blank" class="text-xs text-sky-600 hover:underline">
                            {{ t('expenses.viewCurrent') }}
                        </a>
                    </div>
                    <p class="text-[11px] text-slate-400 mt-1">{{ t('expenses.attachmentHint') }}</p>
                </div>

                <div class="rounded-lg border border-slate-200 bg-slate-50/60 p-4 space-y-3">
                    <label class="flex items-center gap-2 text-sm font-medium text-slate-700">
                        <input v-model="form.is_recurring" type="checkbox" class="rounded border-slate-300 text-sky-600 focus:ring-sky-500" />
                        {{ t('expenses.recurring.enable') }}
                    </label>
                    <p class="text-xs text-slate-500">{{ t('expenses.recurring.hint') }}</p>

                    <div v-if="form.is_recurring" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="lbl">{{ t('expenses.recurring.frequency') }} <span class="text-rose-500">*</span></label>
                            <select v-model="form.recurring_frequency" class="ctrl">
                                <option value="weekly">{{ t('expenses.recurring.weekly') }}</option>
                                <option value="monthly">{{ t('expenses.recurring.monthly') }}</option>
                                <option value="yearly">{{ t('expenses.recurring.yearly') }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="lbl">{{ t('expenses.recurring.nextDue') }} <span class="text-rose-500">*</span></label>
                            <input v-model="form.next_due_date" type="date" class="ctrl" />
                        </div>
                        <div>
                            <label class="lbl">{{ t('expenses.recurring.endDate') }}</label>
                            <input v-model="form.recurring_end_date" type="date" :min="form.next_due_date" class="ctrl" />
                        </div>
                    </div>
                </div>
            </form>

            <section v-if="editing" class="border-t border-slate-200 pt-4">
                <h3 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-3">{{ t('expenses.audit.title') }}</h3>
                <ExpenseTimeline :expense-id="expense?.id" />
            </section>
        </div>

        <template #footer>
            <button @click="close" class="btn-soft">{{ t('common.cancel') }}</button>
            <button type="submit" form="expense-form" :disabled="saving" class="btn-primary">
                <CheckIcon v-if="!saving" class="w-4 h-4" />
                <ArrowPathIcon v-else class="w-4 h-4 animate-spin" />
                {{ saving ? t('common.saving') : t('common.save') }}
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
import ExpenseTimeline from './ExpenseTimeline.vue';
import { ArrowUpTrayIcon, CheckIcon, ArrowPathIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    modelValue: { type: Boolean, required: true },
    expense:    { type: Object, default: null },
    categories: { type: Array, default: () => [] },
    branches:   { type: Array, default: () => [] },
});
const emit = defineEmits(['update:modelValue', 'saved']);

const { t } = useI18n();
const { toast } = useAlert();
const { currencySymbol } = useCurrency();

const visible = computed({
    get: () => props.modelValue,
    set: (v) => emit('update:modelValue', v),
});

const editing = computed(() => !!props.expense);
const title = computed(() => editing.value ? t('expenses.editTitle') : t('expenses.createTitle'));

const fileInput = ref(null);
const attachmentFile = ref(null);
const existingAttachment = ref('');

const saving = ref(false);
const errors = ref({});

const form = ref(blankForm());

function blankForm() {
    return {
        title: '',
        expense_category_id: null,
        branch_id: null,
        amount: 0,
        expense_date: new Date().toISOString().slice(0, 10),
        payment_method: 'cash',
        reference_no: '',
        description: '',
        is_recurring: false,
        recurring_frequency: 'monthly',
        next_due_date: '',
        recurring_end_date: '',
    };
}

watch(() => props.modelValue, (open) => {
    if (!open) return;
    errors.value = {};
    attachmentFile.value = null;
    if (fileInput.value) fileInput.value.value = '';

    if (props.expense) {
        const e = props.expense;
        form.value = {
            title: e.title ?? '',
            expense_category_id: e.expense_category_id,
            branch_id: e.branch_id,
            amount: Number(e.amount) || 0,
            expense_date: e.expense_date,
            payment_method: e.payment_method ?? 'cash',
            reference_no: e.reference_no ?? '',
            description: e.description ?? '',
            is_recurring: !!e.is_recurring,
            recurring_frequency: e.recurring_frequency ?? 'monthly',
            next_due_date: e.next_due_date ?? '',
            recurring_end_date: e.recurring_end_date ?? '',
        };
        existingAttachment.value = e.attachment_url || '';
    } else {
        form.value = blankForm();
        existingAttachment.value = '';
        if (props.branches.length === 1) {
            form.value.branch_id = props.branches[0].id;
        }
    }
});

function onFileChange(e) {
    attachmentFile.value = e.target.files?.[0] ?? null;
}

function close() {
    if (saving.value) return;
    visible.value = false;
}

async function save() {
    errors.value = {};
    saving.value = true;

    const fd = new FormData();
    Object.entries(form.value).forEach(([k, v]) => {
        if (v === null || v === undefined || v === '') return;
        if (typeof v === 'boolean') {
            fd.append(k, v ? '1' : '0');
        } else {
            fd.append(k, v);
        }
    });
    // Only send recurring fields when enabled
    if (!form.value.is_recurring) {
        fd.delete('recurring_frequency');
        fd.delete('next_due_date');
        fd.delete('recurring_end_date');
    }
    if (attachmentFile.value) fd.append('attachment', attachmentFile.value);

    try {
        if (editing.value) {
            await expenseService.update(props.expense.id, fd);
            toast('success', t('common.updatedSuccess'));
        } else {
            await expenseService.store(fd);
            toast('success', t('common.createdSuccess'));
        }
        emit('saved');
        close();
    } catch (err) {
        const data = err.response?.data;
        if (data?.errors) {
            Object.entries(data.errors).forEach(([k, v]) => {
                errors.value[k] = Array.isArray(v) ? v[0] : v;
            });
        } else {
            toast('error', data?.message ?? t('common.unexpectedError'));
        }
    } finally {
        saving.value = false;
    }
}
</script>

<style scoped>
@reference '../../../css/app.css';
.btn-primary { @apply inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white bg-sky-600 hover:bg-sky-700 rounded-lg transition-colors shadow-sm disabled:opacity-50; }
.btn-soft    { @apply inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-slate-300 text-slate-700 text-sm hover:bg-slate-50 transition-colors; }
.lbl         { @apply block text-xs font-medium text-slate-600 mb-1.5; }
.ctrl        { @apply w-full px-3 py-2 text-sm border border-slate-200 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent; }
.err         { @apply text-xs text-rose-600 mt-1; }
</style>
