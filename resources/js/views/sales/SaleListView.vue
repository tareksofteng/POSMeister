<template>
    <div class="p-3 sm:p-6 lg:p-8 space-y-4 sm:space-y-6 anim-fade-in">

        <!-- Page header — unified typography + Button primitives. -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-4 anim-fade-up">
            <div>
                <h1 class="h1-display">{{ t('sales.title') }}</h1>
                <p class="mt-1.5 t-body">
                    {{ t('sales.subtitle') }}
                    <span v-if="meta" class="text-slate-400">({{ meta.total }} {{ t('common.total') }})</span>
                </p>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <Button
                    variant="secondary"
                    :to="{ name: 'pos' }"
                    :leading-icon="ShoppingCartIcon"
                >
                    {{ t('menu.cashTerminal') }}
                </Button>
                <Button
                    variant="primary"
                    :to="{ name: 'sale-create' }"
                    :leading-icon="DocumentPlusIcon"
                >
                    {{ t('sales.newSale') }}
                </Button>
            </div>
        </div>

        <!--
            Filter toolbar — premium chrome via .card surface so the
            filter block reads as a coherent control surface, not four
            stray inputs floating on the background.
        -->
        <div class="card filter-bar">
            <div class="relative flex-1 min-w-[200px]">
                <MagnifyingGlassIcon class="filter-icon" />
                <input v-model="searchQuery" type="search" :placeholder="t('sales.searchPlaceholder')" class="filter-input filter-input-search" />
            </div>
            <select v-model="statusFilter" class="filter-input">
                <option value="">{{ t('common.allStatuses') }}</option>
                <option value="active">{{ t('sales.statusActive') }}</option>
                <option value="cancelled">{{ t('sales.statusCancelled') }}</option>
            </select>
            <input v-model="dateFrom" type="date" class="filter-input" />
            <input v-model="dateTo"   type="date" class="filter-input" />
        </div>

        <div v-if="listError" class="card card-alert card-alert-danger text-sm">{{ listError }}</div>

        <!-- Table — DataTable now consumes the Phase AA premium chrome. -->
        <DataTable
            :columns="columns"
            :rows="rows"
            :loading="loading"
            :meta="meta"
            sort-key="sale_date"
            empty-tone="emerald"
            :empty-icon="ShoppingCartIcon"
            :empty-title="t('sales.emptyTitle')"
            :empty-message="t('sales.emptyMessage')"
            @page-change="fetchPage"
        >
            <template #cell-status="{ value }">
                <span :class="['status-pill', value === 'active' ? 'status-pill-success' : 'status-pill-danger']">
                    {{ value === 'active' ? t('sales.statusActive') : t('sales.statusCancelled') }}
                </span>
            </template>

            <template #row-actions="{ row }">
                <RouterLink
                    :to="{ name: 'sale-invoice', params: { id: row.id } }"
                    class="row-action row-action-indigo"
                    :title="t('sales.viewInvoice')"
                >
                    <DocumentTextIcon class="w-4 h-4" />
                </RouterLink>
                <button
                    v-if="row.status === 'active'"
                    @click="confirmCancel(row)"
                    class="row-action row-action-danger"
                    :title="t('sales.cancel')"
                >
                    <XCircleIcon class="w-4 h-4" />
                </button>
            </template>

            <template #empty-action>
                <Button variant="primary" :to="{ name: 'sale-create' }" :leading-icon="DocumentPlusIcon">
                    {{ t('sales.newSale') }}
                </Button>
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
import Button    from '@/components/ui/Button.vue';
import {
    ShoppingCartIcon, DocumentPlusIcon, MagnifyingGlassIcon,
    DocumentTextIcon, XCircleIcon,
} from '@heroicons/vue/24/outline';

const { t } = useI18n();
const { toast, confirm } = useAlert();
const settingsStore = useSettingsStore();

function formatCurrency(value) {
    if (value == null) return '—';
    const code = settingsStore.settings?.currency_code ?? 'EUR';
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: code }).format(value);
}

const columns = computed(() => [
    { key: 'sale_number', label: t('sales.number'), bold: true, width: '130px' },
    { key: 'sale_date', label: t('sales.date'), width: '110px' },
    { key: 'customer_name', label: t('sales.customer') },
    { key: 'items_count', label: t('sales.items'), width: '70px' },
    {
        key: 'grand_total', label: t('sales.total'), width: '130px',
        format: (v) => formatCurrency(v)
    },
    {
        key: 'total_paid', label: t('sales.paid'), width: '130px',
        format: (v) => formatCurrency(v)
    },
    { key: 'status', label: t('common.status'), type: 'custom', width: '110px' },
    { key: '_actions', label: '', type: 'actions', width: '80px' },
]);

const rows = ref([]);
const meta = ref(null);
const loading = ref(false);
const listError = ref('');
const filters = ref({ search: '', status: '', date_from: '', date_to: '', page: 1, per_page: 20 });

const searchQuery = ref('');
const statusFilter = ref('');
const dateFrom = ref('');
const dateTo = ref('');
const debouncedSearch = useDebounce(searchQuery, 350);

watch(debouncedSearch, (val) => fetchList({ search: val, page: 1 }));
watch(statusFilter, (val) => fetchList({ status: val, page: 1 }));
watch(dateFrom, (val) => fetchList({ date_from: val, page: 1 }));
watch(dateTo, (val) => fetchList({ date_to: val, page: 1 }));

async function fetchList(overrides = {}) {
    Object.assign(filters.value, overrides);
    loading.value = true;
    listError.value = '';
    try {
        if (navigator.onLine === false) {
            await loadFromCache();
            return;
        }
        const { data } = await saleService.index(filters.value);
        rows.value = data.data;
        meta.value = data.meta;
    } catch (err) {
        // SW returns its 503 offline envelope on a flaky connection — degrade
        // to the cached snapshot so the cashier still sees their recent sales.
        const swOffline = err.response?.headers?.['x-posmeister-offline'] === '1';
        if (!err.response || swOffline) {
            try { await loadFromCache(); return; } catch { /* fall through */ }
        }
        listError.value = err.response?.data?.message ?? 'Failed to load sales.';
    } finally {
        loading.value = false;
    }
}

async function loadFromCache() {
    const { loadRecentSales } = await import('@/offline/settingsCache');
    const all = await loadRecentSales();
    const f = filters.value;
    const q = (f.search || '').toLowerCase();
    const filtered = all.filter((s) => {
        if (q && !(`${s.sale_number} ${s.customer_name || ''}`).toLowerCase().includes(q)) return false;
        if (f.status && s.status !== f.status) return false;
        if (f.date_from && s.sale_date < f.date_from) return false;
        if (f.date_to   && s.sale_date > f.date_to)   return false;
        return true;
    });
    rows.value = filtered;
    meta.value = { total: filtered.length, per_page: filtered.length, current_page: 1, last_page: 1 };
}

function fetchPage(page) { fetchList({ page }); }
onMounted(() => fetchList());

async function confirmCancel(row) {
    const ok = await confirm({
        title: t('sales.cancelTitle'),
        text: t('sales.cancelMessage', { number: row.sale_number }),
        confirmText: t('sales.cancel'),
        danger: true,
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

