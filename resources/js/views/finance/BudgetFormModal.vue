<template>
    <Modal v-model="visible" :title="title" size="xl" persistent>
        <form id="budget-form" @submit.prevent="save" class="space-y-5">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="lbl">{{ t('finance.budget.fields.title') }} <span class="text-rose-500">*</span></label>
                    <input v-model="form.title" type="text" class="ctrl" required />
                    <p v-if="errors.title" class="err">{{ errors.title }}</p>
                </div>
                <div>
                    <label class="lbl">{{ t('finance.budget.fields.fiscal_year') }} <span class="text-rose-500">*</span></label>
                    <input v-model.number="form.fiscal_year" type="number" min="2000" max="2100" class="ctrl" required />
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="lbl">{{ t('finance.budget.fields.start_date') }} <span class="text-rose-500">*</span></label>
                    <input v-model="form.start_date" type="date" class="ctrl" required />
                </div>
                <div>
                    <label class="lbl">{{ t('finance.budget.fields.end_date') }} <span class="text-rose-500">*</span></label>
                    <input v-model="form.end_date" type="date" class="ctrl" required />
                </div>
                <div>
                    <label class="lbl">{{ t('finance.budget.fields.branch') }}</label>
                    <select v-model.number="form.branch_id" class="ctrl">
                        <option :value="null">{{ t('finance.budget.allBranches') }}</option>
                        <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="lbl">{{ t('finance.budget.fields.total') }} <span class="text-rose-500">*</span></label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-400">{{ currencySymbol }}</span>
                        <input v-model.number="form.total_budget" type="number" min="0" step="0.01" class="ctrl pl-7 text-right font-mono" required />
                    </div>
                </div>
                <div>
                    <label class="lbl">{{ t('finance.budget.fields.threshold') }}</label>
                    <div class="relative">
                        <input v-model.number="form.warning_threshold_percent" type="number" min="0" max="100" class="ctrl pr-8 text-right font-mono" />
                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-slate-400">%</span>
                    </div>
                    <p class="text-[11px] text-slate-400 mt-1">{{ t('finance.budget.thresholdHint') }}</p>
                </div>
                <div>
                    <label class="lbl">{{ t('common.status') }}</label>
                    <select v-model="form.status" class="ctrl">
                        <option value="draft">{{ t('finance.budget.status_draft') }}</option>
                        <option value="active">{{ t('finance.budget.status_active') }}</option>
                        <option value="archived">{{ t('finance.budget.status_archived') }}</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="lbl">{{ t('finance.budget.fields.notes') }}</label>
                <textarea v-model="form.notes" rows="2" class="ctrl resize-none"></textarea>
            </div>

            <div class="rounded-lg border border-slate-200 bg-slate-50/60 p-4 space-y-3">
                <div class="flex items-center justify-between flex-wrap gap-2">
                    <h3 class="text-xs font-bold text-slate-500 uppercase tracking-wider">{{ t('finance.budget.allocations') }}</h3>
                    <div class="flex items-center gap-3 text-xs">
                        <span class="text-slate-500">{{ t('finance.budget.allocatedSoFar') }}: <span class="font-mono font-semibold" :class="allocationOk ? 'text-emerald-700' : 'text-rose-700'">{{ fmtCurrency(allocatedSum) }}</span></span>
                        <span class="text-slate-500">/ {{ fmtCurrency(form.total_budget || 0) }}</span>
                    </div>
                </div>

                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-[11px] text-slate-500 uppercase tracking-wide">
                            <th class="py-1.5">{{ t('finance.budget.category') }}</th>
                            <th class="py-1.5 text-right w-44">{{ t('finance.budget.allocated') }}</th>
                            <th class="w-10"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="(item, idx) in form.items" :key="idx">
                            <td class="py-1.5 pr-2">
                                <select v-model.number="item.expense_category_id" class="ctrl">
                                    <option :value="null">—</option>
                                    <option v-for="c in availableCategories(item)" :key="c.id" :value="c.id">{{ c.name }}</option>
                                </select>
                            </td>
                            <td class="py-1.5">
                                <div class="relative">
                                    <span class="absolute left-2 top-1/2 -translate-y-1/2 text-xs text-slate-400">{{ currencySymbol }}</span>
                                    <input v-model.number="item.allocated_amount" type="number" min="0" step="0.01" class="ctrl pl-6 text-right font-mono" />
                                </div>
                            </td>
                            <td class="py-1.5 text-center">
                                <button @click="removeItem(idx)" type="button" class="p-1 text-slate-300 hover:text-rose-500 rounded">
                                    <XMarkIcon class="w-4 h-4" />
                                </button>
                            </td>
                        </tr>
                        <tr v-if="form.items.length === 0">
                            <td colspan="3" class="py-6 text-center text-sm text-slate-400">
                                {{ t('finance.budget.noAllocations') }}
                            </td>
                        </tr>
                    </tbody>
                </table>

                <button type="button" @click="addItem" class="text-xs font-semibold text-indigo-700 hover:bg-indigo-50 rounded px-2 py-1 inline-flex items-center gap-1">
                    <PlusIcon class="w-3.5 h-3.5" />
                    {{ t('finance.budget.addAllocation') }}
                </button>

                <p v-if="!allocationOk" class="text-xs text-rose-600">
                    {{ t('finance.budget.overAllocated') }}
                </p>
            </div>
        </form>

        <template #footer>
            <button @click="close" class="btn-soft">{{ t('common.cancel') }}</button>
            <button type="submit" form="budget-form" :disabled="saving || !allocationOk" class="btn-primary">
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
import { budgetService } from '@/services/financeService';
import { useAlert } from '@/composables/useAlert';
import { useCurrency } from '@/composables/useCurrency';
import Modal from '@/components/ui/Modal.vue';
import { PlusIcon, XMarkIcon, CheckIcon, ArrowPathIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    modelValue: { type: Boolean, required: true },
    budget:     { type: Object, default: null },
    categories: { type: Array, default: () => [] },
    branches:   { type: Array, default: () => [] },
});
const emit = defineEmits(['update:modelValue', 'saved']);

