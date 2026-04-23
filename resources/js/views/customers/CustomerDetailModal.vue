<template>
    <Teleport to="body">
        <div class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4">
            <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="$emit('close')" />

            <div class="relative w-full sm:max-w-3xl bg-white sm:rounded-2xl shadow-2xl flex flex-col max-h-screen sm:max-h-[92vh]">

                <!-- ── Header ──────────────────────────────────────────────── -->
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 flex-shrink-0">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-indigo-600 flex items-center justify-center text-white font-bold text-base">
                            {{ customerInitial }}
                        </div>
                        <div v-if="!loading">
                            <h2 class="text-base font-semibold text-gray-900 leading-none">{{ customer?.name }}</h2>
                            <p class="text-xs text-gray-400 mt-0.5 font-mono">{{ customer?.code }}</p>
                        </div>
                        <div v-else class="space-y-1.5">
                            <div class="h-4 bg-gray-100 rounded animate-pulse w-32"></div>
                            <div class="h-3 bg-gray-100 rounded animate-pulse w-20"></div>
                        </div>
                    </div>
                    <button @click="$emit('close')" class="p-1.5 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-colors">
                        <XMarkIcon class="w-5 h-5" />
                    </button>
                </div>

                <!-- ── KPI strip ───────────────────────────────────────────── -->
                <div class="grid grid-cols-3 divide-x divide-gray-100 border-b border-gray-100 flex-shrink-0">
                    <div class="px-5 py-3 text-center">
                        <p class="text-xs text-gray-400 mb-0.5">{{ t('customers.totalSales') }}</p>
                        <p v-if="loading" class="h-5 bg-gray-100 rounded animate-pulse w-20 mx-auto"></p>
                        <p v-else class="text-sm font-bold text-gray-900">{{ formatCurrency(customer?.total_sales_amount ?? 0) }}</p>
                    </div>
                    <div class="px-5 py-3 text-center">
                        <p class="text-xs text-gray-400 mb-0.5">{{ t('customers.totalDue') }}</p>
                        <p v-if="loading" class="h-5 bg-gray-100 rounded animate-pulse w-20 mx-auto"></p>
                        <p v-else :class="['text-sm font-bold', currentDue > 0 ? 'text-amber-600' : 'text-emerald-600']">
                            {{ currentDue > 0 ? formatCurrency(currentDue) : '—' }}
                        </p>
                    </div>
                    <div class="px-5 py-3 text-center">
                        <p class="text-xs text-gray-400 mb-0.5">{{ t('customers.phone') }}</p>
                        <p v-if="loading" class="h-5 bg-gray-100 rounded animate-pulse w-20 mx-auto"></p>
                        <p v-else class="text-sm font-semibold text-gray-700">{{ customer?.phone || '—' }}</p>
                    </div>
                </div>

                <!-- ── Tab bar ─────────────────────────────────────────────── -->
                <div class="flex border-b border-gray-100 flex-shrink-0">
                    <button
                        v-for="tab in tabs"
                        :key="tab.key"
                        @click="activeTab = tab.key"
                        :class="['px-5 py-3 text-sm font-medium transition-colors border-b-2 -mb-px',
                            activeTab === tab.key
                                ? 'text-indigo-600 border-indigo-600'
                                : 'text-gray-500 border-transparent hover:text-gray-700']"
                    >
                        {{ tab.label }}
                    </button>
                </div>

                <!-- ── Body ───────────────────────────────────────────────── -->
                <div class="flex-1 overflow-y-auto">

                    <!-- Tab: Recent Sales -->
                    <div v-if="activeTab === 'sales'" class="p-5">
                        <div v-if="ledgerLoading" class="space-y-3 animate-pulse">
                            <div v-for="i in 4" :key="i" class="h-10 bg-gray-100 rounded-lg"></div>
                        </div>
                        <div v-else-if="recentSales.length" class="divide-y divide-gray-50 rounded-xl border border-gray-100 overflow-hidden">
                            <div
                                v-for="sale in recentSales"
                                :key="sale.id"
                                class="flex items-center justify-between px-4 py-3 hover:bg-gray-50"
                            >
                                <div>
                                    <p class="text-sm font-semibold text-gray-900 font-mono">{{ sale.sale_number }}</p>
                                    <p class="text-xs text-gray-400">{{ sale.sale_date }}</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm font-semibold text-gray-900">{{ formatCurrency(sale.grand_total) }}</p>
                                    <p v-if="parseFloat(sale.due_amount) > 0" class="text-xs text-amber-600 font-medium">
                                        {{ t('customers.saleDue') }}: {{ formatCurrency(sale.due_amount) }}
                                    </p>
                                    <p v-else class="text-xs text-emerald-600">{{ t('dashboard.recentSales.paid') }}</p>
                                </div>
                            </div>
                        </div>
                        <div v-else class="py-12 text-center text-sm text-gray-400">{{ t('customers.noSales') }}</div>
                    </div>

                    <!-- Tab: Payments -->
                    <div v-if="activeTab === 'payments'" class="p-5">
                        <div v-if="ledgerLoading" class="space-y-3 animate-pulse">
                            <div v-for="i in 3" :key="i" class="h-10 bg-gray-100 rounded-lg"></div>
                        </div>
                        <div v-else-if="payments.length" class="divide-y divide-gray-50 rounded-xl border border-gray-100 overflow-hidden mb-4">
                            <div
                                v-for="payment in payments"
                                :key="payment.id"
                                class="flex items-center justify-between px-4 py-3"
                            >
                                <div>
                                    <p class="text-sm font-semibold text-gray-900">{{ formatCurrency(payment.amount) }}</p>
                                    <p class="text-xs text-gray-400 capitalize">
                                        {{ methodLabel(payment.payment_method) }} · {{ payment.payment_date }}
                                        <span v-if="payment.reference" class="ml-1 font-mono">{{ payment.reference }}</span>
                                    </p>
                                </div>
                                <CheckCircleIcon class="w-5 h-5 text-emerald-500 flex-shrink-0" />
                            </div>
                        </div>
                        <div v-else-if="!ledgerLoading" class="py-8 text-center text-sm text-gray-400 mb-4">{{ t('customers.noPayments') }}</div>
                    </div>

                    <!-- Tab: Record Payment -->
                    <div v-if="activeTab === 'record'" class="p-5">
                        <!-- Due summary -->
                        <div v-if="currentDue > 0" class="mb-5 rounded-xl bg-amber-50 border border-amber-200 px-4 py-3 flex items-center justify-between">
                            <div>
                                <p class="text-sm font-semibold text-amber-800">{{ t('customers.totalDue') }}</p>
                                <p class="text-xs text-amber-600">{{ t('customers.ledgerSubtitle') }}</p>
                            </div>
                            <p class="text-xl font-bold text-amber-700">{{ formatCurrency(currentDue) }}</p>
                        </div>
                        <div v-else class="mb-5 rounded-xl bg-emerald-50 border border-emerald-200 px-4 py-3 text-center text-sm text-emerald-700 font-medium">
                            {{ t('customers.customerNoDue', 'No outstanding dues.') }}
                        </div>

                        <form @submit.prevent="submitPayment" class="space-y-4">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="form-label">{{ t('customers.paymentAmount') }} <span class="text-red-500">*</span></label>
                                    <input v-model.number="payForm.amount" type="number" min="0.01" step="0.01"
                                        class="form-input" :class="{ 'border-red-300': payErrors.amount }" />
                                    <p v-if="payErrors.amount" class="form-error">{{ payErrors.amount[0] }}</p>
                                </div>
                                <div>
                                    <label class="form-label">{{ t('customers.paymentDate') }} <span class="text-red-500">*</span></label>
                                    <input v-model="payForm.payment_date" type="date"
                                        class="form-input" :class="{ 'border-red-300': payErrors.payment_date }" />
                                    <p v-if="payErrors.payment_date" class="form-error">{{ payErrors.payment_date[0] }}</p>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="form-label">{{ t('customers.paymentMethod') }}</label>
                                    <select v-model="payForm.payment_method" class="form-input">
                                        <option value="cash">{{ t('customers.methodCash') }}</option>
                                        <option value="card">{{ t('customers.methodCard') }}</option>
                                        <option value="bank_transfer">{{ t('customers.methodBank') }}</option>
                                        <option value="other">{{ t('customers.methodOther') }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="form-label">{{ t('customers.paymentRef') }}</label>
                                    <input v-model="payForm.reference" type="text" class="form-input" />
                                </div>
                            </div>

                            <div>
                                <label class="form-label">{{ t('customers.paymentNote') }}</label>
                                <textarea v-model="payForm.note" rows="2" class="form-input resize-none" />
                            </div>

                            <div v-if="payApiError" class="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
                                {{ payApiError }}
                            </div>

                            <button type="submit" :disabled="paying"
                                class="w-full flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 disabled:opacity-50 transition-colors">
                                <span v-if="paying" class="w-4 h-4 border-2 border-white/40 border-t-white rounded-full animate-spin" />
                                <BanknotesIcon v-else class="w-4 h-4" />
                                {{ t('customers.recordPayment') }}
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useSettingsStore } from '@/stores/settings';
import { useAlert } from '@/composables/useAlert';
import { customerService } from '@/services/customerService';
import { XMarkIcon, BanknotesIcon, CheckCircleIcon } from '@heroicons/vue/24/outline';

