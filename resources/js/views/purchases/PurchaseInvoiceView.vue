<template>
    <div class="min-h-screen bg-slate-100 print:bg-white">

        <!-- ── Action bar (hidden when printing) ─────────────────────── -->
        <div class="no-print sticky top-0 z-20 bg-white border-b border-gray-200 shadow-sm">
            <div class="max-w-4xl mx-auto px-3 sm:px-6 h-14 flex items-center justify-between gap-2">
                <RouterLink :to="{ name: 'purchases' }" class="flex items-center gap-2 text-sm text-gray-600 hover:text-gray-900 transition-colors group min-w-0">
                    <ArrowLeftIcon class="w-4 h-4 flex-shrink-0 group-hover:-translate-x-0.5 transition-transform" />
                    <span class="truncate">{{ t('purchases.backToList') }}</span>
                </RouterLink>
                <div class="flex items-center gap-2 sm:gap-3 flex-shrink-0">
                    <span v-if="purchase" class="hidden sm:block text-sm font-mono font-semibold text-gray-500 bg-gray-100 px-3 py-1 rounded-lg">
                        {{ purchase.purchase_number }}
                    </span>
                    <button
                        @click="printInvoice"
                        class="flex items-center gap-1.5 sm:gap-2 px-3 sm:px-4 py-2 text-sm font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition-colors shadow-sm"
                    >
                        <PrinterIcon class="w-4 h-4" />
                        <span class="hidden xs:inline">{{ t('purchases.printInvoice') }}</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- ── Page content ──────────────────────────────────────────── -->
        <div class="max-w-4xl mx-auto px-2 sm:px-4 py-4 sm:py-8 print:p-0 print:max-w-none">

            <!-- Loading -->
            <div v-if="loading" class="text-center py-24 text-gray-400">
                <div class="w-8 h-8 border-2 border-indigo-500 border-t-transparent rounded-full animate-spin mx-auto mb-3" />
                <p class="text-sm">{{ t('common.loading') }}</p>
            </div>

            <!-- Invoice paper -->
            <!-- POS thermal receipt — kicked in when Settings.invoice_print_format is pos80 / pos58. -->
            <div
                v-else-if="purchase && isPosFormat"
                id="invoice-paper"
                class="bg-white mx-auto shadow-2xl print:shadow-none rounded-xl print:rounded-none"
            >
                <PosReceiptTemplate :doc="posDoc" :format="printFormat" />
            </div>

            <div v-else-if="purchase" id="invoice-paper" class="bg-white shadow-2xl rounded-xl overflow-hidden print:shadow-none print:rounded-none">

                <!-- ══ LETTERHEAD ════════════════════════════════════════ -->
                <div class="px-4 sm:px-10 pt-5 sm:pt-8 pb-5 sm:pb-6">
                    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 sm:gap-6">
                        <!-- Company identity -->
                        <div class="flex items-start gap-3 sm:gap-4 min-w-0">
                            <img
                                v-if="settings?.logo_url"
                                :src="settings.logo_url"
                                alt="Logo"
                                class="h-12 sm:h-16 w-auto object-contain flex-shrink-0"
                            />
                            <div v-else class="h-12 w-12 sm:h-14 sm:w-14 bg-indigo-100 rounded-xl flex items-center justify-center flex-shrink-0">
                                <BuildingOffice2Icon class="w-6 h-6 sm:w-8 sm:h-8 text-indigo-600" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <h1 class="text-lg sm:text-xl font-bold text-gray-900 leading-tight truncate">
                                    {{ settings?.company_name ?? 'POSmeister' }}
                                </h1>
                                <p v-if="settings?.address" class="text-xs text-gray-500 mt-0.5 whitespace-pre-line leading-relaxed break-words">
                                    {{ settings.address }}
                                </p>
                                <div class="flex flex-wrap items-center gap-x-3 gap-y-1 mt-1">
                                    <span v-if="settings?.phone" class="text-xs text-gray-400 flex items-center gap-1 min-w-0">
                                        <PhoneIcon class="w-3 h-3 flex-shrink-0" /> <span class="truncate">{{ settings.phone }}</span>
                                    </span>
                                    <span v-if="settings?.email" class="text-xs text-gray-400 flex items-center gap-1 min-w-0">
                                        <EnvelopeIcon class="w-3 h-3 flex-shrink-0" /> <span class="truncate">{{ settings.email }}</span>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Document title block -->
                        <div class="text-left sm:text-right flex-shrink-0">
                            <div class="bg-indigo-600 text-white px-4 sm:px-5 py-1.5 sm:py-2 rounded-lg inline-block mb-2 sm:mb-3">
                                <p class="text-[10px] sm:text-xs font-bold tracking-[0.2em] uppercase">{{ t('purchases.invoiceTitle') }}</p>
                            </div>
                            <div class="space-y-0.5">
                                <p class="font-mono font-bold text-gray-900 text-base sm:text-lg">{{ purchase.purchase_number }}</p>
                                <p class="text-xs sm:text-sm text-gray-500">{{ formatDate(purchase.purchase_date) }}</p>
                                <p v-if="purchase.reference" class="text-xs text-gray-400 font-mono">Ref: {{ purchase.reference }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Separator line -->
                <div class="mx-4 sm:mx-10 border-t-4 border-indigo-600 mb-0" />

                <!-- ══ SUPPLIER + ORDER INFO ═══════════════════════════════ -->
                <div class="px-4 sm:px-10 py-5 sm:py-6 grid grid-cols-1 sm:grid-cols-2 gap-6 sm:gap-10 bg-gray-50/50 print:bg-white border-b border-gray-100">

                    <!-- Supplier details -->
                    <div>
                        <p class="text-[10px] font-bold text-indigo-600 uppercase tracking-[0.15em] mb-2">
                            {{ t('suppliers.title') }}
                        </p>
                        <div v-if="purchase.supplier" class="text-sm space-y-0.5">
                            <p class="font-semibold text-gray-900 text-base">{{ purchase.supplier.name }}</p>
                            <p v-if="purchase.supplier.contact_person" class="text-gray-500 text-xs">
                                {{ purchase.supplier.contact_person }}
                            </p>
                            <p v-if="purchase.supplier.address" class="text-gray-600">{{ purchase.supplier.address }}</p>
                            <p v-if="purchase.supplier.city || purchase.supplier.country" class="text-gray-600">
                                {{ [purchase.supplier.city, purchase.supplier.country].filter(Boolean).join(', ') }}
                            </p>
                            <div class="pt-1 space-y-0.5">
                                <p v-if="purchase.supplier.vat_number" class="text-xs text-gray-400">
                                    USt-IdNr.: {{ purchase.supplier.vat_number }}
                                </p>
                                <p v-if="purchase.supplier.phone" class="text-xs text-gray-400">
                                    Tel.: {{ purchase.supplier.phone }}
                                </p>
                                <p v-if="purchase.supplier.email" class="text-xs text-gray-400">
                                    {{ purchase.supplier.email }}
                                </p>
                            </div>
                        </div>
                        <p v-else class="text-sm text-gray-400 italic">—</p>
                    </div>

                    <!-- Order metadata -->
                    <div>
                        <p class="text-[10px] font-bold text-indigo-600 uppercase tracking-[0.15em] mb-2">
                            {{ t('purchases.sectionHeader') }}
                        </p>
                        <dl class="text-sm space-y-1.5">
                            <div class="flex justify-between items-center gap-4">
                                <dt class="text-gray-500 text-xs">{{ t('purchases.date') }}</dt>
                                <dd class="font-medium text-gray-900 text-xs">{{ formatDate(purchase.purchase_date) }}</dd>
                            </div>
                            <div class="flex justify-between items-center gap-4">
                                <dt class="text-gray-500 text-xs">{{ t('branches.title') }}</dt>
                                <dd class="font-medium text-gray-900 text-xs">{{ purchase.branch_name }}</dd>
                            </div>
                            <div class="flex justify-between items-center gap-4">
                                <dt class="text-gray-500 text-xs">{{ t('common.status') }}</dt>
                                <dd>
                                    <span :class="['inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold',
                                        purchase.status === 'received' ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800']"
                                    >
                                        <CheckCircleIcon v-if="purchase.status === 'received'" class="w-3 h-3" />
                                        {{ purchase.status === 'received' ? t('purchases.statusReceived') : t('purchases.statusDraft') }}
                                    </span>
                                </dd>
                            </div>
                            <div v-if="purchase.created_by_name && purchase.created_by_name !== '—'" class="flex justify-between items-center gap-4">
                                <dt class="text-gray-500 text-xs">{{ t('purchases.createdBy') }}</dt>
                                <dd class="font-medium text-gray-900 text-xs">{{ purchase.created_by_name }}</dd>
                            </div>
                            <div v-if="purchase.reference" class="flex justify-between items-center gap-4">
                                <dt class="text-gray-500 text-xs">{{ t('purchases.reference') }}</dt>
                                <dd class="font-mono font-medium text-gray-900 text-xs">{{ purchase.reference }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>

                <!-- ══ LINE ITEMS — MOBILE STACKED CARDS (< sm) ══════════ -->
                <div class="sm:hidden px-4 py-4 space-y-2 print:hidden">
                    <div v-for="(item, idx) in purchase.items" :key="`m-${idx}`" class="rounded-lg border border-gray-200 p-3 bg-white">
                        <div class="flex items-start justify-between gap-2 mb-2">
                            <div class="min-w-0">
                                <p class="text-[10px] uppercase font-bold text-gray-400">#{{ idx + 1 }} {{ t('purchases.product') }}</p>
                                <p class="font-semibold text-gray-900 truncate">{{ item.product_name }}</p>
                                <p v-if="item.product_sku" class="text-[10px] text-gray-400 font-mono">{{ item.product_sku }}</p>
                            </div>
                            <span class="text-base font-mono font-bold text-indigo-900 tabular-nums flex-shrink-0">{{ fmt(item.line_total) }}</span>
                        </div>
                        <div class="grid grid-cols-3 gap-2 text-xs">
                            <div>
                                <p class="text-[10px] uppercase text-gray-400">{{ t('purchases.qty') }}</p>
                                <p class="font-mono text-gray-700">{{ item.quantity }} {{ item.unit_name || '' }}</p>
                            </div>
                            <div>
                                <p class="text-[10px] uppercase text-gray-400">{{ t('purchases.unitCost') }}</p>
                                <p class="font-mono text-gray-700">{{ fmt(item.unit_cost) }}</p>
                            </div>
                            <div>
                                <p class="text-[10px] uppercase text-gray-400">{{ t('purchases.vatRate') }}</p>
                                <p class="font-mono text-gray-700">{{ item.vat_rate }}%</p>
                            </div>
                        </div>
                        <!-- Phase Y — serial numbers (mobile card) -->
                        <div v-if="item.is_serialized && item.serial_numbers?.length"
                             class="mt-2 pt-2 border-t border-dashed border-indigo-200">
                            <p class="text-[10px] uppercase tracking-wider text-indigo-600 font-semibold mb-1">
                                {{ t('serials.invoice.label') }}
                            </p>
                            <div class="flex flex-wrap gap-1">
                                <span v-for="sn in item.serial_numbers" :key="sn"
                                      class="inline-flex items-center px-1.5 py-0.5 rounded bg-indigo-50 text-indigo-700 font-mono text-[10px] font-semibold border border-indigo-100">
                                    {{ sn }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ══ LINE ITEMS TABLE (sm+ and print) ═════════════════════ -->
                <div class="hidden sm:block px-4 sm:px-10 py-6 print:block">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b-2 border-gray-200">
                                <th class="pb-2.5 text-center text-[10px] font-bold text-gray-400 uppercase tracking-wide w-8">
                                    {{ t('purchases.position') }}
                                </th>
                                <th class="pb-2.5 text-left text-[10px] font-bold text-gray-400 uppercase tracking-wide pl-3">
                                    {{ t('purchases.product') }}
                                </th>
                                <th class="pb-2.5 text-right text-[10px] font-bold text-gray-400 uppercase tracking-wide w-14">
                                    {{ t('purchases.qty') }}
                                </th>
                                <th class="pb-2.5 text-center text-[10px] font-bold text-gray-400 uppercase tracking-wide w-16">
                                    {{ t('purchases.unit') }}
                                </th>
                                <th class="pb-2.5 text-right text-[10px] font-bold text-gray-400 uppercase tracking-wide w-28">
                                    {{ t('purchases.unitCost') }}
                                </th>
                                <th class="pb-2.5 text-right text-[10px] font-bold text-gray-400 uppercase tracking-wide w-16">
                                    {{ t('purchases.vatRate') }}
                                </th>
                                <th class="pb-2.5 text-right text-[10px] font-bold text-gray-400 uppercase tracking-wide w-28">
                                    {{ t('purchases.lineTotal') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(item, idx) in purchase.items"
                                :key="idx"
                                class="border-b border-gray-50 hover:bg-indigo-50/30 transition-colors"
                            >
                                <td class="py-2.5 text-center text-xs text-gray-400 font-mono">{{ idx + 1 }}</td>
                                <td class="py-2.5 pl-3">
                                    <span class="font-medium text-gray-900">{{ item.product_name }}</span>
                                    <span v-if="item.product_sku" class="ml-2 text-xs text-gray-400 font-mono">({{ item.product_sku }})</span>
                                    <!-- Phase Y — serial numbers under the product name -->
                                    <div v-if="item.is_serialized && item.serial_numbers?.length"
                                         class="mt-0.5 flex flex-wrap gap-x-1.5 gap-y-0.5">
                                        <span class="text-[10px] font-bold text-indigo-600 uppercase tracking-wider">{{ t('serials.invoice.label') }}:</span>
                                        <span class="font-mono text-[10px] text-indigo-700 font-semibold">
                                            {{ item.serial_numbers.join(', ') }}
                                        </span>
                                    </div>
                                </td>
                                <td class="py-2.5 text-right font-mono text-gray-700">{{ item.quantity }}</td>
                                <td class="py-2.5 text-center text-xs text-gray-500">{{ item.unit_name ?? '—' }}</td>
                                <td class="py-2.5 text-right font-mono text-gray-700">{{ fmt(item.unit_cost) }}</td>
                                <td class="py-2.5 text-right text-xs text-gray-500">{{ item.vat_rate }}%</td>
                                <td class="py-2.5 text-right font-mono font-semibold text-gray-900">{{ fmt(item.line_total) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- ══ TOTALS ════════════════════════════════════════════ -->
                <div class="px-4 sm:px-10 pb-6 flex justify-stretch sm:justify-end border-b border-gray-100">
                    <div class="w-full sm:w-72">
                        <dl class="space-y-1.5 text-sm">
                            <div class="flex justify-between text-gray-600">
                                <dt>{{ t('purchases.subtotal') }}</dt>
                                <dd class="font-mono">{{ fmt(purchase.subtotal) }}</dd>
                            </div>
                            <div class="flex justify-between text-gray-600">
                                <dt>{{ t('purchases.vat') }}</dt>
                                <dd class="font-mono">{{ fmt(purchase.vat_amount) }}</dd>
                            </div>
                            <div v-if="(purchase.discount_amount ?? 0) > 0" class="flex justify-between text-green-700">
                                <dt>{{ t('purchases.discount') }}</dt>
                                <dd class="font-mono">− {{ fmt(purchase.discount_amount) }}</dd>
                            </div>
                            <div v-if="(purchase.freight_amount ?? 0) > 0" class="flex justify-between text-gray-600">
                                <dt>{{ t('purchases.freight') }}</dt>
                                <dd class="font-mono">+ {{ fmt(purchase.freight_amount) }}</dd>
                            </div>
                            <!-- Grand total row -->
                            <div class="flex justify-between items-center pt-3 mt-1 border-t-2 border-indigo-600">
                                <dt class="font-bold text-gray-900 text-base">{{ t('purchases.grandTotal') }}</dt>
                                <dd class="font-mono font-bold text-indigo-700 text-lg">{{ fmt(purchase.total_amount) }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>

                <!-- ══ AMOUNT IN WORDS + NOTES ══════════════════════════ -->
                <div class="px-4 sm:px-10 py-4 sm:py-5 bg-slate-50/80 print:bg-white border-b border-gray-100">
                    <p class="text-sm text-gray-700 leading-relaxed">
                        <span class="font-semibold text-gray-900">{{ t('purchases.inWords') }}:</span>
                        {{ amountInWords }}
                    </p>
                    <p v-if="purchase.notes || settings?.invoice_footer" class="mt-2 text-sm text-gray-600 leading-relaxed">
                        <span class="font-semibold text-gray-900">{{ t('purchases.invoiceNote') }}:</span>
                        {{ purchase.notes || settings?.invoice_footer }}
                    </p>
                </div>

                <!-- ══ SIGNATURE LINES ══════════════════════════════════ -->
                <div class="px-4 sm:px-10 py-6 sm:py-8 border-b border-gray-100">
                    <div class="grid grid-cols-2 gap-6 sm:gap-16">
                        <div>
                            <div class="h-14"></div>
                            <div class="border-t border-gray-400 pt-2">
                                <p class="text-xs text-gray-500">{{ t('purchases.receivedBy') }}</p>
                            </div>
                        </div>
                        <div>
                            <div class="h-14"></div>
                            <div class="border-t border-gray-400 pt-2 text-right">
                                <p class="text-xs text-gray-500">{{ t('purchases.authorizedSignature') }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ══ DOCUMENT FOOTER ═══════════════════════════════════ -->
                <div class="px-4 sm:px-10 py-3 sm:py-4 bg-gray-50 print:bg-white">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1 text-[10px] sm:text-xs text-gray-400">
                        <span>{{ t('purchases.printDate') }}: {{ nowFormatted }}</span>
                        <span class="font-medium truncate">{{ settings?.company_name ?? 'POSmeister' }} · POSmeister</span>
                    </div>
                </div>

            </div>

            <!-- Error state -->
            <div v-else class="text-center py-24">
                <ExclamationTriangleIcon class="w-12 h-12 text-red-400 mx-auto mb-3" />
                <p class="text-red-600 font-medium">Failed to load invoice.</p>
                <RouterLink :to="{ name: 'purchases' }" class="mt-4 inline-block text-sm text-indigo-600 hover:underline">
                    {{ t('purchases.backToList') }}
                </RouterLink>
            </div>

        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute, RouterLink } from 'vue-router';
import { purchaseService } from '@/services/purchaseService';
import { useSettingsStore } from '@/stores/settings';
import { useInvoicePrint } from '@/composables/useInvoicePrint';
import PosReceiptTemplate from '@/components/invoice/PosReceiptTemplate.vue';

import {
    ArrowLeftIcon, PrinterIcon,
    BuildingOffice2Icon, PhoneIcon, EnvelopeIcon,
    CheckCircleIcon, ExclamationTriangleIcon,
} from '@heroicons/vue/24/outline';

const { t }    = useI18n();
const route    = useRoute();
const settingsStore = useSettingsStore();

const purchase  = ref(null);
const loading   = ref(true);
const settings  = computed(() => settingsStore.settings);
const currencyCode = computed(() => settings.value?.currency_code ?? 'EUR');

// ── Formatting ────────────────────────────────────────────────────────────

function fmt(value) {
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: currencyCode.value })
        .format(value ?? 0);
}

function formatDate(dateStr) {
    if (!dateStr) return '—';
    return new Date(dateStr + 'T00:00:00').toLocaleDateString('en-US', {
        day: '2-digit', month: 'long', year: 'numeric',
    });
}

const nowFormatted = computed(() =>
    new Date().toLocaleString('en-US', {
        day: '2-digit', month: '2-digit', year: 'numeric',
        hour: '2-digit', minute: '2-digit',
    }) + ' Uhr'
);

// ── German number-to-words ────────────────────────────────────────────────

const amountInWords = computed(() => {
    if (!purchase.value) return '';
    return toWordsDE(purchase.value.total_amount);
});

function toWordsDE(amount) {
    const n      = Math.round(parseFloat(amount ?? 0) * 100);
    const euros  = Math.floor(n / 100);
    const cents  = n % 100;

    const oW = ['', 'ein', 'zwei', 'drei', 'vier', 'fünf', 'sechs', 'sieben', 'acht', 'neun',
                 'zehn', 'elf', 'zwölf', 'dreizehn', 'vierzehn', 'fünfzehn', 'sechzehn',
                 'siebzehn', 'achtzehn', 'neunzehn'];
    const tW = ['', '', 'zwanzig', 'dreißig', 'vierzig', 'fünfzig', 'sechzig', 'siebzig', 'achtzig', 'neunzig'];

    function conv(n) {
        if (n === 0)    return 'null';
        if (n < 20)     return oW[n];
        if (n < 100)  { const t = Math.floor(n / 10), o = n % 10; return o === 0 ? tW[t] : oW[o] + 'und' + tW[t]; }
        if (n < 1000) { const h = Math.floor(n / 100), r = n % 100; return (h === 1 ? 'ein' : oW[h]) + 'hundert' + (r ? conv(r) : ''); }
        if (n < 1e6)  { const th = Math.floor(n / 1000), r = n % 1000; return (th === 1 ? 'ein' : conv(th)) + 'tausend' + (r ? conv(r) : ''); }
        return n.toLocaleString('en-US');
    }

    const euroWord  = conv(euros).charAt(0).toUpperCase() + conv(euros).slice(1);
    const centStr   = cents === 0 ? '00' : (cents < 10 ? '0' + cents : String(cents));
    return `${euroWord} ${currencyCode.value} und ${centStr} Cent`;
}

// ── Print format ──────────────────────────────────────────────────────────
const { printFormat, isPosFormat, printInvoice } = useInvoicePrint();

const posDoc = computed(() => {
    if (!purchase.value) return null;
    const p = purchase.value;
    return {
        kind: 'purchase',
        number: p.purchase_number ?? p.reference ?? p.invoice_number,
        date: formatDate(p.purchase_date),
        time: new Date().toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' }),
        counterparty_label: t('invoice.supplier', 'Supplier'),
        counterparty_name: p.supplier?.name ?? '—',
        cashier_name: p.user?.name ?? '',
        branch_name: p.branch?.name ?? '',
        items: (p.items ?? []).map(it => ({
            name: it.name ?? it.product?.name ?? it.product_name ?? '—',
            quantity: it.quantity,
            unit: it.unit_name ?? it.product?.unit ?? '',
            unit_price: it.unit_price ?? it.cost_price,
            vat_rate: it.vat_rate,
            line_total: it.line_total ?? (it.quantity * (it.unit_price ?? it.cost_price)),
        })),
        subtotal: p.subtotal,
        discount: p.discount_amount,
        vat: p.vat_amount,
        grand_total: p.total_amount ?? p.grand_total,
        cash_paid: p.total_paid,
        due_amount: Math.max(0, (p.total_amount ?? p.grand_total ?? 0) - (p.total_paid ?? 0)),
        printed_at: nowFormatted.value,
    };
});

// ── Load ──────────────────────────────────────────────────────────────────

onMounted(async () => {
    await settingsStore.load();
    try {
        const { data } = await purchaseService.show(route.params.id);
        purchase.value = data.data ?? data;
    } catch {
        purchase.value = null;
    } finally {
        loading.value = false;
    }
});
</script>

<style>
/*
 * @page is injected by useInvoicePrint() to match the operator's chosen
 * paper size; these rules cover everything that's the same regardless.
 */
@media print {
    * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
    .no-print { display: none !important; }
    body { background: white !important; margin: 0 !important; padding: 0 !important; }
    #invoice-paper { box-shadow: none !important; border-radius: 0 !important; }
}
</style>
