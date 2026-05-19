<template>
    <div class="p-6 lg:p-8 space-y-6">

        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 tracking-tight">{{ t('finance.budget.title') }}</h1>
                <p class="mt-1 text-sm text-slate-500">{{ t('finance.budget.subtitle') }}</p>
            </div>
            <button @click="openCreate" class="btn-primary">
                <PlusIcon class="w-4 h-4" />
                {{ t('finance.budget.add') }}
            </button>
        </div>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-4 flex flex-wrap gap-3 items-end">
            <div>
                <label class="lbl">{{ t('finance.budget.fields.fiscal_year') }}</label>
                <select v-model.number="filters.fiscal_year" class="ctrl w-32">
                    <option :value="null">{{ t('common.allTime') }}</option>
                    <option v-for="y in yearChoices" :key="y" :value="y">{{ y }}</option>
                </select>
            </div>
            <div>
                <label class="lbl">{{ t('common.status') }}</label>
                <select v-model="filters.status" class="ctrl w-36">
                    <option value="">{{ t('common.allStatuses') }}</option>
                    <option value="draft">{{ t('finance.budget.status_draft') }}</option>
                    <option value="active">{{ t('finance.budget.status_active') }}</option>
                    <option value="archived">{{ t('finance.budget.status_archived') }}</option>
                </select>
            </div>
        </div>

        <div v-if="errorMsg" class="rounded-lg bg-rose-50 border border-rose-200 px-4 py-3 text-sm text-rose-700">{{ errorMsg }}</div>

        <div v-if="loading" class="text-center py-16 text-slate-400">
            <div class="w-8 h-8 border-2 border-indigo-500 border-t-transparent rounded-full animate-spin mx-auto mb-3"></div>
            <p class="text-sm">{{ t('common.loading') }}</p>
        </div>

        <div v-else-if="budgets.length === 0" class="bg-white rounded-xl border border-dashed border-slate-300 py-16 text-center">
            <ChartPieIcon class="w-10 h-10 text-slate-300 mx-auto mb-2" />
            <p class="text-sm text-slate-500">{{ t('finance.budget.empty') }}</p>
            <button @click="openCreate" class="btn-primary mt-4 inline-flex">
                <PlusIcon class="w-4 h-4" />
                {{ t('finance.budget.add') }}
            </button>
        </div>

        <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <div v-for="b in budgets" :key="b.id"
                class="bg-white border border-slate-200 rounded-xl shadow-sm p-5 hover:border-indigo-300 hover:shadow-md transition-all"
            >
                <div class="flex items-start justify-between gap-3 mb-3">
                    <div class="min-w-0">
                        <h3 class="font-semibold text-slate-900 truncate">{{ b.title }}</h3>
                        <p class="text-xs text-slate-500 mt-0.5">
                            {{ formatDate(b.start_date) }} - {{ formatDate(b.end_date) }}
                            <span v-if="b.branch_name"> · {{ b.branch_name }}</span>
                        </p>
                    </div>
                    <StatusBadge :status="b.status" />
                </div>

                <div class="text-2xl font-bold text-slate-900 font-mono mb-1">{{ fmtCurrency(b.total_budget) }}</div>
                <p class="text-xs text-slate-500">{{ b.items_count ?? 0 }} {{ t('finance.budget.categoriesCount') }}</p>

                <div class="mt-4 flex flex-wrap items-center gap-2">
                    <RouterLink :to="{ name: 'finance-budget-analytics', params: { id: b.id } }" class="action-btn-inline">
                        <ChartBarIcon class="w-4 h-4" />
                        {{ t('finance.budget.analyze') }}
                    </RouterLink>
                    <button @click="openEdit(b)" class="action-btn" :title="t('common.edit')">
                        <PencilSquareIcon class="w-4 h-4" />
                    </button>
                    <button v-if="b.status !== 'archived'" @click="setStatus(b, 'archived')" class="action-btn" :title="t('finance.budget.archive')">
                        <ArchiveBoxIcon class="w-4 h-4" />
                    </button>
                    <button v-if="b.status === 'draft'" @click="setStatus(b, 'active')" class="action-btn hover:text-emerald-700 hover:bg-emerald-50" :title="t('finance.budget.activate')">
                        <BoltIcon class="w-4 h-4" />
                    </button>
                    <button @click="duplicate(b)" class="action-btn" :title="t('finance.budget.duplicate')">
                        <DocumentDuplicateIcon class="w-4 h-4" />
                    </button>
                    <button v-if="b.status !== 'active'" @click="confirmDelete(b)" class="action-btn hover:text-rose-600 hover:bg-rose-50" :title="t('common.delete')">
                        <TrashIcon class="w-4 h-4" />
                    </button>
                </div>
            </div>
        </div>

        <BudgetFormModal
            v-model="modalOpen"
            :budget="editing"
            :categories="categories"
            :branches="branches"
            @saved="fetchList"
        />
    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, h } from 'vue';
