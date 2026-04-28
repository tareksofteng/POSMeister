<template>
    <div class="min-h-screen bg-slate-50">

        <!-- Page Header -->
        <div class="bg-white border-b border-slate-200 px-6 py-4">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-bold text-slate-800">{{ t('customerPayments.title') }}</h1>
                    <p class="text-sm text-slate-500 mt-0.5">{{ t('customerPayments.subtitle') }}</p>
                </div>
                <button
                    @click="printPage"
                    class="flex items-center gap-2 px-4 py-2 text-sm font-medium text-slate-600 bg-white border border-slate-300 rounded-lg hover:bg-slate-50 transition-colors"
                >
                    <PrinterIcon class="w-4 h-4" />
                    {{ t('customerPayments.print') }}
                </button>
            </div>
        </div>

        <!-- KPI Cards -->
        <div class="px-6 py-4 grid grid-cols-3 gap-4">
            <div class="bg-white rounded-xl border border-slate-200 p-4 flex items-center gap-4">
                <div class="w-10 h-10 rounded-lg bg-emerald-100 flex items-center justify-center flex-shrink-0">
                    <BanknotesIcon class="w-5 h-5 text-emerald-600" />
                </div>
                <div>
                    <p class="text-xs text-slate-500 font-medium">{{ t('customerPayments.kpiTotal') }}</p>
                    <p class="text-lg font-bold text-slate-800">{{ fmtCurrency(summary.total_amount) }}</p>
                    <p class="text-xs text-slate-400">{{ summary.count }} {{ t('customerPayments.transactions') }}</p>
                </div>
            </div>
            <div class="bg-white rounded-xl border border-slate-200 p-4 flex items-center gap-4">
                <div class="w-10 h-10 rounded-lg bg-amber-100 flex items-center justify-center flex-shrink-0">
                    <ExclamationCircleIcon class="w-5 h-5 text-amber-600" />
                </div>
                <div>
                    <p class="text-xs text-slate-500 font-medium">{{ t('customerPayments.kpiOutstanding') }}</p>
                    <p class="text-lg font-bold text-slate-800">{{ fmtCurrency(selectedCustomerDue) }}</p>
                    <p class="text-xs text-slate-400">{{ selectedCustomer ? selectedCustomer.name : t('customerPayments.allCustomers') }}</p>
                </div>
            </div>
            <div class="bg-white rounded-xl border border-slate-200 p-4 flex items-center gap-4">
                <div class="w-10 h-10 rounded-lg bg-indigo-100 flex items-center justify-center flex-shrink-0">
                    <CalendarIcon class="w-5 h-5 text-indigo-600" />
                </div>
                <div>
                    <p class="text-xs text-slate-500 font-medium">{{ t('customerPayments.kpiToday') }}</p>
                    <p class="text-lg font-bold text-slate-800">{{ fmtCurrency(todayTotal) }}</p>
                    <p class="text-xs text-slate-400">{{ todayDate }}</p>
                </div>
            </div>
        </div>

        <!-- Main content: form + table -->
        <div class="px-6 pb-6 grid grid-cols-3 gap-5">

            <!-- ── Payment Entry Form ───────────────────────────────────────── -->
            <div class="col-span-1">
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm sticky top-4">
                    <div class="px-5 py-4 border-b border-slate-100">
                        <h2 class="font-semibold text-slate-800 text-sm">{{ t('customerPayments.formTitle') }}</h2>
                        <p class="text-xs text-slate-400 mt-0.5">{{ t('customerPayments.formSubtitle') }}</p>
                    </div>
                    <form @submit.prevent="submitPayment" class="p-5 space-y-4">

                        <!-- Customer select -->
                        <div>
                            <label class="form-label">{{ t('customerPayments.customer') }} <span class="text-red-400">*</span></label>
                            <select v-model="form.customer_id" @change="onCustomerChange" class="form-select" required>
                                <option value="">{{ t('customerPayments.selectCustomer') }}</option>
                                <option v-for="c in customers" :key="c.id" :value="c.id">
                                    {{ c.name }} <template v-if="c.code">({{ c.code }})</template>
                                </option>
                            </select>
                        </div>

                        <!-- Due amount display -->
                        <div v-if="form.customer_id && !customerLoading" class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-medium text-amber-700">{{ t('customerPayments.currentDue') }}</span>
                                <span class="text-base font-bold" :class="selectedCustomerDue > 0 ? 'text-red-600' : 'text-emerald-600'">
                                    {{ fmtCurrency(selectedCustomerDue) }}
                                </span>
                            </div>
                        </div>
                        <div v-if="customerLoading" class="text-xs text-slate-400 text-center py-2">
                            {{ t('common.loading') }}…
                        </div>

                        <!-- Amount -->
                        <div>
                            <label class="form-label">{{ t('customerPayments.amount') }} <span class="text-red-400">*</span></label>
                            <div class="relative">
                                <input
                                    v-model.number="form.amount"
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    class="form-input pr-14"
                                    :placeholder="t('customerPayments.amountPlaceholder')"
                                    required
                                />
                                <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs font-semibold text-slate-400">EUR</span>
                            </div>
                        </div>

                        <!-- Payment method -->
                        <div>
                            <label class="form-label">{{ t('customerPayments.method') }}</label>
                            <div class="grid grid-cols-2 gap-2">
                                <button
                                    v-for="m in paymentMethods"
                                    :key="m.value"
                                    type="button"
                                    @click="form.payment_method = m.value"
                                    :class="[
                                        'flex items-center gap-2 px-3 py-2 rounded-lg border text-xs font-medium transition-colors',
                                        form.payment_method === m.value
                                            ? 'border-indigo-500 bg-indigo-50 text-indigo-700'
                                            : 'border-slate-200 text-slate-600 hover:border-slate-300',
                                    ]"
                                >
                                    <component :is="m.icon" class="w-4 h-4 flex-shrink-0" />
                                    {{ m.label }}
                                </button>
                            </div>
                        </div>

                        <!-- Payment date -->
                        <div>
                            <label class="form-label">{{ t('customerPayments.date') }} <span class="text-red-400">*</span></label>
                            <input v-model="form.payment_date" type="date" class="form-input" required />
                        </div>

                        <!-- Reference -->
                        <div>
                            <label class="form-label">{{ t('customerPayments.reference') }}</label>
                            <input v-model="form.reference" type="text" class="form-input" :placeholder="t('customerPayments.referencePlaceholder')" maxlength="100" />
                        </div>

                        <!-- Note -->
                        <div>
                            <label class="form-label">{{ t('customerPayments.note') }}</label>
                            <textarea v-model="form.note" rows="2" class="form-input resize-none" :placeholder="t('customerPayments.notePlaceholder')"></textarea>
                        </div>

                        <!-- Error -->
                        <p v-if="formError" class="text-xs text-red-600 bg-red-50 border border-red-200 rounded-lg px-3 py-2">{{ formError }}</p>

                        <!-- Submit -->
                        <button
                            type="submit"
                            :disabled="saving"
                            class="w-full py-2.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 disabled:opacity-60 text-white text-sm font-semibold transition-colors flex items-center justify-center gap-2"
                        >
                            <ArrowDownTrayIcon v-if="!saving" class="w-4 h-4" />
                            <span class="animate-spin border-2 border-white border-t-transparent rounded-full w-4 h-4" v-else></span>
                            {{ saving ? t('common.saving') : t('customerPayments.savePayment') }}
                        </button>

                        <!-- Success flash -->
                        <div v-if="successFlash" class="flex items-center gap-2 text-xs text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-lg px-3 py-2">
                            <CheckCircleIcon class="w-4 h-4 flex-shrink-0" />
                            {{ t('customerPayments.savedSuccess') }}
                        </div>

                    </form>
                </div>
            </div>

            <!-- ── Payments List ────────────────────────────────────────────── -->
            <div class="col-span-2">
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm">

                    <!-- Filter bar -->
                    <div class="px-5 py-4 border-b border-slate-100 flex flex-wrap gap-3 items-end">
                        <div class="flex-1 min-w-36">
                            <label class="text-xs font-medium text-slate-500 block mb-1">{{ t('customerPayments.from') }}</label>
                            <input v-model="filters.date_from" type="date" class="form-input text-sm py-1.5" />
                        </div>
                        <div class="flex-1 min-w-36">
                            <label class="text-xs font-medium text-slate-500 block mb-1">{{ t('customerPayments.to') }}</label>
                            <input v-model="filters.date_to" type="date" class="form-input text-sm py-1.5" />
                        </div>
                        <div class="flex-1 min-w-40">
                            <label class="text-xs font-medium text-slate-500 block mb-1">{{ t('customerPayments.customer') }}</label>
                            <select v-model="filters.customer_id" class="form-select text-sm py-1.5">
                                <option value="">{{ t('customerPayments.allCustomers') }}</option>
                                <option v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                        </div>
                        <div class="flex-1 min-w-36">
                            <label class="text-xs font-medium text-slate-500 block mb-1">{{ t('customerPayments.method') }}</label>
                            <select v-model="filters.payment_method" class="form-select text-sm py-1.5">
                                <option value="">{{ t('customerPayments.allMethods') }}</option>
                                <option v-for="m in paymentMethods" :key="m.value" :value="m.value">{{ m.label }}</option>
                            </select>
                        </div>
                        <button
                            @click="loadPayments"
                            class="px-4 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition-colors"
                        >
                            {{ t('customerPayments.search') }}
                        </button>
                    </div>

                    <!-- Table -->
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm" id="payment-table">
                            <thead>
                                <tr class="border-b border-slate-100 bg-slate-50">
                                    <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">#</th>
                                    <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">{{ t('customerPayments.date') }}</th>
                                    <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">{{ t('customerPayments.customer') }}</th>
                                    <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">{{ t('customerPayments.method') }}</th>
                                    <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">{{ t('customerPayments.reference') }}</th>
                                    <th class="text-right px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">{{ t('customerPayments.amount') }}</th>
                                    <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide no-print">{{ t('common.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template v-if="loading">
                                    <tr>
                                        <td colspan="7" class="text-center py-12 text-slate-400 text-sm">{{ t('common.loading') }}…</td>
                                    </tr>
                                </template>
                                <template v-else-if="payments.length === 0">
                                    <tr>
                                        <td colspan="7" class="text-center py-12 text-slate-400 text-sm">{{ t('customerPayments.noResults') }}</td>
                                    </tr>
                                </template>
                                <template v-else>
                                    <tr
                                        v-for="(p, i) in payments"
                                        :key="p.id"
                                        class="border-b border-slate-50 hover:bg-slate-50/60 transition-colors"
                                    >
                                        <td class="px-4 py-3 text-slate-400 text-xs">{{ i + 1 }}</td>
                                        <td class="px-4 py-3 text-slate-700 whitespace-nowrap">{{ fmtDate(p.payment_date) }}</td>
                                        <td class="px-4 py-3">
                                            <span class="font-medium text-slate-800">{{ p.customer?.name ?? '—' }}</span>
                                            <span v-if="p.customer?.code" class="text-xs text-slate-400 ml-1">({{ p.customer.code }})</span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <span :class="methodBadge(p.payment_method)">
                                                {{ methodLabel(p.payment_method) }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-slate-500 text-xs">{{ p.reference || '—' }}</td>
                                        <td class="px-4 py-3 text-right font-semibold text-emerald-700">{{ fmtCurrency(p.amount) }}</td>
                                        <td class="px-4 py-3 no-print">
                                            <RouterLink
                                                :to="{ name: 'customer-payment-receipt', params: { id: p.id } }"
                                                class="inline-flex items-center gap-1 text-xs text-indigo-600 hover:text-indigo-800 font-medium"
                                                target="_blank"
                                            >
                                                <DocumentTextIcon class="w-3.5 h-3.5" />
                                                {{ t('customerPayments.receipt') }}
                                            </RouterLink>
                                        </td>
                                    </tr>

                                    <!-- Totals row -->
                                    <tr class="bg-slate-800 text-white font-semibold">
                                        <td colspan="5" class="px-4 py-3 text-sm">{{ t('customerPayments.grandTotal') }}</td>
                                        <td class="px-4 py-3 text-right text-emerald-300">{{ fmtCurrency(summary.total_amount) }}</td>
                                        <td class="no-print"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { RouterLink } from 'vue-router';
import { useI18n } from 'vue-i18n';
import {
    BanknotesIcon, ExclamationCircleIcon, CalendarIcon,
    PrinterIcon, ArrowDownTrayIcon, CheckCircleIcon,
    DocumentTextIcon, CreditCardIcon, BuildingLibraryIcon,
    EllipsisHorizontalCircleIcon,
} from '@heroicons/vue/24/outline';
import { customerService } from '@/services/customerService';

const { t } = useI18n();

// ── Payment method options ──────────────────────────────────────────────────
const paymentMethods = computed(() => [
    { value: 'cash',          label: t('customerPayments.methodCash'),  icon: BanknotesIcon },
    { value: 'bank_transfer', label: t('customerPayments.methodBank'),  icon: BuildingLibraryIcon },
    { value: 'card',          label: t('customerPayments.methodCard'),  icon: CreditCardIcon },
    { value: 'other',         label: t('customerPayments.methodOther'), icon: EllipsisHorizontalCircleIcon },
]);

// ── State ───────────────────────────────────────────────────────────────────
const customers    = ref([]);
const payments     = ref([]);
const summary      = ref({ count: 0, total_amount: 0 });
const loading      = ref(false);
const saving       = ref(false);
const formError    = ref('');
const successFlash = ref(false);

const customerLoading      = ref(false);
const selectedCustomerDue  = ref(0);
const selectedCustomer     = ref(null);

const todayDate  = new Date().toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' });
const todayISO   = new Date().toISOString().split('T')[0];
const todayTotal = computed(() => {
    return payments.value
        .filter(p => p.payment_date && p.payment_date.startsWith(todayISO))
        .reduce((s, p) => s + parseFloat(p.amount ?? 0), 0);
});

const filters = ref({
    date_from:      '',
    date_to:        '',
    customer_id:    '',
    payment_method: '',
});

const form = ref({
    customer_id:    '',
    amount:         '',
    payment_method: 'cash',
    payment_date:   todayISO,
    reference:      '',
    note:           '',
});

// ── Helpers ─────────────────────────────────────────────────────────────────
function fmtCurrency(val) {
    return new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR' }).format(val ?? 0);
}
function fmtDate(val) {
    if (!val) return '—';
    const d = new Date(val);
    return d.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' });
}
function methodLabel(method) {
    const found = paymentMethods.value.find(m => m.value === method);
    return found ? found.label : method ?? '—';
}
function methodBadge(method) {
    const base = 'inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium';
    const map = {
        cash:          `${base} bg-emerald-100 text-emerald-700`,
        bank_transfer: `${base} bg-blue-100 text-blue-700`,
        card:          `${base} bg-purple-100 text-purple-700`,
        other:         `${base} bg-slate-100 text-slate-600`,
    };
    return map[method] ?? `${base} bg-slate-100 text-slate-600`;
}

// ── Data loading ─────────────────────────────────────────────────────────────
async function loadCustomers() {
    const { data } = await customerService.all();
    customers.value = data.data ?? [];
}

async function loadPayments() {
    loading.value = true;
    try {
        const params = {};
        if (filters.value.date_from)      params.date_from      = filters.value.date_from;
        if (filters.value.date_to)        params.date_to        = filters.value.date_to;
        if (filters.value.customer_id)    params.customer_id    = filters.value.customer_id;
        if (filters.value.payment_method) params.payment_method = filters.value.payment_method;

        const { data } = await customerService.payments(params);
        payments.value = data.data ?? [];
        summary.value  = data.summary ?? { count: 0, total_amount: 0 };
    } finally {
        loading.value = false;
    }
}

async function onCustomerChange() {
    selectedCustomer.value = customers.value.find(c => c.id == form.value.customer_id) ?? null;
    if (!form.value.customer_id) {
        selectedCustomerDue.value = 0;
        return;
    }
    customerLoading.value = true;
    try {
        const { data } = await customerService.show(form.value.customer_id);
        selectedCustomerDue.value = data.data?.current_due ?? 0;
    } catch {
        selectedCustomerDue.value = 0;
    } finally {
        customerLoading.value = false;
    }
}

// ── Form submit ──────────────────────────────────────────────────────────────
async function submitPayment() {
    formError.value = '';
    saving.value    = true;
    try {
        await customerService.createPayment({
            customer_id:    form.value.customer_id,
            amount:         form.value.amount,
            payment_method: form.value.payment_method,
            payment_date:   form.value.payment_date,
            reference:      form.value.reference || null,
            note:           form.value.note || null,
        });

        // Refresh due & list
        await onCustomerChange();
        await loadPayments();

        // Reset form (keep customer & date)
        const keepCustomer = form.value.customer_id;
        form.value = {
            customer_id:    keepCustomer,
            amount:         '',
            payment_method: 'cash',
            payment_date:   todayISO,
            reference:      '',
            note:           '',
        };

        successFlash.value = true;
        setTimeout(() => { successFlash.value = false; }, 3000);
    } catch (err) {
        formError.value = err?.response?.data?.message ?? t('common.unexpectedError');
    } finally {
        saving.value = false;
    }
}

function printPage() {
    window.print();
}

onMounted(async () => {
    await loadCustomers();
    await loadPayments();
});
</script>

<style scoped>
@reference '../../../css/app.css';

.form-label  { @apply block text-xs font-semibold text-slate-600 mb-1; }
.form-input  { @apply w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:border-indigo-400 transition; }
.form-select { @apply w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:border-indigo-400 transition; }

@media print {
    .no-print { display: none !important; }
    body       { background: white !important; }
}
</style>
