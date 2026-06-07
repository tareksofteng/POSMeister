<template>
    <div class="bg-slate-50 min-h-screen pb-24">
        <div class="bg-white border-b border-slate-200">
            <div class="max-w-6xl mx-auto px-6 lg:px-8 py-4 flex items-center justify-between gap-4">
                <div class="flex items-center gap-3 min-w-0">
                    <RouterLink :to="{ name: 'sales' }"
                        class="p-2 text-slate-400 hover:text-slate-700 hover:bg-slate-100 rounded-lg">
                        <ArrowLeftIcon class="w-5 h-5" />
                    </RouterLink>
                    <div class="min-w-0">
                        <h1 class="text-lg font-semibold text-slate-900">{{ t('sales.createTitle') }}</h1>
                        <p class="text-xs text-slate-500">{{ t('sales.createSubtitle') }}</p>
                    </div>
                </div>

            </div>
        </div>

        <div class="max-w-6xl mx-auto px-6 lg:px-8 pt-6 space-y-5">

            <div v-if="serverError"
                class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ serverError }}
            </div>

            <section class="card">
                <header class="card-head">
                    <UserIcon class="w-4 h-4 text-emerald-600" />
                    <h2>{{ t('sales.sectionHeader') }}</h2>
                </header>

                <div class="card-body space-y-4">
                    <div class="flex gap-1.5 p-1 bg-slate-100 rounded-lg w-fit">
                        <button type="button" @click="setCustomerType('walkin')"
                            :class="['tab-btn', form.customer_type === 'walkin' ? 'tab-active' : 'tab-idle']">{{
                                t('sales.walkin') }}</button>
                        <button type="button" @click="setCustomerType('registered')"
                            :class="['tab-btn', form.customer_type === 'registered' ? 'tab-active' : 'tab-idle']">{{
                                t('sales.registered') }}</button>
                    </div>

                    <div v-if="form.customer_type === 'registered'">
                        <label class="lbl">{{ t('sales.selectCustomer') }}</label>
                        <select v-model.number="form.customer_id" @change="onCustomerPick" class="ctrl">
                            <option :value="null">{{ t('sales.selectCustomer') }}</option>
                            <option v-for="c in customers" :key="c.id" :value="c.id">
                                {{ c.name }}<span v-if="c.code"> ({{ c.code }})</span>
                            </option>
                        </select>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="lbl">{{ t('sales.customerName') }}</label>
                            <input v-model="form.customer_name" type="text" class="ctrl"
                                :placeholder="t('sales.customerNamePh')" />
                        </div>
                        <div>
                            <label class="lbl">{{ t('sales.customerPhone') }}</label>
                            <input v-model="form.customer_phone" type="text" class="ctrl" placeholder="+49 ..." />
                        </div>
                        <div>
                            <label class="lbl">{{ t('sales.saleType') }}</label>
                            <select v-model="form.sale_type" class="ctrl">
                                <option value="retail">{{ t('sales.saleTypeRetail') }}</option>
                                <option value="wholesale">{{ t('sales.saleTypeWholesale') }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="sm:col-span-2">
                            <label class="lbl">{{ t('sales.customerAddress') }}</label>
                            <input v-model="form.customer_address" type="text" class="ctrl"
                                :placeholder="t('sales.customerAddressPh')" />
                        </div>
                        <div>
                            <label class="lbl">{{ t('sales.date') }} <span class="text-rose-500">*</span></label>
                            <input v-model="form.sale_date" type="date" class="ctrl" />
                            <p v-if="errors.sale_date" class="err">{{ errors.sale_date }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="card">
                <header class="card-head justify-between">
                    <div class="flex items-center gap-2">
                        <ListBulletIcon class="w-4 h-4 text-emerald-600" />
                        <h2>{{ t('sales.itemsSection') }}</h2>
                    </div>
                    <button type="button" @click="addLine" class="btn-tiny">
                        <PlusIcon class="w-3.5 h-3.5" />
                        {{ t('sales.addItem') }}
                    </button>
                </header>

                <!-- ── Mobile (< lg): stacked card per line item ─────────── -->
                <div class="lg:hidden divide-y divide-slate-100">
                    <div v-for="(line, idx) in form.items" :key="`m-${idx}`" class="p-4 space-y-3">
                        <div class="flex items-start gap-2">
                            <div class="flex-1 min-w-0">
                                <label class="block text-[10px] uppercase tracking-wider font-semibold text-slate-500 mb-1">{{ t('sales.product') }} #{{ idx + 1 }}</label>
                                <ProductSearchInput v-model="line.product_id" :product="line._product"
                                    :placeholder="t('sales.searchProduct')" @select="onProductPick(line, $event)" />
                                <p v-if="line._product?.stock != null" class="mt-1 text-[11px] text-slate-400">
                                    {{ t('sales.stock') }}: {{ line._product.stock }} {{ line._product.unit_symbol }}
                                </p>
                            </div>
                            <button @click="removeLine(idx)" type="button" class="mt-5 p-2 text-slate-400 hover:text-rose-500 hover:bg-rose-50 rounded-lg flex-shrink-0">
                                <XMarkIcon class="w-5 h-5" />
                            </button>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-[10px] uppercase tracking-wider font-semibold text-slate-500 mb-1">{{ t('sales.qty') }}</label>
                                <!-- Phase Y — serialized: badge button instead of editable input -->
                                <button v-if="isSerializedLine(line)"
                                        type="button"
                                        @click="openSerialPicker(line)"
                                        :class="['w-full inline-flex items-center justify-center gap-1.5 px-3 py-2.5 text-base font-bold border rounded-lg transition-colors',
                                                 line._serial_ids?.length
                                                   ? 'text-emerald-700 bg-emerald-50 border-emerald-200 hover:bg-emerald-100'
                                                   : 'text-indigo-700 bg-indigo-50 border-indigo-200 hover:bg-indigo-100']">
                                    <CheckBadgeIcon v-if="line._serial_ids?.length" class="w-4 h-4" />
                                    <CpuChipIcon v-else class="w-4 h-4" />
                                    {{ line._serial_ids?.length ? line._serial_ids.length : t('serials.lineBadge.add') }}
                                </button>
                                <input v-else v-model.number="line.quantity" @input="recalcLine(line)" type="number"
                                    min="0.01" step="0.01" inputmode="decimal"
                                    class="w-full px-3 py-2.5 text-base text-right border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500" />
                            </div>
                            <div>
                                <label class="block text-[10px] uppercase tracking-wider font-semibold text-slate-500 mb-1">{{ t('sales.unitPrice') }}</label>
                                <div class="relative">
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm text-slate-400">{{ currencySymbol }}</span>
                                    <input v-model.number="line.unit_price" @input="recalcLine(line)" type="number"
                                        min="0" step="0.01" inputmode="decimal"
                                        class="w-full pl-8 pr-3 py-2.5 text-base text-right border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500" />
                                </div>
                            </div>
                            <div>
                                <label class="block text-[10px] uppercase tracking-wider font-semibold text-slate-500 mb-1">{{ t('sales.vatRate') }}</label>
                                <select v-model.number="line.tax_rate" @change="recalcLine(line)" class="w-full px-3 py-2.5 text-base border border-slate-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                                    <option :value="0">0 %</option>
                                    <option :value="7">7 %</option>
                                    <option :value="19">19 %</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] uppercase tracking-wider font-semibold text-slate-500 mb-1">{{ t('sales.lineTotal') }}</label>
                                <div class="w-full px-3 py-2.5 text-base text-right font-mono font-semibold text-slate-900 bg-emerald-50/60 border border-emerald-100 rounded-lg tabular-nums">
                                    {{ fmtCurrency(line._line_total) }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="form.items.length === 0" class="px-6 py-10 text-center text-sm text-slate-400">
                        {{ t('sales.noItems') }}
                    </div>
                </div>

                <!-- ── Desktop (lg+): table layout ───────────────────────── -->
                <div class="hidden lg:block overflow-x-auto responsive-table">
                    <table class="w-full text-sm min-w-[820px]">
                        <thead class="bg-slate-50 border-y border-slate-100">
                            <tr>
                                <th class="th w-10">#</th>
                                <th class="th min-w-[280px]">{{ t('sales.product') }}</th>
                                <th class="th-r w-24">{{ t('sales.qty') }}</th>
                                <th class="th-r w-32">{{ t('sales.unitPrice') }}</th>
                                <th class="th-r w-20">{{ t('sales.vatRate') }}</th>
                                <th class="th-r w-28">{{ t('sales.lineTotal') }}</th>
                                <th class="w-10"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(line, idx) in form.items" :key="idx"
                                class="border-b border-slate-100 hover:bg-emerald-50/30">
                                <td class="td text-center text-xs text-slate-400 font-mono">{{ idx + 1 }}</td>
                                <td class="td">
                                    <ProductSearchInput v-model="line.product_id" :product="line._product"
                                        :placeholder="t('sales.searchProduct')" @select="onProductPick(line, $event)" />
                                    <p v-if="line._product?.stock != null" class="mt-1 text-[11px] text-slate-400">
                                        {{ t('sales.stock') }}: {{ line._product.stock }} {{ line._product.unit_symbol
                                        }}
                                    </p>
                                </td>
                                <td class="td">
                                    <button v-if="isSerializedLine(line)"
                                            type="button"
                                            @click="openSerialPicker(line)"
                                            :class="['inline-flex items-center justify-center gap-1 w-full px-2 py-1.5 text-xs font-bold border rounded-md transition-colors',
                                                     line._serial_ids?.length
                                                       ? 'text-emerald-700 bg-emerald-50 border-emerald-200 hover:bg-emerald-100'
                                                       : 'text-indigo-700 bg-indigo-50 border-indigo-200 hover:bg-indigo-100']">
                                        <CheckBadgeIcon v-if="line._serial_ids?.length" class="w-3 h-3" />
                                        <CpuChipIcon v-else class="w-3 h-3" />
                                        {{ line._serial_ids?.length ? line._serial_ids.length : 'SN' }}
                                    </button>
                                    <input v-else v-model.number="line.quantity" @input="recalcLine(line)" type="number"
                                        min="0.01" step="0.01" class="ctrl-sm text-right" />
                                </td>
                                <td class="td">
                                    <div class="relative">
                                        <span class="absolute left-2 top-1/2 -translate-y-1/2 text-xs text-slate-400">{{
                                            currencySymbol }}</span>
                                        <input v-model.number="line.unit_price" @input="recalcLine(line)" type="number"
                                            min="0" step="0.01" class="ctrl-sm text-right pl-5" />
                                    </div>
                                </td>
                                <td class="td">
                                    <select v-model.number="line.tax_rate" @change="recalcLine(line)" class="ctrl-sm">
                                        <option :value="0">0 %</option>
                                        <option :value="7">7 %</option>
                                        <option :value="19">19 %</option>
                                    </select>
                                </td>
                                <td class="td text-right font-mono font-semibold tabular-nums">{{
                                    fmtCurrency(line._line_total) }}</td>
                                <td class="td text-center">
                                    <button @click="removeLine(idx)" type="button"
                                        class="p-1 text-slate-300 hover:text-rose-500 rounded">
                                        <XMarkIcon class="w-4 h-4" />
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="form.items.length === 0">
                                <td colspan="7" class="px-4 py-10 text-center text-sm text-slate-400">
                                    {{ t('sales.noItems') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-if="errors.items" class="px-5 pb-3 err">{{ errors.items }}</p>
            </section>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                <section class="card">
                    <header class="card-head">
                        <AdjustmentsHorizontalIcon class="w-4 h-4 text-emerald-600" />
                        <h2>{{ t('sales.adjustmentsAndPayment') }}</h2>
                    </header>
                    <div class="card-body space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="lbl">{{ t('sales.discount') }}</label>
                                <div class="relative">
                                    <span class="absolute left-2 top-1/2 -translate-y-1/2 text-xs text-slate-400">{{
                                        currencySymbol }}</span>
                                    <input v-model.number="form.discount_amount" type="number" min="0" step="0.01"
                                        class="ctrl pl-6 text-right" />
                                </div>
                            </div>
                            <div>
                                <label class="lbl">{{ t('sales.freight') }}</label>
                                <div class="relative">
                                    <span class="absolute left-2 top-1/2 -translate-y-1/2 text-xs text-slate-400">{{
                                        currencySymbol }}</span>
                                    <input v-model.number="form.freight_amount" type="number" min="0" step="0.01"
                                        class="ctrl pl-6 text-right" />
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="lbl flex items-center gap-1.5">
                                    <BanknotesIcon class="w-3.5 h-3.5 text-emerald-600" />
                                    {{ t('sales.cashPaid') }}
                                </label>
                                <input v-model.number="form.cash_paid" @focus="autoFillCash" type="number" min="0"
                                    step="0.01" class="ctrl text-right" />
                            </div>
                            <div>
                                <label class="lbl flex items-center gap-1.5">
                                    <CreditCardIcon class="w-3.5 h-3.5 text-emerald-600" />
                                    {{ t('sales.cardPaid') }}
                                </label>
                                <input v-model.number="form.card_paid" type="number" min="0" step="0.01"
                                    class="ctrl text-right" />
                            </div>
                        </div>

                        <div>
                            <label class="lbl">{{ t('sales.note') }}</label>
                            <textarea v-model="form.note" rows="2" class="ctrl resize-none"
                                :placeholder="t('sales.notePlaceholder')"></textarea>
                        </div>
                    </div>
                </section>

                <section class="card bg-emerald-50/40 border-emerald-200">
                    <header class="card-head">
                        <CalculatorIcon class="w-4 h-4 text-emerald-600" />
                        <h2>{{ t('sales.summary') }}</h2>
                    </header>
                    <div class="card-body">
                        <dl class="space-y-2 text-sm">
                            <div class="flex justify-between text-slate-700">
                                <dt>{{ t('sales.subtotal') }}</dt>
                                <dd class="font-mono tabular-nums">{{ fmtCurrency(totals.subtotal) }}</dd>
                            </div>
                            <div v-if="form.discount_amount > 0" class="flex justify-between text-emerald-700">
                                <dt>{{ t('sales.discount') }}</dt>
                                <dd class="font-mono tabular-nums">- {{ fmtCurrency(form.discount_amount) }}</dd>
                            </div>
                            <div class="flex justify-between text-slate-700">
                                <dt>{{ t('sales.vat') }}</dt>
                                <dd class="font-mono tabular-nums">{{ fmtCurrency(totals.vat) }}</dd>
                            </div>
                            <div v-if="form.freight_amount > 0" class="flex justify-between text-slate-700">
                                <dt>{{ t('sales.freight') }}</dt>
                                <dd class="font-mono tabular-nums">+ {{ fmtCurrency(form.freight_amount) }}</dd>
                            </div>
                            <div class="flex justify-between items-center pt-3 border-t-2 border-emerald-300 mt-3">
                                <dt class="font-bold text-slate-900 text-base">{{ t('sales.grandTotal') }}</dt>
                                <dd class="font-mono font-bold text-emerald-700 text-lg tabular-nums">{{
                                    fmtCurrency(totals.grand) }}</dd>
                            </div>

                            <div class="pt-3 mt-3 border-t border-emerald-200 space-y-1.5">
                                <div class="flex justify-between text-slate-600 text-xs">
                                    <dt>{{ t('sales.totalPaid') }}</dt>
                                    <dd class="font-mono tabular-nums">{{ fmtCurrency(totalPaid) }}</dd>
                                </div>
                                <div v-if="change > 0" class="flex justify-between text-emerald-700 font-semibold">
                                    <dt>{{ t('sales.change') }}</dt>
                                    <dd class="font-mono tabular-nums">{{ fmtCurrency(change) }}</dd>
                                </div>
                                <div v-if="due > 0" class="flex justify-between text-amber-700 font-semibold">
                                    <dt>{{ t('sales.due') }}</dt>
                                    <dd class="font-mono tabular-nums">{{ fmtCurrency(due) }}</dd>
                                </div>
                            </div>
                        </dl>
                    </div>

                </section>
            </div>

            <div class="flex items-center justify-end gap-2 pt-2">
                <RouterLink :to="{ name: 'sales' }" class="btn-soft">{{ t('common.cancel') }}</RouterLink>
                <button @click="save" :disabled="saving" class="btn-emerald">
                    <CheckIcon v-if="!saving" class="w-4 h-4" />
                    <ArrowPathIcon v-else class="w-4 h-4 animate-spin" />
                    {{ saving ? t('common.saving') : t('sales.saveSale') }}
                </button>
            </div>
        </div>
    </div>

    <!-- Phase Y Round 2C — back-office serial picker -->
    <SelectSerialsModal
        :open="serialPickerOpen"
        :product="serialPickerLine?._product"
        :branch-id="authStore.branchId"
        :initial-ids="serialPickerLine?._serial_ids || []"
        @close="serialPickerOpen = false"
        @confirm="onSerialsPicked"
    />
</template>

<script setup>
import { ref, computed, onMounted, nextTick } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRouter, RouterLink } from 'vue-router';
import { saleService } from '@/services/saleService';
import { customerService } from '@/services/customerService';
import { useAlert } from '@/composables/useAlert';
import { useCurrency } from '@/composables/useCurrency';
import { getAll } from '@/offline/db';
import ProductSearchInput from '@/components/ui/ProductSearchInput.vue';
import SelectSerialsModal from '@/views/serials/SelectSerialsModal.vue';
import { serialService } from '@/services/serialService';
import { useAuthStore } from '@/stores/auth';
import { CheckBadgeIcon, CpuChipIcon } from '@heroicons/vue/24/outline';
import {
    ArrowLeftIcon, PlusIcon, XMarkIcon, CheckIcon, ArrowPathIcon,
    UserIcon, ListBulletIcon, AdjustmentsHorizontalIcon, CalculatorIcon,
    BanknotesIcon, CreditCardIcon,
} from '@heroicons/vue/24/outline';

const { t } = useI18n();
const router = useRouter();
const { toast, confirm } = useAlert();
const { fmtCurrency, currencySymbol } = useCurrency();

const customers = ref([]);
const errors = ref({});
const serverError = ref('');
const saving = ref(false);

function makeLine() {
    return {
        product_id: null,
        quantity: 1,
        unit_price: 0,
        tax_rate: 0,
        _line_total: 0,
        _product: null,
    };
}

const form = ref({
    customer_type: 'walkin',
    customer_id: null,
    customer_name: '',
    customer_phone: '',
    customer_address: '',
    sale_type: 'retail',
    sale_date: new Date().toISOString().slice(0, 10),
    discount_amount: 0,
    freight_amount: 0,
    cash_paid: 0,
    card_paid: 0,
    note: '',
    items: [makeLine()],
});

const totals = computed(() => {
    let net = 0, vat = 0;
    for (const l of form.value.items) {
        const base = (l.quantity || 0) * (l.unit_price || 0);
        net += base;
        vat += round2(base * ((l.tax_rate || 0) / 100));
    }
    const discount = form.value.discount_amount || 0;
    const freight = form.value.freight_amount || 0;
    return {
        subtotal: round2(net),
        vat: round2(vat),
        grand: round2(net - discount + vat + freight),
    };
});

const totalPaid = computed(() => round2((form.value.cash_paid || 0) + (form.value.card_paid || 0)));
const change = computed(() => Math.max(0, totalPaid.value - totals.value.grand));
const due = computed(() => Math.max(0, totals.value.grand - totalPaid.value));

function round2(n) {
    return Math.round((n || 0) * 100) / 100;
}

function recalcLine(l) {
    const base = (l.quantity || 0) * (l.unit_price || 0);
    const vat = round2(base * ((l.tax_rate || 0) / 100));
    l._line_total = round2(base + vat);
}

function addLine() {
    form.value.items.push(makeLine());
}

function removeLine(idx) {
    form.value.items.splice(idx, 1);
}

function setCustomerType(kind) {
    form.value.customer_type = kind;
    if (kind === 'walkin') {
        form.value.customer_id = null;
    }
}

function onCustomerPick() {
    const c = customers.value.find(x => x.id === form.value.customer_id);
    if (c) {
        form.value.customer_name = c.name ?? '';
        form.value.customer_phone = c.phone ?? '';
        form.value.customer_address = c.address ?? '';
    }
}

function onProductPick(line, p) {
    if (!p) {
        line.product_id = null;
        line._product = null;
        line.unit_price = 0;
        line._serial_ids = [];
        line._serials = [];
        recalcLine(line);
        return;
    }
    line.product_id = p.id;
    line._product = p;
    const wholesale = form.value.sale_type === 'wholesale' && p.wholesale_price > 0;
    line.unit_price = parseFloat(wholesale ? p.wholesale_price : p.selling_price) || 0;
    // Phase Y — serialized products clear qty + open picker on next tick.
    line._serial_ids = [];
    line._serials    = [];
    // keep whatever VAT the user already chose on this line; do not override from product
    recalcLine(line);
    if (p.is_serialized) {
        line.quantity = 0;
        nextTick(() => openSerialPicker(line));
    }
}

// ── Phase Y Round 2C — serial picker integration ─────────────────────────
const authStore = useAuthStore();
const serialPickerOpen = ref(false);
const serialPickerLine = ref(null);

function isSerializedLine(line) {
    return !!line?._product?.is_serialized;
}

function openSerialPicker(line) {
    serialPickerLine.value = line;
    serialPickerOpen.value = true;
}

function onSerialsPicked(payload) {
    const line = serialPickerLine.value;
    if (line) {
        line._serial_ids = payload.ids;
        line._serials    = payload.serials;
        line.quantity    = payload.ids.length;
        recalcLine(line);
    }
    serialPickerOpen.value = false;
    serialPickerLine.value = null;
}

/**
 * Post-save attach — mirrors PosView::attachSaleSerialsAfterCreate.
 * Called after saleService.store(). Idempotent on retry thanks to the
 * Round 2B backend guard.
 */
async function attachSaleSerialsAfterCreate(savedSale) {
    if (!savedSale?.id) return;
    const lines = form.value.items.filter(l => isSerializedLine(l) && l._serial_ids?.length);
    if (!lines.length) return;
    const serverItems = savedSale.items ?? [];
    for (const line of lines) {
        const serverItem = serverItems.find(it => it.product_id === line.product_id);
        try {
            await serialService.attachToSale({
                product_id:    line.product_id,
                sale_id:       savedSale.id,
                sale_item_id:  serverItem?.id ?? null,
                customer_id:   form.value.customer_id || null,
                branch_id:     authStore.branchId ?? null,
                serial_ids:    line._serial_ids,
            });
        } catch (err) {
            console.warn('[serials] attach-to-sale (back office) failed', err);
            toast('error', t('serials.attach.saleFailed', { sku: line._product?.sku || '' }));
        }
    }
}

function autoFillCash() {
    // suggest the outstanding amount on first focus when nothing has been typed
    if (!form.value.cash_paid && !form.value.card_paid && totals.value.grand > 0) {
        form.value.cash_paid = totals.value.grand;
    }
}

async function loadCustomers() {
    try {
        if (navigator.onLine === false) {
            customers.value = await getAll('customers');
            return;
        }
        const { data } = await customerService.all();
        customers.value = data.data ?? [];
    } catch {
        customers.value = await getAll('customers').catch(() => []);
    }
}

async function save() {
    errors.value = {};
    serverError.value = '';

    if (!form.value.sale_date) {
        errors.value.sale_date = t('sales.dateRequired');
        return;
    }

    const valid = form.value.items.filter(l => l.product_id && l.quantity > 0);
    if (valid.length === 0) {
        errors.value.items = t('sales.itemsRequired');
        return;
    }

    if (due.value > 0) {
        const ok = await confirm({
            title: t('sales.partialPaymentTitle'),
            text: t('sales.partialPaymentMessage', { amount: fmtCurrency(due.value) }),
            confirmText: t('common.confirm'),
        });
        if (!ok) return;
    }

    saving.value = true;
    const payload = {
        sale_date: form.value.sale_date,
        customer_id: form.value.customer_type === 'registered' ? form.value.customer_id : null,
        customer_name: form.value.customer_name || null,
        customer_phone: form.value.customer_phone || null,
        customer_address: form.value.customer_address || null,
        customer_type: form.value.customer_type,
        sale_type: form.value.sale_type,
        discount_amount: form.value.discount_amount || 0,
        freight_amount: form.value.freight_amount || 0,
        cash_paid: form.value.cash_paid || 0,
        card_paid: form.value.card_paid || 0,
        note: form.value.note || null,
        items: valid.map(l => ({
            product_id: l.product_id,
            quantity: l.quantity,
            unit_price: l.unit_price,
            cost_price: l._product?.cost_price ?? 0,
            tax_rate: l.tax_rate ?? 0,
            is_service: false,
        })),
    };

    // ── Offline path: queue the sale locally and let the sync engine drain
    //    it once connectivity returns. Mirrors PosView.confirmSale().
    async function queueOffline() {
        const { createOfflineSale } = await import('@/offline/offlineSales');
        const { syncNow } = await import('@/offline/syncEngine');
        const row = await createOfflineSale(payload);
        toast('success', t('pos.saleSavedOffline', { ref: row.tempInvoiceNumber }));
        syncNow().catch(() => {});
        router.push({ name: 'sales' });
    }

    // Phase Y Round 2C — pre-save guards.
    // 1. Every serialized line must have at least one picked serial.
    // 2. Serialized products MUST be sold online — there's no way to
    //    arbitrate two offline terminals picking the same SN.
    const missingSerials = valid.filter(l => isSerializedLine(l) && (!l._serial_ids || l._serial_ids.length === 0));
    if (missingSerials.length) {
        const skus = missingSerials.map(l => l._product?.sku || l._product?.name).join(', ');
        serverError.value = t('serials.attach.missingForSale', { skus });
        saving.value = false;
        return;
    }
    const hasSerialized = valid.some(isSerializedLine);
    if (hasSerialized && typeof navigator !== 'undefined' && navigator.onLine === false) {
        serverError.value = t('serials.offline.blocked');
        toast('error', serverError.value);
        saving.value = false;
        return;
    }

    try {
        if (typeof navigator !== 'undefined' && navigator.onLine === false) {
            await queueOffline();
            return;
        }

        const { data } = await saleService.store(payload);
        const saved = data.data ?? data;
        const id    = saved.id;
        await attachSaleSerialsAfterCreate(saved);
        toast('success', t('common.createdSuccess'));
        const goToInvoice = await confirm({
            title: t('sales.printPromptTitle'),
            text: t('sales.printPromptText'),
            confirmText: t('sales.openInvoice'),
            cancelText: t('sales.skipInvoice'),
        });
        router.push(goToInvoice
            ? { name: 'sale-invoice', params: { id } }
            : { name: 'sales' });
    } catch (err) {
        // Network failure mid-flight (timeout, SW 503 envelope, dropped Wi-Fi)
        // — degrade to the offline queue so the cashier never loses a sale.
        const noResponse = !err.response;
        const swOffline  = err.response?.headers?.['x-posmeister-offline'] === '1';
        if (noResponse || swOffline) {
            try { await queueOffline(); return; } catch { /* fall through */ }
        }
        const data = err.response?.data;
        if (data?.errors) {
            Object.entries(data.errors).forEach(([k, v]) => {
                errors.value[k] = Array.isArray(v) ? v[0] : v;
            });
        } else {
            serverError.value = data?.message || t('common.unexpectedError');
        }
    } finally {
        saving.value = false;
    }
}

onMounted(loadCustomers);
</script>

<style scoped>
@reference '../../../css/app.css';

.card {
    @apply bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden;
}

.card-head {
    @apply flex items-center gap-2 px-5 py-3 border-b border-slate-100;
}

.card-head h2 {
    @apply text-sm font-semibold text-slate-700 uppercase tracking-wide;
}

.card-body {
    @apply p-5;
}

.lbl {
    @apply block text-xs font-medium text-slate-600 mb-1.5;
}

.ctrl {
    @apply w-full px-3 py-2 text-sm border border-slate-200 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent;
}

.ctrl-sm {
    @apply w-full px-2 py-1.5 text-xs border border-slate-200 rounded-md bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent;
}

.err {
    @apply text-xs text-rose-600 mt-1;
}

.th {
    @apply px-4 py-2.5 text-left text-[11px] font-semibold text-slate-500 uppercase tracking-wide;
}

.th-r {
    @apply px-4 py-2.5 text-right text-[11px] font-semibold text-slate-500 uppercase tracking-wide;
}

.td {
    @apply px-4 py-2;
}

.tab-btn {
    @apply px-3 py-1.5 text-xs font-semibold rounded-md transition-colors;
}

.tab-active {
    @apply bg-white text-emerald-700 shadow-sm;
}

.tab-idle {
    @apply text-slate-500 hover:text-slate-700;
}

.btn-emerald {
    @apply inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold disabled:opacity-50 disabled:cursor-not-allowed transition-colors;
}

.btn-soft {
    @apply inline-flex items-center px-4 py-2 rounded-lg border border-slate-300 text-slate-700 text-sm hover:bg-slate-50 transition-colors;
}

.btn-tiny {
    @apply inline-flex items-center gap-1 px-2.5 py-1 rounded-md bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold transition-colors;
}
</style>
