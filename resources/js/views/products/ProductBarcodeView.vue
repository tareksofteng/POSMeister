<template>
    <div class="min-h-screen bg-slate-100">

        <!-- Top toolbar -->
        <div class="bg-white border-b border-slate-200 px-6 py-3 flex items-center justify-between print:hidden">
            <div class="flex items-center gap-3">
                <button @click="$router.back()" class="flex items-center gap-1.5 text-sm text-slate-500 hover:text-slate-800 transition-colors">
                    <ArrowLeftIcon class="w-4 h-4" />
                    {{ t('barcode.back') }}
                </button>
                <span class="text-slate-300">|</span>
                <span class="text-sm font-semibold text-slate-700">{{ t('barcode.title') }}</span>
                <span v-if="product" class="text-xs text-slate-400 bg-slate-100 px-2 py-0.5 rounded-full">{{ product.sku }}</span>
            </div>
            <div class="flex items-center gap-2">
                <span v-if="labels.length" class="text-xs text-slate-500">
                    {{ labels.length }} {{ t('barcode.labelsReady') }}
                </span>
                <button
                    v-if="labels.length"
                    @click="printLabels"
                    class="flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-lg transition-colors"
                >
                    <PrinterIcon class="w-4 h-4" />
                    {{ t('barcode.print') }}
                </button>
            </div>
        </div>

        <!-- Loading -->
        <div v-if="loading" class="flex items-center justify-center py-24 text-slate-400">{{ t('common.loading') }}…</div>

        <template v-else-if="product">
            <div class="p-6 grid grid-cols-3 gap-6 max-w-7xl mx-auto">

                <!-- ── Left: Config form ─────────────────────────────────── -->
                <div class="col-span-1">
                    <div class="bg-white rounded-xl border border-slate-200 shadow-sm sticky top-4">
                        <div class="px-5 py-4 border-b border-slate-100">
                            <h2 class="text-sm font-semibold text-slate-800">{{ t('barcode.settings') }}</h2>
                            <p class="text-xs text-slate-400 mt-0.5">{{ t('barcode.settingsSubtitle') }}</p>
                        </div>

                        <!-- Product info card -->
                        <div class="mx-5 mt-4 p-3 bg-indigo-50 rounded-lg border border-indigo-100">
                            <div class="flex items-start gap-3">
                                <div class="w-10 h-10 rounded-lg bg-white border border-indigo-100 flex items-center justify-center flex-shrink-0 overflow-hidden">
                                    <img v-if="product.image_url" :src="product.image_url" class="w-full h-full object-cover" />
                                    <TagIcon v-else class="w-5 h-5 text-indigo-300" />
                                </div>
                                <div class="min-w-0">
                                    <p class="text-sm font-bold text-slate-800 truncate">{{ product.name }}</p>
                                    <p class="text-xs text-slate-500">{{ t('barcode.sku') }}: <span class="font-mono font-semibold">{{ product.sku }}</span></p>
                                    <p class="text-xs text-slate-500">{{ t('barcode.sellingPrice') }}: <span class="font-semibold text-indigo-700">{{ fmtCurrency(product.selling_price) }}</span></p>
                                </div>
                            </div>
                        </div>

                        <div class="p-5 space-y-4">

                            <!-- Barcode value -->
                            <div>
                                <label class="form-label">{{ t('barcode.barcodeValue') }}</label>
                                <input v-model="config.barcodeValue" type="text" class="form-input font-mono" :placeholder="product.sku" />
                                <p class="text-xs text-slate-400 mt-1">{{ t('barcode.barcodeValueHint') }}</p>
                            </div>

                            <!-- Product name on label -->
                            <div>
                                <label class="form-label">{{ t('barcode.labelName') }}</label>
                                <input v-model="config.name" type="text" class="form-input" :placeholder="product.name" />
                            </div>

                            <!-- Artikel (article number, appears rotated in CI3 style) -->
                            <div>
                                <label class="form-label">{{ t('barcode.article') }}</label>
                                <input v-model="config.article" type="text" class="form-input" :placeholder="t('barcode.articlePlaceholder')" />
                            </div>

                            <!-- Sale rate on label -->
                            <div>
                                <label class="form-label">{{ t('barcode.labelPrice') }}</label>
                                <div class="relative">
                                    <input v-model.number="config.saleRate" type="number" step="0.01" min="0" class="form-input pr-14" />
                                    <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs font-semibold text-slate-400">EUR</span>
                                </div>
                            </div>

                            <!-- Quantity -->
                            <div>
                                <label class="form-label">{{ t('barcode.quantity') }} <span class="text-red-400">*</span></label>
                                <input v-model.number="config.quantity" type="number" min="1" max="500" step="1" class="form-input" />
                            </div>

                            <!-- Label size toggle -->
                            <div>
                                <label class="form-label">{{ t('barcode.labelSize') }}</label>
                                <div class="grid grid-cols-2 gap-2">
                                    <button
                                        type="button"
                                        @click="config.customSize = false"
                                        :class="['flex flex-col items-center gap-1 px-3 py-2.5 rounded-lg border text-xs font-medium transition-colors',
                                            !config.customSize ? 'border-indigo-500 bg-indigo-50 text-indigo-700' : 'border-slate-200 text-slate-600 hover:border-slate-300']"
                                    >
                                        <div class="w-10 h-6 border-2 border-current rounded flex items-center justify-center">
                                            <div class="w-7 h-3 bg-current rounded-sm opacity-50"></div>
                                        </div>
                                        {{ t('barcode.sizeStandard') }}
                                    </button>
                                    <button
                                        type="button"
                                        @click="config.customSize = true"
                                        :class="['flex flex-col items-center gap-1 px-3 py-2.5 rounded-lg border text-xs font-medium transition-colors',
                                            config.customSize ? 'border-indigo-500 bg-indigo-50 text-indigo-700' : 'border-slate-200 text-slate-600 hover:border-slate-300']"
                                    >
                                        <div class="w-8 h-8 border-2 border-current rounded flex items-center justify-center">
                                            <div class="w-5 h-5 bg-current rounded-sm opacity-50"></div>
                                        </div>
                                        {{ t('barcode.sizeCustom') }}
                                    </button>
                                </div>
                            </div>

                            <!-- Custom size inputs -->
                            <div v-if="config.customSize" class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="form-label">{{ t('barcode.width') }} (in)</label>
                                    <input v-model.number="config.xAxis" type="number" step="0.1" min="0.5" class="form-input" />
                                </div>
                                <div>
                                    <label class="form-label">{{ t('barcode.height') }} (in)</label>
                                    <input v-model.number="config.yAxis" type="number" step="0.1" min="0.3" class="form-input" />
                                </div>
                            </div>

                            <!-- Barcode format -->
                            <div>
                                <label class="form-label">{{ t('barcode.format') }}</label>
                                <select v-model="config.format" class="form-select">
                                    <option value="CODE128">CODE128 ({{ t('barcode.formatStandardNote') }})</option>
                                    <option value="EAN13">EAN-13</option>
                                    <option value="EAN8">EAN-8</option>
                                    <option value="CODE39">CODE39</option>
                                    <option value="UPC">UPC</option>
                                </select>
                            </div>

                            <!-- Error -->
                            <p v-if="genError" class="text-xs text-red-600 bg-red-50 border border-red-200 rounded-lg px-3 py-2">{{ genError }}</p>

                            <!-- Generate button -->
                            <button
                                @click="generateLabels"
                                :disabled="generating"
                                class="w-full py-2.5 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-60 text-white text-sm font-semibold rounded-lg transition-colors flex items-center justify-center gap-2"
                            >
                                <QrCodeIcon v-if="!generating" class="w-4 h-4" />
                                <span v-else class="animate-spin border-2 border-white border-t-transparent rounded-full w-4 h-4"></span>
                                {{ generating ? t('barcode.generating') : t('barcode.generate') }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ── Right: Preview area ──────────────────────────────── -->
                <div class="col-span-2">
                    <div class="bg-white rounded-xl border border-slate-200 shadow-sm">
                        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                            <div>
                                <h2 class="text-sm font-semibold text-slate-800">{{ t('barcode.preview') }}</h2>
                                <p class="text-xs text-slate-400 mt-0.5">{{ t('barcode.previewSubtitle') }}</p>
                            </div>
                            <span v-if="labels.length" class="text-xs font-medium text-indigo-600 bg-indigo-50 px-3 py-1 rounded-full">
                                {{ labels.length }} {{ t('barcode.labels') }}
                            </span>
                        </div>

                        <!-- Empty state -->
                        <div v-if="!labels.length" class="flex flex-col items-center justify-center py-20 text-slate-400">
                            <div class="w-16 h-16 rounded-xl bg-slate-100 flex items-center justify-center mb-4">
                                <QrCodeIcon class="w-8 h-8 text-slate-300" />
                            </div>
                            <p class="text-sm font-medium">{{ t('barcode.emptyTitle') }}</p>
                            <p class="text-xs mt-1">{{ t('barcode.emptyMessage') }}</p>
                        </div>

                        <!-- Label grid (print target) -->
                        <div v-else id="barcode-print-area" class="p-4">
                            <div class="flex flex-wrap gap-1">
                                <div
                                    v-for="(label, idx) in labels"
                                    :key="idx"
                                    v-show="!config.customSize"
                                    class="label-standard"
                                >
                                    <!-- Article rotated text -->
                                    <div v-if="label.article" class="label-article">{{ label.article }}</div>
                                    <div class="label-content">
                                        <p class="label-name">{{ label.name }}</p>
                                        <svg :id="`bc-${idx}`" class="label-barcode"></svg>
                                        <p class="label-code">{{ label.barcodeValue }}</p>
                                        <p class="label-price">{{ label.currency }} {{ fmtPrice(label.saleRate) }}</p>
                                    </div>
                                </div>

                                <!-- Custom size labels -->
                                <div
                                    v-for="(label, idx) in labels"
                                    :key="`c-${idx}`"
                                    v-show="config.customSize"
                                    class="label-custom overflow-hidden"
                                    :style="{ width: config.xAxis + 'in', height: config.yAxis + 'in' }"
                                >
                                    <div v-if="label.article" class="label-article" style="font-size: 10px;">{{ label.article }}</div>
                                    <div class="label-content" :style="{ width: config.xAxis + 'in', height: config.yAxis + 'in' }">
                                        <p class="label-name" style="font-size: 9px;">{{ label.name }}</p>
                                        <svg :id="`bcc-${idx}`" class="label-barcode" style="height:40px;"></svg>
                                        <p class="label-code" style="font-size:10px;">{{ label.barcodeValue }}</p>
                                        <p class="label-price" style="font-size:10px;">{{ label.currency }} {{ fmtPrice(label.saleRate) }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </template>

        <div v-else-if="!loading" class="text-center py-24 text-slate-400">{{ t('common.noResults') }}</div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, nextTick } from 'vue';
import { useRoute } from 'vue-router';
import { useI18n } from 'vue-i18n';
import JsBarcode from 'jsbarcode';
import {
    ArrowLeftIcon, PrinterIcon, QrCodeIcon, TagIcon,
} from '@heroicons/vue/24/outline';
import { productService } from '@/services/productService';
import { useSettingsStore } from '@/stores/settings';

const { t }         = useI18n();
const route         = useRoute();
const settingsStore = useSettingsStore();

const product    = ref(null);
const loading    = ref(true);
const generating = ref(false);
const genError   = ref('');
const labels     = ref([]);

// ── Config state ────────────────────────────────────────────────────────────
const config = ref({
    barcodeValue: '',
    name:         '',
    article:      '',
    saleRate:     0,
    quantity:     1,
    customSize:   false,
    xAxis:        1.5,
    yAxis:        1.0,
    format:       'CODE128',
});

// ── Helpers ─────────────────────────────────────────────────────────────────
const currency = computed(() => settingsStore.settings?.currency_symbol ?? '€');

function fmtCurrency(val) {
    return new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR' }).format(val ?? 0);
}
function fmtPrice(val) {
    return new Intl.NumberFormat('de-DE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(val ?? 0);
}

// ── Load product ─────────────────────────────────────────────────────────────
onMounted(async () => {
    try {
        const { data } = await productService.barcodeData(route.params.id);
        product.value = data.data;
        config.value.barcodeValue = data.data.barcode || data.data.sku;
        config.value.name         = data.data.name;
        config.value.saleRate     = parseFloat(data.data.selling_price);
        config.value.quantity     = 1;
    } finally {
        loading.value = false;
    }
});

// ── Generate labels ──────────────────────────────────────────────────────────
async function generateLabels() {
    genError.value  = '';
    generating.value = true;

    const qty = parseInt(config.value.quantity);
    if (!qty || qty < 1) {
        genError.value   = t('barcode.errorQty');
        generating.value = false;
        return;
    }
    if (!config.value.barcodeValue?.trim()) {
        genError.value   = t('barcode.errorValue');
        generating.value = false;
        return;
    }

    // Build label array
    const labelData = {
        barcodeValue: config.value.barcodeValue.trim(),
        name:         config.value.name.trim() || product.value.name,
        article:      config.value.article.trim(),
        saleRate:     config.value.saleRate,
        currency:     currency.value,
    };
    labels.value = Array.from({ length: qty }, () => ({ ...labelData }));

    generating.value = false;

    // Render SVG barcodes after DOM update
    await nextTick();
    renderBarcodes();
}

function renderBarcodes() {
    const opts = {
        format:       config.value.format,
        width:        1.2,
        height:       config.value.customSize ? 40 : 30,
        fontSize:     10,
        margin:       0,
        displayValue: false,
        lineColor:    '#000000',
        background:   '#ffffff',
    };

    const prefix = config.value.customSize ? 'bcc-' : 'bc-';
    labels.value.forEach((_, idx) => {
        try {
            const el = document.getElementById(`${prefix}${idx}`);
            if (el) JsBarcode(el, labels.value[idx].barcodeValue, opts);
        } catch {
            // invalid barcode value for the chosen format — show error once
            if (idx === 0) genError.value = t('barcode.errorFormat');
        }
    });
}

// ── Print ────────────────────────────────────────────────────────────────────
function printLabels() {
    const content    = document.getElementById('barcode-print-area').innerHTML;
    const printWin   = window.open('', '_blank', 'width=900,height=700');
    const customCss  = config.value.customSize
        ? `.label-custom { float: left; margin: 0; padding: 0; overflow: hidden; border: 1px solid #ccc; box-sizing: border-box; border-bottom: none; }`
        : `.label-standard { float: left; padding: 2px; height: 95px; width: 140px; border: 1px solid #ddd; }`;

    printWin.document.write(`<!DOCTYPE html>
<html>
<head>
<title>${t('barcode.title')} — ${product.value?.name ?? ''}</title>
<style>
  * { box-sizing: border-box; }
  body { margin: 0; padding: 0; background: #fff; font-family: Arial, sans-serif; }
  #wrap { display: flex; flex-wrap: wrap; }
  .label-article { min-height: 60px; max-height: 95px; float: left; writing-mode: tb-rl; font-size: 11px; font-weight: 700; transform: rotate(180deg); line-height: 1; margin-left: 0; }
  .label-content { float: left; text-align: center; }
  .label-name  { font-size: 9px; font-weight: 700; margin: 0 0 1px; line-height: 1; }
  .label-barcode { display: block; }
  .label-code  { margin: 0; font-size: 10px; font-weight: 900; text-align: center; }
  .label-price { margin: 0; font-size: 11px; font-weight: 700; text-align: center; }
  ${customCss}
  @page { margin: 4mm; }
  @media print { body { background: white; } }
</style>
</head>
<body>
<div id="wrap">${content}</div>
<script>window.onload=()=>{window.print();window.close();}<\/script>
</body>
</html>`);
    printWin.document.close();
}


</script>

<style scoped>
@reference '../../../css/app.css';

.form-label  { @apply block text-xs font-semibold text-slate-600 mb-1; }
.form-input  { @apply w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:border-indigo-400 transition; }
.form-select { @apply w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:border-indigo-400 transition; }

/* Label styles (preview) */
.label-standard {
    float: left;
    padding: 2px;
    height: 95px;
    width: 140px;
    border: 1px solid #ddd;
    background: #fff;
    position: relative;
}
.label-custom {
    float: left;
    margin: 0;
    padding: 0;
    overflow: hidden;
    border: 1px solid #ccc;
    box-sizing: border-box;
    border-bottom: none;
    background: #fff;
}
.label-article {
    min-height: 60px;
    max-height: 95px;
    float: left;
    writing-mode: vertical-rl;
    transform: rotate(180deg);
    font-size: 11px;
    font-weight: 700;
    line-height: 1;
}
.label-content {
    float: left;
    text-align: center;
    width: 120px;
}
.label-name {
    font-size: 9px;
    font-weight: 700;
    margin: 0 0 1px;
    line-height: 1;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.label-barcode { display: block; width: 100%; height: 30px; }
.label-code    { margin: 0; font-size: 10px; font-weight: 900; }
.label-price   { margin: 0; font-size: 11px; font-weight: 700; }

@media print {
    .print\:hidden { display: none !important; }
}
</style>
