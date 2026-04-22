<template>
    <div class="min-h-screen bg-slate-100 print:bg-white">

        <!-- ── Action bar (hidden when printing) ────────────────────────── -->
        <div class="no-print sticky top-0 z-20 bg-white border-b border-gray-200 shadow-sm">
            <div class="max-w-4xl mx-auto px-6 h-14 flex items-center justify-between">
                <RouterLink :to="{ name: 'sales' }" class="flex items-center gap-2 text-sm text-gray-600 hover:text-gray-900 transition-colors group">
                    <ArrowLeftIcon class="w-4 h-4 group-hover:-translate-x-0.5 transition-transform" />
                    {{ t('sales.backToList') }}
                </RouterLink>
                <div class="flex items-center gap-3">
                    <span v-if="sale" class="hidden sm:block text-sm font-mono font-semibold text-gray-500 bg-gray-100 px-3 py-1 rounded-lg">
                        {{ sale.sale_number }}
                    </span>
                    <span v-if="sale && sale.status === 'cancelled'"
                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-700">
                        <XCircleIcon class="w-3.5 h-3.5" />
                        {{ t('sales.statusCancelled') }}
                    </span>
                    <button
                        @click="printInvoice"
                        class="flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition-colors shadow-sm"
                    >
                        <PrinterIcon class="w-4 h-4" />
                        {{ t('sales.printInvoice') }}
                    </button>
                </div>
            </div>
        </div>

        <!-- ── Page content ─────────────────────────────────────────────── -->
        <div class="max-w-4xl mx-auto px-4 py-8 print:p-0 print:max-w-none">

            <!-- Loading -->
            <div v-if="loading" class="text-center py-24 text-gray-400">
                <div class="w-8 h-8 border-2 border-indigo-500 border-t-transparent rounded-full animate-spin mx-auto mb-3" />
                <p class="text-sm">{{ t('common.loading') }}</p>
            </div>

            <!-- Invoice paper -->
            <div v-else-if="sale" id="invoice-paper" class="bg-white shadow-2xl rounded-xl overflow-hidden print:shadow-none print:rounded-none">

                <!-- CANCELLED watermark -->
                <div v-if="sale.status === 'cancelled'"
                    class="no-print bg-red-50 border-b border-red-200 px-10 py-2 text-center">
                    <p class="text-sm font-bold text-red-600 uppercase tracking-widest">
                        {{ t('sales.statusCancelled') }}
                    </p>
                </div>

                <!-- ══ LETTERHEAD ══════════════════════════════════════════ -->
                <div class="px-10 pt-8 pb-6">
                    <div class="flex items-start justify-between gap-6">
                        <!-- Company identity -->
                        <div class="flex items-start gap-4">
                            <img
                                v-if="settings?.logo_url"
                                :src="settings.logo_url"
                                alt="Logo"
                                class="h-16 w-auto object-contain flex-shrink-0"
                            />
                            <div v-else class="h-14 w-14 bg-indigo-100 rounded-xl flex items-center justify-center flex-shrink-0">
                                <BuildingOffice2Icon class="w-8 h-8 text-indigo-600" />
                            </div>
                            <div>
                                <h1 class="text-xl font-bold text-gray-900 leading-tight">
                                    {{ settings?.company_name ?? 'POSmeister' }}
                                </h1>
                                <p v-if="settings?.address" class="text-xs text-gray-500 mt-0.5 whitespace-pre-line leading-relaxed">
                                    {{ settings.address }}
                                </p>
                                <div class="flex items-center gap-3 mt-1">
                                    <span v-if="settings?.phone" class="text-xs text-gray-400 flex items-center gap-1">
                                        <PhoneIcon class="w-3 h-3" /> {{ settings.phone }}
                                    </span>
                                    <span v-if="settings?.email" class="text-xs text-gray-400 flex items-center gap-1">
                                        <EnvelopeIcon class="w-3 h-3" /> {{ settings.email }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Document title block -->
                        <div class="text-right flex-shrink-0">
                            <div :class="['text-white px-5 py-2 rounded-lg inline-block mb-3',
                                sale.status === 'cancelled' ? 'bg-red-500' : 'bg-emerald-600']">
                                <p class="text-xs font-bold tracking-[0.2em] uppercase">{{ t('sales.invoiceTitle') }}</p>
                            </div>
                            <div class="space-y-0.5">
                                <p class="font-mono font-bold text-gray-900 text-lg">{{ sale.sale_number }}</p>
                                <p class="text-sm text-gray-500">{{ formatDate(sale.sale_date) }}</p>
                                <p class="text-xs text-gray-400">
                                    {{ sale.sale_type === 'wholesale' ? t('sales.saleTypeWholesale') : t('sales.saleTypeRetail') }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Separator line -->
                <div :class="['mx-10 border-t-4 mb-0', sale.status === 'cancelled' ? 'border-red-500' : 'border-emerald-600']" />

                <!-- ══ CUSTOMER + SALE INFO ════════════════════════════════ -->
                <div class="px-10 py-6 grid grid-cols-2 gap-10 bg-gray-50/50 print:bg-white border-b border-gray-100">

                    <!-- Customer details -->
                    <div>
                        <p class="text-[10px] font-bold text-indigo-600 uppercase tracking-[0.15em] mb-2">
                            {{ t('pos.customer') }}
                        </p>
                        <div class="text-sm space-y-0.5">
                            <p class="font-semibold text-gray-900 text-base">
                                {{ sale.display_customer_name ?? t('common.walkin') }}
                            </p>
                            <p v-if="sale.customer_phone" class="text-gray-500 text-xs">
                                Tel.: {{ sale.customer_phone }}
                            </p>
                            <p v-if="sale.customer_address" class="text-gray-600 text-xs">
                                {{ sale.customer_address }}
                            </p>
                            <p v-if="sale.customer?.email" class="text-gray-400 text-xs">
                                {{ sale.customer.email }}
                            </p>
                        </div>
                    </div>

                    <!-- Sale metadata -->
                    <div>
                        <p class="text-[10px] font-bold text-indigo-600 uppercase tracking-[0.15em] mb-2">
                            {{ t('sales.sectionHeader') }}
                        </p>
                        <dl class="text-sm space-y-1.5">
                            <div class="flex justify-between items-center gap-4">
                                <dt class="text-gray-500 text-xs">{{ t('sales.date') }}</dt>
                                <dd class="font-medium text-gray-900 text-xs">{{ formatDate(sale.sale_date) }}</dd>
                            </div>
                            <div class="flex justify-between items-center gap-4">
                                <dt class="text-gray-500 text-xs">{{ t('branches.title') }}</dt>
                                <dd class="font-medium text-gray-900 text-xs">{{ sale.branch_name }}</dd>
                            </div>
                            <div class="flex justify-between items-center gap-4">
                                <dt class="text-gray-500 text-xs">{{ t('sales.createdBy') }}</dt>
                                <dd class="font-medium text-gray-900 text-xs">{{ sale.created_by_name ?? '—' }}</dd>
                            </div>
                            <div class="flex justify-between items-center gap-4">
                                <dt class="text-gray-500 text-xs">{{ t('common.status') }}</dt>
                                <dd>
                                    <span :class="['inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold',
                                        sale.status === 'active' ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-700']">
                                        {{ sale.status === 'active' ? t('sales.statusActive') : t('sales.statusCancelled') }}
                                    </span>
                                </dd>
                            </div>
                        </dl>
                    </div>
                </div>

                <!-- ══ LINE ITEMS TABLE ════════════════════════════════════ -->
                <div class="px-10 py-6">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b-2 border-gray-200">
                                <th class="pb-2.5 text-center text-[10px] font-bold text-gray-400 uppercase tracking-wide w-8">
                                    {{ t('sales.position') }}
                                </th>
                                <th class="pb-2.5 text-left text-[10px] font-bold text-gray-400 uppercase tracking-wide pl-3">
                                    {{ t('sales.product') }}
                                </th>
                                <th class="pb-2.5 text-right text-[10px] font-bold text-gray-400 uppercase tracking-wide w-16">
                                    {{ t('sales.qty') }}
                                </th>
                                <th class="pb-2.5 text-center text-[10px] font-bold text-gray-400 uppercase tracking-wide w-16">
                                    {{ t('sales.unit') }}
                                </th>
                                <th class="pb-2.5 text-right text-[10px] font-bold text-gray-400 uppercase tracking-wide w-28">
                                    {{ t('sales.unitPrice') }}
                                </th>
                                <th class="pb-2.5 text-right text-[10px] font-bold text-gray-400 uppercase tracking-wide w-16">
                                    {{ t('sales.vatRate') }}
                                </th>
                                <th class="pb-2.5 text-right text-[10px] font-bold text-gray-400 uppercase tracking-wide w-28">
                                    {{ t('sales.lineTotal') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(item, idx) in sale.items"
                                :key="idx"
                                class="border-b border-gray-50 hover:bg-emerald-50/30 transition-colors"
                            >
                                <td class="py-2.5 text-center text-xs text-gray-400 font-mono">{{ idx + 1 }}</td>
                                <td class="py-2.5 pl-3">
                                    <span class="font-medium text-gray-900">{{ item.product_name }}</span>
                                    <span v-if="item.product_sku" class="ml-2 text-xs text-gray-400 font-mono">({{ item.product_sku }})</span>
                                </td>
                                <td class="py-2.5 text-right font-mono text-gray-700 tabular-nums">{{ item.quantity }}</td>
                                <td class="py-2.5 text-center text-xs text-gray-500">{{ item.unit_name ?? '—' }}</td>
                                <td class="py-2.5 text-right font-mono text-gray-700 tabular-nums">{{ fmt(item.unit_price) }}</td>
                                <td class="py-2.5 text-right text-xs text-gray-500">{{ item.tax_rate }}%</td>
                                <td class="py-2.5 text-right font-mono font-semibold text-gray-900 tabular-nums">{{ fmt(item.line_total) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- ══ TOTALS + PAYMENT ═══════════════════════════════════ -->
                <div class="px-10 pb-6 flex flex-col sm:flex-row justify-end gap-8 border-b border-gray-100">

                    <!-- Totals column -->
                    <div class="w-full sm:w-72">
                        <p class="text-[10px] font-bold text-indigo-600 uppercase tracking-[0.15em] mb-3">
                            {{ t('purchases.subtotal') }}
                        </p>
                        <dl class="space-y-1.5 text-sm">
                            <div class="flex justify-between text-gray-600">
                                <dt>{{ t('sales.subtotal') }}</dt>
                                <dd class="font-mono tabular-nums">{{ fmt(sale.subtotal) }}</dd>
                            </div>
                            <div v-if="(sale.discount_amount ?? 0) > 0" class="flex justify-between text-green-700">
                                <dt>{{ t('sales.discount') }}</dt>
                                <dd class="font-mono tabular-nums">− {{ fmt(sale.discount_amount) }}</dd>
                            </div>
                            <div class="flex justify-between text-gray-600">
                                <dt>{{ t('sales.vat') }}</dt>
                                <dd class="font-mono tabular-nums">{{ fmt(sale.vat_amount) }}</dd>
                            </div>
                            <div v-if="(sale.freight_amount ?? 0) > 0" class="flex justify-between text-gray-600">
                                <dt>{{ t('sales.freight') }}</dt>
                                <dd class="font-mono tabular-nums">+ {{ fmt(sale.freight_amount) }}</dd>
                            </div>
                            <div class="flex justify-between items-center pt-3 mt-1 border-t-2 border-emerald-600">
                                <dt class="font-bold text-gray-900 text-base">{{ t('sales.grandTotal') }}</dt>
                                <dd class="font-mono font-bold text-emerald-700 text-lg tabular-nums">{{ fmt(sale.grand_total) }}</dd>
                            </div>
                        </dl>
                    </div>

                    <!-- Payment column -->
                    <div class="w-full sm:w-56">
                        <p class="text-[10px] font-bold text-indigo-600 uppercase tracking-[0.15em] mb-3">
                            {{ t('sales.paymentSection') }}
                        </p>
                        <dl class="space-y-1.5 text-sm">
                            <div v-if="(sale.cash_paid ?? 0) > 0" class="flex justify-between text-gray-600">
                                <dt class="flex items-center gap-1">
                                    <BanknotesIcon class="w-3.5 h-3.5 text-gray-400" />
                                    {{ t('sales.cashPaid') }}
                                </dt>
                                <dd class="font-mono tabular-nums">{{ fmt(sale.cash_paid) }}</dd>
                            </div>
                            <div v-if="(sale.card_paid ?? 0) > 0" class="flex justify-between text-gray-600">
                                <dt class="flex items-center gap-1">
                                    <CreditCardIcon class="w-3.5 h-3.5 text-gray-400" />
                                    {{ t('sales.cardPaid') }}
                                </dt>
                                <dd class="font-mono tabular-nums">{{ fmt(sale.card_paid) }}</dd>
                            </div>
                            <div class="flex justify-between text-gray-800 font-semibold border-t border-gray-200 pt-2">
                                <dt>{{ t('sales.totalPaid') }}</dt>
                                <dd class="font-mono tabular-nums">{{ fmt(sale.total_paid) }}</dd>
                            </div>
                            <div v-if="change > 0" class="flex justify-between text-emerald-700 font-semibold">
                                <dt>{{ t('sales.change') }}</dt>
                                <dd class="font-mono tabular-nums">{{ fmt(change) }}</dd>
                            </div>
                            <div v-if="due > 0" class="flex justify-between text-amber-700 font-semibold">
                                <dt>{{ t('sales.due') }}</dt>
                                <dd class="font-mono tabular-nums">{{ fmt(due) }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>

                <!-- ══ AMOUNT IN WORDS + NOTES ════════════════════════════ -->
                <div class="px-10 py-5 bg-slate-50/80 print:bg-white border-b border-gray-100">
                    <p class="text-sm text-gray-700 leading-relaxed">
                        <span class="font-semibold text-gray-900">{{ t('sales.inWords') }}:</span>
                        {{ amountInWords }}
                    </p>
                    <p v-if="sale.note || settings?.invoice_footer" class="mt-2 text-sm text-gray-600 leading-relaxed">
                        <span class="font-semibold text-gray-900">{{ t('sales.note') }}:</span>
                        {{ sale.note || settings?.invoice_footer }}
                    </p>
                </div>

                <!-- ══ SIGNATURE LINES ════════════════════════════════════ -->
                <div class="px-10 py-8 border-b border-gray-100">
                    <div class="grid grid-cols-2 gap-16">
                        <div>
                            <div class="h-14"></div>
                            <div class="border-t border-gray-400 pt-2">
                                <p class="text-xs text-gray-500">{{ t('sales.customerSignature') }}</p>
                            </div>
                        </div>
                        <div>
                            <div class="h-14"></div>
                            <div class="border-t border-gray-400 pt-2 text-right">
                                <p class="text-xs text-gray-500">{{ t('sales.createdBy') }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ══ DOCUMENT FOOTER ════════════════════════════════════ -->
                <div class="px-10 py-4 bg-gray-50 print:bg-white">
                    <div class="flex items-center justify-between text-xs text-gray-400">
                        <span>{{ t('sales.printDate') }}: {{ nowFormatted }}</span>
                        <span class="font-medium">{{ settings?.company_name ?? 'POSmeister' }} · POSmeister</span>
                    </div>
                </div>

            </div>

            <!-- Error state -->
            <div v-else class="text-center py-24">
                <ExclamationTriangleIcon class="w-12 h-12 text-red-400 mx-auto mb-3" />
                <p class="text-red-600 font-medium">Failed to load invoice.</p>
                <RouterLink :to="{ name: 'sales' }" class="mt-4 inline-block text-sm text-indigo-600 hover:underline">
                    {{ t('sales.backToList') }}
                </RouterLink>
            </div>

        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute, RouterLink } from 'vue-router';
import { saleService } from '@/services/saleService';
import { useSettingsStore } from '@/stores/settings';

import {
    ArrowLeftIcon, PrinterIcon, XCircleIcon,
    BuildingOffice2Icon, PhoneIcon, EnvelopeIcon,
    BanknotesIcon, CreditCardIcon, ExclamationTriangleIcon,
} from '@heroicons/vue/24/outline';

const { t }    = useI18n();
const route    = useRoute();
const settingsStore = useSettingsStore();

const sale      = ref(null);
const loading   = ref(true);
const settings  = computed(() => settingsStore.settings);
const currencyCode = computed(() => settings.value?.currency_code ?? 'EUR');

const change = computed(() => {
    if (!sale.value) return 0;
    return Math.max(0, (sale.value.total_paid ?? 0) - (sale.value.grand_total ?? 0));
});
const due = computed(() => {
    if (!sale.value) return 0;
    return Math.max(0, (sale.value.grand_total ?? 0) - (sale.value.total_paid ?? 0));
});

// ── Formatting ─────────────────────────────────────────────────────────────

function fmt(value) {
    return new Intl.NumberFormat('de-DE', { style: 'currency', currency: currencyCode.value })
        .format(value ?? 0);
}

function formatDate(dateStr) {
    if (!dateStr) return '—';
    return new Date(dateStr + 'T00:00:00').toLocaleDateString('de-DE', {
        day: '2-digit', month: 'long', year: 'numeric',
    });
}

const nowFormatted = computed(() =>
    new Date().toLocaleString('de-DE', {
        day: '2-digit', month: '2-digit', year: 'numeric',
        hour: '2-digit', minute: '2-digit',
    }) + ' Uhr'
);

// ── German number-to-words ─────────────────────────────────────────────────

const amountInWords = computed(() => {
    if (!sale.value) return '';
    return toWordsDE(sale.value.grand_total);
});

function toWordsDE(amount) {
    const n     = Math.round(parseFloat(amount ?? 0) * 100);
    const euros = Math.floor(n / 100);
    const cents = n % 100;

    const oW = ['', 'ein', 'zwei', 'drei', 'vier', 'fünf', 'sechs', 'sieben', 'acht', 'neun',
                 'zehn', 'elf', 'zwölf', 'dreizehn', 'vierzehn', 'fünfzehn', 'sechzehn',
                 'siebzehn', 'achtzehn', 'neunzehn'];
    const tW = ['', '', 'zwanzig', 'dreißig', 'vierzig', 'fünfzig', 'sechzig', 'siebzig', 'achtzig', 'neunzig'];

    function conv(n) {
        if (n === 0)   return 'null';
        if (n < 20)    return oW[n];
        if (n < 100) { const t2 = Math.floor(n / 10), o = n % 10; return o === 0 ? tW[t2] : oW[o] + 'und' + tW[t2]; }
        if (n < 1000) { const h = Math.floor(n / 100), r = n % 100; return (h === 1 ? 'ein' : oW[h]) + 'hundert' + (r ? conv(r) : ''); }
        if (n < 1e6)  { const th = Math.floor(n / 1000), r = n % 1000; return (th === 1 ? 'ein' : conv(th)) + 'tausend' + (r ? conv(r) : ''); }
        return n.toLocaleString('de-DE');
    }

    const euroWord = conv(euros).charAt(0).toUpperCase() + conv(euros).slice(1);
    const centStr  = cents === 0 ? '00' : (cents < 10 ? '0' + cents : String(cents));
    return `${euroWord} ${currencyCode.value} und ${centStr} Cent`;
}

// ── Actions ────────────────────────────────────────────────────────────────

function printInvoice() {
    window.print();
}

// ── Load ───────────────────────────────────────────────────────────────────

onMounted(async () => {
    await settingsStore.load();
    try {
        const { data } = await saleService.show(route.params.id);
        sale.value = data.data ?? data;
    } catch {
        sale.value = null;
    } finally {
        loading.value = false;
    }
});
</script>

<style>
@media print {
    @page { margin: 12mm; size: A4 portrait; }
    * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
    .no-print { display: none !important; }
    body { background: white !important; margin: 0 !important; padding: 0 !important; }
    #invoice-paper { box-shadow: none !important; border-radius: 0 !important; }
}
</style>
