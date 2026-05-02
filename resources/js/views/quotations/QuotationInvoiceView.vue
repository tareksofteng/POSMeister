<template>
    <div class="min-h-screen bg-slate-100 print:bg-white">

        <!-- ── Action bar (hidden on print) ──────────────────────────── -->
        <div class="no-print sticky top-0 z-30 bg-white border-b border-slate-200 shadow-sm">
            <div class="max-w-4xl mx-auto px-6 h-14 flex items-center justify-between">
                <RouterLink :to="{ name: 'quotations' }" class="flex items-center gap-2 text-sm text-slate-600 hover:text-slate-900 transition-colors group">
                    <ArrowLeftIcon class="w-4 h-4 group-hover:-translate-x-0.5 transition-transform" />
                    {{ t('quotations.backToList') }}
                </RouterLink>
                <div class="flex items-center gap-3">
                    <span v-if="quotation" class="hidden sm:block text-sm font-mono font-semibold text-slate-500 bg-slate-100 px-3 py-1 rounded-lg">
                        {{ quotation.quotation_number }}
                    </span>

                    <!-- Status select dropdown for live update -->
                    <select
                        v-if="quotation && quotation.status !== 'converted'"
                        v-model="quotation.status"
                        @change="updateStatus($event.target.value)"
                        :class="['text-xs font-semibold rounded-lg px-3 py-1.5 border-0 cursor-pointer focus:outline-none focus:ring-2 focus:ring-violet-500',
                            statusBadge(quotation.status).bg, statusBadge(quotation.status).text]"
                    >
                        <option value="draft">{{ t('quotations.status_draft') }}</option>
                        <option value="sent">{{ t('quotations.status_sent') }}</option>
                        <option value="accepted">{{ t('quotations.status_accepted') }}</option>
                        <option value="rejected">{{ t('quotations.status_rejected') }}</option>
                        <option value="expired">{{ t('quotations.status_expired') }}</option>
                    </select>

                    <RouterLink
                        v-if="quotation && ['draft', 'sent'].includes(quotation.status)"
                        :to="{ name: 'quotation-edit', params: { id: quotation.id } }"
                        class="flex items-center gap-2 px-3 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-lg hover:bg-slate-50 transition-colors"
                    >
                        <PencilSquareIcon class="w-4 h-4" />
                        {{ t('common.edit') }}
                    </RouterLink>

                    <button
                        @click="printDoc"
                        class="flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white rounded-lg transition-all bg-gradient-to-r from-violet-600 to-purple-600 hover:from-violet-700 hover:to-purple-700 shadow-sm"
                    >
                        <PrinterIcon class="w-4 h-4" />
                        {{ t('quotations.print') }}
                    </button>
                </div>
            </div>
        </div>

        <div class="max-w-4xl mx-auto px-4 py-8 print:p-0 print:max-w-none">

            <!-- Loading -->
            <div v-if="loading" class="text-center py-24 text-slate-400">
                <div class="w-8 h-8 border-2 border-violet-500 border-t-transparent rounded-full animate-spin mx-auto mb-3" />
                <p class="text-sm">{{ t('common.loading') }}</p>
            </div>

            <!-- Document -->
            <div v-else-if="quotation" id="quote-paper" class="bg-white shadow-2xl rounded-xl overflow-hidden print:shadow-none print:rounded-none">

                <!-- Status banner (hidden on print except converted/rejected) -->
                <div v-if="quotation.status === 'converted'" class="bg-violet-50 border-b border-violet-200 px-10 py-2 text-center">
                    <p class="text-sm font-bold text-violet-700 uppercase tracking-widest">{{ t('quotations.status_converted') }}</p>
                </div>
                <div v-else-if="quotation.status === 'rejected'" class="no-print bg-red-50 border-b border-red-200 px-10 py-2 text-center">
                    <p class="text-sm font-bold text-red-600 uppercase tracking-widest">{{ t('quotations.status_rejected') }}</p>
                </div>

                <!-- ══ LETTERHEAD ═══════════════════════════════════════════ -->
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
                            <div v-else class="h-14 w-14 bg-violet-100 rounded-xl flex items-center justify-center flex-shrink-0">
                                <BuildingOffice2Icon class="w-8 h-8 text-violet-600" />
                            </div>
                            <div>
                                <h1 class="text-xl font-bold text-slate-900 leading-tight">
                                    {{ settings?.company_name ?? 'POSmeister' }}
                                </h1>
                                <p v-if="settings?.address" class="text-xs text-slate-500 mt-0.5 whitespace-pre-line leading-relaxed">
                                    {{ settings.address }}
                                </p>
                                <div class="flex items-center gap-3 mt-1 flex-wrap">
                                    <span v-if="settings?.phone" class="text-xs text-slate-400 flex items-center gap-1">
                                        <PhoneIcon class="w-3 h-3" /> {{ settings.phone }}
                                    </span>
                                    <span v-if="settings?.email" class="text-xs text-slate-400 flex items-center gap-1">
                                        <EnvelopeIcon class="w-3 h-3" /> {{ settings.email }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Document title block -->
                        <div class="text-right flex-shrink-0">
                            <div class="text-white px-5 py-2 rounded-lg inline-block mb-3 bg-gradient-to-r from-violet-600 to-purple-600">
                                <p class="text-xs font-bold tracking-[0.2em] uppercase">{{ t('quotations.docTitle') }}</p>
                            </div>
                            <div class="space-y-0.5">
                                <p class="font-mono font-bold text-slate-900 text-lg">{{ quotation.quotation_number }}</p>
                                <p class="text-sm text-slate-500">{{ formatDate(quotation.quotation_date) }}</p>
                                <p v-if="quotation.valid_until" class="text-xs" :class="quotation.is_expired ? 'text-red-500' : 'text-emerald-600'">
                                    {{ t('quotations.validUntilLabel') }}: {{ formatDate(quotation.valid_until) }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Separator -->
                <div class="mx-10 border-t-4 border-violet-600 mb-0" />

                <!-- ══ CUSTOMER + META ═══════════════════════════════════ -->
                <div class="px-10 py-6 grid grid-cols-2 gap-10 bg-slate-50/50 print:bg-white border-b border-slate-100">

                    <!-- Recipient block (German "An:" letterhead style) -->
                    <div>
                        <p class="text-[10px] font-bold text-violet-600 uppercase tracking-[0.15em] mb-2">
                            {{ t('quotations.recipientLabel') }}
                        </p>
                        <div class="text-sm space-y-0.5">
                            <p class="font-semibold text-slate-900 text-base">
                                {{ quotation.customer_name || t('common.walkin') }}
                            </p>
                            <p v-if="quotation.customer_address" class="text-slate-600 text-xs leading-relaxed whitespace-pre-line">
                                {{ quotation.customer_address }}
                            </p>
                            <p v-if="quotation.customer_phone" class="text-slate-500 text-xs">
                                Tel.: {{ quotation.customer_phone }}
                            </p>
                            <p v-if="quotation.customer_email" class="text-slate-500 text-xs">
                                {{ quotation.customer_email }}
                            </p>
                        </div>
                    </div>

                    <!-- Quotation metadata -->
                    <div>
                        <p class="text-[10px] font-bold text-violet-600 uppercase tracking-[0.15em] mb-2">
                            {{ t('quotations.detailsLabel') }}
                        </p>
                        <dl class="text-sm space-y-1.5">
                            <div class="flex justify-between items-center gap-4">
                                <dt class="text-slate-500 text-xs">{{ t('quotations.quotationDate') }}</dt>
                                <dd class="font-medium text-slate-900 text-xs">{{ formatDate(quotation.quotation_date) }}</dd>
                            </div>
                            <div v-if="quotation.valid_until" class="flex justify-between items-center gap-4">
                                <dt class="text-slate-500 text-xs">{{ t('quotations.validUntil') }}</dt>
                                <dd class="font-medium text-xs" :class="quotation.is_expired ? 'text-red-600' : 'text-slate-900'">
                                    {{ formatDate(quotation.valid_until) }}
                                </dd>
                            </div>
                            <div class="flex justify-between items-center gap-4">
                                <dt class="text-slate-500 text-xs">{{ t('branches.title') }}</dt>
                                <dd class="font-medium text-slate-900 text-xs">{{ quotation.branch_name }}</dd>
                            </div>
                            <div class="flex justify-between items-center gap-4">
                                <dt class="text-slate-500 text-xs">{{ t('quotations.createdBy') }}</dt>
                                <dd class="font-medium text-slate-900 text-xs">{{ quotation.created_by_name ?? '—' }}</dd>
                            </div>
                            <div class="flex justify-between items-center gap-4">
                                <dt class="text-slate-500 text-xs">{{ t('common.status') }}</dt>
                                <dd>
                                    <span :class="['inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold', statusBadge(quotation.status).bg, statusBadge(quotation.status).text]">
                                        {{ t(`quotations.status_${quotation.status}`) }}
                                    </span>
                                </dd>
                            </div>
                        </dl>
                    </div>
                </div>

                <!-- ══ INTRO TEXT ═══════════════════════════════════════ -->
                <div class="px-10 pt-6">
                    <p class="text-sm text-slate-700 leading-relaxed">
                        {{ t('quotations.introText') }}
                    </p>
                </div>

                <!-- ══ LINE ITEMS TABLE ═══════════════════════════════════ -->
                <div class="px-10 py-6">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b-2 border-slate-200">
                                <th class="pb-2.5 text-center text-[10px] font-bold text-slate-400 uppercase tracking-wide w-8">
                                    {{ t('quotations.position') }}
                                </th>
                                <th class="pb-2.5 text-left text-[10px] font-bold text-slate-400 uppercase tracking-wide pl-3">
                                    {{ t('quotations.product') }}
                                </th>
                                <th class="pb-2.5 text-right text-[10px] font-bold text-slate-400 uppercase tracking-wide w-16">
                                    {{ t('quotations.qty') }}
                                </th>
                                <th class="pb-2.5 text-center text-[10px] font-bold text-slate-400 uppercase tracking-wide w-16">
                                    {{ t('quotations.unit') }}
                                </th>
                                <th class="pb-2.5 text-right text-[10px] font-bold text-slate-400 uppercase tracking-wide w-28">
                                    {{ t('quotations.unitPrice') }}
                                </th>
                                <th class="pb-2.5 text-right text-[10px] font-bold text-slate-400 uppercase tracking-wide w-16">
                                    {{ t('quotations.vatRate') }}
                                </th>
                                <th class="pb-2.5 text-right text-[10px] font-bold text-slate-400 uppercase tracking-wide w-28">
                                    {{ t('quotations.lineTotal') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(item, idx) in quotation.items"
                                :key="idx"
                                class="border-b border-slate-50 hover:bg-violet-50/30 transition-colors"
                            >
                                <td class="py-2.5 text-center text-xs text-slate-400 font-mono">{{ idx + 1 }}</td>
                                <td class="py-2.5 pl-3">
                                    <div class="flex items-center gap-2.5">
                                        <div v-if="item.image_url" class="w-9 h-9 rounded-lg overflow-hidden bg-slate-100 border border-slate-200 flex-shrink-0 no-print">
                                            <img :src="item.image_url" :alt="item.name" class="w-full h-full object-cover" />
                                        </div>
                                        <div>
                                            <span class="font-medium text-slate-900">{{ item.name }}</span>
                                            <span v-if="item.is_service" class="ml-1.5 text-[10px] uppercase tracking-wide text-violet-600 bg-violet-50 px-1.5 py-0.5 rounded">{{ t('quotations.service') }}</span>
                                            <span v-if="item.sku && item.sku !== '—'" class="ml-1.5 text-xs text-slate-400 font-mono">({{ item.sku }})</span>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-2.5 text-right font-mono text-slate-700 tabular-nums">{{ item.quantity }}</td>
                                <td class="py-2.5 text-center text-xs text-slate-500">{{ item.unit_name || (item.is_service ? t('quotations.service') : '—') }}</td>
                                <td class="py-2.5 text-right font-mono text-slate-700 tabular-nums">{{ fmtCurrency(item.unit_price) }}</td>
                                <td class="py-2.5 text-right text-xs text-slate-500">{{ item.tax_rate }} %</td>
                                <td class="py-2.5 text-right font-mono font-semibold text-slate-900 tabular-nums">{{ fmtCurrency(item.line_total) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- ══ TOTALS ═══════════════════════════════════════════ -->
                <div class="px-10 pb-6 flex justify-end border-b border-slate-100">
                    <div class="w-full sm:w-80">
                        <p class="text-[10px] font-bold text-violet-600 uppercase tracking-[0.15em] mb-3 text-right">
                            {{ t('quotations.summary') }}
                        </p>
                        <dl class="space-y-1.5 text-sm">
                            <div class="flex justify-between text-slate-700">
                                <dt>{{ t('quotations.subtotal') }}</dt>
                                <dd class="font-mono tabular-nums">{{ fmtCurrency(quotation.subtotal) }}</dd>
                            </div>
                            <div v-if="quotation.discount_amount > 0" class="flex justify-between text-emerald-700">
                                <dt>{{ t('quotations.discount') }}</dt>
                                <dd class="font-mono tabular-nums">− {{ fmtCurrency(quotation.discount_amount) }}</dd>
                            </div>
                            <div class="flex justify-between text-slate-700">
                                <dt>{{ t('quotations.vat') }} ({{ t('quotations.MwSt') }})</dt>
                                <dd class="font-mono tabular-nums">{{ fmtCurrency(quotation.vat_amount) }}</dd>
                            </div>
                            <div v-if="quotation.freight_amount > 0" class="flex justify-between text-slate-700">
                                <dt>{{ t('quotations.freight') }}</dt>
                                <dd class="font-mono tabular-nums">+ {{ fmtCurrency(quotation.freight_amount) }}</dd>
                            </div>
                            <div class="flex justify-between items-center pt-3 mt-1 border-t-2 border-violet-600">
                                <dt class="font-bold text-slate-900 text-base">{{ t('quotations.grandTotal') }}</dt>
                                <dd class="font-mono font-bold text-violet-700 text-lg tabular-nums">{{ fmtCurrency(quotation.grand_total) }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>

                <!-- ══ AMOUNT IN WORDS ═══════════════════════════════════ -->
                <div class="px-10 py-4 bg-slate-50/80 print:bg-white border-b border-slate-100">
                    <p class="text-sm text-slate-700 leading-relaxed">
                        <span class="font-semibold text-slate-900">{{ t('quotations.inWords') }}:</span>
                        {{ amountInWords }}
                    </p>
                </div>

                <!-- ══ TERMS & NOTES ═══════════════════════════════════════ -->
                <div v-if="quotation.terms || quotation.note" class="px-10 py-5 border-b border-slate-100 space-y-3">
                    <div v-if="quotation.terms">
                        <p class="text-[10px] font-bold text-violet-600 uppercase tracking-[0.15em] mb-1.5">{{ t('quotations.terms') }}</p>
                        <p class="text-sm text-slate-700 leading-relaxed whitespace-pre-line">{{ quotation.terms }}</p>
                    </div>
                    <div v-if="quotation.note">
                        <p class="text-[10px] font-bold text-violet-600 uppercase tracking-[0.15em] mb-1.5">{{ t('quotations.note') }}</p>
                        <p class="text-sm text-slate-700 leading-relaxed whitespace-pre-line">{{ quotation.note }}</p>
                    </div>
                </div>

                <!-- ══ CLOSING TEXT ═══════════════════════════════════════ -->
                <div class="px-10 py-5 border-b border-slate-100">
                    <p class="text-sm text-slate-700 leading-relaxed">
                        {{ t('quotations.closingText') }}
                    </p>
                    <p class="text-sm text-slate-700 mt-3">
                        {{ t('quotations.regards') }}<br>
                        <span class="font-semibold">{{ settings?.company_name ?? 'POSmeister' }}</span>
                    </p>
                </div>

                <!-- ══ SIGNATURE LINES ════════════════════════════════════ -->
                <div class="px-10 py-8 border-b border-slate-100">
                    <div class="grid grid-cols-2 gap-16">
                        <div>
                            <div class="h-14"></div>
                            <div class="border-t border-slate-400 pt-2">
                                <p class="text-xs text-slate-500">{{ t('quotations.customerSignature') }}</p>
                            </div>
                        </div>
                        <div>
                            <div class="h-14"></div>
                            <div class="border-t border-slate-400 pt-2 text-right">
                                <p class="text-xs text-slate-500">{{ t('quotations.authorizedSignature') }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ══ FOOTER ═══════════════════════════════════════════ -->
                <div class="px-10 py-4 bg-slate-50 print:bg-white">
                    <div class="flex items-center justify-between text-xs text-slate-400">
                        <span>{{ t('quotations.printDate') }}: {{ nowFormatted }}</span>
                        <span class="font-medium">{{ settings?.company_name ?? 'POSmeister' }} · POSmeister</span>
                    </div>
                </div>

            </div>

            <!-- Error -->
            <div v-else class="text-center py-24">
                <ExclamationTriangleIcon class="w-12 h-12 text-red-400 mx-auto mb-3" />
                <p class="text-red-600 font-medium">{{ t('common.unexpectedError') }}</p>
                <RouterLink :to="{ name: 'quotations' }" class="mt-4 inline-block text-sm text-violet-600 hover:underline">
                    {{ t('quotations.backToList') }}
                </RouterLink>
            </div>

        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute, RouterLink } from 'vue-router';
import { quotationService } from '@/services/quotationService';
import { useSettingsStore } from '@/stores/settings';
import { useAlert } from '@/composables/useAlert';
import { useCurrency } from '@/composables/useCurrency';

import {
    ArrowLeftIcon, PrinterIcon, PencilSquareIcon,
    BuildingOffice2Icon, PhoneIcon, EnvelopeIcon,
    ExclamationTriangleIcon,
} from '@heroicons/vue/24/outline';

const { t }           = useI18n();
const route           = useRoute();
const settingsStore   = useSettingsStore();
const { toast }       = useAlert();
const { fmtCurrency, currencyCode } = useCurrency();

const quotation = ref(null);
const loading   = ref(true);
const settings  = computed(() => settingsStore.settings);

function statusBadge(status) {
    const map = {
        draft:     { bg: 'bg-slate-100',   text: 'text-slate-700' },
        sent:      { bg: 'bg-blue-100',    text: 'text-blue-700' },
        accepted:  { bg: 'bg-emerald-100', text: 'text-emerald-700' },
        rejected:  { bg: 'bg-red-100',     text: 'text-red-700' },
        expired:   { bg: 'bg-amber-100',   text: 'text-amber-700' },
        converted: { bg: 'bg-violet-100',  text: 'text-violet-700' },
    };
    return map[status] ?? map.draft;
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

// ── German number-to-words ────────────────────────────────────────────────
const amountInWords = computed(() => {
    if (!quotation.value) return '';
    return toWordsDE(quotation.value.grand_total);
});

function toWordsDE(amount) {
    const n     = Math.round(parseFloat(amount ?? 0) * 100);
    const euros = Math.floor(n / 100);
    const cents = n % 100;

    const oW = ['', 'ein', 'zwei', 'drei', 'vier', 'fünf', 'sechs', 'sieben', 'acht', 'neun',
                 'zehn', 'elf', 'zwölf', 'dreizehn', 'vierzehn', 'fünfzehn', 'sechzehn',
                 'siebzehn', 'achtzehn', 'neunzehn'];
    const tW = ['', '', 'zwanzig', 'dreißig', 'vierzig', 'fünfzig', 'sechzig', 'siebzig', 'achtzig', 'neunzig'];

    function conv(num) {
        if (num === 0)   return 'null';
        if (num < 20)    return oW[num];
        if (num < 100) { const t2 = Math.floor(num / 10), o = num % 10; return o === 0 ? tW[t2] : oW[o] + 'und' + tW[t2]; }
        if (num < 1000) { const h = Math.floor(num / 100), r = num % 100; return (h === 1 ? 'ein' : oW[h]) + 'hundert' + (r ? conv(r) : ''); }
        if (num < 1e6)  { const th = Math.floor(num / 1000), r = num % 1000; return (th === 1 ? 'ein' : conv(th)) + 'tausend' + (r ? conv(r) : ''); }
        return num.toLocaleString('de-DE');
    }

    const euroWord = conv(euros).charAt(0).toUpperCase() + conv(euros).slice(1);
    const centStr  = cents === 0 ? '00' : (cents < 10 ? '0' + cents : String(cents));
    return `${euroWord} ${currencyCode.value} und ${centStr} Cent`;
}

// ── Actions ────────────────────────────────────────────────────────────────
function printDoc() {
    window.print();
}

async function updateStatus(newStatus) {
    try {
        const { data } = await quotationService.updateStatus(quotation.value.id, newStatus);
        quotation.value = data.data ?? data;
        toast('success', t('quotations.statusUpdated'));
    } catch (err) {
        toast('error', err.response?.data?.message ?? t('common.unexpectedError'));
    }
}

// ── Load ───────────────────────────────────────────────────────────────────
onMounted(async () => {
    await settingsStore.load();
    try {
        const { data } = await quotationService.show(route.params.id);
        quotation.value = data.data ?? data;
    } catch {
        quotation.value = null;
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
    #quote-paper { box-shadow: none !important; border-radius: 0 !important; }
}
</style>
