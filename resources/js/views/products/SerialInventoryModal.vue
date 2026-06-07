<template>
    <!--
        Premium Serial Inventory modal.

        Layout:
          - Sticky header with product name + close
          - KPI strip: total / in_stock / sold / under_warranty
          - Filter bar: search, status pills (multi), branch select, export
          - Either: stacked card list (mobile) OR data table (lg+)
          - Footer with pagination

        The same data renders two ways so phones get a thumb-friendly
        card layout while desktop keeps the compact tabular view.
    -->
    <Modal :open="open" size="xl" @close="$emit('close')">
        <template #title>
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 grid place-items-center text-white flex-shrink-0">
                    <CpuChipIcon class="w-5 h-5" />
                </div>
                <div class="min-w-0">
                    <p class="text-[10px] font-semibold uppercase tracking-wider text-indigo-600">{{ t('serials.module') }}</p>
                    <h3 class="text-base font-semibold text-slate-900 dark:text-slate-100 truncate">
                        {{ product?.name }}
                    </h3>
                    <p v-if="product?.sku" class="text-xs text-slate-500 font-mono truncate">{{ product.sku }}</p>
                </div>
            </div>
        </template>

        <div class="p-3 sm:p-5 space-y-4">

            <!-- ── KPI strip ────────────────────────────────────────────── -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
                <KpiTile :label="t('serials.kpi.total')"        :value="kpis.total"            tone="slate" />
                <KpiTile :label="t('serials.kpi.inStock')"      :value="kpis.in_stock"         tone="emerald" />
                <KpiTile :label="t('serials.kpi.sold')"         :value="kpis.sold"             tone="indigo" />
                <KpiTile :label="t('serials.kpi.underWarranty')":value="kpis.under_warranty"  tone="amber" />
            </div>

            <!-- ── Filter bar ───────────────────────────────────────────── -->
            <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 p-3 space-y-3">
                <div class="flex flex-col sm:flex-row gap-2.5 sm:items-center">
                    <div class="relative flex-1">
                        <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 pointer-events-none" />
                        <input
                            v-model="search"
                            type="search"
                            :placeholder="t('serials.searchPlaceholder')"
                            class="w-full pl-9 pr-3 py-2 text-sm border border-slate-300 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        />
                    </div>
                    <button
                        type="button"
                        @click="exportCsv"
                        :disabled="!rows.length"
                        class="inline-flex items-center justify-center gap-1.5 px-3 py-2 text-xs font-semibold text-slate-700 dark:text-slate-200 bg-slate-50 dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 border border-slate-200 dark:border-slate-700 rounded-lg transition-colors disabled:opacity-40"
                        :title="t('serials.exportCsv')"
                    >
                        <ArrowDownTrayIcon class="w-4 h-4" />
                        <span>{{ t('serials.export') }}</span>
                    </button>
                </div>

                <!-- Status filter pills — mobile scrolls horizontally -->
                <div class="-mx-1 px-1 flex gap-1.5 overflow-x-auto pb-0.5">
                    <button v-for="opt in statusOptions" :key="opt.value"
                        type="button"
                        @click="toggleStatus(opt.value)"
                        :class="['status-pill', isStatusActive(opt.value) ? 'is-active' : '', toneClass(opt.tone)]"
                    >
                        <span :class="['w-1.5 h-1.5 rounded-full', dotClass(opt.tone)]" />
                        {{ opt.label }}
                        <span v-if="isStatusActive(opt.value)" class="text-[10px] opacity-70">×</span>
                    </button>
                </div>
            </div>

            <!-- ── Error banner ─────────────────────────────────────────── -->
            <div v-if="error" class="rounded-lg border border-rose-200 bg-rose-50 dark:bg-rose-950/30 dark:border-rose-900 px-3 py-2 text-sm text-rose-700 dark:text-rose-300">
                {{ error }}
            </div>

            <!-- ── Loading skeleton ─────────────────────────────────────── -->
            <div v-if="loading && !rows.length" class="space-y-2">
                <div v-for="i in 4" :key="i"
                     class="h-14 rounded-lg bg-slate-100 dark:bg-slate-800 animate-pulse"
                ></div>
            </div>

            <!-- ── Empty state ──────────────────────────────────────────── -->
            <div v-else-if="!loading && !rows.length"
                 class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 py-12 text-center">
                <div class="w-12 h-12 rounded-2xl bg-slate-100 dark:bg-slate-800 text-slate-400 grid place-items-center mx-auto mb-3">
                    <InboxIcon class="w-6 h-6" />
                </div>
                <h4 class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ t('serials.emptyTitle') }}</h4>
                <p class="text-xs text-slate-500 mt-1">{{ t('serials.emptyDescription') }}</p>
            </div>

            <!-- ── Mobile: stacked cards ────────────────────────────────── -->
            <div v-else class="lg:hidden space-y-2">
                <article v-for="row in rows" :key="row.id" @click="openDetail(row.id)" class="serial-card cursor-pointer">
                    <div class="flex items-start gap-3">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <p class="font-mono text-sm font-semibold text-slate-900 dark:text-slate-100 truncate">
                                    {{ row.serial_number }}
                                </p>
                                <StatusBadge :status="row.status" />
                            </div>
                            <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400 flex flex-wrap gap-x-2 gap-y-0.5">
                                <span v-if="row.branch_name">{{ row.branch_name }}</span>
                                <span v-if="row.purchase_date">· {{ t('serials.col.received') }} {{ row.purchase_date }}</span>
                            </p>
                        </div>
                        <WarrantyPill :expiry="row.warranty_expiry_date" :remaining="row.warranty_remaining_days" />
                    </div>
                </article>
            </div>

            <!-- ── Desktop: compact table ───────────────────────────────── -->
            <div v-if="rows.length" class="hidden lg:block overflow-hidden border border-slate-200 dark:border-slate-800 rounded-xl">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 dark:bg-slate-900/50 text-[10px] uppercase tracking-wider font-semibold text-slate-500">
                        <tr>
                            <th class="px-3 py-2 text-left">{{ t('serials.col.serial') }}</th>
                            <th class="px-3 py-2 text-left">{{ t('serials.col.status') }}</th>
                            <th class="px-3 py-2 text-left">{{ t('serials.col.branch') }}</th>
                            <th class="px-3 py-2 text-left">{{ t('serials.col.received') }}</th>
                            <th class="px-3 py-2 text-left">{{ t('serials.col.warranty') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr v-for="row in rows" :key="row.id" @click="openDetail(row.id)" class="hover:bg-slate-50/60 dark:hover:bg-slate-800/40 cursor-pointer">
                            <td class="px-3 py-2 font-mono text-slate-900 dark:text-slate-100">{{ row.serial_number }}</td>
                            <td class="px-3 py-2"><StatusBadge :status="row.status" /></td>
                            <td class="px-3 py-2 text-slate-600 dark:text-slate-400">{{ row.branch_name || '—' }}</td>
                            <td class="px-3 py-2 text-slate-600 dark:text-slate-400">{{ row.purchase_date || '—' }}</td>
                            <td class="px-3 py-2"><WarrantyPill :expiry="row.warranty_expiry_date" :remaining="row.warranty_remaining_days" /></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- ── Pagination ───────────────────────────────────────────── -->
            <div v-if="meta.last_page > 1" class="flex items-center justify-between text-xs">
                <span class="text-slate-500">
                    {{ t('common.page') }} {{ meta.current_page }} / {{ meta.last_page }}
                    · {{ meta.total }} {{ t('serials.total') }}
                </span>
                <div class="flex items-center gap-1">
                    <button @click="goPage(meta.current_page - 1)" :disabled="meta.current_page <= 1" class="page-btn">
                        <ChevronLeftIcon class="w-4 h-4" />
                    </button>
                    <button @click="goPage(meta.current_page + 1)" :disabled="meta.current_page >= meta.last_page" class="page-btn">
                        <ChevronRightIcon class="w-4 h-4" />
                    </button>
                </div>
            </div>
        </div>
    </Modal>

    <!-- Phase Y Round 2C — Serial timeline drawer -->
    <SerialDetailDrawer
        :open="detailOpen"
        :serial-id="detailSerialId"
        @close="detailOpen = false"
    />
</template>

<script setup>
import { ref, reactive, computed, watch, h } from 'vue';
import { useI18n } from 'vue-i18n';
import {
    CpuChipIcon, MagnifyingGlassIcon, ArrowDownTrayIcon, InboxIcon,
    ChevronLeftIcon, ChevronRightIcon,
} from '@heroicons/vue/24/outline';
import Modal from '@/components/ui/Modal.vue';
import SerialDetailDrawer from '@/views/serials/SerialDetailDrawer.vue';
import { useDebounce } from '@vueuse/core';
import { serialService } from '@/services/serialService';

const props = defineProps({
    open:    { type: Boolean, required: true },
    product: { type: Object,  default: null },
});

defineEmits(['close']);

const { t } = useI18n();

// ── State ──────────────────────────────────────────────────────────────────
const rows    = ref([]);
const meta    = reactive({ current_page: 1, last_page: 1, per_page: 25, total: 0 });
const loading = ref(false);
const error   = ref('');
const search  = ref('');
const statuses = ref([]);                    // multi-select status filter
const debouncedSearch = useDebounce(search, 280);

// Phase Y Round 2C — drawer state for the timeline view.
const detailOpen     = ref(false);
const detailSerialId = ref(null);
function openDetail(id) {
    detailSerialId.value = id;
    detailOpen.value = true;
}

// Aggregate KPI counts derived from the full filtered set (current page
// for now — backend aggregate endpoint can come in Round 2C if needed).
const kpis = computed(() => {
    const base = { total: meta.total, in_stock: 0, sold: 0, under_warranty: 0 };
    for (const r of rows.value) {
        if (r.status === 'in_stock')  base.in_stock++;
        if (r.status === 'sold')      base.sold++;
        if (r.is_under_warranty)      base.under_warranty++;
    }
    return base;
});

// ── Status pills ───────────────────────────────────────────────────────────
const statusOptions = computed(() => [
    { value: 'in_stock',          label: t('serials.status.in_stock'),          tone: 'emerald' },
    { value: 'sold',              label: t('serials.status.sold'),              tone: 'indigo'  },
    { value: 'reserved',          label: t('serials.status.reserved'),          tone: 'amber'   },
    { value: 'sales_returned',    label: t('serials.status.sales_returned'),    tone: 'sky'     },
    { value: 'purchase_returned', label: t('serials.status.purchase_returned'), tone: 'slate'   },
    { value: 'damaged',           label: t('serials.status.damaged'),           tone: 'rose'    },
    { value: 'lost',              label: t('serials.status.lost'),              tone: 'rose'    },
]);

function isStatusActive(v) { return statuses.value.includes(v); }
function toggleStatus(v) {
    const i = statuses.value.indexOf(v);
    if (i >= 0) statuses.value.splice(i, 1);
    else        statuses.value.push(v);
}

// ── Tone helpers (pills + dots) ────────────────────────────────────────────
const toneMap = {
    emerald: 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-900/30 dark:text-emerald-200 dark:border-emerald-800',
    indigo:  'bg-indigo-50  text-indigo-700  border-indigo-200  dark:bg-indigo-900/30  dark:text-indigo-200  dark:border-indigo-800',
    amber:   'bg-amber-50   text-amber-700   border-amber-200   dark:bg-amber-900/30   dark:text-amber-200   dark:border-amber-800',
    rose:    'bg-rose-50    text-rose-700    border-rose-200    dark:bg-rose-900/30    dark:text-rose-200    dark:border-rose-800',
    sky:     'bg-sky-50     text-sky-700     border-sky-200     dark:bg-sky-900/30     dark:text-sky-200     dark:border-sky-800',
    slate:   'bg-slate-50   text-slate-700   border-slate-200   dark:bg-slate-800      dark:text-slate-200   dark:border-slate-700',
};
const dotMap = {
    emerald: 'bg-emerald-500', indigo: 'bg-indigo-500', amber: 'bg-amber-500',
    rose: 'bg-rose-500', sky: 'bg-sky-500', slate: 'bg-slate-400',
};
function toneClass(t) { return toneMap[t] || toneMap.slate; }
function dotClass(t)  { return dotMap[t]  || dotMap.slate;  }

// ── Inline functional components ───────────────────────────────────────────
const KpiTile = (props) => h('div', { class: 'rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 px-3 py-2.5' }, [
    h('p', { class: 'text-[10px] uppercase tracking-wider font-semibold text-slate-500' }, props.label),
    h('p', { class: `text-lg sm:text-xl font-bold mt-0.5 tabular-nums ${{
        slate: 'text-slate-900 dark:text-slate-100', emerald: 'text-emerald-600',
        indigo: 'text-indigo-600', amber: 'text-amber-600',
    }[props.tone] || 'text-slate-900 dark:text-slate-100'}` }, String(props.value ?? 0)),
]);
KpiTile.props = ['label', 'value', 'tone'];

const StatusBadge = (props) => {
    const cfg = {
        in_stock:          { tone: 'emerald', label: t('serials.status.in_stock') },
        sold:              { tone: 'indigo',  label: t('serials.status.sold') },
        reserved:          { tone: 'amber',   label: t('serials.status.reserved') },
        sales_returned:    { tone: 'sky',     label: t('serials.status.sales_returned') },
        purchase_returned: { tone: 'slate',   label: t('serials.status.purchase_returned') },
        damaged:           { tone: 'rose',    label: t('serials.status.damaged') },
        lost:              { tone: 'rose',    label: t('serials.status.lost') },
    }[props.status] || { tone: 'slate', label: props.status };
    return h('span', { class: `inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-[10px] font-semibold border ${toneMap[cfg.tone]}` }, [
        h('span', { class: `w-1 h-1 rounded-full ${dotMap[cfg.tone]}` }),
        cfg.label,
    ]);
};
StatusBadge.props = ['status'];

const WarrantyPill = (props) => {
    if (!props.expiry) return h('span', { class: 'text-[11px] text-slate-400' }, '—');
    const days = props.remaining;
    let tone = 'emerald', label = t('serials.warranty.valid');
    if (days != null) {
        if (days < 0)       { tone = 'rose';    label = t('serials.warranty.expired'); }
        else if (days <= 30){ tone = 'amber';   label = t('serials.warranty.expiringSoon', { n: days }); }
        else                { tone = 'emerald'; label = t('serials.warranty.daysLeft', { n: days }); }
    }
    return h('span', { class: `inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-[10px] font-semibold border ${toneMap[tone]}` }, [
        h('span', { class: `w-1 h-1 rounded-full ${dotMap[tone]}` }),
        label,
    ]);
};
WarrantyPill.props = ['expiry', 'remaining'];

// ── Fetch ──────────────────────────────────────────────────────────────────
let fetchToken = 0;

async function fetchPage(page = 1) {
    if (!props.product?.id) return;
    const myToken = ++fetchToken;
    loading.value = true;
    error.value   = '';
    try {
        // The backend accepts a single status today; for multi-select we
        // pass the first one and filter client-side for the rest. Backend
        // bulk-status filter is a Round 2C upgrade.
        const params = {
            page,
            per_page: meta.per_page,
            q:        search.value || undefined,
            status:   statuses.value[0] || undefined,
        };
        const { data } = await serialService.listForProduct(props.product.id, params);
        if (myToken !== fetchToken) return;        // stale response — drop
        let list = data.data ?? [];
        if (statuses.value.length > 1) {
            list = list.filter(r => statuses.value.includes(r.status));
        }
        rows.value = list;
        Object.assign(meta, data.meta ?? {});
    } catch (err) {
        if (myToken !== fetchToken) return;
        error.value = err?.response?.data?.message ?? t('common.unexpectedError');
        rows.value = [];
    } finally {
        if (myToken === fetchToken) loading.value = false;
    }
}

function goPage(p) {
    if (p < 1 || p > meta.last_page || p === meta.current_page) return;
    fetchPage(p);
}

watch(() => props.open, (isOpen) => {
    if (!isOpen) return;
    statuses.value = [];
    search.value   = '';
    rows.value     = [];
    meta.current_page = 1;
    fetchPage(1);
});

watch(debouncedSearch, () => { if (props.open) fetchPage(1); });
watch(statuses, () => { if (props.open) fetchPage(1); }, { deep: true });

// ── CSV export — pure client side, no backend needed ───────────────────────
function exportCsv() {
    if (!rows.value.length) return;
    const header = ['serial_number', 'status', 'branch', 'purchase_date',
                    'sale_date', 'warranty_expiry_date', 'warranty_remaining_days'];
    const csv = [header.join(',')];
    for (const r of rows.value) {
        csv.push([
            r.serial_number, r.status, r.branch_name ?? '',
            r.purchase_date ?? '', r.sale_date ?? '',
            r.warranty_expiry_date ?? '', r.warranty_remaining_days ?? '',
        ].map(v => `"${String(v).replace(/"/g, '""')}"`).join(','));
    }
    const blob = new Blob([csv.join('\n')], { type: 'text/csv;charset=utf-8' });
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href = url;
    a.download = `serials-${props.product?.sku || 'export'}-${new Date().toISOString().slice(0,10)}.csv`;
    a.click();
    URL.revokeObjectURL(url);
}
</script>

<style scoped>
@reference '../../../css/app.css';

.status-pill {
    @apply inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold border whitespace-nowrap transition-colors;
}
.status-pill:not(.is-active) {
    @apply bg-white dark:bg-slate-900 text-slate-500 dark:text-slate-400 border-slate-200 dark:border-slate-800
           hover:border-slate-300 hover:text-slate-700;
}
.status-pill.is-active { @apply ring-1 ring-current ring-offset-0; }

.serial-card {
    @apply bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl px-3 py-2.5 transition-shadow;
    box-shadow: 0 1px 2px rgba(15,23,42,0.04);
}
.serial-card:active { transform: scale(0.99); }

.page-btn {
    @apply inline-flex items-center justify-center w-8 h-8 rounded-lg border border-slate-200 dark:border-slate-800
           text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 disabled:opacity-40
           disabled:cursor-not-allowed transition-colors;
}
</style>
