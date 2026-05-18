<template>
    <div class="p-6 lg:p-8 space-y-6">

        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 tracking-tight">{{ t('expenses.title') }}</h1>
                <p class="mt-1 text-sm text-slate-500">{{ t('expenses.subtitle') }}</p>
            </div>
            <button @click="openCreate" class="btn-primary">
                <PlusIcon class="w-4 h-4" />
                {{ t('expenses.add') }}
            </button>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-5 gap-3">
            <SummaryCard :label="t('expenses.summary.total')"    :value="fmtCurrency(summary.total_amount)" tone="slate" />
            <SummaryCard :label="t('expenses.status_pending')"   :value="fmtCurrency(summary.pending)"     tone="amber" />
            <SummaryCard :label="t('expenses.status_approved')"  :value="fmtCurrency(summary.approved)"    tone="indigo" />
            <SummaryCard :label="t('expenses.status_paid')"      :value="fmtCurrency(summary.paid)"        tone="emerald" />
            <SummaryCard :label="t('expenses.status_rejected')"  :value="fmtCurrency(summary.rejected)"    tone="rose" />
        </div>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-4 flex flex-wrap gap-3 items-end">
            <div class="relative flex-1 min-w-[220px]">
                <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 pointer-events-none" />
                <input v-model="searchTerm" type="search" :placeholder="t('expenses.searchPh')" class="ctrl pl-9" />
            </div>
            <select v-model.number="filters.expense_category_id" class="ctrl w-48">
                <option :value="null">{{ t('expenses.filters.allCategories') }}</option>
                <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
            <select v-model="filters.status" class="ctrl w-40">
                <option value="">{{ t('expenses.filters.allStatuses') }}</option>
                <option value="pending">{{ t('expenses.status_pending') }}</option>
                <option value="approved">{{ t('expenses.status_approved') }}</option>
                <option value="paid">{{ t('expenses.status_paid') }}</option>
                <option value="rejected">{{ t('expenses.status_rejected') }}</option>
            </select>
            <div>
                <label class="text-[10px] text-slate-500 block">{{ t('common.dateFrom') }}</label>
                <input v-model="filters.from" type="date" class="ctrl w-40" />
            </div>
            <div>
                <label class="text-[10px] text-slate-500 block">{{ t('common.dateTo') }}</label>
                <input v-model="filters.to" type="date" class="ctrl w-40" />
            </div>
        </div>

        <div v-if="errorMsg" class="rounded-lg bg-rose-50 border border-rose-200 px-4 py-3 text-sm text-rose-700">{{ errorMsg }}</div>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 border-b border-slate-100">
                        <tr>
                            <th class="th">{{ t('expenses.fields.expense_number') }}</th>
                            <th class="th">{{ t('expenses.fields.date') }}</th>
                            <th class="th">{{ t('expenses.fields.title') }}</th>
                            <th class="th">{{ t('expenses.fields.category') }}</th>
                            <th class="th">{{ t('expenses.fields.payment_method') }}</th>
                            <th class="th text-right">{{ t('expenses.fields.amount') }}</th>
                            <th class="th">{{ t('common.status') }}</th>
                            <th class="th w-32 text-right">{{ t('common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-if="loading">
                            <td colspan="8" class="py-12 text-center text-slate-400">
                                <div class="w-6 h-6 border-2 border-sky-500 border-t-transparent rounded-full animate-spin mx-auto mb-2"></div>
                                {{ t('common.loading') }}
                            </td>
                        </tr>
                        <tr v-else-if="expenses.length === 0">
                            <td colspan="8" class="py-16 text-center">
                                <ReceiptPercentIcon class="w-10 h-10 text-slate-300 mx-auto mb-2" />
                                <p class="text-sm text-slate-500">{{ t('expenses.empty') }}</p>
                            </td>
                        </tr>
                        <tr v-else v-for="e in expenses" :key="e.id" class="hover:bg-slate-50/60">
                            <td class="td font-mono text-xs text-slate-600">{{ e.expense_number }}</td>
                            <td class="td text-slate-700">{{ formatDate(e.expense_date) }}</td>
                            <td class="td">
                                <p class="font-medium text-slate-900">{{ e.title }}</p>
                                <p v-if="e.reference_no" class="text-xs text-slate-500 mt-0.5 font-mono">{{ e.reference_no }}</p>
                            </td>
                            <td class="td">
                                <span v-if="e.category" class="inline-flex items-center px-2 py-0.5 rounded-md text-xs bg-sky-50 text-sky-700 border border-sky-100">
                                    {{ e.category.name }}
                                </span>
                            </td>
                            <td class="td text-slate-600 text-xs">{{ paymentLabel(e.payment_method) }}</td>
                            <td class="td text-right font-mono font-semibold text-slate-900">{{ fmtCurrency(e.amount) }}</td>
                            <td class="td"><StatusBadge :status="e.status" /></td>
                            <td class="td">
                                <div class="flex items-center justify-end gap-1">
                                    <a v-if="e.attachment_url" :href="e.attachment_url" target="_blank" class="action-btn" :title="t('expenses.openAttachment')">
                                        <PaperClipIcon class="w-4 h-4" />
                                    </a>
                                    <button @click="openEdit(e)" class="action-btn" :title="t('common.edit')">
                                        <PencilSquareIcon class="w-4 h-4" />
                                    </button>
                                    <button @click="confirmDelete(e)" class="action-btn hover:text-rose-600 hover:bg-rose-50" :title="t('common.delete')">
                                        <TrashIcon class="w-4 h-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="meta && meta.last_page > 1" class="flex items-center justify-between border-t border-slate-100 px-4 py-3">
                <p class="text-xs text-slate-500">
                    {{ t('common.showing') }} {{ meta.from }}–{{ meta.to }} {{ t('common.of') }} {{ meta.total }}
                </p>
                <div class="flex items-center gap-1">
                    <button
                        v-for="p in visiblePages" :key="p"
                        @click="goToPage(p)"
                        :class="['w-8 h-8 text-xs font-medium rounded-lg transition-colors',
                            p === meta.current_page ? 'bg-sky-600 text-white' : 'text-slate-600 hover:bg-slate-100']"
                    >
                        {{ p }}
                    </button>
                </div>
            </div>
        </div>

        <ExpenseFormModal
            v-model="modalOpen"
            :expense="editing"
            :categories="categories"
            :branches="branches"
            @saved="onSaved"
        />
    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, h } from 'vue';
import { useI18n } from 'vue-i18n';
import { expenseService, expenseCategoryService } from '@/services/expenseService';
import { branchService } from '@/services/branchService';
import { useDebounce } from '@vueuse/core';
import { useAlert } from '@/composables/useAlert';
import { useCurrency } from '@/composables/useCurrency';
import ExpenseFormModal from './ExpenseFormModal.vue';
import {
    PlusIcon, MagnifyingGlassIcon, ReceiptPercentIcon,
    PencilSquareIcon, TrashIcon, PaperClipIcon,
} from '@heroicons/vue/24/outline';

const { t } = useI18n();
const { toast, confirm } = useAlert();
const { fmtCurrency } = useCurrency();

const expenses = ref([]);
const categories = ref([]);
const branches = ref([]);
const meta = ref(null);
const summary = ref({ total_amount: 0, pending: 0, approved: 0, paid: 0, rejected: 0 });

const loading = ref(false);
const errorMsg = ref('');

const searchTerm = ref('');
const debouncedSearch = useDebounce(searchTerm, 350);

const filters = ref({
    expense_category_id: null,
    status: '',
    from: '',
    to: '',
    page: 1,
    per_page: 20,
});

const modalOpen = ref(false);
const editing = ref(null);

const visiblePages = computed(() => {
    if (!meta.value) return [];
    const cur = meta.value.current_page, total = meta.value.last_page;
    const start = Math.max(1, cur - 2);
    const end   = Math.min(total, cur + 2);
    return Array.from({ length: end - start + 1 }, (_, i) => start + i);
});

const SummaryCard = (props) => {
    const palette = {
        slate:   'border-slate-200 bg-white',
        amber:   'border-amber-200 bg-amber-50/40',
        indigo:  'border-indigo-200 bg-indigo-50/40',
        emerald: 'border-emerald-200 bg-emerald-50/40',
        rose:    'border-rose-200 bg-rose-50/40',
    }[props.tone] ?? 'border-slate-200 bg-white';
    return h('div', { class: `border rounded-xl shadow-sm px-4 py-3 ${palette}` }, [
        h('p', { class: 'text-[11px] uppercase tracking-wide text-slate-500 font-medium' }, props.label),
        h('p', { class: 'text-lg font-bold text-slate-900 mt-1 font-mono' }, props.value),
    ]);
};
SummaryCard.props = ['label', 'value', 'tone'];

const StatusBadge = (props) => {
    const palette = {
        pending:  { tone: 'bg-amber-100 text-amber-700',   dot: 'bg-amber-500' },
        approved: { tone: 'bg-indigo-100 text-indigo-700', dot: 'bg-indigo-500' },
        paid:     { tone: 'bg-emerald-100 text-emerald-700', dot: 'bg-emerald-500' },
        rejected: { tone: 'bg-rose-100 text-rose-700',     dot: 'bg-rose-500' },
    }[props.status] ?? { tone: 'bg-slate-100 text-slate-700', dot: 'bg-slate-400' };
    return h('span', { class: `inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-medium ${palette.tone}` }, [
        h('span', { class: `w-1.5 h-1.5 rounded-full ${palette.dot}` }),
        t('expenses.status_' + props.status),
    ]);
};
StatusBadge.props = ['status'];

function formatDate(s) {
    return s ? new Date(s + 'T00:00:00').toLocaleDateString('de-DE') : '';
}

function paymentLabel(method) {
    if (method === 'cheque') return t('expenses.paymentMethod.cheque');
    return t('paymentMethod.' + method);
}

watch(debouncedSearch, () => { filters.value.page = 1; fetchList(); });
watch(() => filters.value.expense_category_id, () => { filters.value.page = 1; fetchList(); fetchSummary(); });
watch(() => filters.value.status,              () => { filters.value.page = 1; fetchList(); });
watch(() => filters.value.from,                () => { filters.value.page = 1; fetchList(); fetchSummary(); });
watch(() => filters.value.to,                  () => { filters.value.page = 1; fetchList(); fetchSummary(); });

async function fetchList() {
    loading.value = true;
    errorMsg.value = '';
    try {
        const params = {
            ...filters.value,
            search: debouncedSearch.value || undefined,
            expense_category_id: filters.value.expense_category_id || undefined,
            status: filters.value.status || undefined,
            from: filters.value.from || undefined,
            to: filters.value.to || undefined,
        };
        const { data } = await expenseService.index(params);
        expenses.value = data.data;
        meta.value = data.meta;
    } catch (err) {
        errorMsg.value = err.response?.data?.message ?? t('common.unexpectedError');
    } finally {
        loading.value = false;
    }
}

async function fetchSummary() {
    try {
        const params = {
            expense_category_id: filters.value.expense_category_id || undefined,
            from: filters.value.from || undefined,
            to: filters.value.to || undefined,
        };
        const { data } = await expenseService.summary(params);
        summary.value = { ...summary.value, ...data.data };
    } catch { /* silent */ }
}

async function loadCategories() {
    try {
        const { data } = await expenseCategoryService.all();
        categories.value = data.data ?? [];
    } catch {
        categories.value = [];
    }
}

async function loadBranches() {
    try {
        const { data } = await branchService.all();
        branches.value = data.data ?? [];
    } catch {
        branches.value = [];
    }
}

function goToPage(p) {
    filters.value.page = p;
    fetchList();
}

function openCreate() {
    editing.value = null;
    modalOpen.value = true;
}

function openEdit(e) {
    editing.value = e;
    modalOpen.value = true;
}

function onSaved() {
    fetchList();
    fetchSummary();
}

async function confirmDelete(e) {
    const ok = await confirm({
        title: t('expenses.deleteTitle'),
        text:  t('expenses.deleteMessage', { number: e.expense_number }),
        confirmText: t('common.delete'),
        danger: true,
    });
    if (!ok) return;
    try {
        await expenseService.destroy(e.id);
        toast('success', t('common.deletedSuccess'));
        fetchList();
        fetchSummary();
    } catch (err) {
        toast('error', err.response?.data?.message ?? t('common.unexpectedError'));
    }
}

onMounted(async () => {
    await Promise.all([loadCategories(), loadBranches()]);
    fetchList();
    fetchSummary();
});
</script>

<style scoped>
@reference '../../../css/app.css';
.btn-primary { @apply inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white bg-sky-600 hover:bg-sky-700 rounded-lg transition-colors shadow-sm; }
.action-btn  { @apply p-1.5 text-slate-400 hover:text-sky-600 hover:bg-sky-50 rounded-lg transition-colors inline-flex; }
.th          { @apply px-4 py-2.5 text-left text-[11px] font-semibold text-slate-500 uppercase tracking-wide; }
.td          { @apply px-4 py-2.5 align-middle; }
.ctrl        { @apply px-3 py-2 text-sm border border-slate-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent; }
</style>
