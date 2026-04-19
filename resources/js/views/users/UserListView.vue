<template>
    <div class="p-6 lg:p-8 space-y-6">

        <!-- Page header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Users</h1>
                <p class="mt-1 text-sm text-gray-500">
                    Manage system access and roles.
                    <span v-if="meta" class="text-gray-400">({{ meta.total }} total)</span>
                </p>
            </div>
            <button @click="openCreate" class="btn-primary">
                <PlusIcon class="w-4 h-4" />
                New User
            </button>
        </div>

        <!-- Toolbar -->
        <div class="flex flex-col sm:flex-row gap-3">
            <div class="relative flex-1 max-w-sm">
                <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" />
                <input
                    v-model="searchQuery"
                    type="search"
                    placeholder="Search name or email…"
                    class="w-full pl-9 pr-4 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                />
            </div>

            <select
                v-model="roleFilter"
                class="px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white"
            >
                <option value="">All roles</option>
                <option value="admin">Admin</option>
                <option value="manager">Manager</option>
                <option value="cashier">Cashier</option>
            </select>

            <select
                v-model="branchFilter"
                class="px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white"
            >
                <option value="">All branches</option>
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
                <option value="">All statuses</option>
                <option value="1">Active</option>
                <option value="0">Inactive</option>
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
            empty-title="No users found"
            empty-message="Create your first user to get started."
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

    <!-- Toggle status confirm -->
    <ConfirmDialog
        v-model="toggleOpen"
        :title="toggleTarget?.is_active ? 'Deactivate User' : 'Activate User'"
        :message="toggleMessage"
        :confirm-label="toggleTarget?.is_active ? 'Deactivate' : 'Activate'"
        :danger="toggleTarget?.is_active"
        :loading="toggleLoading"
        @confirm="executeToggle"
    />

    <!-- Delete confirm -->
    <ConfirmDialog
        v-model="deleteOpen"
        title="Delete User"
        :message="`Are you sure you want to delete &quot;${deleteTarget?.name}&quot;? This action cannot be undone.`"
        confirm-label="Delete"
        :danger="true"
        :loading="deleteLoading"
        @confirm="executeDelete"
    />
</template>

<script setup>
import { ref, watch, onMounted, computed } from 'vue';
import { userService } from '@/services/userService';
import { useBranchStore } from '@/stores/branch';
import { useDebounce } from '@vueuse/core';

import DataTable     from '@/components/ui/DataTable.vue';
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue';
import UserFormModal from './UserFormModal.vue';

import {
    PlusIcon,
    MagnifyingGlassIcon,
    PencilSquareIcon,
    TrashIcon,
    NoSymbolIcon,
    CheckCircleIcon,
} from '@heroicons/vue/24/outline';

const branchStore = useBranchStore();

const columns = [
    { key: 'name',        label: 'Name',    bold: true },
    { key: 'email',       label: 'Email' },
    { key: 'phone',       label: 'Phone' },
    { key: 'role',        label: 'Role',    type: 'role',   width: '110px' },
    { key: 'branch_name', label: 'Branch' },
    { key: 'is_active',   label: 'Status',  type: 'badge',  width: '110px' },
    { key: '_actions',    label: '',        type: 'actions', width: '100px' },
];

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

function openCreate() {
    editTarget.value = null;
    formOpen.value   = true;
}

function openEdit(row) {
    editTarget.value = { ...row };
    formOpen.value   = true;
}

function onSaved() {
    formOpen.value = false;
    fetchUsers();
}

// ── Toggle status ─────────────────────────────────────────────────────────
const toggleOpen    = ref(false);
const toggleTarget  = ref(null);
const toggleLoading = ref(false);

const toggleMessage = computed(() => {
    if (!toggleTarget.value) return '';
    return toggleTarget.value.is_active
        ? `Deactivate "${toggleTarget.value.name}"? They will lose access immediately.`
        : `Activate "${toggleTarget.value.name}"? They will regain access.`;
});

function confirmToggle(row) {
    toggleTarget.value = row;
    toggleOpen.value   = true;
}

async function executeToggle() {
    if (!toggleTarget.value) return;
    toggleLoading.value = true;
    try {
        await userService.toggleStatus(toggleTarget.value.id);
        toggleOpen.value = false;
        fetchUsers();
    } catch (err) {
        alert(err.response?.data?.message ?? 'Failed to update user status.');
    } finally {
        toggleLoading.value = false;
    }
}

// ── Delete ────────────────────────────────────────────────────────────────
const deleteOpen    = ref(false);
const deleteTarget  = ref(null);
const deleteLoading = ref(false);

function confirmDelete(row) {
    deleteTarget.value = row;
    deleteOpen.value   = true;
}

async function executeDelete() {
    if (!deleteTarget.value) return;
    deleteLoading.value = true;
    try {
        await userService.destroy(deleteTarget.value.id);
        deleteOpen.value = false;
        fetchUsers();
    } catch (err) {
        alert(err.response?.data?.message ?? 'Failed to delete user.');
    } finally {
        deleteLoading.value = false;
    }
}
</script>

<style scoped>
@reference '../../../css/app.css';
.btn-primary {
    @apply flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition-colors shadow-sm;
}
</style>
