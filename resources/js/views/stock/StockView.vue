<template>
    <div class="p-6 lg:p-8 space-y-6">

        <!-- Page header -->
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 no-print">
            <div class="flex items-start gap-3">
                <div class="flex items-center justify-center w-11 h-11 rounded-xl bg-emerald-100 flex-shrink-0">
                    <ArchiveBoxIcon class="w-6 h-6 text-emerald-600" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 tracking-tight">{{ t('stock.title') }}</h1>
                    <p class="mt-0.5 text-sm text-gray-500">{{ t('stock.subtitle') }}</p>
                </div>
            </div>
            <button
                v-if="rows.length"
                @click="printReport"
                class="flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition-colors shadow-sm self-start"
            >
                <PrinterIcon class="w-4 h-4" />
                {{ t('stock.printReport') }}
            </button>
        </div>

        <!-- Print header (only visible when printing) -->
        <div class="print-only mb-4">
            <div class="flex items-center justify-between border-b-2 border-indigo-600 pb-3">
                <div>
                    <h2 class="text-xl font-bold text-gray-900">{{ t('stock.reportTitle') }}</h2>
                    <p class="text-sm text-gray-500 mt-0.5">{{ activeFilterLabel }}</p>
                </div>
                <div class="text-right">
                    <p class="text-xs text-gray-400">{{ t('stock.printDate') }}</p>
                    <p class="text-sm font-medium text-gray-700">{{ todayFormatted }}</p>
                </div>
            </div>
        </div>

        <!-- Filter type tabs -->
        <div class="flex flex-wrap items-center gap-2 no-print">
            <button
                v-for="ft in filterTypes"
                :key="ft.value"
                @click="setFilterType(ft.value)"
                :class="[
                    'px-4 py-2 text-sm font-medium rounded-lg border transition-colors',
                    filterType === ft.value
                        ? 'bg-indigo-600 text-white border-indigo-600 shadow-sm'
                        : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50',
                ]"
            >
                <component :is="ft.icon" class="w-4 h-4 inline-block mr-1.5 -mt-0.5" />
                {{ t(ft.labelKey) }}
            </button>
        </div>

        <!-- Conditional dropdowns + search -->
        <div class="flex flex-wrap gap-3 no-print">
            <select
                v-if="filterType === 'category'"
                v-model="selectedCategoryId"
                @change="fetchStock"
                class="px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white min-w-[210px]"
            >
                <option value="">— {{ t('stock.selectCategory') }} —</option>
                <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>

            <select
                v-if="filterType === 'brand'"
                v-model="selectedBrandId"
                @change="fetchStock"
                class="px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white min-w-[210px]"
            >
                <option value="">— {{ t('stock.selectBrand') }} —</option>
                <option v-for="b in brands" :key="b.id" :value="b.id">{{ b.name }}</option>
            </select>

            <select
                v-if="filterType === 'product'"
                v-model="selectedProductId"
                @change="fetchStock"
                class="px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white min-w-[260px]"
            >
                <option value="">— {{ t('stock.selectProduct') }} —</option>
                <option v-for="p in products" :key="p.id" :value="p.id">{{ p.text }}</option>
            </select>

            <!-- Live search (client-side within loaded rows) -->
            <div class="relative flex-1 min-w-[200px]">
                <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" />
                <input
                    v-model="searchQuery"
                    type="search"
                    :placeholder="t('stock.searchPlaceholder')"
                    class="w-full pl-9 pr-4 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                />
            </div>
        </div>

        <!-- Error -->
        <div v-if="error" class="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700 no-print">{{ error }}</div>

        <!-- Summary cards -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 no-print">
            <!-- Total products -->
            <div class="bg-white rounded-xl border border-gray-200 p-4 flex items-start gap-3 shadow-sm">
                <div class="w-10 h-10 rounded-xl bg-indigo-100 flex items-center justify-center flex-shrink-0">
                    <CubeIcon class="w-5 h-5 text-indigo-600" />
                </div>
                <div class="min-w-0">
                    <p class="text-xs font-medium text-gray-500 truncate">{{ t('stock.cardProducts') }}</p>
                    <p class="text-2xl font-bold text-gray-900 mt-0.5 tabular-nums">{{ summary.total_products }}</p>
                </div>
            </div>
            <!-- Stock value -->
            <div class="bg-white rounded-xl border border-gray-200 p-4 flex items-start gap-3 shadow-sm">
                <div class="w-10 h-10 rounded-xl bg-emerald-100 flex items-center justify-center flex-shrink-0">
                    <BanknotesIcon class="w-5 h-5 text-emerald-600" />
                </div>
                <div class="min-w-0">
                    <p class="text-xs font-medium text-gray-500 truncate">{{ t('stock.cardValue') }}</p>
                    <p class="text-xl font-bold text-gray-900 mt-0.5 tabular-nums">{{ formatCurrency(summary.total_value) }}</p>
                </div>
            </div>
            <!-- Low stock -->
            <div class="bg-white rounded-xl border border-gray-200 p-4 flex items-start gap-3 shadow-sm">
                <div class="w-10 h-10 rounded-xl bg-amber-100 flex items-center justify-center flex-shrink-0">
                    <ExclamationTriangleIcon class="w-5 h-5 text-amber-600" />
                </div>
                <div class="min-w-0">
                    <p class="text-xs font-medium text-gray-500 truncate">{{ t('stock.cardLow') }}</p>
                    <p class="text-2xl font-bold text-amber-600 mt-0.5 tabular-nums">{{ summary.low_stock_count }}</p>
                </div>
            </div>
            <!-- Out of stock -->
            <div class="bg-white rounded-xl border border-gray-200 p-4 flex items-start gap-3 shadow-sm">
                <div class="w-10 h-10 rounded-xl bg-red-100 flex items-center justify-center flex-shrink-0">
                    <XCircleIcon class="w-5 h-5 text-red-600" />
                </div>
                <div class="min-w-0">
                    <p class="text-xs font-medium text-gray-500 truncate">{{ t('stock.cardOut') }}</p>
                    <p class="text-2xl font-bold text-red-600 mt-0.5 tabular-nums">{{ summary.out_of_stock }}</p>
                </div>
            </div>
        </div>

        <!-- Table card -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">

            <!-- Loading -->
            <div v-if="loading" class="flex items-center justify-center py-20 no-print">
                <svg class="w-8 h-8 animate-spin text-indigo-500" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                </svg>
            </div>

            <!-- Empty state -->
            <div v-else-if="!filteredRows.length" class="py-20 text-center no-print">
                <ArchiveBoxIcon class="w-14 h-14 text-gray-200 mx-auto mb-3" />
                <p class="text-sm font-semibold text-gray-500">{{ t('stock.emptyTitle') }}</p>
                <p class="text-xs text-gray-400 mt-1">{{ t('stock.emptyMessage') }}</p>
            </div>

            <!-- Data table -->
            <div v-else class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 bg-gray-50/80">
                            <th class="w-14 px-3 py-3"></th>
                            <th class="text-left px-3 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">{{ t('stock.colSku') }}</th>
                            <th class="text-left px-3 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">{{ t('stock.colName') }}</th>
                            <th class="text-left px-3 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500 hidden sm:table-cell">{{ t('stock.colCategory') }}</th>
                            <th class="text-left px-3 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500 hidden lg:table-cell">{{ t('stock.colBrand') }}</th>
                            <th class="text-right px-3 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">{{ t('stock.colQty') }}</th>
                            <th class="text-right px-3 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500 hidden xl:table-cell">{{ t('stock.colReorder') }}</th>
                            <th class="text-right px-3 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500 hidden lg:table-cell">{{ t('stock.colCostPrice') }}</th>
                            <th class="text-right px-3 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">{{ t('stock.colStockValue') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <tr
                            v-for="row in filteredRows"
                            :key="row.id"
                            :class="[
                                'hover:bg-gray-50/60 transition-colors',
                                row.out_of_stock ? 'bg-red-50/30'   : '',
                                row.low_stock    ? 'bg-amber-50/30' : '',
                            ]"
                        >
                            <!-- Product image -->
                            <td class="px-3 py-2.5">
                                <div class="w-10 h-10 rounded-lg overflow-hidden bg-gray-100 flex-shrink-0 border border-gray-200">
                                    <img
                                        v-if="row.image_url"
                                        :src="row.image_url"
                                        :alt="row.name"
                                        class="w-full h-full object-cover"
                                    />
                                    <div v-else class="w-full h-full flex items-center justify-center">
                                        <PhotoIcon class="w-5 h-5 text-gray-300" />
                                    </div>
                                </div>
                            </td>

                            <!-- SKU -->
                            <td class="px-3 py-2.5">
                                <span class="font-mono text-xs text-gray-500 bg-gray-100 px-2 py-0.5 rounded">{{ row.sku }}</span>
                            </td>

                            <!-- Product name -->
                            <td class="px-3 py-2.5">
                                <span class="font-semibold text-gray-900">{{ row.name }}</span>
                            </td>

                            <!-- Category -->
                            <td class="px-3 py-2.5 text-gray-500 hidden sm:table-cell">
                                {{ row.category_name }}
                            </td>

                            <!-- Brand -->
                            <td class="px-3 py-2.5 text-gray-500 hidden lg:table-cell">
                                {{ row.brand_name !== '—' ? row.brand_name : '' }}
                            </td>

                            <!-- Quantity + badge -->
                            <td class="px-3 py-2.5 text-right">
                                <div class="flex items-center justify-end gap-1.5 flex-wrap">
                                    <span
                                        :class="[
                                            'inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium',
                                            row.out_of_stock
                                                ? 'bg-red-100 text-red-700'
                                                : row.low_stock
                                                    ? 'bg-amber-100 text-amber-700'
                                                    : 'bg-emerald-100 text-emerald-700',
                                        ]"
                                    >
                                        <span v-if="row.out_of_stock" class="w-1.5 h-1.5 rounded-full bg-red-500 mr-1"></span>
                                        <span v-else-if="row.low_stock" class="w-1.5 h-1.5 rounded-full bg-amber-500 mr-1"></span>
                                        <span v-else class="w-1.5 h-1.5 rounded-full bg-emerald-500 mr-1"></span>
                                        {{ row.out_of_stock ? t('stock.badgeOut') : row.low_stock ? t('stock.badgeLow') : t('stock.badgeNormal') }}
                                    </span>
                                    <span class="font-bold text-gray-900 tabular-nums text-sm">
                                        {{ row.quantity % 1 === 0 ? row.quantity : row.quantity.toFixed(2) }}
                                        <span class="text-xs font-normal text-gray-400 ml-0.5">{{ row.unit_symbol || row.unit_name }}</span>
                                    </span>
                                </div>
                            </td>

                            <!-- Reorder level -->
                            <td class="px-3 py-2.5 text-right text-gray-500 tabular-nums hidden xl:table-cell">
                                <span v-if="row.reorder_level > 0" class="text-amber-600 font-medium">
                                    {{ row.reorder_level % 1 === 0 ? row.reorder_level : row.reorder_level.toFixed(2) }}
                                </span>
                                <span v-else class="text-gray-300">—</span>
                            </td>

                            <!-- Cost price -->
                            <td class="px-3 py-2.5 text-right text-gray-600 tabular-nums hidden lg:table-cell">
                                {{ formatCurrency(row.cost_price) }}
                            </td>

                            <!-- Stock value -->
                            <td class="px-3 py-2.5 text-right font-semibold text-gray-900 tabular-nums">
                                {{ formatCurrency(row.stock_value) }}
                            </td>
                        </tr>
                    </tbody>

                    <!-- Totals footer -->
                    <tfoot>
                        <tr class="border-t-2 border-gray-200 bg-indigo-50/50">
                            <td colspan="5" class="px-3 py-3 text-sm font-bold text-gray-700 text-right">
                                {{ t('stock.totalRow') }}
                                <span class="text-xs font-normal text-gray-400 ml-2">({{ filteredRows.length }} {{ t('stock.items') }})</span>
                            </td>
                            <td class="px-3 py-3 text-right font-bold text-gray-900 tabular-nums">
                                {{ totalQty % 1 === 0 ? totalQty : totalQty.toFixed(2) }}
                            </td>
                            <td class="hidden xl:table-cell"></td>
                            <td class="hidden lg:table-cell"></td>
                            <td class="px-3 py-3 text-right font-bold text-indigo-700 tabular-nums text-base">
                                {{ formatCurrency(totalValue) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- Print footer -->
        <div class="print-only text-xs text-gray-400 text-center pt-4 border-t border-gray-200 mt-6">
            POSmeister · {{ t('stock.printDate') }}: {{ todayFormatted }}
        </div>

    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useSettingsStore } from '@/stores/settings';
import { stockService } from '@/services/stockService';

import {
    ArchiveBoxIcon, PrinterIcon, MagnifyingGlassIcon,
    PhotoIcon, CubeIcon, BanknotesIcon,
    ExclamationTriangleIcon, XCircleIcon,
    FunnelIcon, TagIcon, BuildingStorefrontIcon, CubeTransparentIcon,
} from '@heroicons/vue/24/outline';

const { t } = useI18n();
const settingsStore = useSettingsStore();

function formatCurrency(value) {
    if (value == null) return '—';
    const code = settingsStore.settings?.currency_code ?? 'EUR';
    return new Intl.NumberFormat('de-DE', { style: 'currency', currency: code }).format(value);
}

const todayFormatted = new Date().toLocaleDateString('de-DE', {
    day: '2-digit', month: '2-digit', year: 'numeric',
});

// ── Filter type config ─────────────────────────────────────────────────────
const filterTypes = [
    { value: 'all',      labelKey: 'stock.filterAll',      icon: CubeIcon },
    { value: 'category', labelKey: 'stock.filterCategory', icon: FunnelIcon },
    { value: 'brand',    labelKey: 'stock.filterBrand',    icon: BuildingStorefrontIcon },
    { value: 'product',  labelKey: 'stock.filterProduct',  icon: CubeTransparentIcon },
];

// ── State ──────────────────────────────────────────────────────────────────
const filterType        = ref('all');
const selectedCategoryId = ref('');
const selectedBrandId    = ref('');
const selectedProductId  = ref('');
const searchQuery        = ref('');

const categories = ref([]);
const brands     = ref([]);
const products   = ref([]);

const rows    = ref([]);
const summary = ref({ total_products: 0, total_value: 0, low_stock_count: 0, out_of_stock: 0 });
const loading = ref(false);
const error   = ref('');

// ── Computed ───────────────────────────────────────────────────────────────
const filteredRows = computed(() => {
    if (!searchQuery.value.trim()) return rows.value;
    const q = searchQuery.value.toLowerCase();
    return rows.value.filter(r =>
        r.name.toLowerCase().includes(q) ||
        r.sku.toLowerCase().includes(q)  ||
        r.category_name.toLowerCase().includes(q) ||
        r.brand_name.toLowerCase().includes(q)
    );
});

const totalQty   = computed(() => filteredRows.value.reduce((s, r) => s + r.quantity, 0));
const totalValue = computed(() => filteredRows.value.reduce((s, r) => s + r.stock_value, 0));

const activeFilterLabel = computed(() => {
    if (filterType.value === 'category' && selectedCategoryId.value) {
        const c = categories.value.find(c => c.id === selectedCategoryId.value);
        return c ? `${t('stock.filterCategory')}: ${c.name}` : t('stock.filterAll');
    }
    if (filterType.value === 'brand' && selectedBrandId.value) {
        const b = brands.value.find(b => b.id === selectedBrandId.value);
        return b ? `${t('stock.filterBrand')}: ${b.name}` : t('stock.filterAll');
    }
    if (filterType.value === 'product' && selectedProductId.value) {
        const p = products.value.find(p => p.id === selectedProductId.value);
        return p ? p.text : t('stock.filterAll');
    }
    return t('stock.filterAll');
});

// ── Methods ────────────────────────────────────────────────────────────────
function setFilterType(type) {
    filterType.value         = type;
    selectedCategoryId.value = '';
    selectedBrandId.value    = '';
    selectedProductId.value  = '';
    fetchStock();
}

async function fetchStock() {
    const params = {};
    if (filterType.value === 'category' && selectedCategoryId.value)
        params.category_id = selectedCategoryId.value;
    if (filterType.value === 'brand' && selectedBrandId.value)
        params.brand_id = selectedBrandId.value;
    if (filterType.value === 'product' && selectedProductId.value)
        params.product_id = selectedProductId.value;

    loading.value = true;
    error.value   = '';
    try {
        const { data } = await stockService.current(params);
        rows.value    = data.data;
        summary.value = data.summary;
    } catch (err) {
        error.value = err.response?.data?.message ?? t('common.unexpectedError');
    } finally {
        loading.value = false;
    }
}

async function loadFilterOptions() {
    try {
        const { data } = await stockService.filterOptions();
        categories.value = data.categories;
        brands.value     = data.brands;
        products.value   = data.products;
    } catch {
        // non-critical — filters just won't populate
    }
}

function printReport() {
    window.print();
}

onMounted(() => {
    loadFilterOptions();
    fetchStock();
});
</script>

<style scoped>
@reference '../../../css/app.css';

@media print {
    @page { margin: 12mm; size: A4 portrait; }
    * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }

    .no-print { display: none !important; }
    .print-only { display: block !important; }

    body { background: white !important; margin: 0 !important; }

    /* Reset card shadows for print */
    .bg-white { box-shadow: none !important; }

    /* Expand all responsive-hidden columns */
    .hidden { display: table-cell !important; }
    .sm\\:table-cell,
    .lg\\:table-cell,
    .xl\\:table-cell { display: table-cell !important; }
}

.print-only { display: none; }
</style>
