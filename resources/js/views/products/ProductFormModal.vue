<template>
    <Modal
        :model-value="open"
        :title="isEdit ? t('products.editTitle') : t('products.createTitle')"
        size="lg"
        @update:model-value="$emit('update:open', $event)"
    >
        <form id="product-form" @submit.prevent="handleSubmit" class="space-y-6" novalidate>

            <!-- Section: Basic Info -->
            <div>
                <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-400 mb-3">{{ t('products.sectionBasic') }}</h3>
                <div class="grid grid-cols-2 gap-4">
                    <FormField :label="t('products.sku')" :error="errors.sku" :hint="t('products.skuHint')">
                        <input v-model="form.sku" type="text" :placeholder="t('products.skuPlaceholder')" :class="inputClass(errors.sku)" />
                    </FormField>
                    <FormField :label="t('products.barcode')" :error="errors.barcode">
                        <input v-model="form.barcode" type="text" :placeholder="t('products.barcodePlaceholder')" :class="inputClass(errors.barcode)" />
                    </FormField>
                </div>

                <FormField :label="t('common.name')" :error="errors.name" required class="mt-4">
                    <input v-model="form.name" type="text" :placeholder="t('products.namePlaceholder')" :class="inputClass(errors.name)" />
                </FormField>

                <div class="grid grid-cols-3 gap-4 mt-4">
                    <FormField :label="t('products.category')" :error="errors.category_id">
                        <select v-model="form.category_id" :class="inputClass(errors.category_id)">
                            <option value="">— {{ t('products.noCategory') }} —</option>
                            <option v-for="c in categoryOptions" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </FormField>
                    <FormField :label="t('products.brand')" :error="errors.brand_id">
                        <select v-model="form.brand_id" :class="inputClass(errors.brand_id)">
                            <option value="">— {{ t('products.noBrand') }} —</option>
                            <option v-for="b in brandOptions" :key="b.id" :value="b.id">{{ b.name }}</option>
                        </select>
                    </FormField>
                    <FormField :label="t('products.unit')" :error="errors.unit_id">
                        <select v-model="form.unit_id" :class="inputClass(errors.unit_id)">
                            <option value="">— {{ t('products.noUnit') }} —</option>
                            <option v-for="u in unitOptions" :key="u.id" :value="u.id">{{ u.name }} ({{ u.symbol }})</option>
                        </select>
                    </FormField>
                </div>

                <FormField :label="t('common.description')" :error="errors.description" class="mt-4">
                    <textarea v-model="form.description" rows="2" :placeholder="t('products.descriptionPlaceholder')" :class="inputClass(errors.description)" />
                </FormField>
            </div>

            <div class="border-t border-gray-100" />

            <!-- Section: Pricing -->
            <div>
                <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-400 mb-3">{{ t('products.sectionPricing') }}</h3>
                <div class="grid grid-cols-2 gap-4">
                    <FormField :label="t('products.costPrice')" :error="errors.cost_price" :required="!form.is_service">
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm text-gray-400">€</span>
                            <input
                                v-model="form.cost_price"
                                type="number" min="0" step="0.01"
                                :disabled="form.is_service"
                                :class="[inputClass(errors.cost_price), 'pl-7', form.is_service ? 'bg-gray-50 cursor-not-allowed' : '']"
                            />
                        </div>
                    </FormField>
                    <FormField :label="t('products.sellingPrice')" :error="errors.selling_price" required>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm text-gray-400">€</span>
                            <input v-model="form.selling_price" type="number" min="0" step="0.01" :class="[inputClass(errors.selling_price), 'pl-7']" />
                        </div>
                    </FormField>
                    <FormField :label="t('products.wholesalePrice')" :error="errors.wholesale_price">
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm text-gray-400">€</span>
                            <input v-model="form.wholesale_price" type="number" min="0" step="0.01" :class="[inputClass(errors.wholesale_price), 'pl-7']" />
                        </div>
                    </FormField>
                    <FormField :label="t('products.minSellingPrice')" :error="errors.min_selling_price">
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm text-gray-400">€</span>
                            <input v-model="form.min_selling_price" type="number" min="0" step="0.01" :class="[inputClass(errors.min_selling_price), 'pl-7']" />
                        </div>
                    </FormField>
                </div>

                <!-- Tax Rate (German VAT) -->
                <div class="mt-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">
                        {{ t('products.taxRate') }} <span class="text-red-500">*</span>
                    </label>
                    <div class="flex gap-3">
                        <label v-for="rate in taxRates" :key="rate.value" class="flex items-center gap-2 cursor-pointer">
                            <input
                                type="radio"
                                :value="rate.value"
                                v-model="form.tax_rate"
                                class="w-4 h-4 text-indigo-600 border-gray-300 focus:ring-indigo-500"
                            />
                            <span class="text-sm text-gray-700">
                                <span class="font-semibold">{{ rate.value }}%</span>
                                <span class="text-xs text-gray-400 ml-1">{{ rate.label }}</span>
                            </span>
                        </label>
                    </div>
                    <p v-if="errors.tax_rate" class="mt-1 text-xs text-red-600">{{ errors.tax_rate }}</p>
                </div>

                <!-- Profit Margin preview -->
                <div v-if="profitMargin !== null" class="mt-3 flex items-center gap-2 text-xs text-gray-500 bg-gray-50 rounded-lg px-3 py-2">
                    <span>{{ t('products.profitMargin') }}:</span>
                    <span :class="['font-semibold', profitMargin >= 0 ? 'text-emerald-600' : 'text-red-600']">
                        {{ profitMargin.toFixed(2) }}%
                    </span>
                </div>
            </div>

            <div class="border-t border-gray-100" />

            <!-- Section: Settings -->
            <div>
                <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-400 mb-3">{{ t('products.sectionSettings') }}</h3>
                <div class="grid grid-cols-2 gap-4">
                    <FormField :label="t('products.reorderLevel')" :error="errors.reorder_level">
                        <input v-model="form.reorder_level" type="number" min="0" :class="inputClass(errors.reorder_level)" />
                    </FormField>
                </div>

                <div class="flex flex-wrap gap-6 mt-4">
                    <!-- Is Service toggle -->
                    <div class="flex items-center gap-3">
                        <button type="button" @click="toggleService"
                            :class="['relative inline-flex h-5 w-9 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2', form.is_service ? 'bg-indigo-600' : 'bg-gray-300']"
                        >
                            <span :class="['inline-block h-3.5 w-3.5 rounded-full bg-white shadow transition-transform', form.is_service ? 'translate-x-4' : 'translate-x-1']" />
                        </button>
                        <label class="text-sm font-medium text-gray-700">{{ t('products.isService') }}</label>
                    </div>

                    <!-- Is Active toggle -->
                    <div class="flex items-center gap-3">
                        <button type="button" @click="form.is_active = !form.is_active"
                            :class="['relative inline-flex h-5 w-9 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2', form.is_active ? 'bg-indigo-600' : 'bg-gray-300']"
                        >
                            <span :class="['inline-block h-3.5 w-3.5 rounded-full bg-white shadow transition-transform', form.is_active ? 'translate-x-4' : 'translate-x-1']" />
                        </button>
                        <label class="text-sm font-medium text-gray-700">
                            {{ form.is_active ? t('common.active') : t('common.inactive') }}
                        </label>
                    </div>
                </div>
            </div>

            <div class="border-t border-gray-100" />

            <!-- Section: Produktbild -->
            <div>
                <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-400 mb-3">{{ t('products.sectionImage') }}</h3>
                <div class="flex items-start gap-4">
                    <!-- Preview -->
                    <div class="w-24 h-24 rounded-xl border-2 border-dashed border-gray-200 overflow-hidden flex items-center justify-center bg-gray-50 flex-shrink-0">
                        <img v-if="imagePreview" :src="imagePreview" alt="Vorschau" class="w-full h-full object-cover" />
                        <div v-else class="text-center">
                            <PhotoIcon class="w-8 h-8 text-gray-300 mx-auto" />
                            <span class="text-xs text-gray-400 mt-1 block">{{ t('products.noImage') }}</span>
                        </div>
                    </div>

                    <!-- Upload controls -->
                    <div class="flex-1 space-y-2">
                        <label class="block">
                            <span class="sr-only">{{ t('products.image') }}</span>
                            <input
                                ref="imageInputRef"
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                class="block w-full text-sm text-gray-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 cursor-pointer"
                                @change="onImageSelected"
                            />
                        </label>
                        <p class="text-xs text-gray-400">{{ t('products.imageHint') }}</p>
                        <button
                            v-if="imagePreview"
                            type="button"
                            @click="removeImage"
                            class="text-xs text-red-500 hover:text-red-700 font-medium"
                        >
                            {{ t('products.removeImage') }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Global error -->
            <p v-if="globalError" class="text-sm text-red-600 bg-red-50 rounded-lg px-3 py-2">{{ globalError }}</p>
        </form>

        <template #footer>
            <button type="button" @click="$emit('update:open', false)" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                {{ t('common.cancel') }}
            </button>
            <button type="submit" form="product-form" :disabled="submitting" class="flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 disabled:opacity-60 transition-colors">
                <svg v-if="submitting" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                </svg>
                {{ isEdit ? t('common.saveChanges') : t('products.createProduct') }}
            </button>
        </template>
    </Modal>
</template>

<script setup>
import { ref, reactive, computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import Modal     from '@/components/ui/Modal.vue';
import FormField from '@/components/ui/FormField.vue';
import { PhotoIcon } from '@heroicons/vue/24/outline';
import { productService } from '@/services/productService';

const { t } = useI18n();

const props = defineProps({
    open:            { type: Boolean, required: true },
    product:         { type: Object,  default: null },
    categoryOptions: { type: Array,   default: () => [] },
    brandOptions:    { type: Array,   default: () => [] },
    unitOptions:     { type: Array,   default: () => [] },
});

const emit = defineEmits(['update:open', 'saved']);

const isEdit      = computed(() => !!props.product?.id);
const submitting  = ref(false);
const globalError = ref('');

// ── Image state ───────────────────────────────────────────────────────────
const imageInputRef  = ref(null);
const selectedImage  = ref(null);   // File object
const imagePreview   = ref(null);   // data URL or existing URL
const removeExisting = ref(false);  // user clicked "remove" on saved image

const taxRates = computed(() => [
    { value: 0,  label: t('products.tax.exempt') },
    { value: 7,  label: t('products.tax.reduced') },
    { value: 19, label: t('products.tax.standard') },
]);

const form = reactive({
    sku: '', name: '', description: '', barcode: '',
    category_id: '', brand_id: '', unit_id: '',
    cost_price: '', selling_price: '', wholesale_price: '', min_selling_price: '',
    tax_rate: 19,
    reorder_level: 0,
    is_service: false,
    is_active: true,
});

const errors = reactive({
    sku: '', name: '', description: '', barcode: '',
    category_id: '', brand_id: '', unit_id: '',
    cost_price: '', selling_price: '', wholesale_price: '', min_selling_price: '',
    tax_rate: '', reorder_level: '',
});

// Profit margin preview
const profitMargin = computed(() => {
    const sell = parseFloat(form.selling_price);
    const cost = parseFloat(form.cost_price);
    if (!sell || sell === 0) return null;
    return ((sell - cost) / sell) * 100;
});

watch(() => props.product, (val) => {
    clearErrors();
    globalError.value   = '';
    selectedImage.value = null;
    removeExisting.value = false;
    if (imageInputRef.value) imageInputRef.value.value = '';

    if (val) {
        form.sku               = val.sku               ?? '';
        form.name              = val.name              ?? '';
        form.description       = val.description       ?? '';
        form.barcode           = val.barcode           ?? '';
        form.category_id       = val.category_id       ?? '';
        form.brand_id          = val.brand_id          ?? '';
        form.unit_id           = val.unit_id           ?? '';
        form.cost_price        = val.cost_price        ?? '';
        form.selling_price     = val.selling_price     ?? '';
        form.wholesale_price   = val.wholesale_price   ?? '';
        form.min_selling_price = val.min_selling_price ?? '';
        form.tax_rate          = val.tax_rate          ?? 19;
        form.reorder_level     = val.reorder_level     ?? 0;
        form.is_service        = val.is_service        ?? false;
        form.is_active         = val.is_active         ?? true;
        imagePreview.value     = val.image_url         ?? null;
    } else {
        Object.assign(form, {
            sku: '', name: '', description: '', barcode: '',
            category_id: '', brand_id: '', unit_id: '',
            cost_price: '', selling_price: '', wholesale_price: '', min_selling_price: '',
            tax_rate: 19, reorder_level: 0, is_service: false, is_active: true,
        });
        imagePreview.value = null;
    }
}, { immediate: true });

function toggleService() {
    form.is_service = !form.is_service;
    if (form.is_service) form.cost_price = 0;
}

function onImageSelected(event) {
    const file = event.target.files?.[0];
    if (!file) return;
    selectedImage.value  = file;
    removeExisting.value = false;
    const reader = new FileReader();
    reader.onload = (e) => { imagePreview.value = e.target.result; };
    reader.readAsDataURL(file);
}

function removeImage() {
    selectedImage.value  = null;
    imagePreview.value   = null;
    removeExisting.value = true;
    if (imageInputRef.value) imageInputRef.value.value = '';
}

async function handleSubmit() {
    clearErrors();
    globalError.value = '';
    if (!clientValidate()) return;

    submitting.value = true;
    const payload = buildPayload();

    try {
        let saved;
        if (isEdit.value) {
            saved = await productService.update(props.product.id, payload);
        } else {
            saved = await productService.store(payload);
        }

        // Laravel Resource wraps response: { data: { id: ... } }
        const productId = saved.data?.data?.id ?? props.product?.id;

        if (selectedImage.value && productId) {
            await productService.uploadImage(productId, selectedImage.value);
        } else if (removeExisting.value && productId && isEdit.value) {
            await productService.deleteImage(productId);
        }

        emit('saved');
    } catch (err) {
        const { status, data } = err.response ?? {};
        if (status === 422 && data?.errors) {
            Object.entries(data.errors).forEach(([field, msgs]) => {
                if (field in errors) errors[field] = msgs[0];
            });
        } else {
            globalError.value = data?.message ?? t('common.unexpectedError');
        }
    } finally {
        submitting.value = false;
    }
}

function buildPayload() {
    return {
        sku:              form.sku             || undefined,
        name:             form.name,
        description:      form.description     || null,
        barcode:          form.barcode         || null,
        category_id:      form.category_id     || null,
        brand_id:         form.brand_id        || null,
        unit_id:          form.unit_id         || null,
        cost_price:       form.is_service ? 0 : parseFloat(form.cost_price) || 0,
        selling_price:    parseFloat(form.selling_price)    || 0,
        wholesale_price:  parseFloat(form.wholesale_price)  || 0,
        min_selling_price:parseFloat(form.min_selling_price)|| 0,
        tax_rate:         form.tax_rate,
        reorder_level:    parseInt(form.reorder_level)      || 0,
        is_service:       form.is_service,
        is_active:        form.is_active,
    };
}

function clientValidate() {
    let valid = true;
    if (!form.name.trim())      { errors.name          = t('common.nameRequired');         valid = false; }
    if (!form.selling_price)    { errors.selling_price = t('products.sellingPriceRequired'); valid = false; }
    return valid;
}

function clearErrors() { Object.keys(errors).forEach(k => (errors[k] = '')); }

function inputClass(error) {
    return ['block w-full rounded-lg border px-3 py-2 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-colors', error ? 'border-red-400 bg-red-50' : 'border-gray-300 bg-white'];
}
</script>
