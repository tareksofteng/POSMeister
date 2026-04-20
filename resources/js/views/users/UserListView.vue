<template>
    <div class="p-6 lg:p-8 space-y-6">

        <!-- Page header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">{{ t('users.title') }}</h1>
                <p class="mt-1 text-sm text-gray-500">
                    {{ t('users.subtitle') }}
                    <span v-if="meta" class="text-gray-400">({{ meta.total }} {{ t('common.total') }})</span>
                </p>
            </div>
            <button @click="openCreate" class="btn-primary">
                <PlusIcon class="w-4 h-4" />
                {{ t('users.new') }}
            </button>
        </div>

        <!-- Toolbar -->
        <div class="flex flex-col sm:flex-row gap-3">
            <div class="relative flex-1 max-w-sm">
                <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" />
                <input
                    v-model="searchQuery"
                    type="search"
                    :placeholder="t('users.searchPlaceholder')"
                    class="w-full pl-9 pr-4 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                />
            </div>

            <select
                v-model="roleFilter"
                class="px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white"
            >
                <option value="">{{ t('users.allRoles') }}</option>
                <option value="admin">{{ t('users.roles.admin') }}</option>
                <option value="manager">{{ t('users.roles.manager') }}</option>
                <option value="cashier">{{ t('users.roles.cashier') }}</option>
            </select>

            <select
                v-model="branchFilter"
                class="px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white"
            >
                <option value="">{{ t('users.allBranches') }}</option>
                <option
                    v-for="opt in branchStore.branchOptions"
                    :key="opt.value"
                    :value="opt.value"
                >
                    {{ opt.label }}
                </option>
            </select>

            <select
                v-model="statusFilter"
                class="px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white"
            >
                <option value="">{{ t('common.allStatuses') }}</option>
                <option value="1">{{ t('common.active') }}</option>
                <option value="0">{{ t('common.inactive') }}</option>
            </select>
        </div>

        <!-- Error banner -->
        <div v-if="listError" class="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
            {{ listError }}
        </div>

        <!-- Table -->
        <DataTable
            :columns="columns"
            :rows="rows"
            :loading="loading"
            :meta="meta"
            sort-key="name"
            :empty-title="t('users.emptyTitle')"
            :empty-message="t('users.emptyMessage')"
            @page-change="fetchPage"
        >
            <template #row-actions="{ row }">
                <button
                    @click="openEdit(row)"
                    class="p-1.5 text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors"
                    title="Edit"
                >
                    <PencilSquareIcon class="w-4 h-4" />
                </button>
                <button
                    @click="confirmToggle(row)"
                    class="p-1.5 text-gray-400 hover:text-amber-600 hover:bg-amber-50 rounded-lg transition-colors"
                    :title="row.is_active ? 'Deactivate' : 'Activate'"
                >
                    <NoSymbolIcon v-if="row.is_active" class="w-4 h-4" />
                    <CheckCircleIcon v-else class="w-4 h-4" />
                </button>
                <button
                    @click="confirmDelete(row)"
                    class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                    title="Delete"
                >
                    <TrashIcon class="w-4 h-4" />
                </button>
            </template>
        </DataTable>

    </div>

    <!-- Create / Edit modal -->
    <UserFormModal
        v-model:open="formOpen"
        :user="editTarget"
        @saved="onSaved"
    />

</template>

