<template>
    <div class="p-6 lg:p-8 space-y-6">

        <!-- Page header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">{{ t('branches.title') }}</h1>
                <p class="mt-1 text-sm text-gray-500">
                    {{ t('branches.subtitle') }}
                    <span v-if="store.meta" class="text-gray-400">
                        ({{ store.meta.total }} {{ t('common.total') }})
                    </span>
                </p>
            </div>
            <button @click="openCreate" class="btn-primary">
                <PlusIcon class="w-4 h-4" />
                {{ t('branches.new') }}
            </button>
        </div>

        <!-- Toolbar -->
        <div class="flex flex-col sm:flex-row gap-3">
            <div class="relative flex-1 max-w-sm">
                <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" />
                <input
                    v-model="searchQuery"
                    type="search"
                    :placeholder="t('branches.searchPlaceholder')"
                    class="w-full pl-9 pr-4 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                />
            </div>

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
            :empty-title="t('branches.emptyTitle')"
            :empty-message="t('branches.emptyMessage')"
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

</template>

<script setup>
import { ref, watch, onMounted, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useBranchStore } from '@/stores/branch';
import { branchService } from '@/services/branchService';
import { useDebounce } from '@vueuse/core';

import DataTable        from '@/components/ui/DataTable.vue';
import BranchFormModal  from './BranchFormModal.vue';
import { useAlert }     from '@/composables/useAlert';

import {
    PlusIcon,
    MagnifyingGlassIcon,
    PencilSquareIcon,
    TrashIcon,
} from '@heroicons/vue/24/outline';

const { t } = useI18n();
const store = useBranchStore();
const { toast, confirm } = useAlert();

// ── Table columns ─────────────────────────────────────────────────────────
const columns = computed(() => [
    { key: 'code',       label: t('common.code'),        bold: true, width: '110px' },
    { key: 'name',       label: t('branches.name') },
    { key: 'phone',      label: t('common.phone') },
    { key: 'email',      label: t('common.email') },
    { key: 'user_count', label: t('branches.userCount'), width: '80px',  align: 'center' },
    { key: 'is_active',  label: t('common.status'),      type: 'badge',  width: '110px' },
    { key: '_actions',   label: '',                       type: 'actions', width: '80px' },
]);

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

function openCreate() { editTarget.value = null; formOpen.value = true; }
function openEdit(row) { editTarget.value = { ...row }; formOpen.value = true; }

function onSaved(isEdit) {
    formOpen.value = false;
    store.fetch();
    store.invalidateCache();
    toast('success', isEdit ? t('common.updatedSuccess') : t('common.createdSuccess'));
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
        await branchService.destroy(row.id);
        store.fetch();
        store.invalidateCache();
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
