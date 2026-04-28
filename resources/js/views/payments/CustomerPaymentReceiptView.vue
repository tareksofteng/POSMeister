<template>
    <div class="min-h-screen bg-slate-100 flex items-start justify-center py-8 print:bg-white print:py-0">
        <div class="w-full max-w-md bg-white shadow-lg rounded-xl print:shadow-none print:rounded-none print:max-w-none" id="receipt">

            <!-- Loading -->
            <div v-if="loading" class="p-12 text-center text-slate-400">{{ t('common.loading') }}…</div>

            <template v-else-if="payment">
                <!-- Toolbar -->
                <div class="flex items-center justify-between px-6 py-3 bg-emerald-600 rounded-t-xl print:hidden">
                    <button @click="$router.back()" class="text-white/80 hover:text-white text-sm flex items-center gap-1.5 transition-colors">
                        <ArrowLeftIcon class="w-4 h-4" />
                        {{ t('customerPayments.backToList') }}
                    </button>
                    <button @click="printReceipt" class="flex items-center gap-1.5 text-sm text-white/80 hover:text-white transition-colors">
                        <PrinterIcon class="w-4 h-4" />
                        {{ t('customerPayments.printReceipt') }}
                    </button>
                </div>

                <!-- Receipt body -->
                <div class="px-8 py-6">

                    <!-- Header: company -->
                    <div class="text-center mb-6">
                        <div v-if="settingsStore.settings?.logo_url" class="flex justify-center mb-3">
                            <img :src="settingsStore.settings.logo_url" alt="logo" class="h-12 object-contain" />
                        </div>
                        <h1 class="text-lg font-bold text-slate-800">{{ settingsStore.settings?.company_name ?? 'POSmeister' }}</h1>
                        <p v-if="settingsStore.settings?.address" class="text-xs text-slate-500 mt-1">{{ settingsStore.settings.address }}</p>
                        <p v-if="settingsStore.settings?.phone" class="text-xs text-slate-500">{{ settingsStore.settings.phone }}</p>
                    </div>

                    <!-- Divider -->
                    <div class="border-t-2 border-dashed border-slate-300 my-4"></div>

                    <!-- Receipt title -->
                    <div class="text-center mb-5">
                        <span class="inline-block px-4 py-1.5 bg-emerald-100 text-emerald-700 text-sm font-bold rounded-full tracking-wide uppercase">
                            {{ t('customerPayments.receiptTitle') }}
                        </span>
                        <p class="text-xs text-slate-400 mt-1">{{ t('customerPayments.receiptSubtitle') }}</p>
                    </div>

                    <!-- Meta rows -->
                    <div class="space-y-2 mb-5">
                        <div class="flex justify-between text-sm">
                            <span class="text-slate-500">{{ t('customerPayments.receiptNo') }}</span>
                            <span class="font-semibold text-slate-800">ZLG-{{ String(payment.id).padStart(6, '0') }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-slate-500">{{ t('customerPayments.date') }}</span>
                            <span class="font-medium text-slate-800">{{ fmtDate(payment.payment_date) }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-slate-500">{{ t('customerPayments.customer') }}</span>
                            <span class="font-medium text-slate-800">{{ payment.customer?.name ?? '—' }}</span>
                        </div>
                        <div v-if="payment.reference" class="flex justify-between text-sm">
                            <span class="text-slate-500">{{ t('customerPayments.reference') }}</span>
                            <span class="text-slate-600">{{ payment.reference }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-slate-500">{{ t('customerPayments.method') }}</span>
                            <span class="text-slate-700 font-medium">{{ methodLabel(payment.payment_method) }}</span>
                        </div>
                    </div>

                    <!-- Divider -->
                    <div class="border-t-2 border-dashed border-slate-300 my-4"></div>

                    <!-- Amount -->
                    <div class="text-center my-6">
                        <p class="text-xs text-slate-400 uppercase tracking-wider mb-1">{{ t('customerPayments.amountReceived') }}</p>
                        <p class="text-3xl font-bold text-emerald-700">{{ fmtCurrency(payment.amount) }}</p>
                        <p class="text-xs text-slate-500 mt-2 italic">{{ t('customerPayments.inWords') }}: {{ toWordsDE(payment.amount) }}</p>
                    </div>

                    <!-- Divider -->
                    <div class="border-t-2 border-dashed border-slate-300 my-4"></div>

                    <!-- Note -->
                    <div v-if="payment.note" class="mb-5 bg-slate-50 rounded-lg p-3">
                        <p class="text-xs text-slate-500 font-medium mb-1">{{ t('customerPayments.note') }}</p>
                        <p class="text-sm text-slate-700">{{ payment.note }}</p>
                    </div>

                    <!-- Signature -->
                    <div class="grid grid-cols-2 gap-6 mt-8">
                        <div class="text-center">
                            <div class="border-b border-slate-400 h-8 mb-1"></div>
                            <p class="text-xs text-slate-500">{{ t('customerPayments.customerSignature') }}</p>
                        </div>
                        <div class="text-center">
                            <div class="border-b border-slate-400 h-8 mb-1"></div>
                            <p class="text-xs text-slate-500">{{ t('customerPayments.authorizedSignature') }}</p>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="text-center mt-6">
                        <p class="text-xs text-slate-400">{{ t('customerPayments.printDate') }}: {{ printDate }}</p>
                        <p class="text-xs text-emerald-600 mt-1 font-medium">{{ t('customerPayments.thankYou') }}</p>
                    </div>

                </div>
            </template>

            <div v-else class="p-12 text-center text-slate-400">{{ t('common.noResults') }}</div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { ArrowLeftIcon, PrinterIcon } from '@heroicons/vue/24/outline';
import { useSettingsStore } from '@/stores/settings';
import { customerService } from '@/services/customerService';

const { t }         = useI18n();
const route         = useRoute();
const settingsStore = useSettingsStore();
const payment       = ref(null);
const loading       = ref(true);

const printDate = new Date().toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' });

const METHODS = {
    cash:          'Bar',
    bank_transfer: 'Überweisung',
    card:          'Karte',
    other:         'Sonstiges',
};
function methodLabel(m) { return METHODS[m] ?? m ?? '—'; }

function fmtCurrency(val) {
    return new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR' }).format(val ?? 0);
}
function fmtDate(val) {
    if (!val) return '—';
    return new Date(val).toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

function toWordsDE(amount) {
    const n = Math.floor(amount ?? 0);
    const cents = Math.round(((amount ?? 0) - n) * 100);
    const ones = ['', 'ein', 'zwei', 'drei', 'vier', 'fünf', 'sechs', 'sieben', 'acht', 'neun',
                  'zehn', 'elf', 'zwölf', 'dreizehn', 'vierzehn', 'fünfzehn', 'sechzehn',
                  'siebzehn', 'achtzehn', 'neunzehn'];
    const tens = ['', '', 'zwanzig', 'dreißig', 'vierzig', 'fünfzig', 'sechzig', 'siebzig', 'achtzig', 'neunzig'];
    function below100(n) {
        if (n < 20) return ones[n];
        const t = tens[Math.floor(n / 10)];
        const o = ones[n % 10];
        return o ? `${o}und${t}` : t;
    }
    function below1000(n) {
        if (n < 100) return below100(n);
        return `${ones[Math.floor(n / 100)]}hundert${below100(n % 100)}`;
    }
    function convert(n) {
        if (n === 0) return 'null';
        if (n < 1000) return below1000(n);
        if (n < 1000000) return `${below1000(Math.floor(n / 1000))}tausend${below1000(n % 1000)}`;
        return `${below1000(Math.floor(n / 1000000))} Millionen ${convert(n % 1000000)}`;
    }
    const euroWord  = convert(n);
    const centsWord = cents > 0 ? ` und ${below100(cents)} Cent` : '';
    return `${euroWord.charAt(0).toUpperCase()}${euroWord.slice(1)} Euro${centsWord}`;
}

function printReceipt() { window.print(); }

onMounted(async () => {
    try {
        const { data } = await customerService.showPayment(route.params.id);
        payment.value = data.data ?? data;
    } finally {
        loading.value = false;
    }
});
</script>

<style>
@media print {
    @page { size: A6 portrait; margin: 8mm; }
    body  { background: white !important; }
}
</style>
