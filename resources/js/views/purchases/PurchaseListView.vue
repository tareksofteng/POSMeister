<template>
    <div class="p-6 lg:p-8 space-y-6">

        <!-- Page header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">{{ t('purchases.title') }}</h1>
                <p class="mt-1 text-sm text-gray-500">
                    {{ t('purchases.subtitle') }}
                    <span v-if="meta" class="text-gray-400">({{ meta.total }} {{ t('common.total') }})</span>
                </p>
            </div>
            <RouterLink :to="{ name: 'purchase-create' }" class="btn-primary">
                <PlusIcon class="w-4 h-4" />
                {{ t('purchases.new') }}
            </RouterLink>
        </div>

        <!-- Toolbar -->
        <div class="flex flex-col sm:flex-row gap-3 flex-wrap">
            <div class="relative flex-1 min-w-[200px]">
                <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" />
                <input
                    v-model="searchQuery"
                    type="search"
                    :placeholder="t('purchases.searchPlaceholder')"
                    class="w-full pl-9 pr-4 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                />
            </div>
            <select v-model="statusFilter" class="px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white">
                <option value="">{{ t('purchases.allStatuses') }}</option>
                <option value="draft">{{ t('purchases.statusDraft') }}</option>
                <option value="received">{{ t('purchases.statusReceived') }}</option>
            </select>
            <input v-model="dateFrom" type="date" class="px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white" />
            <input v-model="dateTo"   type="date" class="px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white" />
        </div>

        <div v-if="listError" class="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">{{ listError }}</div>

        <!-- Table -->
        <DataTable
            :columns="columns"
            :rows="rows"
            :loading="loading"
            :meta="meta"
            sort-key="purchase_date"
            :empty-title="t('purchases.emptyTitle')"
            :empty-message="t('purchases.emptyMessage')"
            @page-change="fetchPage"
        >
            <template #cell-status="{ value }">
                <span :class="['inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium', value === 'received' ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800']">
                    {{ value === 'received' ? t('purchases.statusReceived') : t('purchases.statusDraft') }}
                </span>
            </template>

            <template #row-actions="{ row }">
                <!-- Invoice (received only) -->
                <RouterLink
                    v-if="row.status === 'received'"
                    :to="{ name: 'purchase-invoice', params: { id: row.id } }"
                    class="p-1.5 text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors inline-flex"
                    :title="t('purchases.viewInvoice')"
                >
                    <DocumentTextIcon class="w-4 h-4" />
                </RouterLink>
                <!-- Edit (draft only) -->
                <RouterLink
                    v-if="row.status === 'draft'"
                    :to="{ name: 'purchase-edit', params: { id: row.id } }"
                    class="p-1.5 text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors inline-flex"
                    :title="t('common.edit')"
                >
                    <PencilSquareIcon class="w-4 h-4" />
                </RouterLink>
                <!-- Receive stock (draft only) -->
                <button
                    v-if="row.status === 'draft'"
                    @click="confirmReceive(row)"
                    class="p-1.5 text-gray-400 hover:text-green-600 hover:bg-green-50 rounded-lg transition-colors"
                    :title="t('purchases.receive')"
                >
                    <CheckBadgeIcon class="w-4 h-4" />
                </button>
                <!-- Delete (draft only) -->
                <button
                    v-if="row.status === 'draft'"
                    @click="confirmDelete(row)"
                    class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                    :title="t('common.delete')"
                >
                    <TrashIcon class="w-4 h-4" />
                </button>
            </template>
        </DataTable>

    </div>
</template>

<script setup>
import { ref, watch, onMounted, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { RouterLink, useRouter } from 'vue-router';
import { purchaseService } from '@/services/purchaseService';
import { useSettingsStore } from '@/stores/settings';
import { useDebounce } from '@vueuse/core';
import { useAlert } from '@/composables/useAlert';

import DataTable from '@/components/ui/DataTable.vue';

import {
    PlusIcon, MagnifyingGlassIcon, PencilSquareIcon,
    TrashIcon, CheckBadgeIcon, DocumentTextIcon,
} from '@heroicons/vue/24/outline';

const { t } = useI18n();
const { toast, confirm } = useAlert();
const settingsStore = useSettingsStore();
const router = useRouter();

function formatCurrency(value) {
    if (value == null) return '—';
    const code = settingsStore.settings?.currency_code ?? 'EUR';
    return new Intl.NumberFormat('de-DE', { style: 'currency', currency: code }).format(value);
}

const columns = computed(() => [
    { key: 'purchase_number', label: t('purchases.number'),       bold: true, width: '140px' },
    { key: 'purchase_date',   label: t('purchases.date'),         width: '110px' },
    { key: 'supplier_name',   label: t('suppliers.title') },
    { key: 'items_count',     label: t('purchases.items'),        width: '70px' },
    { key: 'total_amount',    label: t('purchases.total'),        width: '120px',
      format: (v) => formatCurrency(v) },
    { key: 'status',          label: t('common.status'),          type: 'custom', width: '100px' },
    { key: '_actions',        label: '',                          type: 'actions', width: '110px' },
]);

const rows    = ref([]);
const meta    = ref(null);
const loading = ref(false);
const listError = ref('');
const filters = ref({ search: '', status: '', date_from: '', date_to: '', page: 1, per_page: 20 });

const searchQuery = ref('');
const statusFilter = ref('');
const dateFrom    = ref('');
const dateTo      = ref('');
const debouncedSearch = useDebounce(searchQuery, 350);

watch(debouncedSearch, (val) => fetchList({ search: val, page: 1 }));
watch(statusFilter,   (val) => fetchList({ status: val, page: 1 }));
watch(dateFrom,       (val) => fetchList({ date_from: val, page: 1 }));
watch(dateTo,         (val) => fetchList({ date_to: val, page: 1 }));

async function fetchList(overrides = {}) {
    Object.assign(filters.value, overrides);
    loading.value   = true;
    listError.value = '';
    try {
        const { data } = await purchaseService.index(filters.value);
        rows.value = data.data;
        meta.value = data.meta;
    } catch (err) {
        listError.value = err.response?.data?.message ?? 'Failed to load purchases.';
    } finally {
        loading.value = false;
    }
}

function fetchPage(page) { fetchList({ page }); }
onMounted(() => fetchList());

async function confirmReceive(row) {
    const ok = await confirm({
        title:       t('purchases.receiveTitle'),
        text:        t('purchases.receiveMessage', { number: row.purchase_number }),
        confirmText: t('purchases.receive'),
    });
    if (!ok) return;
    try {
        await purchaseService.receive(row.id);
        fetchList();
        toast('success', t('purchases.receivedSuccess'));

        const wantsPrint = await confirm({
            title:       t('purchases.printPromptTitle'),
            text:        t('purchases.printPromptText'),
            confirmText: t('purchases.printNow'),
            cancelText:  t('purchases.skipPrint'),
        });
        if (wantsPrint) {
            router.push({ name: 'purchase-invoice', params: { id: row.id } });
        }
    } catch (err) {
        toast('error', err.response?.data?.message ?? t('common.unexpectedError'));
    }
}

async function confirmDelete(row) {
    const ok = await confirm({
        title:       t('common.deleteConfirmTitle'),
        text:        t('common.deleteConfirmMessage', { name: row.purchase_number }),
        confirmText: t('common.delete'),
        danger:      true,
    });
    if (!ok) return;
    try {
        await purchaseService.destroy(row.id);
        fetchList();
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
