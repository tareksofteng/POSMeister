<template>
    <div class="p-6 lg:p-8 space-y-6">

        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">{{ t('products.title') }}</h1>
                <p class="mt-1 text-sm text-gray-500">
                    {{ t('products.subtitle') }}
                    <span v-if="meta" class="text-gray-400">({{ meta.total }} {{ t('common.total') }})</span>
                </p>
            </div>
            <button @click="openCreate" class="btn-primary">
                <PlusIcon class="w-4 h-4" />
                {{ t('products.new') }}
            </button>
        </div>

        <!-- Filters -->
        <div class="flex flex-col sm:flex-row gap-3 flex-wrap">
            <div class="relative flex-1 min-w-[200px] max-w-sm">
                <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" />
                <input
                    v-model="searchQuery"
                    type="search"
                    :placeholder="t('products.searchPlaceholder')"
                    class="w-full pl-9 pr-4 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                />
            </div>

            <select v-model="categoryFilter" class="px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white">
                <option value="">{{ t('products.allCategories') }}</option>
                <option v-for="c in categoryOptions" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>

            <select v-model="brandFilter" class="px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white">
                <option value="">{{ t('products.allBrands') }}</option>
                <option v-for="b in brandOptions" :key="b.id" :value="b.id">{{ b.name }}</option>
            </select>

            <select v-model="statusFilter" class="px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white">
                <option value="">{{ t('common.allStatuses') }}</option>
                <option value="1">{{ t('common.active') }}</option>
                <option value="0">{{ t('common.inactive') }}</option>
            </select>
        </div>

        <!-- Error -->
        <div v-if="listError" class="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
            {{ listError }}
        </div>

        <!-- Table -->
        <DataTable
            :columns="columns"
            :rows="rows"
            :loading="loading"
            :meta="meta"
            :empty-title="t('products.emptyTitle')"
            :empty-message="t('products.emptyMessage')"
            @page-change="fetchPage"
        >
            <template #cell(_image)="{ row }">
                <div class="w-9 h-9 rounded-lg overflow-hidden bg-gray-100 flex items-center justify-center flex-shrink-0">
                    <img v-if="row.image_url" :src="row.image_url" :alt="row.name" class="w-full h-full object-cover" />
                    <PhotoIcon v-else class="w-4 h-4 text-gray-300" />
                </div>
            </template>

            <template #row-actions="{ row }">
                <button @click="router.push({ name: 'product-detail', params: { id: row.id } })" class="p-1.5 text-gray-400 hover:text-sky-600 hover:bg-sky-50 rounded-lg transition-colors" :title="t('products.viewDetail')">
                    <EyeIcon class="w-4 h-4" />
                </button>
                <button @click="openEdit(row)" class="p-1.5 text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors" :title="t('common.edit')">
                    <PencilSquareIcon class="w-4 h-4" />
                </button>
                <button
                    @click="router.push({ name: 'product-barcode', params: { id: row.id } })"
                    class="p-1.5 text-gray-400 hover:text-violet-600 hover:bg-violet-50 rounded-lg transition-colors"
                    :title="t('products.generateBarcode')"
                >
                    <QrCodeIcon class="w-4 h-4" />
                </button>
                <button @click="confirmToggle(row)" class="p-1.5 text-gray-400 hover:text-amber-600 hover:bg-amber-50 rounded-lg transition-colors" :title="row.is_active ? t('common.deactivate') : t('common.activate')">
                    <NoSymbolIcon v-if="row.is_active" class="w-4 h-4" />
                    <CheckCircleIcon v-else class="w-4 h-4" />
                </button>
                <button @click="confirmDelete(row)" class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors" :title="t('common.delete')">
                    <TrashIcon class="w-4 h-4" />
                </button>
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
import ProductFormModal from './ProductFormModal.vue';
import { useAlert } from '@/composables/useAlert';
import { PlusIcon, MagnifyingGlassIcon, PencilSquareIcon, TrashIcon, NoSymbolIcon, CheckCircleIcon, EyeIcon, PhotoIcon, QrCodeIcon } from '@heroicons/vue/24/outline';
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
        const { data } = await productService.index(filters.value);
        rows.value = data.data;
        meta.value = data.meta;
    } catch (err) {
        listError.value = err.response?.data?.message ?? t('common.unexpectedError');
    } finally {
        loading.value = false;
    }
}

function fetchPage(page) { fetchProducts({ page }); }

// ── Dropdown options ──────────────────────────────────────────────────────
const categoryOptions = ref([]);
const brandOptions    = ref([]);
const unitOptions     = ref([]);

onMounted(async () => {
    fetchProducts();
    const [cats, brnds, unts] = await Promise.all([
        categoryService.all(),
        brandService.all(),
        unitService.all(),
    ]);
    categoryOptions.value = cats.data;
    brandOptions.value    = brnds.data;
    unitOptions.value     = unts.data;
});

// ── Create / Edit ─────────────────────────────────────────────────────────
const formOpen   = ref(false);
const editTarget = ref(null);

function openCreate() { editTarget.value = null; formOpen.value = true; }
function openEdit(row) { editTarget.value = { ...row }; formOpen.value = true; }

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

<style scoped>
@reference '../../../css/app.css';
.btn-primary {
    @apply flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition-colors shadow-sm;
}
</style>