const props  = defineProps({ customerId: { type: Number, required: true } });
const emit   = defineEmits(['close']);
const { t }  = useI18n();
const { toast } = useAlert();
const settingsStore = useSettingsStore();

function formatCurrency(value) {
    const code = settingsStore.settings?.currency_code ?? 'EUR';
    return new Intl.NumberFormat('de-DE', { style: 'currency', currency: code }).format(value ?? 0);
}

// ── State ──────────────────────────────────────────────────────────────────
const loading      = ref(true);
const ledgerLoading = ref(true);
const customer     = ref(null);
const currentDue   = ref(0);
const recentSales  = ref([]);
const payments     = ref([]);
const activeTab    = ref('sales');

const tabs = computed(() => [
    { key: 'sales',   label: t('customers.recentSales') },
    { key: 'payments', label: t('customers.payments') },
    { key: 'record',  label: t('customers.recordPayment') },
]);

const customerInitial = computed(() => {
    const name = customer.value?.name ?? '?';
    return name.charAt(0).toUpperCase();
});

// ── Load customer detail ───────────────────────────────────────────────────
async function loadCustomer() {
    loading.value = true;
    try {
        const { data } = await customerService.show(props.customerId);
        customer.value = data.data;
        currentDue.value = data.data.current_due ?? 0;
    } catch { /**/ } finally {
        loading.value = false;
    }
}