<script setup>
import { ref, watch, onMounted, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { userService } from '@/services/userService';
import { useBranchStore } from '@/stores/branch';
import { useDebounce } from '@vueuse/core';
import { useAlert } from '@/composables/useAlert';

import DataTable     from '@/components/ui/DataTable.vue';
import UserFormModal from './UserFormModal.vue';

import {
    PlusIcon,
    MagnifyingGlassIcon,
    PencilSquareIcon,
    TrashIcon,
    NoSymbolIcon,
    CheckCircleIcon,
} from '@heroicons/vue/24/outline';

const { t } = useI18n();
const branchStore = useBranchStore();
const { toast, confirm } = useAlert();

const columns = computed(() => [
    { key: 'name',        label: t('common.name'),      bold: true },
    { key: 'email',       label: t('common.email') },
    { key: 'phone',       label: t('common.phone') },
    { key: 'role',        label: t('users.role'),        type: 'role',   width: '110px' },
    { key: 'branch_name', label: t('users.branch') },
    { key: 'is_active',   label: t('common.status'),     type: 'badge',  width: '110px' },
    { key: '_actions',    label: '',                      type: 'actions', width: '100px' },
]);

// ── List state ────────────────────────────────────────────────────────────
const rows    = ref([]);
const meta    = ref(null);
const loading = ref(false);
const listError = ref('');
const filters = ref({ search: '', role: '', branch_id: '', is_active: '', page: 1, per_page: 20 });

// ── Filters ───────────────────────────────────────────────────────────────
const searchQuery  = ref('');
const roleFilter   = ref('');
const branchFilter = ref('');
const statusFilter = ref('');
const debouncedSearch = useDebounce(searchQuery, 350);

watch(debouncedSearch, (val) => fetchUsers({ search: val, page: 1 }));
watch(roleFilter,   (val) => fetchUsers({ role: val, page: 1 }));
watch(branchFilter, (val) => fetchUsers({ branch_id: val, page: 1 }));
watch(statusFilter, (val) => fetchUsers({ is_active: val, page: 1 }));

async function fetchUsers(overrides = {}) {
    Object.assign(filters.value, overrides);
    loading.value  = true;
    listError.value = '';
    try {
        const { data } = await userService.index(filters.value);
        rows.value = data.data;
        meta.value = data.meta;
    } catch (err) {
        listError.value = err.response?.data?.message ?? 'Failed to load users.';
    } finally {
        loading.value = false;
    }
}

function fetchPage(page) {
    fetchUsers({ page });
}

onMounted(() => {
    fetchUsers();
    branchStore.fetchAllActive();
});

// ── Create / Edit ─────────────────────────────────────────────────────────
const formOpen   = ref(false);
const editTarget = ref(null);

function openCreate() { editTarget.value = null; formOpen.value = true; }
function openEdit(row) { editTarget.value = { ...row }; formOpen.value = true; }

function onSaved(isEdit) {
    formOpen.value = false;
    fetchUsers();
    toast('success', isEdit ? t('common.updatedSuccess') : t('common.createdSuccess'));
}

// ── Toggle status ─────────────────────────────────────────────────────────
async function confirmToggle(row) {
    const isActive = row.is_active;
    const ok = await confirm({
        title:       isActive ? t('users.deactivateTitle') : t('users.activateTitle'),
        text:        isActive ? t('users.deactivateMessage', { name: row.name }) : t('users.activateMessage', { name: row.name }),
        confirmText: isActive ? t('users.deactivateButton') : t('users.activateButton'),
        danger:      isActive,
    });
    if (!ok) return;
    try {
        await userService.toggleStatus(row.id);
        fetchUsers();
        toast('success', isActive ? t('common.deactivatedSuccess') : t('common.activatedSuccess'));
    } catch (err) {
        toast('error', err.response?.data?.message ?? t('common.unexpectedError'));
    }
}

// ── Delete ────────────────────────────────────────────────────────────────
async function confirmDelete(row) {
    const ok = await confirm({
        title:       t('common.deleteConfirmTitle'),
        text:        t('common.deleteConfirmMessage', { name: row.name }),
        confirmText: t('common.delete'),
        danger:      true,
    });
    if (!ok) return;
    try {
        await userService.destroy(row.id);
        fetchUsers();
        toast('success', t('common.deletedSuccess'));
    } catch (err) {
        toast('error', err.response?.data?.message ?? t('common.unexpectedError'));
    }
}
</script>

<style scoped>
@reference '../../../css/app.css';
.btn-primary {
    @apply flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition-colors shadow-sm;
}
</style>
