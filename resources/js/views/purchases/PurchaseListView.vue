<template>
    <div class="p-3 sm:p-6 lg:p-8 space-y-4 sm:space-y-6 anim-fade-in">

        <!-- Page header — unified typography + Button primitive. -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-4 anim-fade-up">
            <div>
                <h1 class="h1-display">{{ t('purchases.title') }}</h1>
                <p class="mt-1.5 t-body">
                    {{ t('purchases.subtitle') }}
                    <span v-if="meta" class="text-slate-400">({{ meta.total }} {{ t('common.total') }})</span>
                </p>
            </div>
            <Button variant="primary" :to="{ name: 'purchase-create' }" :leading-icon="PlusIcon">
                {{ t('purchases.new') }}
            </Button>
        </div>

        <!-- Filter toolbar — same control surface as Sales / Stock / etc. -->
        <div class="card filter-bar">
            <div class="relative flex-1 min-w-[200px]">
                <MagnifyingGlassIcon class="filter-icon" />
                <input v-model="searchQuery" type="search" :placeholder="t('purchases.searchPlaceholder')" class="filter-input filter-input-search" />
            </div>
            <select v-model="statusFilter" class="filter-input">
                <option value="">{{ t('purchases.allStatuses') }}</option>
                <option value="draft">{{ t('purchases.statusDraft') }}</option>
                <option value="received">{{ t('purchases.statusReceived') }}</option>
            </select>
            <input v-model="dateFrom" type="date" class="filter-input" />
            <input v-model="dateTo"   type="date" class="filter-input" />
        </div>

        <div v-if="listError" class="card card-alert card-alert-danger text-sm">{{ listError }}</div>

        <!-- Table -->
        <DataTable
            :columns="columns"
            :rows="rows"
            :loading="loading"
            :meta="meta"
            sort-key="purchase_date"
            empty-tone="indigo"
            :empty-icon="TruckIcon"
            :empty-title="t('purchases.emptyTitle')"
            :empty-message="t('purchases.emptyMessage')"
            @page-change="fetchPage"
        >
            <template #cell-status="{ value }">
                <span :class="['status-pill', value === 'received' ? 'status-pill-success' : 'status-pill-warning']">
                    {{ value === 'received' ? t('purchases.statusReceived') : t('purchases.statusDraft') }}
                </span>
            </template>

            <template #row-actions="{ row }">
                <RouterLink
                    v-if="row.status === 'received'"
                    :to="{ name: 'purchase-invoice', params: { id: row.id } }"
                    class="row-action row-action-indigo"
                    :title="t('purchases.viewInvoice')"
                >
                    <DocumentTextIcon class="w-4 h-4" />
                </RouterLink>
                <RouterLink
                    v-if="row.status === 'draft'"
                    :to="{ name: 'purchase-edit', params: { id: row.id } }"
                    class="row-action row-action-indigo"
                    :title="t('common.edit')"
                >
                    <PencilSquareIcon class="w-4 h-4" />
                </RouterLink>
                <button
                    v-if="row.status === 'draft'"
                    @click="confirmReceive(row)"
                    class="row-action row-action-emerald"
                    :title="t('purchases.receive')"
                >
                    <CheckBadgeIcon class="w-4 h-4" />
                </button>
                <button
                    v-if="row.status === 'draft'"
                    @click="confirmDelete(row)"
                    class="row-action row-action-danger"
                    :title="t('common.delete')"
                >
                    <TrashIcon class="w-4 h-4" />
                </button>
            </template>

            <template #empty-action>
                <Button variant="primary" :to="{ name: 'purchase-create' }" :leading-icon="PlusIcon">
                    {{ t('purchases.new') }}
                </Button>
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
import Button    from '@/components/ui/Button.vue';

import {
    PlusIcon, MagnifyingGlassIcon, PencilSquareIcon,
    TrashIcon, CheckBadgeIcon, DocumentTextIcon, TruckIcon,
} from '@heroicons/vue/24/outline';

const { t } = useI18n();
const { toast, confirm } = useAlert();
const settingsStore = useSettingsStore();
const router = useRouter();

function formatCurrency(value) {
    if (value == null) return '—';
    const code = settingsStore.settings?.currency_code ?? 'EUR';
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: code }).format(value);
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
// Sensible defaults so a fresh load shows current-month activity instead
// of an empty list.
const _today      = new Date().toISOString().slice(0, 10);
const _monthStart = (() => { const d = new Date(); return new Date(d.getFullYear(), d.getMonth(), 1).toISOString().slice(0, 10); })();

const filters = ref({ search: '', status: '', date_from: _monthStart, date_to: _today, page: 1, per_page: 20 });

const searchQuery = ref('');
const statusFilter = ref('');
const dateFrom    = ref(_monthStart);
const dateTo      = ref(_today);
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
        if (navigator.onLine === false) {
            await loadFromCache();
            return;
        }
        const { data } = await purchaseService.index(filters.value);
        rows.value = data.data;
        meta.value = data.meta;
    } catch (err) {
        const swOffline = err.response?.headers?.['x-posmeister-offline'] === '1';
        if (!err.response || swOffline) {
            try { await loadFromCache(); return; } catch { /* fall through */ }
        }
        listError.value = err.response?.data?.message ?? 'Failed to load purchases.';
    } finally {
        loading.value = false;
    }
}

async function loadFromCache() {
    const { loadRecentPurchases } = await import('@/offline/settingsCache');
    const all = await loadRecentPurchases();
    const f = filters.value;
    const q = (f.search || '').toLowerCase();
    const filtered = all.filter((p) => {
        if (q && !(`${p.purchase_number} ${p.supplier_name || ''} ${p.reference || ''}`).toLowerCase().includes(q)) return false;
        if (f.status && p.status !== f.status) return false;
        if (f.date_from && p.purchase_date < f.date_from) return false;
        if (f.date_to   && p.purchase_date > f.date_to)   return false;
        return true;
    });
    rows.value = filtered;
    meta.value = { total: filtered.length, per_page: filtered.length, current_page: 1, last_page: 1 };
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

