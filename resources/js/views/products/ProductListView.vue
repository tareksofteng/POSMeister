<template>
    <div class="p-3 sm:p-6 lg:p-8 space-y-4 sm:space-y-6 anim-fade-in">

        <!-- Header — unified typography + Button primitive. -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-4 anim-fade-up">
            <div>
                <h1 class="h1-display">{{ t('products.title') }}</h1>
                <p class="mt-1.5 t-body">
                    {{ t('products.subtitle') }}
                    <span v-if="meta" class="text-slate-400">({{ meta.total }} {{ t('common.total') }})</span>
                </p>
            </div>
            <Button variant="primary" :leading-icon="PlusIcon" @click="openCreate">
                {{ t('products.new') }}
            </Button>
        </div>

        <!-- Filters — premium toolbar with search + 3 selects. -->
        <div class="card filter-bar">
            <div class="relative flex-1 min-w-[200px]">
                <MagnifyingGlassIcon class="filter-icon" />
                <input v-model="searchQuery" type="search" :placeholder="t('products.searchPlaceholder')" class="filter-input filter-input-search" />
            </div>

            <select v-model="categoryFilter" class="filter-input">
                <option value="">{{ t('products.allCategories') }}</option>
                <option v-for="c in categoryOptions" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>

            <select v-model="brandFilter" class="filter-input">
                <option value="">{{ t('products.allBrands') }}</option>
                <option v-for="b in brandOptions" :key="b.id" :value="b.id">{{ b.name }}</option>
            </select>

            <select v-model="statusFilter" class="filter-input">
                <option value="">{{ t('common.allStatuses') }}</option>
                <option value="1">{{ t('common.active') }}</option>
                <option value="0">{{ t('common.inactive') }}</option>
            </select>
        </div>

        <!-- Error -->
        <div v-if="listError" class="card card-alert card-alert-danger text-sm">{{ listError }}</div>

        <!-- Table -->
        <DataTable
            :columns="columns"
            :rows="rows"
            :loading="loading"
            :meta="meta"
            empty-tone="indigo"
            :empty-icon="CubeIcon"
            :empty-title="t('products.emptyTitle')"
            :empty-message="t('products.emptyMessage')"
            @page-change="fetchPage"
        >
            <template #cell(_image)="{ row }">
                <div class="w-9 h-9 rounded-lg overflow-hidden bg-slate-100 dark:bg-slate-800 flex items-center justify-center flex-shrink-0">
                    <img v-if="row.image_url" :src="row.image_url" :alt="row.name" class="w-full h-full object-cover" />
                    <PhotoIcon v-else class="w-4 h-4 text-slate-300 dark:text-slate-600" />
                </div>
            </template>

            <template #row-actions="{ row }">
                <button
                    @click="router.push({ name: 'product-detail', params: { id: row.id } })"
                    class="row-action row-action-indigo"
                    :title="t('products.viewDetail')"
                >
                    <EyeIcon class="w-4 h-4" />
                </button>
                <!-- Phase Y — Serial Inventory modal. Only visible for products
                     that opted into serial tracking. Count badge surfaces the
                     in-stock figure without an extra click. -->
                <button
                    v-if="row.is_serialized"
                    @click="openSerials(row)"
                    class="row-action row-action-indigo relative"
                    :title="t('serials.openSerials')"
                >
                    <CpuChipIcon class="w-4 h-4" />
                    <span
                        v-if="row.in_stock_serials_count != null"
                        class="absolute -top-1 -right-1 min-w-[16px] h-[16px] px-1 rounded-full bg-indigo-600 text-white text-[9px] font-bold leading-none grid place-items-center"
                    >
                        {{ row.in_stock_serials_count }}
                    </span>
                </button>
                <button @click="openEdit(row)" class="row-action row-action-indigo" :title="t('common.edit')">
                    <PencilSquareIcon class="w-4 h-4" />
                </button>
                <button
                    @click="router.push({ name: 'product-barcode', params: { id: row.id } })"
                    class="row-action row-action-indigo"
                    :title="t('products.generateBarcode')"
                >
                    <QrCodeIcon class="w-4 h-4" />
                </button>
                <button @click="confirmToggle(row)" class="row-action row-action-amber" :title="row.is_active ? t('common.deactivate') : t('common.activate')">
                    <NoSymbolIcon v-if="row.is_active" class="w-4 h-4" />
                    <CheckCircleIcon v-else class="w-4 h-4" />
                </button>
                <button @click="confirmDelete(row)" class="row-action row-action-danger" :title="t('common.delete')">
                    <TrashIcon class="w-4 h-4" />
                </button>
            </template>

            <template #empty-action>
                <Button variant="primary" :leading-icon="PlusIcon" @click="openCreate">
                    {{ t('products.new') }}
                </Button>
            </template>
        </DataTable>
    </div>

    <!-- Form Modal -->
    <ProductFormModal
        v-model:open="formOpen"
        :product="editTarget"
        :category-options="categoryOptions"
        :brand-options="brandOptions"
        :unit-options="unitOptions"
        @saved="onSaved"
    />

    <!-- Phase Y — Serial Inventory modal -->
    <SerialInventoryModal
        :open="serialsOpen"
        :product="serialsTarget"
        @close="serialsOpen = false"
    />

