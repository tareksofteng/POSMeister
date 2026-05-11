<template>
    <div class="p-6 lg:p-8 space-y-6">

        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 tracking-tight">{{ t('hrm.employees.title') }}</h1>
                <p class="mt-1 text-sm text-slate-500">{{ t('hrm.employees.subtitle') }}</p>
            </div>
            <RouterLink :to="{ name: 'hrm-employee-create' }" class="btn-primary">
                <UserPlusIcon class="w-4 h-4" />
                {{ t('hrm.employees.add') }}
            </RouterLink>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
            <div class="stat-card">
                <p class="stat-label">{{ t('hrm.stats.total') }}</p>
                <p class="stat-value">{{ stats.total }}</p>
            </div>
            <div class="stat-card border-l-4 border-l-emerald-400">
                <p class="stat-label">{{ t('hrm.stats.active') }}</p>
                <p class="stat-value text-emerald-700">{{ stats.active }}</p>
            </div>
            <div class="stat-card border-l-4 border-l-slate-400">
                <p class="stat-label">{{ t('hrm.stats.inactive') }}</p>
                <p class="stat-value text-slate-700">{{ stats.inactive }}</p>
            </div>
            <div class="stat-card border-l-4 border-l-rose-400">
                <p class="stat-label">{{ t('hrm.stats.terminated') }}</p>
                <p class="stat-value text-rose-700">{{ stats.terminated }}</p>
            </div>
        </div>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-4 flex flex-wrap gap-3">
            <div class="relative flex-1 min-w-[220px]">
                <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 pointer-events-none" />
                <input v-model="searchTerm" type="search" :placeholder="t('hrm.employees.searchPh')" class="form-input pl-9" />
            </div>
            <select v-model="filters.department_id" class="form-input w-48">
                <option :value="null">{{ t('hrm.filters.allDepartments') }}</option>
                <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
            </select>
            <select v-model="filters.status" class="form-input w-40">
                <option value="">{{ t('hrm.filters.allStatuses') }}</option>
                <option value="active">{{ t('hrm.status_active') }}</option>
                <option value="inactive">{{ t('hrm.status_inactive') }}</option>
                <option value="terminated">{{ t('hrm.status_terminated') }}</option>
                <option value="resigned">{{ t('hrm.status_resigned') }}</option>
            </select>
            <select v-model="filters.employment_type" class="form-input w-44">
                <option value="">{{ t('hrm.filters.allEmploymentTypes') }}</option>
                <option value="full_time">{{ t('hrm.employment.full_time') }}</option>
                <option value="part_time">{{ t('hrm.employment.part_time') }}</option>
                <option value="contract">{{ t('hrm.employment.contract') }}</option>
                <option value="intern">{{ t('hrm.employment.intern') }}</option>
            </select>
        </div>

        <div v-if="errorMsg" class="rounded-lg bg-rose-50 border border-rose-200 px-4 py-3 text-sm text-rose-700">{{ errorMsg }}</div>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 border-b border-slate-100">
                        <tr>
                            <th class="th w-12"></th>
                            <th class="th">{{ t('hrm.employees.empId') }}</th>
                            <th class="th">{{ t('hrm.employees.name') }}</th>
                            <th class="th">{{ t('hrm.employees.designation') }}</th>
                            <th class="th">{{ t('hrm.employees.department') }}</th>
                            <th class="th">{{ t('hrm.employees.branch') }}</th>
                            <th class="th">{{ t('common.status') }}</th>
                            <th class="th w-32 text-right">{{ t('common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-if="loading">
                            <td colspan="8" class="py-12 text-center text-slate-400">
                                <div class="w-6 h-6 border-2 border-indigo-500 border-t-transparent rounded-full animate-spin mx-auto mb-2"></div>
                                {{ t('common.loading') }}
                            </td>
                        </tr>
                        <tr v-else-if="employees.length === 0">
                            <td colspan="8" class="py-16 text-center">
                                <UsersIcon class="w-10 h-10 text-slate-300 mx-auto mb-2" />
                                <p class="text-sm text-slate-500">{{ t('hrm.employees.empty') }}</p>
                            </td>
                        </tr>
                        <tr v-else v-for="emp in employees" :key="emp.id" class="hover:bg-slate-50/60 transition-colors">
                            <td class="td">
                                <EmployeeAvatar :src="emp.photo_url" :name="emp.full_name" size="sm" />
                            </td>
                            <td class="td font-mono text-xs text-slate-600">{{ emp.employee_id }}</td>
                            <td class="td">
                                <RouterLink :to="{ name: 'hrm-employee-show', params: { id: emp.id } }" class="font-medium text-slate-900 hover:text-indigo-600">
                                    {{ emp.full_name }}
                                </RouterLink>
                                <p class="text-xs text-slate-500 mt-0.5" v-if="emp.email">{{ emp.email }}</p>
                            </td>
                            <td class="td text-slate-700">{{ emp.designation?.title ?? '—' }}</td>
                            <td class="td text-slate-700">{{ emp.department?.name ?? '—' }}</td>
                            <td class="td text-slate-700">{{ emp.branch?.name ?? '—' }}</td>
                            <td class="td"><EmployeeStatusBadge :status="emp.status" /></td>
                            <td class="td">
                                <div class="flex items-center justify-end gap-1">
                                    <RouterLink :to="{ name: 'hrm-employee-show', params: { id: emp.id } }" class="action-btn" :title="t('common.view')">
                                        <EyeIcon class="w-4 h-4" />
                                    </RouterLink>
                                    <RouterLink :to="{ name: 'hrm-employee-edit', params: { id: emp.id } }" class="action-btn" :title="t('common.edit')">
                                        <PencilSquareIcon class="w-4 h-4" />
                                    </RouterLink>
                                    <button @click="confirmDelete(emp)" class="action-btn hover:text-rose-600 hover:bg-rose-50" :title="t('common.delete')">
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
                        v-for="p in visiblePages"
                        :key="p"
                        @click="goToPage(p)"
                        :class="['w-8 h-8 text-xs font-medium rounded-lg transition-colors',
                            p === meta.current_page ? 'bg-indigo-600 text-white' : 'text-slate-600 hover:bg-slate-100']"
                    >
                        {{ p }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { RouterLink } from 'vue-router';
import { employeeService, departmentService } from '@/services/hrmService';
import { useDebounce } from '@vueuse/core';
import { useAlert } from '@/composables/useAlert';
import EmployeeAvatar from '@/components/hrm/EmployeeAvatar.vue';
import EmployeeStatusBadge from '@/components/hrm/EmployeeStatusBadge.vue';
import {
    UserPlusIcon, MagnifyingGlassIcon, UsersIcon,
    EyeIcon, PencilSquareIcon, TrashIcon,
} from '@heroicons/vue/24/outline';

const { t } = useI18n();
const { toast, confirm } = useAlert();

const employees = ref([]);
const departments = ref([]);
const meta = ref(null);
const stats = ref({ total: 0, active: 0, inactive: 0, terminated: 0, resigned: 0 });
const loading = ref(false);
const errorMsg = ref('');

const searchTerm = ref('');
const debouncedSearch = useDebounce(searchTerm, 350);
const filters = ref({
    department_id: null,
    status: '',
    employment_type: '',
    page: 1,
    per_page: 20,
});

watch(debouncedSearch, () => { filters.value.page = 1; fetchList(); });
watch(() => filters.value.department_id,   () => { filters.value.page = 1; fetchList(); });
watch(() => filters.value.status,          () => { filters.value.page = 1; fetchList(); });
watch(() => filters.value.employment_type, () => { filters.value.page = 1; fetchList(); });

const visiblePages = computed(() => {
    if (!meta.value) return [];
    const cur = meta.value.current_page, total = meta.value.last_page;
    const start = Math.max(1, cur - 2);
    const end   = Math.min(total, cur + 2);
    return Array.from({ length: end - start + 1 }, (_, i) => start + i);
});

async function fetchList() {
    loading.value = true;
    errorMsg.value = '';
    try {
        const params = {
            ...filters.value,
            search: debouncedSearch.value || undefined,
            department_id: filters.value.department_id || undefined,
            status: filters.value.status || undefined,
            employment_type: filters.value.employment_type || undefined,
        };
        const { data } = await employeeService.index(params);
        employees.value = data.data;
        meta.value = data.meta;
    } catch (err) {
        errorMsg.value = err.response?.data?.message ?? t('common.unexpectedError');
    } finally {
        loading.value = false;
    }
}

async function fetchStats() {
    try {
        const { data } = await employeeService.stats();
        stats.value = { ...stats.value, ...data.data };
    } catch { /* ignore stats errors */ }
}

async function loadDepartments() {
    try {
        const { data } = await departmentService.all();
        departments.value = data.data ?? [];
    } catch {
        departments.value = [];
    }
}

function goToPage(p) {
    filters.value.page = p;
    fetchList();
}

async function confirmDelete(emp) {
    const ok = await confirm({
        title: t('hrm.employees.deleteTitle'),
        text:  t('hrm.employees.deleteMessage', { name: emp.full_name }),
        confirmText: t('common.delete'),
        danger: true,
    });
    if (!ok) return;
    try {
        await employeeService.destroy(emp.id);
        toast('success', t('hrm.employees.deletedSuccess'));
        await Promise.all([fetchList(), fetchStats()]);
    } catch (err) {
        toast('error', err.response?.data?.message ?? t('common.unexpectedError'));
    }
}

onMounted(() => {
    loadDepartments();
    fetchList();
    fetchStats();
});
</script>

<style scoped>
@reference '../../../css/app.css';

.btn-primary { @apply inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg transition-colors shadow-sm; }
.form-input  { @apply px-3 py-2 text-sm border border-slate-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent; }
.action-btn  { @apply p-1.5 text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors inline-flex; }
.th          { @apply px-4 py-2.5 text-left text-[11px] font-semibold text-slate-500 uppercase tracking-wide; }
.td          { @apply px-4 py-2.5 align-middle; }

.stat-card   { @apply bg-white border border-slate-200 rounded-xl px-4 py-3 shadow-sm; }
.stat-label  { @apply text-[11px] uppercase tracking-wide text-slate-500 font-medium; }
.stat-value  { @apply text-xl font-bold text-slate-900 mt-1; }
</style>
