<template>
    <div class="p-6 lg:p-8 space-y-6 max-w-5xl">

        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-3">
                <button @click="router.push({ name: 'products' })" class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-colors">
                    <ArrowLeftIcon class="w-5 h-5" />
                </button>
                <div>
                    <p class="text-xs text-gray-400 uppercase tracking-wider">{{ t('products.detailTitle') }}</p>
                    <h1 class="text-xl font-bold text-gray-900 leading-tight">{{ product?.name ?? '—' }}</h1>
                </div>
            </div>
            <div class="flex items-center gap-2 ml-10 sm:ml-0">
                <StatusBadge v-if="product" :active="product.is_active" />
                <button @click="openEdit" class="btn-primary">
                    <PencilSquareIcon class="w-4 h-4" />
                    {{ t('common.edit') }}
                </button>
            </div>
        </div>

        <!-- Loading skeleton -->
        <div v-if="loading" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-1 space-y-4">
                <div class="animate-pulse bg-gray-200 rounded-xl aspect-square" />
            </div>
            <div class="lg:col-span-2 space-y-4">
                <div v-for="i in 6" :key="i" class="animate-pulse h-12 bg-gray-100 rounded-xl" />
            </div>
        </div>

        <!-- Error -->
        <div v-else-if="loadError" class="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
            {{ loadError }}
        </div>

        <!-- Content -->
        <template v-else-if="product">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                <!-- Left column: Image + identifiers -->
                <div class="space-y-4">
                    <!-- Product image -->
                    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden aspect-square flex items-center justify-center">
                        <img
                            v-if="product.image_url"
                            :src="product.image_url"
                            :alt="product.name"
                            class="w-full h-full object-cover"
                        />
                        <div v-else class="flex flex-col items-center gap-3 text-center p-8">
                            <PhotoIcon class="w-16 h-16 text-gray-200" />
                            <span class="text-xs text-gray-400">{{ t('products.noImage') }}</span>
                        </div>
                    </div>

                    <!-- Identifiers card -->
                    <div class="bg-white rounded-xl border border-gray-200 p-4 space-y-3">
                        <DetailRow :label="t('products.sku')" mono>{{ product.sku }}</DetailRow>
                        <DetailRow v-if="product.barcode" :label="t('products.barcode')" mono>{{ product.barcode }}</DetailRow>
                        <DetailRow :label="t('products.category')">{{ product.category_name }}</DetailRow>
                        <DetailRow :label="t('products.brand')">{{ product.brand_name }}</DetailRow>
                        <DetailRow :label="t('products.unit')">
                            {{ product.unit_name }}
                            <span v-if="product.unit_symbol" class="ml-1 text-gray-400 font-mono text-xs">({{ product.unit_symbol }})</span>
                        </DetailRow>
                    </div>
                </div>

                <!-- Right column: pricing + settings -->
                <div class="lg:col-span-2 space-y-4">

                    <!-- Pricing card -->
                    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                        <div class="px-5 py-3 border-b border-gray-100 bg-gray-50">
                            <h2 class="text-sm font-semibold text-gray-700">{{ t('products.pricingOverview') }}</h2>
                        </div>
                        <div class="p-5 grid grid-cols-2 gap-x-8 gap-y-4">
                            <PriceRow :label="t('products.sellingPrice')" :value="product.selling_price" highlight />
                            <PriceRow :label="t('products.costPrice')"    :value="product.cost_price" />
                            <PriceRow :label="t('products.wholesalePrice')"   :value="product.wholesale_price" />
                            <PriceRow :label="t('products.minSellingPrice')"  :value="product.min_selling_price" />

                            <!-- Tax rate -->
                            <div class="col-span-2 flex items-center justify-between pt-3 border-t border-gray-100">
                                <span class="text-sm text-gray-500">{{ t('products.taxRate') }}</span>
                                <span class="text-sm font-semibold text-gray-800">
                                    {{ product.tax_rate }}%
                                    <span class="ml-1.5 text-xs font-normal text-gray-400">{{ taxLabel(product.tax_rate) }}</span>
                                </span>
                            </div>

                            <!-- Profit margin -->
                            <div class="col-span-2 flex items-center justify-between">
                                <span class="text-sm text-gray-500">{{ t('products.profitMargin') }}</span>
                                <span :class="['text-sm font-semibold', product.profit_margin >= 0 ? 'text-emerald-600' : 'text-red-600']">
                                    {{ product.profit_margin?.toFixed(2) ?? t('products.notAvailable') }}%
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Stock & Settings card -->
                    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                        <div class="px-5 py-3 border-b border-gray-100 bg-gray-50">
                            <h2 class="text-sm font-semibold text-gray-700">{{ t('products.stockInfo') }}</h2>
                        </div>
                        <div class="p-5 space-y-4">
                            <DetailRow :label="t('products.reorderLevel')">
                                {{ product.reorder_level ?? 0 }}
                            </DetailRow>
                            <DetailRow :label="t('products.isService')">
                                <span :class="product.is_service ? 'text-indigo-600 font-medium' : 'text-gray-500'">
                                    {{ product.is_service ? t('common.active') : '—' }}
                                </span>
                            </DetailRow>
                            <DetailRow :label="t('common.status')">
                                <StatusBadge :active="product.is_active" />
                            </DetailRow>
                            <DetailRow :label="t('common.createdAt')" v-if="product.created_at">
                                {{ formatDate(product.created_at) }}
                            </DetailRow>
                        </div>
                    </div>

                    <!-- Description -->
                    <div v-if="product.description" class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                        <div class="px-5 py-3 border-b border-gray-100 bg-gray-50">
                            <h2 class="text-sm font-semibold text-gray-700">{{ t('common.description') }}</h2>
                        </div>
                        <p class="p-5 text-sm text-gray-600 leading-relaxed whitespace-pre-line">{{ product.description }}</p>
                    </div>
                </div>
            </div>
        </template>
    </div>

    <!-- Edit modal -->
    <ProductFormModal
        v-if="product"
        v-model:open="editOpen"
        :product="editTarget"
        :category-options="categoryOptions"
        :brand-options="brandOptions"
        :unit-options="unitOptions"
        @saved="onSaved"
    />
