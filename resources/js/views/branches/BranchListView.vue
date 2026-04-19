<template>
    <div class="p-6 lg:p-8 space-y-6">

        <!-- Page header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Branches</h1>
                <p class="mt-1 text-sm text-gray-500">
                    Manage your business locations.
                    <span v-if="store.meta" class="text-gray-400">
                        ({{ store.meta.total }} total)
                    </span>
                </p>
            </div>
            <button @click="openCreate" class="btn-primary">
                <PlusIcon class="w-4 h-4" />
                New Branch
            </button>
        </div>

        <!-- Toolbar -->
        <div class="flex flex-col sm:flex-row gap-3">
            <div class="relative flex-1 max-w-sm">
                <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" />
                <input
                    v-model="searchQuery"
                    type="search"
                    placeholder="Search name or code…"
                    class="w-full pl-9 pr-4 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                />
            </div>

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
        <div v-if="store.error" class="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
            {{ store.error }}
        </div>

        <!-- Table -->
        <DataTable
            :columns="columns"
            :rows="store.rows"
            :loading="store.loading"
            :meta="store.meta"
            sort-key="name"
            empty-title="No branches found"
            empty-message="Create your first branch to get started."
            @page-change="store.fetch({ page: $event })"
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
    <BranchFormModal
        v-model:open="formOpen"
        :branch="editTarget"
        @saved="onSaved"
    />

    <!-- Delete confirm -->
    <ConfirmDialog
        v-model="deleteOpen"
        title="Delete Branch"
        :message="`Are you sure you want to delete &quot;${deleteTarget?.name}&quot;? This action cannot be undone.`"
        confirm-label="Delete"
        :danger="true"
        :loading="deleteLoading"
        @confirm="executeDelete"
    />
</template>

<script setup>
import { ref, watch, onMounted } from 'vue';
import { useBranchStore } from '@/stores/branch';
import { branchService } from '@/services/branchService';
import { useDebounce } from '@vueuse/core';

import DataTable    from '@/components/ui/DataTable.vue';
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue';
import BranchFormModal from './BranchFormModal.vue';

import {
    PlusIcon,
    MagnifyingGlassIcon,
    PencilSquareIcon,
    TrashIcon,
} from '@heroicons/vue/24/outline';

const store = useBranchStore();

// ── Table columns ─────────────────────────────────────────────────────────
const columns = [
    { key: 'code',       label: 'Code',    bold: true, width: '110px' },
    { key: 'name',       label: 'Branch Name' },
    { key: 'phone',      label: 'Phone' },
    { key: 'email',      label: 'Email' },
    { key: 'user_count', label: 'Users',   width: '80px',  align: 'center' },
    { key: 'is_active',  label: 'Status',  type: 'badge',  width: '110px' },
    { key: '_actions',   label: '',        type: 'actions', width: '80px' },
];

// ── Filters ───────────────────────────────────────────────────────────────
const searchQuery  = ref('');
const statusFilter = ref('');
const debouncedSearch = useDebounce(searchQuery, 350);

watch(debouncedSearch, (val) => store.fetch({ search: val, page: 1 }));
watch(statusFilter,   (val) => store.fetch({ is_active: val, page: 1 }));

onMounted(() => store.fetch());

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
    store.fetch();
    store.invalidateCache(); // Refresh dropdown cache
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
        await branchService.destroy(deleteTarget.value.id);
        deleteOpen.value = false;
        store.fetch();
        store.invalidateCache();
    } catch (err) {
        alert(err.response?.data?.message ?? 'Failed to delete branch.');
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
