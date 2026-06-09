<template>
    <div class="p-3 sm:p-6 lg:p-8 space-y-4 sm:space-y-6 anim-fade-in">

        <!-- ── Page Header ─────────────────────────────────────────────── -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-4 anim-fade-up">
            <div>
                <h1 class="h1-display">{{ t('customers.title') }}</h1>
                <p class="mt-1.5 t-body">
                    {{ t('customers.subtitle') }}
                    <span v-if="meta" class="text-slate-400">({{ meta.total }} {{ t('common.total') }})</span>
                </p>
            </div>
            <Button variant="primary" :leading-icon="PlusIcon" @click="openForm(null)">
                {{ t('customers.newCustomer') }}
            </Button>
        </div>

        <!-- ── Filters ─────────────────────────────────────────────────── -->
        <div class="card filter-bar">
            <div class="relative flex-1 min-w-[200px]">
                <MagnifyingGlassIcon class="filter-icon" />
                <input v-model="searchQuery" type="search" :placeholder="t('customers.searchPlaceholder')" class="filter-input filter-input-search" />
            </div>
            <select v-model="statusFilter" class="filter-input">
                <option value="">{{ t('common.allStatuses') }}</option>
                <option value="1">{{ t('common.active') }}</option>
                <option value="0">{{ t('common.inactive') }}</option>
            </select>
        </div>

        <div v-if="listError" class="card card-alert card-alert-danger text-sm">{{ listError }}</div>

        <!-- ── Table ───────────────────────────────────────────────────── -->
        <DataTable
            :columns="columns"
            :rows="rows"
            :loading="loading"
            :meta="meta"
            sort-key="name"
            empty-tone="emerald"
            :empty-icon="UsersIcon"
            :empty-title="t('customers.emptyTitle')"
            :empty-message="t('customers.emptyMessage')"
            @page-change="fetchPage"
        >
            <template #cell-customer_type="{ value }">
                <span :class="['status-pill', value === 'wholesale' ? 'status-pill-info' : 'status-pill-neutral']">
                    {{ value === 'wholesale' ? t('customers.typeWholesale') : t('customers.typeRetail') }}
                </span>
            </template>

            <template #cell-current_due="{ value }">
                <span v-if="value > 0" class="text-amber-700 dark:text-amber-400 font-semibold font-mono">
                    {{ formatCurrency(value) }}
                </span>
                <span v-else class="text-emerald-600 dark:text-emerald-400 text-xs font-medium">—</span>
            </template>

            <template #cell-is_active="{ value }">
                <span :class="['status-pill', value ? 'status-pill-success' : 'status-pill-neutral']">
                    <span :class="['w-1.5 h-1.5 rounded-full', value ? 'bg-emerald-500' : 'bg-slate-400']"></span>
                    {{ value ? t('common.active') : t('common.inactive') }}
                </span>
            </template>

            <template #row-actions="{ row }">
                <button
                    @click="openDetail(row)"
                    class="row-action row-action-indigo"
                    :title="t('customers.ledger')"
                >
                    <BookOpenIcon class="w-4 h-4" />
                </button>
                <button
                    @click="openForm(row)"
                    class="row-action row-action-indigo"
                    :title="t('common.edit')"
                >
                    <PencilSquareIcon class="w-4 h-4" />
                </button>
            </template>

            <template #empty-action>
                <Button variant="primary" :leading-icon="PlusIcon" @click="openForm(null)">
                    {{ t('customers.newCustomer') }}
                </Button>
            </template>
        </DataTable>

        <!-- ── Form Modal ──────────────────────────────────────────────── -->
        <CustomerFormModal
            v-if="showForm"
            :customer="editTarget"
            @close="showForm = false"
            @saved="onSaved"
        />

        <!-- ── Detail / Ledger Panel ───────────────────────────────────── -->
        <CustomerDetailModal
            v-if="showDetail"
            :customer-id="detailId"
            @close="showDetail = false"
        />

    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useDebounce } from '@vueuse/core';
import { useSettingsStore } from '@/stores/settings';
import { useAlert } from '@/composables/useAlert';
import { customerService } from '@/services/customerService';
import DataTable from '@/components/ui/DataTable.vue';
import Button    from '@/components/ui/Button.vue';
import CustomerFormModal from './CustomerFormModal.vue';
import CustomerDetailModal from './CustomerDetailModal.vue';
import {
    PlusIcon, MagnifyingGlassIcon, PencilSquareIcon, BookOpenIcon, UsersIcon,
} from '@heroicons/vue/24/outline';

const { t }           = useI18n();
const { toast }       = useAlert();
const settingsStore   = useSettingsStore();

function formatCurrency(value) {
    const code = settingsStore.settings?.currency_code ?? 'EUR';
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: code }).format(value ?? 0);
}

const columns = computed(() => [
    { key: 'code',          label: t('customers.code'),        width: '100px', bold: true },
    { key: 'name',          label: t('customers.name') },
    { key: 'phone',         label: t('customers.phone'),       width: '130px' },
    { key: 'customer_type', label: t('customers.type'),        type: 'custom', width: '110px' },
    { key: 'credit_limit',  label: t('customers.creditLimit'), width: '130px', format: (v) => formatCurrency(v) },
    { key: 'current_due',   label: t('customers.currentDue'),  type: 'custom', width: '130px' },
    { key: 'is_active',     label: t('common.status'),         type: 'custom', width: '100px' },
    { key: '_actions',      label: '',                         type: 'actions', width: '80px' },
]);

const rows        = ref([]);
const meta        = ref(null);
const loading     = ref(false);
const listError   = ref('');
const filters     = ref({ search: '', is_active: '', page: 1, per_page: 20 });
const searchQuery = ref('');
const statusFilter = ref('');
const debouncedSearch = useDebounce(searchQuery, 350);

watch(debouncedSearch, (val) => fetchList({ search: val, page: 1 }));
watch(statusFilter,   (val) => fetchList({ is_active: val, page: 1 }));

async function fetchList(overrides = {}) {
    Object.assign(filters.value, overrides);
    loading.value   = true;
    listError.value = '';
    try {
        const { data } = await customerService.index(filters.value);
        rows.value = data.data.map(c => ({
            ...c,
            current_due: Math.max(0, (parseFloat(c.total_due_raw) || 0) - (parseFloat(c.total_paid_due) || 0)),
        }));
        meta.value = data.meta;
    } catch (err) {
        listError.value = err.response?.data?.message ?? 'Failed to load customers.';
    } finally {
        loading.value = false;
    }
}

function fetchPage(page) { fetchList({ page }); }
onMounted(() => fetchList());

// ── Form modal ─────────────────────────────────────────────────────────────
const showForm   = ref(false);
const editTarget = ref(null);

function openForm(customer) {
    editTarget.value = customer;
    showForm.value   = true;
}

function onSaved() {
    showForm.value = false;
    fetchList();
}

// ── Detail modal ───────────────────────────────────────────────────────────
const showDetail = ref(false);
const detailId   = ref(null);

function openDetail(row) {
    detailId.value  = row.id;
    showDetail.value = true;
}
</script>

