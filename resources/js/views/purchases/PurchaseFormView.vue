<template>
    <div class="p-6 lg:p-8 space-y-6 max-w-6xl mx-auto">

        <!-- Page header -->
        <div class="flex items-center gap-4">
            <RouterLink :to="{ name: 'purchases' }" class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-colors">
                <ArrowLeftIcon class="w-5 h-5" />
            </RouterLink>
            <div>
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">
                    {{ isEdit ? t('purchases.editTitle') + ' · ' + purchase?.purchase_number : t('purchases.createTitle') }}
                </h1>
                <p class="mt-0.5 text-sm text-gray-500">{{ isEdit ? t('purchases.editSubtitle') : t('purchases.createSubtitle') }}</p>
            </div>
            <div v-if="isEdit && purchase?.status === 'received'" class="ml-auto">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
                    <CheckBadgeIcon class="w-4 h-4" />
                    {{ t('purchases.statusReceived') }}
                </span>
            </div>
        </div>

        <!-- Received warning banner -->
        <div v-if="isEdit && purchase?.status === 'received'" class="flex items-center gap-3 bg-amber-50 border border-amber-200 rounded-xl px-4 py-3 text-sm text-amber-800">
            <ExclamationTriangleIcon class="w-5 h-5 flex-shrink-0" />
            {{ t('purchases.receivedLocked') }}
        </div>

        <!-- Header card: supplier + date + ref -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4">{{ t('purchases.sectionHeader') }}</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Branch (always shown; disabled for non-admin users with a fixed branch) -->
                <div>
                    <label class="form-label">{{ t('branches.title') }}</label>
                    <select
                        v-model="form.branch_id"
                        class="form-input"
                        :disabled="isLocked || (!authStore.isAdmin && !!authStore.branchId)"
                    >
                        <option value="">— {{ t('branches.selectBranch') }} —</option>
                        <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                    </select>
                    <p v-if="errors.branch_id" class="form-error">{{ errors.branch_id }}</p>
                </div>

                <!-- Supplier -->
                <div>
                    <label class="form-label">{{ t('suppliers.title') }}</label>
                    <select v-model="form.supplier_id" class="form-input" :disabled="isLocked">
                        <option value="">— {{ t('purchases.noSupplier') }} —</option>
                        <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                </div>

                <!-- Date -->
                <div>
                    <label class="form-label">{{ t('purchases.date') }} <span class="text-red-500">*</span></label>
                    <input v-model="form.purchase_date" type="date" class="form-input" :disabled="isLocked" />
                    <p v-if="errors.purchase_date" class="form-error">{{ errors.purchase_date }}</p>
                </div>

                <!-- Reference -->
                <div>
                    <label class="form-label">{{ t('purchases.reference') }}</label>
                    <input v-model="form.reference" type="text" class="form-input" :placeholder="t('purchases.referencePlaceholder')" :disabled="isLocked" />
                </div>
            </div>

            <!-- Notes -->
            <div class="mt-4">
                <label class="form-label">{{ t('purchases.notes') }}</label>
                <textarea v-model="form.notes" rows="2" class="form-input resize-none" :placeholder="t('purchases.notesPlaceholder')" :disabled="isLocked" />
            </div>
        </div>

        <!-- Line items card -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide">{{ t('purchases.sectionItems') }}</h2>
                <button v-if="!isLocked" @click="addLine" class="btn-sm-primary">
                    <PlusIcon class="w-3.5 h-3.5" />
                    {{ t('purchases.addItem') }}
                </button>
            </div>

            <!-- Items table -->
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-100">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide w-[320px]">{{ t('purchases.product') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wide w-[100px]">{{ t('purchases.qty') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wide w-[120px]">{{ t('purchases.unitCost') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wide w-[90px]">{{ t('purchases.vatRate') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wide w-[110px]">{{ t('purchases.vatAmount') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wide w-[120px]">{{ t('purchases.lineTotal') }}</th>
                            <th v-if="!isLocked" class="px-4 py-3 w-[48px]"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <tr v-for="(line, idx) in form.items" :key="idx" class="hover:bg-gray-50/50 transition-colors">
                            <!-- Product selector -->
                            <td class="px-4 py-2">
                                <div class="flex items-center gap-2">
                                    <img
                                        v-if="line._image_url"
                                        :src="line._image_url"
                                        class="w-8 h-8 rounded object-cover flex-shrink-0 border border-gray-200"
                                        alt=""
                                    />
                                    <div v-else class="w-8 h-8 rounded bg-gray-100 flex-shrink-0 border border-gray-200 flex items-center justify-center">
                                        <PhotoIcon class="w-4 h-4 text-gray-300" />
                                    </div>
                                    <select
                                        v-model="line.product_id"
                                        @change="onProductChange(line)"
                                        class="form-input text-xs flex-1"
                                        :disabled="isLocked"
                                    >
                                        <option value="">— {{ t('purchases.selectProduct') }} —</option>
                                        <option v-for="p in products" :key="p.id" :value="p.id">
                                            {{ p.name }} <template v-if="p.sku">({{ p.sku }})</template>
                                        </option>
                                    </select>
                                </div>
                            </td>

                            <!-- Qty -->
                            <td class="px-4 py-2">
                                <input
                                    v-model.number="line.quantity"
                                    @input="recalc(line)"
                                    type="number" min="0.01" step="0.01"
                                    class="form-input text-xs text-right"
                                    :disabled="isLocked"
                                />
                            </td>

                            <!-- Unit cost -->
                            <td class="px-4 py-2">
                                <div class="relative">
                                    <span class="absolute left-2 top-1/2 -translate-y-1/2 text-gray-400 text-xs">{{ currencySymbol }}</span>
                                    <input
                                        v-model.number="line.unit_cost"
                                        @input="recalc(line)"
                                        type="number" min="0" step="0.01"
                                        class="form-input text-xs text-right pl-5"
                                        :disabled="isLocked"
                                    />
                                </div>
                            </td>

                            <!-- VAT rate -->
                            <td class="px-4 py-2">
                                <select v-model.number="line.vat_rate" @change="recalc(line)" class="form-input text-xs" :disabled="isLocked">
                                    <option :value="0">0 %</option>
                                    <option :value="7">7 %</option>
                                    <option :value="19">19 %</option>
                                </select>
                            </td>

                            <!-- VAT amount (read-only) -->
                            <td class="px-4 py-2 text-right text-gray-600 font-mono text-xs">
                                {{ fmt(line._vat_amount) }}
                            </td>

                            <!-- Line total (read-only) -->
                            <td class="px-4 py-2 text-right text-gray-900 font-semibold font-mono text-xs">
                                {{ fmt(line._line_total) }}
                            </td>

                            <!-- Remove -->
                            <td v-if="!isLocked" class="px-4 py-2 text-center">
                                <button @click="removeLine(idx)" class="p-1 text-gray-300 hover:text-red-500 transition-colors rounded">
                                    <XMarkIcon class="w-4 h-4" />
                                </button>
                            </td>
                        </tr>

                        <!-- Empty state -->
                        <tr v-if="form.items.length === 0">
                            <td colspan="7" class="px-6 py-10 text-center text-sm text-gray-400">
                                {{ t('purchases.noItems') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Totals + adjustments -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            <!-- Discount & Freight -->
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 space-y-4">
                <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide">{{ t('purchases.sectionAdjustments') }}</h2>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="form-label">{{ t('purchases.discount') }}</label>
                        <div class="relative">
                            <span class="absolute left-2 top-1/2 -translate-y-1/2 text-gray-400 text-xs">{{ currencySymbol }}</span>
                            <input v-model.number="form.discount_amount" type="number" min="0" step="0.01" class="form-input text-right pl-5 text-sm" :disabled="isLocked" />
                        </div>
                    </div>
                    <div>
                        <label class="form-label">{{ t('purchases.freight') }}</label>
                        <div class="relative">
                            <span class="absolute left-2 top-1/2 -translate-y-1/2 text-gray-400 text-xs">{{ currencySymbol }}</span>
                            <input v-model.number="form.freight_amount" type="number" min="0" step="0.01" class="form-input text-right pl-5 text-sm" :disabled="isLocked" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Summary -->
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
                <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4">{{ t('purchases.sectionTotals') }}</h2>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between text-gray-600">
                        <dt>{{ t('purchases.subtotal') }}</dt>
                        <dd class="font-mono">{{ fmt(totals.subtotal) }}</dd>
                    </div>
                    <div class="flex justify-between text-gray-600">
                        <dt>{{ t('purchases.vat') }}</dt>
                        <dd class="font-mono">{{ fmt(totals.vat_amount) }}</dd>
                    </div>
                    <div v-if="form.discount_amount > 0" class="flex justify-between text-green-600">
                        <dt>{{ t('purchases.discount') }}</dt>
                        <dd class="font-mono">− {{ fmt(form.discount_amount) }}</dd>
                    </div>
                    <div v-if="form.freight_amount > 0" class="flex justify-between text-gray-600">
                        <dt>{{ t('purchases.freight') }}</dt>
                        <dd class="font-mono">+ {{ fmt(form.freight_amount) }}</dd>
                    </div>
                    <div class="flex justify-between pt-3 border-t border-gray-200 text-base font-bold text-gray-900">
                        <dt>{{ t('purchases.grandTotal') }}</dt>
                        <dd class="font-mono text-indigo-700">{{ fmt(totals.total_amount) }}</dd>
                    </div>
                </dl>
            </div>
        </div>

        <!-- Validation errors -->
        <div v-if="serverError" class="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">{{ serverError }}</div>
        <div v-if="itemsError" class="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">{{ itemsError }}</div>

        <!-- Action bar -->
        <div v-if="!isLocked" class="flex items-center justify-between bg-white rounded-xl border border-gray-200 shadow-sm px-6 py-4">
            <RouterLink :to="{ name: 'purchases' }" class="btn-secondary">
                {{ t('common.cancel') }}
            </RouterLink>
            <div class="flex gap-3">
                <button @click="save(false)" :disabled="saving" class="btn-secondary font-semibold">
                    <DocumentArrowDownIcon class="w-4 h-4" />
                    {{ saving === 'draft' ? t('common.saving') : t('purchases.saveDraft') }}
                </button>
                <button @click="save(true)" :disabled="saving" class="btn-primary">
                    <CheckBadgeIcon class="w-4 h-4" />
                    {{ saving === 'receive' ? t('common.saving') : t('purchases.saveAndReceive') }}
                </button>
            </div>
        </div>

        <!-- Back button when locked -->
        <div v-else class="flex justify-start">
            <RouterLink :to="{ name: 'purchases' }" class="btn-secondary">
                <ArrowLeftIcon class="w-4 h-4" />
                {{ t('purchases.backToList') }}
            </RouterLink>
        </div>

    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRouter, useRoute, RouterLink } from 'vue-router';
import api from '@/services/api';
import { purchaseService } from '@/services/purchaseService';
import { supplierService } from '@/services/supplierService';
import { useSettingsStore } from '@/stores/settings';
import { useAuthStore } from '@/stores/auth';
import { useAlert } from '@/composables/useAlert';

import {
    ArrowLeftIcon, PlusIcon, XMarkIcon,
    CheckBadgeIcon, ExclamationTriangleIcon,
    DocumentArrowDownIcon, PhotoIcon,
} from '@heroicons/vue/24/outline';

const { t }    = useI18n();
const router   = useRouter();
const route    = useRoute();
const { toast, confirm } = useAlert();
const settingsStore = useSettingsStore();
const authStore     = useAuthStore();

const currencySymbol = computed(() => settingsStore.settings?.currency_symbol ?? '€');
const defaultVat     = computed(() => settingsStore.settings?.vat_default ?? 19);

const isEdit    = computed(() => !!route.params.id);
const purchase  = ref(null);
const isLocked  = computed(() => purchase.value?.status === 'received');

const suppliers = ref([]);
const branches  = ref([]);
const products  = ref([]);

// ── Form state ─────────────────────────────────────────────────────────────

function newLine() {
    return { product_id: '', quantity: 1, unit_cost: 0, vat_rate: defaultVat.value, _vat_amount: 0, _line_total: 0, _image_url: null };
}

const form = ref({
    branch_id:       authStore.branchId ?? '',
    supplier_id:     '',
    purchase_date:   new Date().toISOString().slice(0, 10),
    reference:       '',
    notes:           '',
    discount_amount: 0,
    freight_amount:  0,
    items:           [newLine()],
});

const errors      = ref({});
const serverError = ref('');
const itemsError  = ref('');
const saving      = ref(false);

// ── Totals ────────────────────────────────────────────────────────────────

const totals = computed(() => {
    let subtotal  = 0;
    let vatAmount = 0;

    for (const line of form.value.items) {
        const base    = (line.quantity ?? 0) * (line.unit_cost ?? 0);
        const lineVat = Math.round(base * ((line.vat_rate ?? 0) / 100) * 100) / 100;
        subtotal  += base;
        vatAmount += lineVat;
    }

    const discount = form.value.discount_amount ?? 0;
    const freight  = form.value.freight_amount  ?? 0;

    return {
        subtotal:     Math.round(subtotal  * 100) / 100,
        vat_amount:   Math.round(vatAmount * 100) / 100,
        total_amount: Math.round((subtotal + vatAmount - discount + freight) * 100) / 100,
    };
});

// Recalculate a single line's display fields
function recalc(line) {
    const base    = (line.quantity ?? 0) * (line.unit_cost ?? 0);
    const lineVat = Math.round(base * ((line.vat_rate ?? 0) / 100) * 100) / 100;
    line._vat_amount = lineVat;
    line._line_total = Math.round((base + lineVat) * 100) / 100;
}

function fmt(value) {
    const code = settingsStore.settings?.currency_code ?? 'EUR';
    return new Intl.NumberFormat('de-DE', { style: 'currency', currency: code }).format(value ?? 0);
}

// ── Line management ───────────────────────────────────────────────────────

function addLine() {
    form.value.items.push(newLine());
}

function removeLine(idx) {
    form.value.items.splice(idx, 1);
}

function onProductChange(line) {
    const product = products.value.find(p => p.id === line.product_id);
    if (!product) {
        line._image_url = null;
        return;
    }
    line.unit_cost  = parseFloat(product.cost_price ?? 0);
    line.vat_rate   = parseFloat(product.tax_rate   ?? defaultVat.value);
    line._image_url = product.image_url ?? null;
    recalc(line);
}

// ── Load data ─────────────────────────────────────────────────────────────

async function loadSuppliers() {
    try {
        const { data } = await supplierService.all();
        suppliers.value = data.data ?? data;
    } catch { /* silent */ }
}

async function loadBranches() {
    try {
        const { data } = await api.get('/branches/all');
        branches.value = data.data ?? data;
    } catch { /* silent */ }
}

async function loadProducts() {
    try {
        const { data } = await api.get('/products/all');
        products.value = data.data ?? data;
    } catch { /* silent */ }
}

async function loadPurchase() {
    try {
        const { data } = await purchaseService.show(route.params.id);
        const p = data.data ?? data;
        purchase.value = p;

        form.value = {
            branch_id:       p.branch_id ?? authStore.branchId ?? '',
            supplier_id:     p.supplier_id ?? '',
            purchase_date:   p.purchase_date,
            reference:       p.reference ?? '',
            notes:           p.notes ?? '',
            discount_amount: p.discount_amount,
            freight_amount:  p.freight_amount,
            items: (p.items ?? []).map(item => {
                const product = products.value.find(pr => pr.id === item.product_id);
                const base    = item.quantity * item.unit_cost;
                const lineVat = Math.round(base * (item.vat_rate / 100) * 100) / 100;
                return {
                    product_id:  item.product_id,
                    quantity:    item.quantity,
                    unit_cost:   item.unit_cost,
                    vat_rate:    item.vat_rate,
                    _vat_amount: lineVat,
                    _line_total: Math.round((base + lineVat) * 100) / 100,
                    _image_url:  product?.image_url ?? null,
                };
            }),
        };
    } catch {
        toast('error', 'Failed to load purchase.');
        router.push({ name: 'purchases' });
    }
}

onMounted(async () => {
    await Promise.all([loadSuppliers(), loadProducts(), loadBranches()]);
    if (isEdit.value) await loadPurchase();
    form.value.items.forEach(recalc);
});

// ── Save ──────────────────────────────────────────────────────────────────

async function save(receive) {
    errors.value      = {};
    serverError.value = '';
    itemsError.value  = '';

    if (!form.value.purchase_date) {
        errors.value.purchase_date = t('purchases.dateRequired');
        return;
    }

    const validItems = form.value.items.filter(l => l.product_id);
    if (validItems.length === 0) {
        itemsError.value = t('purchases.itemsRequired');
        return;
    }

    saving.value = receive ? 'receive' : 'draft';

    const payload = {
        branch_id:       form.value.branch_id   || null,
        supplier_id:     form.value.supplier_id || null,
        purchase_date:   form.value.purchase_date,
        reference:       form.value.reference || null,
        notes:           form.value.notes || null,
        discount_amount: form.value.discount_amount ?? 0,
        freight_amount:  form.value.freight_amount  ?? 0,
        receive:         receive,
        items: validItems.map(l => ({
            product_id: l.product_id,
            quantity:   l.quantity,
            unit_cost:  l.unit_cost,
            vat_rate:   l.vat_rate ?? 0,
        })),
    };

    try {
        let savedId;
        if (isEdit.value) {
            savedId = parseInt(route.params.id);
            await purchaseService.update(savedId, payload);
        } else {
            const { data } = await purchaseService.store(payload);
            savedId = (data.data ?? data).id;
        }

        toast('success', receive ? t('purchases.receivedSuccess') : (isEdit.value ? t('common.updatedSuccess') : t('common.createdSuccess')));

        const wantsPrint = await confirm({
            title:       t('purchases.printPromptTitle'),
            text:        t('purchases.printPromptText'),
            confirmText: t('purchases.printNow'),
            cancelText:  t('purchases.skipPrint'),
        });

        if (wantsPrint) {
            router.push({ name: 'purchase-invoice', params: { id: savedId } });
        } else {
            router.push({ name: 'purchases' });
        }
    } catch (err) {
        const errData = err.response?.data;
        if (errData?.errors) {
            Object.entries(errData.errors).forEach(([k, v]) => {
                errors.value[k] = Array.isArray(v) ? v[0] : v;
            });
        } else {
            serverError.value = errData?.message ?? t('common.unexpectedError');
        }
    } finally {
        saving.value = false;
    }
}
</script>

<style scoped>
@reference '../../../css/app.css';
.form-label   { @apply block text-xs font-medium text-gray-600 mb-1; }
.form-input   { @apply w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent bg-white disabled:bg-gray-50 disabled:text-gray-500; }
.form-error   { @apply mt-1 text-xs text-red-600; }
.btn-primary  { @apply flex items-center gap-2 px-5 py-2.5 text-sm font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition-colors shadow-sm disabled:opacity-50; }
.btn-secondary{ @apply flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors; }
.btn-sm-primary { @apply flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition-colors; }
</style>
