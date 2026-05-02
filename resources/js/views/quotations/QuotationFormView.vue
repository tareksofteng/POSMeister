<template>
    <div class="min-h-screen bg-gradient-to-b from-slate-50 to-white pb-12">

        <!-- ── Sticky header ────────────────────────────────────────────── -->
        <div class="sticky top-0 z-30 bg-white/95 backdrop-blur-sm border-b border-slate-200/80">
            <div class="max-w-6xl mx-auto px-6 lg:px-8 py-4 flex items-center justify-between gap-4">
                <div class="flex items-center gap-3 min-w-0">
                    <RouterLink :to="{ name: 'quotations' }" class="p-2 text-slate-400 hover:text-slate-700 hover:bg-slate-100 rounded-lg transition-colors flex-shrink-0">
                        <ArrowLeftIcon class="w-5 h-5" />
                    </RouterLink>
                    <div class="min-w-0">
                        <h1 class="text-xl font-bold text-slate-900 tracking-tight truncate">
                            {{ isEdit ? t('quotations.editTitle') : t('quotations.createTitle') }}
                            <span v-if="isEdit && quotation?.quotation_number" class="text-violet-600 font-mono ml-2">· {{ quotation.quotation_number }}</span>
                        </h1>
                        <p class="text-xs text-slate-500 mt-0.5">{{ isEdit ? t('quotations.editSubtitle') : t('quotations.createSubtitle') }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-2 flex-shrink-0">
                    <button @click="save('draft')" :disabled="saving" class="btn-secondary">
                        <DocumentArrowDownIcon class="w-4 h-4" />
                        {{ saving === 'draft' ? t('common.saving') : t('quotations.saveAsDraft') }}
                    </button>
                    <button @click="save('sent')" :disabled="saving" class="btn-violet">
                        <PaperAirplaneIcon class="w-4 h-4" />
                        {{ saving === 'sent' ? t('common.saving') : t('quotations.saveAndSend') }}
                    </button>
                </div>
            </div>
        </div>

        <div class="max-w-6xl mx-auto px-6 lg:px-8 py-6 space-y-6">

            <!-- ── Header card: customer + dates ─────────────────────── -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                <!-- Customer block -->
                <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 shadow-sm">
                    <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-violet-50 flex items-center justify-center">
                            <UserIcon class="w-4 h-4 text-violet-600" />
                        </div>
                        <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wide">{{ t('quotations.sectionCustomer') }}</h2>
                    </div>
                    <div class="p-6 space-y-4">
                        <!-- Customer dropdown -->
                        <div>
                            <label class="form-label">{{ t('quotations.selectExisting') }}</label>
                            <select v-model="form.customer_id" @change="onCustomerChange" class="form-input">
                                <option :value="null">— {{ t('quotations.guestCustomer') }} —</option>
                                <option v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }} <span v-if="c.code">({{ c.code }})</span></option>
                            </select>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="form-label">{{ t('quotations.customerName') }}</label>
                                <input v-model="form.customer_name" type="text" class="form-input" :placeholder="t('quotations.namePlaceholder')" />
                            </div>
                            <div>
                                <label class="form-label">{{ t('quotations.customerPhone') }}</label>
                                <input v-model="form.customer_phone" type="text" class="form-input" placeholder="+49 ..." />
                            </div>
                            <div>
                                <label class="form-label">{{ t('quotations.customerEmail') }}</label>
                                <input v-model="form.customer_email" type="email" class="form-input" placeholder="customer@example.com" />
                            </div>
                            <div>
                                <label class="form-label">{{ t('quotations.quotationType') }}</label>
                                <select v-model="form.quotation_type" class="form-input">
                                    <option value="retail">{{ t('quotations.typeRetail') }}</option>
                                    <option value="wholesale">{{ t('quotations.typeWholesale') }}</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="form-label">{{ t('quotations.customerAddress') }}</label>
                            <input v-model="form.customer_address" type="text" class="form-input" :placeholder="t('quotations.addressPlaceholder')" />
                        </div>
                    </div>
                </div>

                <!-- Dates block -->
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm">
                    <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-violet-50 flex items-center justify-center">
                            <CalendarDaysIcon class="w-4 h-4 text-violet-600" />
                        </div>
                        <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wide">{{ t('quotations.sectionDates') }}</h2>
                    </div>
                    <div class="p-6 space-y-4">
                        <div>
                            <label class="form-label">{{ t('quotations.quotationDate') }} <span class="text-red-500">*</span></label>
                            <input v-model="form.quotation_date" type="date" class="form-input" />
                            <p v-if="errors.quotation_date" class="form-error">{{ errors.quotation_date }}</p>
                        </div>
                        <div>
                            <label class="form-label">{{ t('quotations.validUntil') }}</label>
                            <input v-model="form.valid_until" type="date" class="form-input" :min="form.quotation_date" />
                            <p v-if="form.valid_until" class="text-xs mt-1.5" :class="isExpired ? 'text-red-500' : 'text-emerald-600'">
                                {{ isExpired ? t('quotations.expiredAgo') : t('quotations.daysLeft', { days: daysLeft }) }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── Line items ──────────────────────────────────────────── -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-violet-50 flex items-center justify-center">
                            <ListBulletIcon class="w-4 h-4 text-violet-600" />
                        </div>
                        <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wide">{{ t('quotations.sectionItems') }}</h2>
                    </div>
                    <div class="flex items-center gap-2">
                        <button @click="addServiceLine" class="btn-mini-secondary">
                            <SparklesIcon class="w-3.5 h-3.5" />
                            {{ t('quotations.addServiceLine') }}
                        </button>
                        <button @click="addLine" class="btn-mini-violet">
                            <PlusIcon class="w-3.5 h-3.5" />
                            {{ t('quotations.addItem') }}
                        </button>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 border-b border-slate-100">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide w-10">#</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide w-[320px]">{{ t('quotations.product') }}</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide w-24">{{ t('quotations.qty') }}</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide w-32">{{ t('quotations.unitPrice') }}</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide w-24">{{ t('quotations.vatRate') }}</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide w-28">{{ t('quotations.vatAmount') }}</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide w-32">{{ t('quotations.lineTotal') }}</th>
                                <th class="px-4 py-3 w-12"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            <tr v-for="(line, idx) in form.items" :key="idx" class="hover:bg-violet-50/30 transition-colors">
                                <td class="px-4 py-2 text-center text-xs text-slate-400 font-mono">{{ idx + 1 }}</td>

                                <!-- Product or service description -->
                                <td class="px-4 py-2">
                                    <ProductSearchInput
                                        v-if="!line.is_service"
                                        v-model="line.product_id"
                                        :product="line._product"
                                        :placeholder="t('quotations.selectProduct')"
                                        @select="onProductSelect(line, $event)"
                                    />
                                    <input
                                        v-else
                                        v-model="line.description"
                                        type="text"
                                        class="form-input text-sm"
                                        :placeholder="t('quotations.serviceDescription')"
                                    />
                                </td>

                                <td class="px-4 py-2">
                                    <input v-model.number="line.quantity" @input="recalc(line)" type="number" min="0.01" step="0.01" class="form-input text-xs text-right" />
                                </td>

                                <td class="px-4 py-2">
                                    <div class="relative">
                                        <span class="absolute left-2 top-1/2 -translate-y-1/2 text-slate-400 text-xs">{{ currencySymbol }}</span>
                                        <input v-model.number="line.unit_price" @input="recalc(line)" type="number" min="0" step="0.01" class="form-input text-xs text-right pl-5" />
                                    </div>
                                </td>

                                <td class="px-4 py-2">
                                    <select v-model.number="line.tax_rate" @change="recalc(line)" class="form-input text-xs">
                                        <option :value="0">0 %</option>
                                        <option :value="7">7 %</option>
                                        <option :value="19">19 %</option>
                                    </select>
                                </td>

                                <td class="px-4 py-2 text-right text-slate-600 font-mono text-xs tabular-nums">{{ fmtCurrency(line._vat_amount) }}</td>
                                <td class="px-4 py-2 text-right text-slate-900 font-semibold font-mono text-xs tabular-nums">{{ fmtCurrency(line._line_total) }}</td>

                                <td class="px-4 py-2 text-center">
                                    <button @click="removeLine(idx)" class="p-1 text-slate-300 hover:text-red-500 transition-colors rounded">
                                        <XMarkIcon class="w-4 h-4" />
                                    </button>
                                </td>
                            </tr>

                            <tr v-if="form.items.length === 0">
                                <td colspan="8" class="px-6 py-12 text-center text-sm text-slate-400">
                                    {{ t('quotations.noItems') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ── Adjustments + Totals ─────────────────────────────── -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Adjustments + terms -->
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm">
                    <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-violet-50 flex items-center justify-center">
                            <AdjustmentsHorizontalIcon class="w-4 h-4 text-violet-600" />
                        </div>
                        <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wide">{{ t('quotations.sectionAdjustments') }}</h2>
                    </div>
                    <div class="p-6 space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="form-label">{{ t('quotations.discount') }}</label>
                                <div class="relative">
                                    <span class="absolute left-2 top-1/2 -translate-y-1/2 text-slate-400 text-xs">{{ currencySymbol }}</span>
                                    <input v-model.number="form.discount_amount" type="number" min="0" step="0.01" class="form-input text-right pl-5 text-sm" />
                                </div>
                            </div>
                            <div>
                                <label class="form-label">{{ t('quotations.freight') }}</label>
                                <div class="relative">
                                    <span class="absolute left-2 top-1/2 -translate-y-1/2 text-slate-400 text-xs">{{ currencySymbol }}</span>
                                    <input v-model.number="form.freight_amount" type="number" min="0" step="0.01" class="form-input text-right pl-5 text-sm" />
                                </div>
                            </div>
                        </div>
                        <div>
                            <label class="form-label">{{ t('quotations.terms') }}</label>
                            <textarea v-model="form.terms" rows="3" class="form-input resize-none" :placeholder="t('quotations.termsPlaceholder')"></textarea>
                        </div>
                        <div>
                            <label class="form-label">{{ t('quotations.note') }}</label>
                            <textarea v-model="form.note" rows="2" class="form-input resize-none" :placeholder="t('quotations.notePlaceholder')"></textarea>
                        </div>
                    </div>
                </div>

                <!-- Totals -->
                <div class="bg-gradient-to-br from-violet-50 to-purple-50 rounded-2xl border border-violet-200 shadow-sm">
                    <div class="px-6 py-4 border-b border-violet-200 flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-white/70 flex items-center justify-center">
                            <CalculatorIcon class="w-4 h-4 text-violet-600" />
                        </div>
                        <h2 class="text-sm font-semibold text-violet-800 uppercase tracking-wide">{{ t('quotations.sectionTotals') }}</h2>
                    </div>
                    <div class="p-6">
                        <dl class="space-y-2.5 text-sm">
                            <div class="flex justify-between text-slate-700">
                                <dt>{{ t('quotations.subtotal') }}</dt>
                                <dd class="font-mono tabular-nums">{{ fmtCurrency(totals.subtotal) }}</dd>
                            </div>
                            <div v-if="form.discount_amount > 0" class="flex justify-between text-emerald-700">
                                <dt>{{ t('quotations.discount') }}</dt>
                                <dd class="font-mono tabular-nums">− {{ fmtCurrency(form.discount_amount) }}</dd>
                            </div>
                            <div class="flex justify-between text-slate-700">
                                <dt>{{ t('quotations.vat') }} ({{ t('quotations.MwSt') }})</dt>
                                <dd class="font-mono tabular-nums">{{ fmtCurrency(totals.vat_amount) }}</dd>
                            </div>
                            <div v-if="form.freight_amount > 0" class="flex justify-between text-slate-700">
                                <dt>{{ t('quotations.freight') }}</dt>
                                <dd class="font-mono tabular-nums">+ {{ fmtCurrency(form.freight_amount) }}</dd>
                            </div>
                            <div class="flex justify-between items-center pt-3 border-t-2 border-violet-300 mt-3">
                                <dt class="font-bold text-slate-900 text-base">{{ t('quotations.grandTotal') }}</dt>
                                <dd class="font-mono font-bold text-violet-700 text-lg tabular-nums">{{ fmtCurrency(totals.grand_total) }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </div>

            <!-- Errors -->
            <div v-if="serverError" class="rounded-xl bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">{{ serverError }}</div>
            <div v-if="itemsError" class="rounded-xl bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">{{ itemsError }}</div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRouter, useRoute, RouterLink } from 'vue-router';
import { quotationService } from '@/services/quotationService';
import { customerService } from '@/services/customerService';
import { useSettingsStore } from '@/stores/settings';
import { useAlert } from '@/composables/useAlert';
import { useCurrency } from '@/composables/useCurrency';
import {
    ArrowLeftIcon, PlusIcon, XMarkIcon, PaperAirplaneIcon,
    DocumentArrowDownIcon, ListBulletIcon, UserIcon,
    CalendarDaysIcon, AdjustmentsHorizontalIcon, CalculatorIcon,
    SparklesIcon,
} from '@heroicons/vue/24/outline';
import ProductSearchInput from '@/components/ui/ProductSearchInput.vue';

const { t }              = useI18n();
const router             = useRouter();
const route              = useRoute();
const { toast, confirm } = useAlert();
const settingsStore      = useSettingsStore();
const { fmtCurrency, currencySymbol } = useCurrency();

const defaultVat = computed(() => settingsStore.settings?.vat_default ?? 19);

const isEdit    = computed(() => !!route.params.id);
const quotation = ref(null);

const customers   = ref([]);
const errors      = ref({});
const serverError = ref('');
const itemsError  = ref('');
const saving      = ref(false);

// ── Form state ──────────────────────────────────────────────────────────
function newLine(isService = false) {
    return {
        product_id: null, description: '', quantity: 1, unit_price: 0,
        tax_rate: defaultVat.value, is_service: isService,
        _vat_amount: 0, _line_total: 0, _product: null,
    };
}

const form = ref({
    customer_id:      null,
    customer_name:    '',
    customer_phone:   '',
    customer_email:   '',
    customer_address: '',
    quotation_type:   'retail',
    quotation_date:   new Date().toISOString().slice(0, 10),
    valid_until:      addDays(new Date(), 14).toISOString().slice(0, 10),
    discount_amount:  0,
    freight_amount:   0,
    terms:            '',
    note:             '',
    items:            [newLine()],
});

function addDays(date, days) {
    const d = new Date(date);
    d.setDate(d.getDate() + days);
    return d;
}

// ── Validity countdown ──────────────────────────────────────────────────
const daysLeft = computed(() => {
    if (!form.value.valid_until) return 0;
    const target = new Date(form.value.valid_until + 'T00:00:00').getTime();
    const today  = new Date(new Date().toDateString()).getTime();
    return Math.max(0, Math.round((target - today) / 86_400_000));
});
const isExpired = computed(() => {
    if (!form.value.valid_until) return false;
    return new Date(form.value.valid_until + 'T00:00:00') < new Date(new Date().toDateString());
});

// ── Totals ──────────────────────────────────────────────────────────────
const totals = computed(() => {
    let subtotal  = 0;
    let vatAmount = 0;
    for (const line of form.value.items) {
        const base    = (line.quantity ?? 0) * (line.unit_price ?? 0);
        const lineVat = Math.round(base * ((line.tax_rate ?? 0) / 100) * 100) / 100;
        subtotal  += base;
        vatAmount += lineVat;
    }
    const discount = form.value.discount_amount ?? 0;
    const freight  = form.value.freight_amount  ?? 0;
    return {
        subtotal:    Math.round(subtotal  * 100) / 100,
        vat_amount:  Math.round(vatAmount * 100) / 100,
        grand_total: Math.round((subtotal - discount + vatAmount + freight) * 100) / 100,
    };
});

function recalc(line) {
    const base    = (line.quantity ?? 0) * (line.unit_price ?? 0);
    const lineVat = Math.round(base * ((line.tax_rate ?? 0) / 100) * 100) / 100;
    line._vat_amount = lineVat;
    line._line_total = Math.round((base + lineVat) * 100) / 100;
}

// ── Line management ─────────────────────────────────────────────────────
function addLine()        { form.value.items.push(newLine(false)); }
function addServiceLine() { form.value.items.push(newLine(true)); }
function removeLine(idx)  { form.value.items.splice(idx, 1); }

function onProductSelect(line, product) {
    if (!product) {
        line.product_id = null;
        line._product   = null;
        line.unit_price = 0;
        recalc(line);
        return;
    }
    line.product_id = product.id;
    line._product   = product;
    line.unit_price = parseFloat(product.selling_price ?? product.cost_price ?? 0);
    line.tax_rate   = parseFloat(product.tax_rate ?? defaultVat.value);
    recalc(line);
}

function onCustomerChange() {
    const c = customers.value.find(x => x.id === form.value.customer_id);
    if (c) {
        form.value.customer_name    = c.name ?? '';
        form.value.customer_phone   = c.phone ?? '';
        form.value.customer_email   = c.email ?? '';
        form.value.customer_address = c.address ?? '';
    }
}

// ── Load ────────────────────────────────────────────────────────────────
async function loadCustomers() {
    try {
        const { data } = await customerService.all();
        customers.value = data.data ?? data;
    } catch { /* silent */ }
}

async function loadQuotation() {
    try {
        const { data } = await quotationService.show(route.params.id);
        const q = data.data ?? data;
        quotation.value = q;

        form.value = {
            customer_id:      q.customer_id,
            customer_name:    q.customer_name ?? '',
            customer_phone:   q.customer_phone ?? '',
            customer_email:   q.customer_email ?? '',
            customer_address: q.customer_address ?? '',
            quotation_type:   q.quotation_type ?? 'retail',
            quotation_date:   q.quotation_date,
            valid_until:      q.valid_until ?? '',
            discount_amount:  q.discount_amount ?? 0,
            freight_amount:   q.freight_amount  ?? 0,
            terms:            q.terms ?? '',
            note:             q.note  ?? '',
            items: (q.items ?? []).map(item => {
                const base    = item.quantity * item.unit_price;
                const lineVat = Math.round(base * (item.tax_rate / 100) * 100) / 100;
                return {
                    product_id:  item.product_id,
                    description: item.description ?? '',
                    quantity:    item.quantity,
                    unit_price:  item.unit_price,
                    tax_rate:    item.tax_rate,
                    is_service:  item.is_service ?? false,
                    _vat_amount: lineVat,
                    _line_total: Math.round((base + lineVat) * 100) / 100,
                    _product:    item.product_id ? {
                        id:            item.product_id,
                        name:          item.name ?? '—',
                        sku:           item.sku  ?? '',
                        selling_price: item.unit_price,
                        tax_rate:      item.tax_rate,
                        image_url:     item.image_url ?? null,
                        unit_symbol:   item.unit_symbol ?? '',
                    } : null,
                };
            }),
        };
    } catch {
        toast('error', t('common.unexpectedError'));
        router.push({ name: 'quotations' });
    }
}

onMounted(async () => {
    await loadCustomers();
    if (isEdit.value) await loadQuotation();
    form.value.items.forEach(recalc);
});

// ── Save ────────────────────────────────────────────────────────────────
async function save(targetStatus) {
    errors.value      = {};
    serverError.value = '';
    itemsError.value  = '';

    if (!form.value.quotation_date) {
        errors.value.quotation_date = t('quotations.dateRequired');
        return;
    }

    const validItems = form.value.items.filter(l => (l.is_service ? !!l.description : !!l.product_id));
    if (validItems.length === 0) {
        itemsError.value = t('quotations.itemsRequired');
        return;
    }

    saving.value = targetStatus;

    const payload = {
        customer_id:      form.value.customer_id || null,
        customer_name:    form.value.customer_name    || null,
        customer_phone:   form.value.customer_phone   || null,
        customer_email:   form.value.customer_email   || null,
        customer_address: form.value.customer_address || null,
        quotation_type:   form.value.quotation_type,
        quotation_date:   form.value.quotation_date,
        valid_until:      form.value.valid_until || null,
        discount_amount:  form.value.discount_amount ?? 0,
        freight_amount:   form.value.freight_amount  ?? 0,
        terms:            form.value.terms || null,
        note:             form.value.note  || null,
        status:           targetStatus,
        items: validItems.map(l => ({
            product_id:  l.product_id,
            description: l.description || null,
            quantity:    l.quantity,
            unit_price:  l.unit_price,
            tax_rate:    l.tax_rate ?? 0,
            is_service:  l.is_service ?? false,
        })),
    };

    try {
        let savedId;
        if (isEdit.value) {
            savedId = parseInt(route.params.id);
            await quotationService.update(savedId, payload);
        } else {
            const { data } = await quotationService.store(payload);
            savedId = (data.data ?? data).id;
        }

        toast('success', isEdit.value ? t('common.updatedSuccess') : t('common.createdSuccess'));

        const goPrint = await confirm({
            title:       t('quotations.printPromptTitle'),
            text:        t('quotations.printPromptText'),
            confirmText: t('quotations.openInvoice'),
            cancelText:  t('quotations.skipForNow'),
        });

        router.push(goPrint
            ? { name: 'quotation-invoice', params: { id: savedId } }
            : { name: 'quotations' }
        );
    } catch (err) {
        const errData = err.response?.data;
        if (errData?.errors) {
            Object.entries(errData.errors).forEach(([k, v]) => {
                errors.value[k] = Array.isArray(v) ? v[0] : v;
            });
            itemsError.value = errData.errors['items'] || (Object.keys(errData.errors).find(k => k.startsWith('items.')) ? t('quotations.itemsInvalid') : '');
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
.form-label   { @apply block text-xs font-medium text-slate-600 mb-1.5; }
.form-input   { @apply w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent bg-white disabled:bg-slate-50 disabled:text-slate-500; }
.form-error   { @apply mt-1 text-xs text-red-600; }
.btn-violet   { @apply flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white rounded-lg transition-all duration-150 shadow-sm bg-gradient-to-r from-violet-600 to-purple-600 hover:from-violet-700 hover:to-purple-700 hover:shadow-md hover:shadow-violet-200 disabled:opacity-50; }
.btn-secondary{ @apply flex items-center gap-2 px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-lg hover:bg-slate-50 transition-colors disabled:opacity-50; }
.btn-mini-violet { @apply flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-white bg-violet-600 rounded-lg hover:bg-violet-700 transition-colors; }
.btn-mini-secondary { @apply flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-slate-700 bg-white border border-slate-300 rounded-lg hover:bg-slate-50 transition-colors; }
</style>
