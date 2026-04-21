<template>
    <div class="p-6 lg:p-8 space-y-6">

        <!-- Page header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">{{ t('sales.title') }}</h1>
                <p class="mt-1 text-sm text-gray-500">
                    {{ t('sales.subtitle') }}
                    <span v-if="meta" class="text-gray-400">({{ meta.total }} {{ t('common.total') }})</span>
                </p>
            </div>
            <RouterLink :to="{ name: 'pos' }" class="btn-primary">
                <ShoppingCartIcon class="w-4 h-4" />
                {{ t('sales.newSale') }}
            </RouterLink>
        </div>

        <!-- Filters -->
        <div class="flex flex-col sm:flex-row gap-3 flex-wrap">
            <div class="relative flex-1 min-w-[200px]">
                <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" />
                <input v-model="searchQuery" type="search" :placeholder="t('sales.searchPlaceholder')"
                    class="w-full pl-9 pr-4 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            </div>
            <select v-model="statusFilter" class="px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white">
                <option value="">{{ t('common.allStatuses') }}</option>
                <option value="active">{{ t('sales.statusActive') }}</option>
                <option value="cancelled">{{ t('sales.statusCancelled') }}</option>
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
            sort-key="sale_date"
            :empty-title="t('sales.emptyTitle')"
            :empty-message="t('sales.emptyMessage')"
            @page-change="fetchPage"
        >
            <template #cell-status="{ value }">
                <span :class="['inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium',
                    value === 'active' ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-700']">
                    {{ value === 'active' ? t('sales.statusActive') : t('sales.statusCancelled') }}
                </span>
            </template>

            <template #row-actions="{ row }">
                <!-- View invoice -->
                <RouterLink
                    :to="{ name: 'sale-invoice', params: { id: row.id } }"
                    class="p-1.5 text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors inline-flex"
                    :title="t('sales.viewInvoice')"
                >
                    <DocumentTextIcon class="w-4 h-4" />
                </RouterLink>
                <!-- Cancel (active only) -->
                <button
                    v-if="row.status === 'active'"
                    @click="confirmCancel(row)"
                    class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                    :title="t('sales.cancel')"
                >
                    <XCircleIcon class="w-4 h-4" />
                </button>
            </template>
        </DataTable>

    </div>
</template>

<script setup>
import { ref, watch, onMounted, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { RouterLink } from 'vue-router';
import { saleService } from '@/services/saleService';
import { useSettingsStore } from '@/stores/settings';
import { useDebounce } from '@vueuse/core';
import { useAlert } from '@/composables/useAlert';
import DataTable from '@/components/ui/DataTable.vue';
import {
    ShoppingCartIcon, MagnifyingGlassIcon,
    DocumentTextIcon, XCircleIcon,
} from '@heroicons/vue/24/outline';

const { t }           = useI18n();
const { toast, confirm } = useAlert();
const settingsStore   = useSettingsStore();

function formatCurrency(value) {
    if (value == null) return '—';
    const code = settingsStore.settings?.currency_code ?? 'EUR';
    return new Intl.NumberFormat('de-DE', { style: 'currency', currency: code }).format(value);
}

const columns = computed(() => [
    { key: 'sale_number',   label: t('sales.number'),      bold: true,  width: '130px' },
    { key: 'sale_date',     label: t('sales.date'),                      width: '110px' },
    { key: 'customer_name', label: t('sales.customer') },
    { key: 'items_count',   label: t('sales.items'),                     width: '70px' },
    { key: 'grand_total',   label: t('sales.total'),                     width: '130px',
      format: (v) => formatCurrency(v) },
    { key: 'total_paid',    label: t('sales.paid'),                      width: '130px',
      format: (v) => formatCurrency(v) },
    { key: 'status',        label: t('common.status'),     type: 'custom', width: '110px' },
    { key: '_actions',      label: '',                     type: 'actions', width: '80px' },
]);

const rows    = ref([]);
const meta    = ref(null);
const loading = ref(false);
const listError = ref('');
const filters = ref({ search: '', status: '', date_from: '', date_to: '', page: 1, per_page: 20 });

const searchQuery  = ref('');
const statusFilter = ref('');
const dateFrom     = ref('');
const dateTo       = ref('');
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
        const { data } = await saleService.index(filters.value);
        rows.value = data.data;
        meta.value = data.meta;
    } catch (err) {
        listError.value = err.response?.data?.message ?? 'Failed to load sales.';
    } finally {
        loading.value = false;
    }
}

function fetchPage(page) { fetchList({ page }); }
onMounted(() => fetchList());

async function confirmCancel(row) {
    const ok = await confirm({
        title:       t('sales.cancelTitle'),
        text:        t('sales.cancelMessage', { number: row.sale_number }),
        confirmText: t('sales.cancel'),
        danger:      true,
    });
    if (!ok) return;
    try {
        await saleService.cancel(row.id);
        fetchList();
        toast('success', t('sales.cancelledSuccess'));
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
