<template>
    <div class="p-6 lg:p-8 space-y-5 max-w-7xl mx-auto">

        <header class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 tracking-tight">{{ t('accounting.journal.title') }}</h1>
                <p class="mt-1 text-sm text-slate-500">{{ t('accounting.journal.subtitle') }}</p>
            </div>
            <button @click="openManual" class="btn-primary">
                <PlusIcon class="w-4 h-4" />
                {{ t('accounting.journal.addManual') }}
            </button>
        </header>

        <div class="card flex flex-wrap items-end gap-3">
            <div>
                <label class="lbl">{{ t('common.dateFrom') }}</label>
                <input v-model="filters.from" @change="load" type="date" class="ctrl w-40" />
            </div>
            <div>
                <label class="lbl">{{ t('common.dateTo') }}</label>
                <input v-model="filters.to" @change="load" type="date" class="ctrl w-40" />
            </div>
            <div>
                <label class="lbl">{{ t('accounting.fields.refType') }}</label>
                <select v-model="filters.reference_type" @change="load" class="ctrl">
                    <option value="">{{ t('common.all') }}</option>
                    <option value="sale">{{ t('accounting.refType.sale') }}</option>
                    <option value="purchase">{{ t('accounting.refType.purchase') }}</option>
                    <option value="expense">{{ t('accounting.refType.expense') }}</option>
                    <option value="payslip">{{ t('accounting.refType.payslip') }}</option>
                    <option value="customer_payment">{{ t('accounting.refType.customer_payment') }}</option>
                    <option value="supplier_payment">{{ t('accounting.refType.supplier_payment') }}</option>
                    <option value="manual">{{ t('accounting.refType.manual') }}</option>
                    <option value="reversal">{{ t('accounting.refType.reversal') }}</option>
                </select>
            </div>
            <div>
                <label class="lbl">{{ t('common.status') }}</label>
                <select v-model="filters.status" @change="load" class="ctrl">
                    <option value="">{{ t('common.all') }}</option>
                    <option value="draft">{{ t('accounting.status.draft') }}</option>
                    <option value="posted">{{ t('accounting.status.posted') }}</option>
                    <option value="reversed">{{ t('accounting.status.reversed') }}</option>
                </select>
            </div>
        </div>

        <div class="card overflow-hidden p-0">
            <table class="w-full text-sm">
                <thead class="bg-slate-50/70">
                    <tr class="text-left text-[11px] text-slate-500 uppercase tracking-wide border-b border-slate-100">
                        <th class="px-4 py-2.5">{{ t('accounting.fields.entryDate') }}</th>
                        <th class="px-4 py-2.5">{{ t('accounting.fields.entryNumber') }}</th>
                        <th class="px-4 py-2.5">{{ t('accounting.fields.refType') }}</th>
                        <th class="px-4 py-2.5">{{ t('accounting.fields.narration') }}</th>
                        <th class="px-4 py-2.5 text-right">{{ t('accounting.fields.debit') }}</th>
                        <th class="px-4 py-2.5 text-right">{{ t('accounting.fields.credit') }}</th>
                        <th class="px-4 py-2.5 text-center">{{ t('common.status') }}</th>
                        <th class="px-4 py-2.5 text-right w-32"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <tr v-for="e in rows" :key="e.id" class="hover:bg-slate-50/60">
                        <td class="px-4 py-2 font-mono text-xs text-slate-600">{{ formatDate(e.entry_date) }}</td>
                        <td class="px-4 py-2 font-mono text-xs">
                            <button class="text-indigo-600 hover:underline" @click="openDetail(e)">
                                {{ e.entry_number }}
                            </button>
                        </td>
                        <td class="px-4 py-2">
                            <span class="text-[10px] uppercase tracking-wider px-2 py-0.5 rounded-md font-semibold"
                                  :class="refClass(e.reference_type)">
                                {{ t('accounting.refType.' + (e.reference_type ?? 'manual')) }}
                            </span>
                        </td>
                        <td class="px-4 py-2 text-slate-700 truncate max-w-md">{{ e.narration }}</td>
                        <td class="px-4 py-2 text-right font-mono">{{ fmt(e.total_debit) }}</td>
                        <td class="px-4 py-2 text-right font-mono">{{ fmt(e.total_credit) }}</td>
                        <td class="px-4 py-2 text-center">
                            <span :class="statusClass(e.status)" class="text-[10px] uppercase tracking-wider px-2 py-0.5 rounded-md font-semibold">
                                {{ t('accounting.status.' + e.status) }}
                            </span>
                        </td>
                        <td class="px-4 py-2 text-right">
                            <button v-if="e.status === 'posted'" @click="reverse(e)" class="text-xs text-rose-600 hover:underline">
                                {{ t('accounting.journal.reverse') }}
                            </button>
                        </td>
                    </tr>
                    <tr v-if="!loading && rows.length === 0">
                        <td colspan="8" class="py-10 text-center text-sm text-slate-400">{{ t('accounting.journal.empty') }}</td>
                    </tr>
                </tbody>
            </table>
            <div v-if="meta && meta.last_page > 1" class="flex items-center justify-between px-4 py-3 border-t border-slate-100 text-xs">
                <span class="text-slate-500">
                    {{ t('common.page') }} {{ meta.current_page }} / {{ meta.last_page }} · {{ meta.total }} {{ t('common.records') }}
                </span>
                <div class="flex items-center gap-1">
                    <button @click="loadPage(meta.current_page - 1)" :disabled="meta.current_page <= 1" class="px-2 py-1 border border-slate-300 rounded disabled:opacity-40">‹</button>
                    <button @click="loadPage(meta.current_page + 1)" :disabled="meta.current_page >= meta.last_page" class="px-2 py-1 border border-slate-300 rounded disabled:opacity-40">›</button>
                </div>
            </div>
        </div>

        <JournalEntryDetailModal v-if="detail" :entry="detail" @close="detail = null" />
        <ManualJournalModal v-if="manualOpen" @close="manualOpen = false" @saved="onManualSaved" />

    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { journalEntryService } from '@/services/accountingService';
