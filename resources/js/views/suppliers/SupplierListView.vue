<template>
    <div class="p-6 lg:p-8 space-y-6">

        <!-- Page header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">{{ t('suppliers.title') }}</h1>
                <p class="mt-1 text-sm text-gray-500">
                    {{ t('suppliers.subtitle') }}
                    <span v-if="meta" class="text-gray-400">({{ meta.total }} {{ t('common.total') }})</span>
                </p>
            </div>
            <button @click="openCreate" class="btn-primary">
                <PlusIcon class="w-4 h-4" />
                {{ t('suppliers.new') }}
            </button>
        </div>

        <!-- Toolbar -->
        <div class="flex flex-col sm:flex-row gap-3">
            <div class="relative flex-1 max-w-sm">
                <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" />
                <input
                    v-model="searchQuery"
                    type="search"
                    :placeholder="t('suppliers.searchPlaceholder')"
                    class="w-full pl-9 pr-4 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                />
            </div>
            <select v-model="statusFilter" class="px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white">
                <option value="">{{ t('common.allStatuses') }}</option>
                <option value="1">{{ t('common.active') }}</option>
                <option value="0">{{ t('common.inactive') }}</option>
            </select>
        </div>

        <!-- Error banner -->
        <div v-if="listError" class="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">{{ listError }}</div>

        <!-- Table -->
        <DataTable
            :columns="columns"
            :rows="rows"
            :loading="loading"
            :meta="meta"
            sort-key="name"
            :empty-title="t('suppliers.emptyTitle')"
            :empty-message="t('suppliers.emptyMessage')"
            @page-change="fetchPage"
        >
            <template #row-actions="{ row }">
                <button @click="openEdit(row)" class="p-1.5 text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors" title="Edit">
                    <PencilSquareIcon class="w-4 h-4" />
                </button>
                <button @click="confirmToggle(row)" class="p-1.5 text-gray-400 hover:text-amber-600 hover:bg-amber-50 rounded-lg transition-colors">
                    <NoSymbolIcon v-if="row.is_active" class="w-4 h-4" />
                    <CheckCircleIcon v-else class="w-4 h-4" />
                </button>
                <button @click="confirmDelete(row)" class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors" title="Delete">
                    <TrashIcon class="w-4 h-4" />
                </button>
            </template>
        </DataTable>

    </div>

    <SupplierFormModal v-model:open="formOpen" :supplier="editTarget" @saved="onSaved" />
</template>

<script setup>
import { ref, watch, onMounted, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { supplierService } from '@/services/supplierService';
import { useDebounce } from '@vueuse/core';
import { useAlert } from '@/composables/useAlert';

import DataTable         from '@/components/ui/DataTable.vue';
import SupplierFormModal from './SupplierFormModal.vue';

import {
    PlusIcon, MagnifyingGlassIcon, PencilSquareIcon,
    TrashIcon, NoSymbolIcon, CheckCircleIcon,
} from '@heroicons/vue/24/outline';

const { t } = useI18n();
const { toast, confirm } = useAlert();

const columns = computed(() => [
    { key: 'code',    label: t('common.code'),    width: '110px' },
    { key: 'name',    label: t('common.name'),    bold: true },
    { key: 'city',    label: t('suppliers.city') },
    { key: 'phone',   label: t('common.phone') },
    { key: 'email',   label: t('common.email') },
    { key: 'is_active', label: t('common.status'), type: 'badge', width: '100px' },
    { key: '_actions',  label: '', type: 'actions', width: '100px' },
]);

const rows    = ref([]);
const meta    = ref(null);
const loading = ref(false);
const listError   = ref('');
const filters = ref({ search: '', is_active: '', page: 1, per_page: 20 });

const searchQuery  = ref('');
const statusFilter = ref('');
const debouncedSearch = useDebounce(searchQuery, 350);

watch(debouncedSearch, (val) => fetch({ search: val, page: 1 }));
watch(statusFilter,   (val) => fetch({ is_active: val, page: 1 }));

async function fetch(overrides = {}) {
    Object.assign(filters.value, overrides);
    loading.value   = true;
    listError.value = '';
    try {
        const { data } = await supplierService.index(filters.value);
        rows.value = data.data;
        meta.value = data.meta;
    } catch (err) {
        listError.value = err.response?.data?.message ?? 'Failed to load suppliers.';
    } finally {
        loading.value = false;
    }
}

function fetchPage(page) { fetch({ page }); }

onMounted(() => fetch());

const formOpen   = ref(false);
const editTarget = ref(null);

function openCreate() { editTarget.value = null; formOpen.value = true; }
function openEdit(row) { editTarget.value = { ...row }; formOpen.value = true; }

function onSaved(isEdit) {
    formOpen.value = false;
    fetch();
    toast('success', isEdit ? t('common.updatedSuccess') : t('common.createdSuccess'));
}

async function confirmToggle(row) {
    const isActive = row.is_active;
    const ok = await confirm({
        title:       isActive ? t('common.deactivateTitle') : t('common.activateTitle'),
        text:        isActive ? t('common.deactivateMessage', { name: row.name }) : t('common.activateMessage', { name: row.name }),
        confirmText: isActive ? t('common.deactivate') : t('common.activate'),
        danger:      isActive,
    });
    if (!ok) return;
    try {
        await supplierService.toggleStatus(row.id);
        fetch();
        toast('success', isActive ? t('common.deactivatedSuccess') : t('common.activatedSuccess'));
    } catch (err) {
        toast('error', err.response?.data?.message ?? t('common.unexpectedError'));
    }
}

async function confirmDelete(row) {
    const ok = await confirm({
        title: t('common.deleteConfirmTitle'),
        text:  t('common.deleteConfirmMessage', { name: row.name }),
        confirmText: t('common.delete'),
        danger: true,
    });
    if (!ok) return;
    try {
        await supplierService.destroy(row.id);
        fetch();
        toast('success', t('common.deletedSuccess'));
    } catch (err) {
        toast('error', err.response?.data?.message ?? t('common.unexpectedError'));
    }
}
</script>