const { t } = useI18n();
const { toast } = useAlert();
const { fmtCurrency, currencySymbol } = useCurrency();

const visible = computed({
    get: () => props.modelValue,
    set: (v) => emit('update:modelValue', v),
});

const editing = computed(() => !!props.budget);
const title = computed(() => editing.value ? t('finance.budget.editTitle') : t('finance.budget.createTitle'));

const saving = ref(false);
const errors = ref({});
const form = ref(blankForm());

function blankForm() {
    const year = new Date().getFullYear();
    return {
        title: '',
        fiscal_year: year,
        start_date: `${year}-01-01`,
        end_date:   `${year}-12-31`,
        branch_id: null,
        total_budget: 0,
        warning_threshold_percent: 80,
        status: 'draft',
        notes: '',
        items: [],
    };
}

const allocatedSum = computed(() =>
    form.value.items.reduce((s, i) => s + (Number(i.allocated_amount) || 0), 0)
);

const allocationOk = computed(() =>
    allocatedSum.value <= (Number(form.value.total_budget) || 0) + 0.001
);

function availableCategories(currentItem) {
    const usedIds = form.value.items
        .filter(i => i !== currentItem)
        .map(i => i.expense_category_id)
        .filter(Boolean);
    return props.categories.filter(c => !usedIds.includes(c.id));
}

watch(() => props.modelValue, (open) => {
    if (!open) return;
    errors.value = {};
    if (props.budget) {
        const b = props.budget;
        form.value = {
            title: b.title,
            fiscal_year: b.fiscal_year,
            start_date: b.start_date,
            end_date: b.end_date,
            branch_id: b.branch_id,
            total_budget: Number(b.total_budget) || 0,
            warning_threshold_percent: b.warning_threshold_percent ?? 80,
            status: b.status,
            notes: b.notes ?? '',
            items: (b.items ?? []).map(i => ({
                expense_category_id: i.expense_category_id,
                allocated_amount: Number(i.allocated_amount) || 0,
            })),
        };
    } else {
        form.value = blankForm();
        if (props.branches.length === 1) form.value.branch_id = props.branches[0].id;
    }
});

function addItem() {
    form.value.items.push({ expense_category_id: null, allocated_amount: 0 });
}

function removeItem(idx) {
    form.value.items.splice(idx, 1);
}

function close() {
    if (saving.value) return;
    visible.value = false;
}

async function save() {
    errors.value = {};
    saving.value = true;
    try {
        const payload = {
            ...form.value,
            items: form.value.items.filter(i => i.expense_category_id && i.allocated_amount > 0),
        };
        if (editing.value) {
            await budgetService.update(props.budget.id, payload);
            toast('success', t('common.updatedSuccess'));
        } else {
            await budgetService.store(payload);
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
.btn-primary { @apply inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg transition-colors shadow-sm disabled:opacity-50 disabled:cursor-not-allowed; }
.btn-soft    { @apply inline-flex items-center px-4 py-2 rounded-lg border border-slate-300 text-slate-700 text-sm hover:bg-slate-50 transition-colors; }
.lbl         { @apply block text-xs font-medium text-slate-600 mb-1.5; }
.ctrl        { @apply w-full px-3 py-2 text-sm border border-slate-200 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent; }
.err         { @apply text-xs text-rose-600 mt-1; }
</style>
