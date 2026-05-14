<template>
    <div class="p-6 lg:p-8 space-y-6">

        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 tracking-tight">{{ t('hrm.designations.title') }}</h1>
                <p class="mt-1 text-sm text-slate-500">{{ t('hrm.designations.subtitle') }}</p>
            </div>
            <button @click="openCreate" class="btn-primary">
                <PlusIcon class="w-4 h-4" />
                {{ t('hrm.designations.add') }}
            </button>
        </div>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-4">
            <label class="text-xs font-medium text-slate-600 block mb-1.5">{{ t('hrm.filters.allDepartments') }}</label>
            <select v-model.number="departmentFilter" @change="fetchList" class="ctrl w-72">
                <option :value="null">{{ t('hrm.filters.allDepartments') }}</option>
                <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
            </select>
        </div>

        <div v-if="errorMsg" class="rounded-lg bg-rose-50 border border-rose-200 px-4 py-3 text-sm text-rose-700">{{ errorMsg }}</div>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 border-b border-slate-100">
                        <tr>
                            <th class="th w-16 text-center">{{ t('hrm.designations.level') }}</th>
                            <th class="th">{{ t('hrm.fields.title') }}</th>
                            <th class="th">{{ t('hrm.fields.department') }}</th>
                            <th class="th">{{ t('hrm.fields.description') }}</th>
                            <th class="th text-right">{{ t('hrm.departments.employees') }}</th>
                            <th class="th">{{ t('common.status') }}</th>
                            <th class="th w-36 text-right">{{ t('common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-if="loading">
                            <td colspan="7" class="py-10 text-center text-slate-400">
                                <div class="w-6 h-6 border-2 border-indigo-500 border-t-transparent rounded-full animate-spin mx-auto mb-2"></div>
                                {{ t('common.loading') }}
                            </td>
                        </tr>
                        <tr v-else-if="designations.length === 0">
                            <td colspan="7" class="py-12 text-center">
                                <BriefcaseIcon class="w-10 h-10 text-slate-300 mx-auto mb-2" />
                                <p class="text-sm text-slate-500">{{ t('hrm.designations.empty') }}</p>
                            </td>
                        </tr>
                        <tr v-else v-for="d in designations" :key="d.id" class="hover:bg-slate-50/60">
                            <td class="td text-center">
                                <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-indigo-50 text-indigo-700 text-xs font-bold">
                                    {{ d.hierarchy_level }}
                                </span>
                            </td>
                            <td class="td font-medium text-slate-900">{{ d.title }}</td>
                            <td class="td text-slate-700">{{ d.department_name ?? '—' }}</td>
                            <td class="td text-slate-600 max-w-sm truncate">{{ d.description || '—' }}</td>
                            <td class="td text-right font-mono text-slate-700">{{ d.employees_count ?? 0 }}</td>
                            <td class="td">
                                <button @click="toggleStatus(d)">
                                    <span :class="['inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-medium',
                                        d.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600']">
                                        <span :class="['w-1.5 h-1.5 rounded-full', d.is_active ? 'bg-emerald-500' : 'bg-slate-400']"></span>
                                        {{ t(d.is_active ? 'common.active' : 'common.inactive') }}
                                    </span>
                                </button>
                            </td>
                            <td class="td">
                                <div class="flex items-center justify-end gap-1">
                                    <button @click="openEdit(d)" class="action-btn" :title="t('common.edit')">
                                        <PencilSquareIcon class="w-4 h-4" />
                                    </button>
                                    <button @click="confirmDelete(d)" class="action-btn hover:text-rose-600 hover:bg-rose-50" :title="t('common.delete')">
                                        <TrashIcon class="w-4 h-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <Modal v-model="modalOpen" :title="editing ? t('hrm.designations.editTitle') : t('hrm.designations.createTitle')" size="md">
            <form @submit.prevent="save" class="space-y-4" id="dsg-form">
                <div>
                    <label class="lbl">{{ t('hrm.fields.title') }} <span class="text-rose-500">*</span></label>
                    <input v-model="form.title" type="text" class="ctrl" required />
                    <p v-if="errors.title" class="err">{{ errors.title }}</p>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="lbl">{{ t('hrm.fields.department') }}</label>
                        <select v-model.number="form.department_id" class="ctrl">
                            <option :value="null">—</option>
                            <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="lbl">{{ t('hrm.designations.level') }}</label>
                        <input v-model.number="form.hierarchy_level" type="number" min="0" max="99" class="ctrl" />
                        <p class="text-[11px] text-slate-400 mt-1">{{ t('hrm.designations.levelHint') }}</p>
                    </div>
                </div>
                <div>
                    <label class="lbl">{{ t('hrm.fields.description') }}</label>
                    <textarea v-model="form.description" rows="3" class="ctrl resize-none"></textarea>
                </div>
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input v-model="form.is_active" type="checkbox" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
                    {{ t('common.active') }}
                </label>
            </form>
            <template #footer>
                <button @click="modalOpen = false" class="btn-soft">{{ t('common.cancel') }}</button>
                <button @click="save" :disabled="saving" type="submit" form="dsg-form" class="btn-primary">
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
import { designationService, departmentService } from '@/services/hrmService';
import { useAlert } from '@/composables/useAlert';
import Modal from '@/components/ui/Modal.vue';
import {
    PlusIcon, PencilSquareIcon, TrashIcon, CheckIcon, ArrowPathIcon, BriefcaseIcon,
} from '@heroicons/vue/24/outline';

const { t } = useI18n();
const { toast, confirm } = useAlert();

const designations = ref([]);
const departments = ref([]);
const loading = ref(false);
const errorMsg = ref('');
const departmentFilter = ref(null);

const modalOpen = ref(false);
const editing = ref(null);
const saving = ref(false);
const errors = ref({});
const form = ref({
    title: '',
    department_id: null,
    hierarchy_level: 0,
    description: '',
    is_active: true,
});

async function fetchList() {
    loading.value = true;
    errorMsg.value = '';
    try {
        const params = departmentFilter.value ? { department_id: departmentFilter.value } : {};
        const { data } = await designationService.index(params);
        designations.value = data.data ?? [];
    } catch (err) {
        errorMsg.value = err.response?.data?.message ?? t('common.unexpectedError');
    } finally {
        loading.value = false;
    }
}

async function fetchDepartments() {
    try {
        const { data } = await departmentService.all();
        departments.value = data.data ?? [];
    } catch {
        departments.value = [];
    }
}

function openCreate() {
    editing.value = null;
    form.value = { title: '', department_id: departmentFilter.value, hierarchy_level: 0, description: '', is_active: true };
    errors.value = {};
    modalOpen.value = true;
}

function openEdit(d) {
    editing.value = d;
    form.value = {
        title: d.title,
        department_id: d.department_id,
        hierarchy_level: d.hierarchy_level ?? 0,
        description: d.description ?? '',
        is_active: d.is_active,
    };
    errors.value = {};
    modalOpen.value = true;
}

async function save() {
    errors.value = {};
    saving.value = true;
    try {
        if (editing.value) {
            await designationService.update(editing.value.id, form.value);
            toast('success', t('common.updatedSuccess'));
        } else {
            await designationService.store(form.value);
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

async function toggleStatus(d) {
    try {
        await designationService.toggleStatus(d.id);
        fetchList();
    } catch (err) {
        toast('error', err.response?.data?.message ?? t('common.unexpectedError'));
    }
}

async function confirmDelete(d) {
    const ok = await confirm({
        title: t('hrm.designations.deleteTitle'),
        text:  t('hrm.designations.deleteMessage', { name: d.title }),
        confirmText: t('common.delete'),
        danger: true,
    });
    if (!ok) return;
    try {
        await designationService.destroy(d.id);
        toast('success', t('common.deletedSuccess'));
        fetchList();
    } catch (err) {
        toast('error', err.response?.data?.message ?? t('common.unexpectedError'));
    }
}

onMounted(async () => {
    await fetchDepartments();
    fetchList();
});
</script>

<style scoped>
@reference '../../../css/app.css';
.btn-primary { @apply inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg transition-colors shadow-sm disabled:opacity-50; }
.btn-soft    { @apply inline-flex items-center px-4 py-2 rounded-lg border border-slate-300 text-slate-700 text-sm hover:bg-slate-50 transition-colors; }
.action-btn  { @apply p-1.5 text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors inline-flex; }
.th          { @apply px-4 py-2.5 text-left text-[11px] font-semibold text-slate-500 uppercase tracking-wide; }
.td          { @apply px-4 py-2.5 align-middle; }
.lbl         { @apply block text-xs font-medium text-slate-600 mb-1.5; }
.ctrl        { @apply w-full px-3 py-2 text-sm border border-slate-200 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent; }
.err         { @apply text-xs text-rose-600 mt-1; }
</style>