import { useCurrency } from '@/composables/useCurrency';
import { useAlert } from '@/composables/useAlert';
import { PlusIcon } from '@heroicons/vue/24/outline';
import JournalEntryDetailModal from './JournalEntryDetailModal.vue';
import ManualJournalModal from './ManualJournalModal.vue';

const { t, locale } = useI18n();
const { fmtCurrency } = useCurrency();
const { toast, confirm } = useAlert();

const rows = ref([]);
const meta = ref(null);
const loading = ref(false);

const today = new Date().toISOString().slice(0, 10);
const monthStart = (() => { const d = new Date(); return new Date(d.getFullYear(), d.getMonth(), 1).toISOString().slice(0, 10); })();
const filters = reactive({ from: monthStart, to: today, reference_type: '', status: '' });

const detail = ref(null);
const manualOpen = ref(false);

function fmt(v) { return v === null || v === undefined ? '' : fmtCurrency(v); }
function formatDate(d) {
    return new Intl.DateTimeFormat(locale.value || 'de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(new Date(d));
}

function refClass(type) {
    return {
        sale:             'bg-emerald-50 text-emerald-700',
        purchase:         'bg-indigo-50 text-indigo-700',
        expense:          'bg-rose-50 text-rose-700',
        payslip:          'bg-amber-50 text-amber-700',
        customer_payment: 'bg-emerald-50 text-emerald-700',
        supplier_payment: 'bg-rose-50 text-rose-700',
        manual:           'bg-slate-100 text-slate-700',
        reversal:         'bg-slate-100 text-slate-700',
    }[type] ?? 'bg-slate-100 text-slate-700';
}

function statusClass(status) {
    return {
        draft:    'bg-slate-100 text-slate-700',
        posted:   'bg-emerald-50 text-emerald-700',
        reversed: 'bg-amber-50 text-amber-700',
    }[status] ?? 'bg-slate-100 text-slate-700';
}

async function load() {
    loading.value = true;
    try {
        const { data } = await journalEntryService.index({
            from: filters.from || undefined,
            to: filters.to || undefined,
            reference_type: filters.reference_type || undefined,
            status: filters.status || undefined,
            per_page: 25,
        });
        rows.value = data.data ?? [];
        meta.value = { current_page: data.current_page, last_page: data.last_page, total: data.total };
    } finally {
        loading.value = false;
    }
}

async function loadPage(page) {
    loading.value = true;
    try {
        const { data } = await journalEntryService.index({
            from: filters.from || undefined,
            to: filters.to || undefined,
            reference_type: filters.reference_type || undefined,
            status: filters.status || undefined,
            per_page: 25,
            page,
        });
        rows.value = data.data ?? [];
        meta.value = { current_page: data.current_page, last_page: data.last_page, total: data.total };
    } finally {
        loading.value = false;
    }
}

async function openDetail(e) {
    const { data } = await journalEntryService.show(e.id);
    detail.value = data.data;
}

function openManual() { manualOpen.value = true; }

function onManualSaved() {
    manualOpen.value = false;
    load();
    toast.success(t('common.created'));
}

async function reverse(e) {
    const ok = await confirm(t('accounting.journal.reverseConfirm', { number: e.entry_number }));
    if (!ok) return;
    await journalEntryService.reverse(e.id, {});
    toast.success(t('accounting.journal.reversed'));
    load();
}

onMounted(load);
</script>

<style scoped>
@reference '../../../css/app.css';
.card        { @apply bg-white border border-slate-200 rounded-xl shadow-sm p-4; }
.btn-primary { @apply inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700 transition-colors; }
.lbl         { @apply block text-xs font-medium text-slate-600 mb-1; }
.ctrl        { @apply px-3 py-2 text-sm border border-slate-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent; }
</style>
