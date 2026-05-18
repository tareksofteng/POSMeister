<template>
    <div class="p-6 lg:p-8 space-y-6">

        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 tracking-tight">{{ t('expenses.categories.title') }}</h1>
                <p class="mt-1 text-sm text-slate-500">{{ t('expenses.categories.subtitle') }}</p>
            </div>
            <button @click="openCreate" class="btn-primary">
                <PlusIcon class="w-4 h-4" />
                {{ t('expenses.categories.add') }}
            </button>
        </div>

        <div v-if="errorMsg" class="rounded-lg bg-rose-50 border border-rose-200 px-4 py-3 text-sm text-rose-700">{{ errorMsg }}</div>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 border-b border-slate-100">
                        <tr>
                            <th class="th">{{ t('expenses.fields.name') }}</th>
                            <th class="th">{{ t('expenses.fields.code') }}</th>
                            <th class="th">{{ t('expenses.fields.description') }}</th>
                            <th class="th text-right">{{ t('expenses.categories.expensesCount') }}</th>
                            <th class="th">{{ t('common.status') }}</th>
                            <th class="th w-32 text-right">{{ t('common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-if="loading">
                            <td colspan="6" class="py-10 text-center text-slate-400">
                                <div class="w-6 h-6 border-2 border-sky-500 border-t-transparent rounded-full animate-spin mx-auto mb-2"></div>
                                {{ t('common.loading') }}
                            </td>
                        </tr>
                        <tr v-else-if="categories.length === 0">
                            <td colspan="6" class="py-12 text-center">
                                <TagIcon class="w-10 h-10 text-slate-300 mx-auto mb-2" />
                                <p class="text-sm text-slate-500">{{ t('expenses.categories.empty') }}</p>
                            </td>
                        </tr>
                        <tr v-else v-for="c in categories" :key="c.id" class="hover:bg-slate-50/60">
                            <td class="td font-medium text-slate-900">{{ c.name }}</td>
                            <td class="td font-mono text-xs text-slate-500">{{ c.code || '—' }}</td>
                            <td class="td text-slate-600 max-w-md truncate">{{ c.description || '—' }}</td>
                            <td class="td text-right font-mono text-slate-700">{{ c.expenses_count ?? 0 }}</td>
                            <td class="td">
                                <button @click="toggleStatus(c)">
                                    <span :class="['inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-medium',
                                        c.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600']">
                                        <span :class="['w-1.5 h-1.5 rounded-full', c.is_active ? 'bg-emerald-500' : 'bg-slate-400']"></span>
                                        {{ t(c.is_active ? 'common.active' : 'common.inactive') }}
                                    </span>
                                </button>
                            </td>
                            <td class="td">
                                <div class="flex items-center justify-end gap-1">
                                    <button @click="openEdit(c)" class="action-btn" :title="t('common.edit')">
                                        <PencilSquareIcon class="w-4 h-4" />
                                    </button>
                                    <button @click="confirmDelete(c)" class="action-btn hover:text-rose-600 hover:bg-rose-50" :title="t('common.delete')">
                                        <TrashIcon class="w-4 h-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <Modal v-model="modalOpen" :title="editing ? t('expenses.categories.editTitle') : t('expenses.categories.createTitle')" size="md">
            <form @submit.prevent="save" id="cat-form" class="space-y-4">
                <div>
                    <label class="lbl">{{ t('expenses.fields.name') }} <span class="text-rose-500">*</span></label>
                    <input v-model="form.name" type="text" class="ctrl" required />
                    <p v-if="errors.name" class="err">{{ errors.name }}</p>
                </div>
                <div>
                    <label class="lbl">{{ t('expenses.fields.code') }}</label>
                    <input v-model="form.code" type="text" class="ctrl" maxlength="30" placeholder="z. B. MIETE" />
                </div>
                <div>
                    <label class="lbl">{{ t('expenses.fields.description') }}</label>
                    <textarea v-model="form.description" rows="3" class="ctrl resize-none"></textarea>
                </div>
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input v-model="form.is_active" type="checkbox" class="rounded border-slate-300 text-sky-600 focus:ring-sky-500" />
                    {{ t('common.active') }}
                </label>
            </form>
            <template #footer>
                <button @click="modalOpen = false" class="btn-soft">{{ t('common.cancel') }}</button>
                <button type="submit" form="cat-form" :disabled="saving" class="btn-primary">
                    <CheckIcon v-if="!saving" class="w-4 h-4" />
                    <ArrowPathIcon v-else class="w-4 h-4 animate-spin" />
                    {{ saving ? t('common.saving') : t('common.save') }}
                </button>
            </template>
        </Modal>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { expenseCategoryService } from '@/services/expenseService';
import { useAlert } from '@/composables/useAlert';
import Modal from '@/components/ui/Modal.vue';
import {
    PlusIcon, PencilSquareIcon, TrashIcon, CheckIcon, ArrowPathIcon, TagIcon,
} from '@heroicons/vue/24/outline';

const { t } = useI18n();
const { toast, confirm } = useAlert();

const categories = ref([]);
const loading    = ref(false);
const errorMsg   = ref('');

const modalOpen  = ref(false);
const editing    = ref(null);
const saving     = ref(false);
const errors     = ref({});
const form       = ref({ name: '', code: '', description: '', is_active: true });

async function fetchList() {
    loading.value = true;
    errorMsg.value = '';
    try {
        const { data } = await expenseCategoryService.index();
        categories.value = data.data ?? [];
    } catch (err) {
        errorMsg.value = err.response?.data?.message ?? t('common.unexpectedError');
    } finally {
        loading.value = false;
    }
}

function openCreate() {
    editing.value = null;
    form.value = { name: '', code: '', description: '', is_active: true };
    errors.value = {};
    modalOpen.value = true;
}

function openEdit(c) {
    editing.value = c;
    form.value = {
        name: c.name,
        code: c.code ?? '',
        description: c.description ?? '',
        is_active: c.is_active,
    };
    errors.value = {};
    modalOpen.value = true;
}

async function save() {
    errors.value = {};
    saving.value = true;
    try {
        const payload = { ...form.value, code: form.value.code || null };
        if (editing.value) {
            await expenseCategoryService.update(editing.value.id, payload);
            toast('success', t('common.updatedSuccess'));
        } else {
            await expenseCategoryService.store(payload);
            toast('success', t('common.createdSuccess'));
        }
        modalOpen.value = false;
        fetchList();
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

async function toggleStatus(c) {
    try {
        await expenseCategoryService.toggleStatus(c.id);
        fetchList();
    } catch (err) {
        toast('error', err.response?.data?.message ?? t('common.unexpectedError'));
    }
}

async function confirmDelete(c) {
    const ok = await confirm({
        title: t('expenses.categories.deleteTitle'),
        text:  t('expenses.categories.deleteMessage', { name: c.name }),
        confirmText: t('common.delete'),
        danger: true,
    });
    if (!ok) return;
    try {
        await expenseCategoryService.destroy(c.id);
        toast('success', t('common.deletedSuccess'));
        fetchList();
    } catch (err) {
        toast('error', err.response?.data?.message ?? t('common.unexpectedError'));
    }
}

onMounted(fetchList);
</script>

<style scoped>
@reference '../../../css/app.css';
.btn-primary { @apply inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white bg-sky-600 hover:bg-sky-700 rounded-lg transition-colors shadow-sm disabled:opacity-50; }
.btn-soft    { @apply inline-flex items-center px-4 py-2 rounded-lg border border-slate-300 text-slate-700 text-sm hover:bg-slate-50 transition-colors; }
.action-btn  { @apply p-1.5 text-slate-400 hover:text-sky-600 hover:bg-sky-50 rounded-lg transition-colors inline-flex; }
.th          { @apply px-4 py-2.5 text-left text-[11px] font-semibold text-slate-500 uppercase tracking-wide; }
.td          { @apply px-4 py-2.5 align-middle; }
.lbl         { @apply block text-xs font-medium text-slate-600 mb-1.5; }
.ctrl        { @apply w-full px-3 py-2 text-sm border border-slate-200 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent; }
.err         { @apply text-xs text-rose-600 mt-1; }
</style>