</template>

<script setup>
import { ref, onMounted, defineComponent, h } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { productService }  from '@/services/productService';
import { categoryService } from '@/services/categoryService';
import { brandService }    from '@/services/brandService';
import { unitService }     from '@/services/unitService';
import { ArrowLeftIcon, PencilSquareIcon, PhotoIcon } from '@heroicons/vue/24/outline';
import StatusBadge     from '@/components/ui/StatusBadge.vue';
import ProductFormModal from './ProductFormModal.vue';

const { t, locale } = useI18n();
const route  = useRoute();
const router = useRouter();

// ── Data ──────────────────────────────────────────────────────────────────
const product   = ref(null);
const loading   = ref(true);
const loadError = ref('');

async function loadProduct() {
    loading.value   = true;
    loadError.value = '';
    try {
        const { data } = await productService.show(route.params.id);
        product.value = data;
    } catch {
        loadError.value = t('common.unexpectedError');
    } finally {
        loading.value = false;
    }
}

// ── Dropdown options (for edit modal) ─────────────────────────────────────
const categoryOptions = ref([]);
const brandOptions    = ref([]);
const unitOptions     = ref([]);

onMounted(async () => {
    loadProduct();
    const [cats, brnds, unts] = await Promise.all([
        categoryService.all(),
        brandService.all(),
        unitService.all(),
    ]);
    categoryOptions.value = cats.data;
    brandOptions.value    = brnds.data;
    unitOptions.value     = unts.data;
});

// ── Edit ──────────────────────────────────────────────────────────────────
const editOpen   = ref(false);
const editTarget = ref(null);

function openEdit() {
    editTarget.value = { ...product.value };
    editOpen.value   = true;
}

function onSaved() {
    editOpen.value = false;
    loadProduct();
}

// ── Helpers ───────────────────────────────────────────────────────────────
const taxLabels = { 0: 'Steuerfrei', 7: 'Ermäßigt', 19: 'Regelsteuersatz' };
function taxLabel(rate) { return taxLabels[rate] ?? ''; }

function formatDate(iso) {
    return new Intl.DateTimeFormat(locale.value === 'de' ? 'de-DE' : 'en-GB', {
        day: '2-digit', month: '2-digit', year: 'numeric',
        hour: '2-digit', minute: '2-digit',
    }).format(new Date(iso));
}

function formatCurrency(value) {
    if (value == null) return '—';
    return new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR' }).format(value);
}

// ── Sub-components ────────────────────────────────────────────────────────
const DetailRow = defineComponent({
    props: { label: String, mono: Boolean },
    setup(props, { slots }) {
        return () => h('div', { class: 'flex items-start justify-between gap-4' }, [
            h('span', { class: 'text-sm text-gray-500 flex-shrink-0 w-32' }, props.label),
            h('span', { class: ['text-sm font-medium text-gray-900 text-right', props.mono ? 'font-mono' : ''] },
                slots.default?.()
            ),
        ]);
    },
});

const PriceRow = defineComponent({
    props: { label: String, value: [Number, String], highlight: Boolean },
    setup(props) {
        return () => h('div', { class: 'flex items-center justify-between' }, [
            h('span', { class: 'text-sm text-gray-500' }, props.label),
            h('span', {
                class: props.highlight
                    ? 'text-lg font-bold text-gray-900'
                    : 'text-sm font-medium text-gray-700',
            }, formatCurrency(props.value)),
        ]);
    },
});
</script>

<style scoped>
@reference '../../../css/app.css';
.btn-primary {
    @apply flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition-colors shadow-sm;
}
</style>