import { useI18n } from 'vue-i18n';
import { RouterLink } from 'vue-router';
import { budgetService } from '@/services/financeService';
import { expenseCategoryService } from '@/services/expenseService';
import { branchService } from '@/services/branchService';
import { useAlert } from '@/composables/useAlert';
import { useCurrency } from '@/composables/useCurrency';
import BudgetFormModal from './BudgetFormModal.vue';
import {
    PlusIcon, PencilSquareIcon, TrashIcon, ArchiveBoxIcon, BoltIcon,
    DocumentDuplicateIcon, ChartBarIcon, ChartPieIcon,
} from '@heroicons/vue/24/outline';

const { t } = useI18n();
const { toast, confirm, prompt } = useAlert();
const { fmtCurrency } = useCurrency();

const budgets = ref([]);
const categories = ref([]);
const branches = ref([]);
const loading = ref(false);
const errorMsg = ref('');

const filters = ref({
    fiscal_year: new Date().getFullYear(),
    status: '',
});

const modalOpen = ref(false);
const editing = ref(null);

const yearChoices = computed(() => {
    const cur = new Date().getFullYear();
    return [cur - 2, cur - 1, cur, cur + 1];
});

const StatusBadge = (props) => {
    const palette = {
        draft:    { tone: 'bg-slate-100 text-slate-700',     dot: 'bg-slate-400' },
        active:   { tone: 'bg-emerald-100 text-emerald-700', dot: 'bg-emerald-500' },
        archived: { tone: 'bg-amber-100 text-amber-700',     dot: 'bg-amber-500' },
    }[props.status] ?? { tone: 'bg-slate-100 text-slate-700', dot: 'bg-slate-400' };
    return h('span', { class: `inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[11px] font-medium ${palette.tone}` }, [
        h('span', { class: `w-1.5 h-1.5 rounded-full ${palette.dot}` }),
        t('finance.budget.status_' + props.status),
    ]);
};
StatusBadge.props = ['status'];

function formatDate(s) {
    return s ? new Date(s + 'T00:00:00').toLocaleDateString('de-DE') : '';
}

watch(() => filters.value.fiscal_year, fetchList);
watch(() => filters.value.status,      fetchList);

async function fetchList() {
    loading.value = true;
    errorMsg.value = '';
    try {
        const { data } = await budgetService.index({
            fiscal_year: filters.value.fiscal_year || undefined,
            status: filters.value.status || undefined,
        });
        budgets.value = data.data;
    } catch (err) {
        errorMsg.value = err.response?.data?.message ?? t('common.unexpectedError');
    } finally {
        loading.value = false;
    }
}

async function loadCategories() {
    try {
        const { data } = await expenseCategoryService.all();
        categories.value = data.data ?? [];
    } catch { categories.value = []; }
}

async function loadBranches() {
    try {
        const { data } = await branchService.all();
        branches.value = data.data ?? [];
    } catch { branches.value = []; }
}

function openCreate() {
    editing.value = null;
    modalOpen.value = true;
}

async function openEdit(b) {
    // Fetch full details (items) before opening
    try {
        const { data } = await budgetService.show(b.id);
        editing.value = data.data ?? data;
        modalOpen.value = true;
    } catch (err) {
        toast('error', err.response?.data?.message ?? t('common.unexpectedError'));
    }
}

async function setStatus(b, status) {
    try {
        await budgetService.setStatus(b.id, status);
        toast('success', t('common.updatedSuccess'));
        fetchList();
    } catch (err) {
        toast('error', err.response?.data?.message ?? t('common.unexpectedError'));
    }
}

async function duplicate(b) {
    const ok = await confirm({
        title: t('finance.budget.duplicateTitle'),
        text: t('finance.budget.duplicateMessage', { title: b.title }),
        confirmText: t('finance.budget.duplicate'),
    });
    if (!ok) return;
    try {
        const next = (b.fiscal_year || new Date().getFullYear()) + 1;
        await budgetService.duplicate(b.id, next);
        toast('success', t('common.createdSuccess'));
        fetchList();
    } catch (err) {
        toast('error', err.response?.data?.message ?? t('common.unexpectedError'));
    }
}

async function confirmDelete(b) {
    const ok = await confirm({
        title: t('finance.budget.deleteTitle'),
        text:  t('finance.budget.deleteMessage', { title: b.title }),
        confirmText: t('common.delete'),
        danger: true,
    });
    if (!ok) return;
    try {
        await budgetService.destroy(b.id);
        toast('success', t('common.deletedSuccess'));
        fetchList();
    } catch (err) {
        toast('error', err.response?.data?.message ?? t('common.unexpectedError'));
    }
}

onMounted(async () => {
    await Promise.all([loadCategories(), loadBranches()]);
    fetchList();
});
</script>

<style scoped>
@reference '../../../css/app.css';
.btn-primary { @apply inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg transition-colors shadow-sm; }
.action-btn  { @apply p-1.5 text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors inline-flex; }
.action-btn-inline { @apply inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold text-indigo-700 bg-indigo-50 hover:bg-indigo-100 rounded-lg transition-colors; }
.lbl         { @apply block text-xs font-medium text-slate-600 mb-1.5; }
.ctrl        { @apply px-3 py-2 text-sm border border-slate-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent; }
</style>
