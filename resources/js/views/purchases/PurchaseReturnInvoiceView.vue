<template>
    <div class="min-h-screen bg-slate-100 print:bg-white">

        <!-- ── Action bar ─────────────────────────────────────────────── -->
        <div class="no-print sticky top-0 z-20 bg-white border-b border-gray-200 shadow-sm">
            <div class="max-w-4xl mx-auto px-6 h-14 flex items-center justify-between">
                <RouterLink :to="{ name: 'purchase-returns' }" class="flex items-center gap-2 text-sm text-gray-600 hover:text-gray-900 transition-colors group">
                    <ArrowLeftIcon class="w-4 h-4 group-hover:-translate-x-0.5 transition-transform" />
                    {{ t('purchaseReturns.title') }}
                </RouterLink>
                <div class="flex items-center gap-3">
                    <span v-if="ret" class="hidden sm:block text-sm font-mono font-semibold text-gray-500 bg-gray-100 px-3 py-1 rounded-lg">
                        {{ ret.return_number }}
                    </span>
                    <button
                        @click="window.print()"
                        class="flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition-colors shadow-sm"
                    >
                        <PrinterIcon class="w-4 h-4" />
                        {{ t('purchaseReturns.printInvoice') }}
                    </button>
                </div>
            </div>
        </div>

        <!-- ── Page content ──────────────────────────────────────────── -->
        <div class="max-w-4xl mx-auto px-4 py-8 print:p-0 print:max-w-none">

            <div v-if="loading" class="text-center py-24 text-gray-400">
                <div class="w-8 h-8 border-2 border-indigo-500 border-t-transparent rounded-full animate-spin mx-auto mb-3" />
                <p class="text-sm">{{ t('common.loading') }}</p>
            </div>

            <!-- Return invoice paper -->
            <div v-else-if="ret" id="invoice-paper" class="bg-white shadow-2xl rounded-xl overflow-hidden print:shadow-none print:rounded-none">

                <!-- ══ LETTERHEAD ═══════════════════════════════════════ -->
                <div class="px-10 pt-8 pb-6">
                    <div class="flex items-start justify-between gap-6">
                        <div class="flex items-start gap-4">
                            <img v-if="settings?.logo_url" :src="settings.logo_url" alt="Logo"
                                class="h-16 w-auto object-contain flex-shrink-0" />
                            <div v-else class="h-14 w-14 bg-orange-100 rounded-xl flex items-center justify-center flex-shrink-0">
                                <ArrowUturnLeftIcon class="w-8 h-8 text-orange-600" />
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
                        <!-- Document title -->
                        <div class="text-right flex-shrink-0">
                            <div class="bg-orange-600 text-white px-5 py-2 rounded-lg inline-block mb-3">
                                <p class="text-xs font-bold tracking-[0.2em] uppercase">{{ t('purchaseReturns.invoiceTitle') }}</p>
                            </div>
                            <div class="space-y-0.5">
                                <p class="font-mono font-bold text-gray-900 text-lg">{{ ret.return_number }}</p>
                                <p class="text-sm text-gray-500">{{ formatDate(ret.return_date) }}</p>
                                <p v-if="ret.purchase" class="text-xs text-gray-400">
                                    {{ t('purchaseReturns.originalPurchase') }}: {{ ret.purchase.purchase_number }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mx-10 border-t-4 border-orange-600 mb-0" />

                <!-- ══ SUPPLIER + DETAILS ════════════════════════════════ -->
                <div class="px-10 py-6 grid grid-cols-2 gap-10 bg-gray-50/50 print:bg-white border-b border-gray-100">
                    <div>
                        <p class="text-[10px] font-bold text-orange-600 uppercase tracking-[0.15em] mb-2">
                            {{ t('suppliers.title') }}
                        </p>
                        <div v-if="ret.supplier" class="text-sm space-y-0.5">
                            <p class="font-semibold text-gray-900 text-base">{{ ret.supplier.name }}</p>
                            <p v-if="ret.supplier.address" class="text-gray-600">{{ ret.supplier.address }}</p>
                            <p v-if="ret.supplier.phone" class="text-xs text-gray-400">Tel.: {{ ret.supplier.phone }}</p>
                        </div>
                        <p v-else class="text-sm text-gray-400 italic">—</p>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold text-orange-600 uppercase tracking-[0.15em] mb-2">
                            {{ t('purchaseReturns.sectionHeader') }}
                        </p>
                        <dl class="text-sm space-y-1.5">
                            <div class="flex justify-between items-center gap-4">
                                <dt class="text-gray-500 text-xs">{{ t('purchaseReturns.returnDate') }}</dt>
                                <dd class="font-medium text-gray-900 text-xs">{{ formatDate(ret.return_date) }}</dd>
                            </div>
                            <div v-if="ret.purchase" class="flex justify-between items-center gap-4">
                                <dt class="text-gray-500 text-xs">{{ t('purchaseReturns.originalPurchase') }}</dt>
                                <dd class="font-mono font-medium text-gray-900 text-xs">{{ ret.purchase.purchase_number }}</dd>
                            </div>
                            <div class="flex justify-between items-center gap-4">
                                <dt class="text-gray-500 text-xs">{{ t('branches.title') }}</dt>
                                <dd class="font-medium text-gray-900 text-xs">{{ ret.branch_name }}</dd>
                            </div>
                            <div v-if="ret.created_by_name && ret.created_by_name !== '—'" class="flex justify-between items-center gap-4">
                                <dt class="text-gray-500 text-xs">{{ t('purchaseReturns.createdBy') }}</dt>
                                <dd class="font-medium text-gray-900 text-xs">{{ ret.created_by_name }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>

                <!-- ══ LINE ITEMS ════════════════════════════════════════ -->
                <div class="px-10 py-6">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b-2 border-gray-200">
                                <th class="pb-2.5 text-center text-[10px] font-bold text-gray-400 uppercase tracking-wide w-8">
                                    {{ t('purchases.position') }}
                                </th>
                                <th class="pb-2.5 text-left text-[10px] font-bold text-gray-400 uppercase tracking-wide pl-3">
                                    {{ t('purchases.product') }}
                                </th>
                                <th class="pb-2.5 text-right text-[10px] font-bold text-gray-400 uppercase tracking-wide w-16">
                                    {{ t('purchases.qty') }}
                                </th>
                                <th class="pb-2.5 text-center text-[10px] font-bold text-gray-400 uppercase tracking-wide w-16">
                                    {{ t('purchases.unit') }}
                                </th>
                                <th class="pb-2.5 text-right text-[10px] font-bold text-gray-400 uppercase tracking-wide w-28">
                                    {{ t('purchaseReturns.unitCost') }}
                                </th>
                                <th class="pb-2.5 text-right text-[10px] font-bold text-gray-400 uppercase tracking-wide w-28">
                                    {{ t('purchaseReturns.amount') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(item, idx) in ret.items" :key="idx"
                                class="border-b border-gray-50 hover:bg-orange-50/30 transition-colors">
                                <td class="py-2.5 text-center text-xs text-gray-400 font-mono">{{ idx + 1 }}</td>
                                <td class="py-2.5 pl-3">
                                    <span class="font-medium text-gray-900">{{ item.product_name }}</span>
                                    <span v-if="item.product_sku" class="ml-2 text-xs text-gray-400 font-mono">({{ item.product_sku }})</span>
                                </td>
                                <td class="py-2.5 text-right font-mono text-gray-700">{{ item.quantity }}</td>
                                <td class="py-2.5 text-center text-xs text-gray-500">{{ item.unit_name ?? '—' }}</td>
                                <td class="py-2.5 text-right font-mono text-gray-700">{{ fmt(item.unit_cost) }}</td>
                                <td class="py-2.5 text-right font-mono font-semibold text-gray-900">{{ fmt(item.line_total) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- ══ TOTAL ═════════════════════════════════════════════ -->
                <div class="px-10 pb-6 flex justify-end border-b border-gray-100">
                    <div class="w-64">
                        <div class="flex justify-between items-center pt-3 border-t-2 border-orange-600">
                            <dt class="font-bold text-gray-900 text-base">{{ t('purchaseReturns.totalReturn') }}</dt>
                            <dd class="font-mono font-bold text-orange-700 text-lg">{{ fmt(ret.total_amount) }}</dd>
                        </div>
                    </div>
                </div>

                <!-- ══ NOTE + IN WORDS ════════════════════════════════════ -->
                <div class="px-10 py-5 bg-slate-50/80 print:bg-white border-b border-gray-100">
                    <p class="text-sm text-gray-700">
                        <span class="font-semibold text-gray-900">{{ t('purchaseReturns.inWords') }}:</span>
                        {{ amountInWords }}
                    </p>
                    <p v-if="ret.note" class="mt-2 text-sm text-gray-600">
                        <span class="font-semibold text-gray-900">{{ t('purchaseReturns.noteLabel') }}:</span>
                        {{ ret.note }}
                    </p>
                </div>

                <!-- ══ SIGNATURE LINES ════════════════════════════════════ -->
                <div class="px-10 py-8 border-b border-gray-100">
                    <div class="grid grid-cols-2 gap-16">
                        <div>
                            <div class="h-14"></div>
                            <div class="border-t border-gray-400 pt-2">
                                <p class="text-xs text-gray-500">{{ t('purchaseReturns.confirmedBy') }}</p>
                            </div>
                        </div>
                        <div>
                            <div class="h-14"></div>
                            <div class="border-t border-gray-400 pt-2 text-right">
                                <p class="text-xs text-gray-500">{{ t('purchaseReturns.authorizedSignature') }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ══ FOOTER ═════════════════════════════════════════════ -->
                <div class="px-10 py-4 bg-gray-50 print:bg-white">
                    <div class="flex items-center justify-between text-xs text-gray-400">
                        <span>{{ t('purchaseReturns.printDate') }}: {{ nowFormatted }}</span>
                        <span class="font-medium">{{ settings?.company_name ?? 'POSmeister' }} · POSmeister</span>
                    </div>
                </div>

            </div>

            <div v-else class="text-center py-24">
                <ExclamationTriangleIcon class="w-12 h-12 text-red-400 mx-auto mb-3" />
                <p class="text-red-600 font-medium">Failed to load return document.</p>
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
import {
    ArrowLeftIcon, PrinterIcon, PhoneIcon, EnvelopeIcon,
    ArrowUturnLeftIcon, ExclamationTriangleIcon,
} from '@heroicons/vue/24/outline';

const { t }         = useI18n();
const route         = useRoute();
const settingsStore = useSettingsStore();

const ret     = ref(null);
const loading = ref(true);
const settings = computed(() => settingsStore.settings);
const currencyCode = computed(() => settings.value?.currency_code ?? 'EUR');

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

const amountInWords = computed(() => ret.value ? toWordsDE(ret.value.total_amount) : '');

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
        if (n < 100)  { const t = Math.floor(n / 10), o = n % 10; return o === 0 ? tW[t] : oW[o] + 'und' + tW[t]; }
        if (n < 1000) { const h = Math.floor(n / 100), r = n % 100; return (h === 1 ? 'ein' : oW[h]) + 'hundert' + (r ? conv(r) : ''); }
        if (n < 1e6)  { const th = Math.floor(n / 1000), r = n % 1000; return (th === 1 ? 'ein' : conv(th)) + 'tausend' + (r ? conv(r) : ''); }
        return n.toLocaleString('de-DE');
    }
    const ew = conv(euros);
    const euroWord = ew.charAt(0).toUpperCase() + ew.slice(1);
    const centStr  = cents === 0 ? '00' : (cents < 10 ? '0' + cents : String(cents));
    return `${euroWord} ${currencyCode.value} und ${centStr} Cent`;
}

onMounted(async () => {
    await settingsStore.load();
    try {
        const { data } = await purchaseService.returnShow(route.params.id);
        ret.value = data.data ?? data;
    } catch {
        ret.value = null;
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