</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useDebounce } from '@vueuse/core';
import { productService }  from '@/services/productService';
import { categoryService } from '@/services/categoryService';
import { brandService }    from '@/services/brandService';
import { unitService }     from '@/services/unitService';
import DataTable        from '@/components/ui/DataTable.vue';
import Button           from '@/components/ui/Button.vue';
import ProductFormModal from './ProductFormModal.vue';
import SerialInventoryModal from './SerialInventoryModal.vue';
import { useAlert } from '@/composables/useAlert';
import { PlusIcon, MagnifyingGlassIcon, PencilSquareIcon, TrashIcon, NoSymbolIcon, CheckCircleIcon, EyeIcon, PhotoIcon, QrCodeIcon, CpuChipIcon, CubeIcon } from '@heroicons/vue/24/outline';
import { useRouter } from 'vue-router';

const router = useRouter();
const { t }  = useI18n();
const { toast, confirm } = useAlert();

// ── Columns ───────────────────────────────────────────────────────────────
const columns = computed(() => [
    { key: '_image',        label: '',                           type: 'image',    width: '56px' },
    { key: 'sku',           label: t('products.sku'),           width: '110px' },
    { key: 'name',          label: t('common.name'),            bold: true },
    { key: 'category_name', label: t('products.category') },
    { key: 'brand_name',    label: t('products.brand'),         class: 'hidden lg:table-cell' },
    { key: 'unit_symbol',   label: t('products.unit'),          width: '80px' },
    { key: 'selling_price', label: t('products.sellingPrice'),  type: 'currency', width: '110px' },
    { key: 'tax_rate',      label: t('products.taxRate'),       type: 'percent',  width: '80px' },
    { key: 'is_active',     label: t('common.status'),          type: 'badge',    width: '100px' },
    { key: '_actions',      label: '',                           type: 'actions',  width: '120px' },
]);

// ── List state ────────────────────────────────────────────────────────────
const rows      = ref([]);
const meta      = ref(null);
const loading   = ref(false);
const listError = ref('');
const filters   = ref({ search: '', category_id: '', brand_id: '', is_active: '', page: 1, per_page: 20 });

// ── Filters ───────────────────────────────────────────────────────────────
const searchQuery  = ref('');
const categoryFilter = ref('');
const brandFilter    = ref('');
const statusFilter   = ref('');
const debouncedSearch = useDebounce(searchQuery, 350);

watch(debouncedSearch,  (val) => fetchProducts({ search: val,        page: 1 }));
watch(categoryFilter,   (val) => fetchProducts({ category_id: val,   page: 1 }));
watch(brandFilter,      (val) => fetchProducts({ brand_id: val,      page: 1 }));
watch(statusFilter,     (val) => fetchProducts({ is_active: val,     page: 1 }));