// ── Load ledger (sales + payments) ────────────────────────────────────────
async function loadLedger() {
    ledgerLoading.value = true;
    try {
        const { data } = await customerService.getPayments(props.customerId);
        recentSales.value = data.recent_sales ?? [];
        payments.value    = data.payments ?? [];
        currentDue.value  = data.current_due ?? 0;
    } catch { /**/ } finally {
        ledgerLoading.value = false;
    }
}

onMounted(() => {
    loadCustomer();
    loadLedger();
});

// ── Payment form ───────────────────────────────────────────────────────────
const defaultPayForm = () => ({
    amount: '',
    payment_method: 'cash',
    payment_date: new Date().toISOString().slice(0, 10),
    reference: '',
    note: '',
});

const payForm     = ref(defaultPayForm());
const payErrors   = ref({});
const payApiError = ref('');
const paying      = ref(false);

async function submitPayment() {
    payErrors.value   = {};
    payApiError.value = '';
    paying.value      = true;
    try {
        await customerService.storePayment(props.customerId, payForm.value);
        toast('success', t('customers.paymentSuccess'));
        payForm.value = defaultPayForm();
        await loadLedger();
        activeTab.value = 'payments';
    } catch (err) {
        if (err.response?.status === 422) {
            payErrors.value = err.response.data.errors ?? {};
        } else {
            payApiError.value = err.response?.data?.message ?? t('common.unexpectedError');
        }
    } finally {
        paying.value = false;
    }
}

// ── Method label helper ────────────────────────────────────────────────────
function methodLabel(method) {
    const map = {
        cash:          t('customers.methodCash'),
        card:          t('customers.methodCard'),
        bank_transfer: t('customers.methodBank'),
        other:         t('customers.methodOther'),
    };
    return map[method] ?? method;
}
</script>

<style scoped>
@reference '../../../../css/app.css';
.form-label { @apply block text-sm font-medium text-gray-700 mb-1; }
.form-input  { @apply w-full px-3 py-2 text-sm border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors; }
.form-error  { @apply mt-1 text-xs text-red-600; }
</style>