async function fetchProducts(overrides = {}) {
    Object.assign(filters.value, overrides);
    loading.value   = true;
    listError.value = '';
    try {
        if (navigator.onLine === false) {
            await loadFromCache();
            return;
        }
        const { data } = await productService.index(filters.value);
        rows.value = data.data;
        meta.value = data.meta;
    } catch (err) {
        const swOffline = err.response?.headers?.['x-posmeister-offline'] === '1';
        if (!err.response || swOffline) {
            try { await loadFromCache(); return; } catch { /* fall through */ }
        }
        listError.value = err.response?.data?.message ?? t('common.unexpectedError');
    } finally {
        loading.value = false;
    }
}

async function loadFromCache() {
    const { getAll } = await import('@/offline/db');
    const all = await getAll('products');
    const f = filters.value;
    const q = (f.search || '').toLowerCase();
    const filtered = all.filter((p) => {
        if (q && !((p.name_lc || (p.name || '').toLowerCase()).includes(q) ||
                   (p.sku || '').toLowerCase().includes(q) ||
                   (p.barcode || '').toLowerCase().includes(q))) return false;
        if (f.category_id && p.category_id != f.category_id) return false;
        if (f.brand_id    && p.brand_id    != f.brand_id)    return false;
        if (f.is_active === false && p._pendingSync !== true) return false;
        return true;
    });
    rows.value = filtered;
    meta.value = { total: filtered.length, per_page: filtered.length, current_page: 1, last_page: 1 };
}

function fetchPage(page) { fetchProducts({ page }); }

// ── Dropdown options ──────────────────────────────────────────────────────
const categoryOptions = ref([]);
const brandOptions    = ref([]);
const unitOptions     = ref([]);

onMounted(async () => {
    fetchProducts();
    await loadDropdowns();
});

async function loadDropdowns() {
    // Try the network first when we have it, but always fall back to the
    // IndexedDB snapshot so opening Products offline still shows category /
    // brand / unit options in the create-product modal.
    if (navigator.onLine === false) {
        await loadDropdownsFromCache();
        return;
    }
    try {
        const [cats, brnds, unts] = await Promise.all([
            categoryService.all(),
            brandService.all(),
            unitService.all(),
        ]);
        categoryOptions.value = cats.data;
        brandOptions.value    = brnds.data;
        unitOptions.value     = unts.data;
    } catch {
        await loadDropdownsFromCache();
    }
}

async function loadDropdownsFromCache() {
    try {
        const { loadCategories, loadBrands, loadUnits } = await import('@/offline/settingsCache');
        const [cats, brnds, unts] = await Promise.all([loadCategories(), loadBrands(), loadUnits()]);
        categoryOptions.value = cats;
        brandOptions.value    = brnds;
        unitOptions.value     = unts;
    } catch { /* leave dropdowns empty */ }
}

// ── Create / Edit ─────────────────────────────────────────────────────────
const formOpen   = ref(false);
const editTarget = ref(null);

// Phase Y — Serial Inventory modal state.
const serialsOpen   = ref(false);
const serialsTarget = ref(null);

function openCreate() { editTarget.value = null; formOpen.value = true; }
function openEdit(row) { editTarget.value = { ...row }; formOpen.value = true; }
function openSerials(row) { serialsTarget.value = row; serialsOpen.value = true; }

function onSaved(isEdit) {
    formOpen.value = false;
    fetchProducts();
    toast('success', isEdit ? t('common.updatedSuccess') : t('common.createdSuccess'));
}

// ── Toggle status ─────────────────────────────────────────────────────────
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
        await productService.toggleStatus(row.id);
        fetchProducts();
        toast('success', isActive ? t('common.deactivatedSuccess') : t('common.activatedSuccess'));
    } catch (err) {
        toast('error', err.response?.data?.message ?? t('common.unexpectedError'));
    }
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
        await productService.destroy(row.id);
        fetchProducts();
        toast('success', t('common.deletedSuccess'));
    } catch (err) {
        toast('error', err.response?.data?.message ?? t('common.unexpectedError'));
    }
}
</script>

