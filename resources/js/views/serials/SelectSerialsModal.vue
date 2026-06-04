<template>
    <!--
        Cashier-facing serial picker. Opens from PosView / SaleFormView
        when a serialized product is added to a line item. Loads the
        in-stock pool from /api/products/{id}/serials/available, lets the
        user multi-select or scan-paste a serial, and emits the picked
        IDs back to the parent. Quantity on the parent line then becomes
        a derived value: `_serial_ids.length`.

        Mobile: large tap rows, sticky confirm bar at the bottom.
        Desktop: dense table.
    -->
    <Modal :open="open" size="lg" @close="close">
        <template #title>
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-emerald-500 to-teal-600 grid place-items-center text-white flex-shrink-0">
                    <CheckBadgeIcon class="w-5 h-5" />
                </div>
                <div class="min-w-0">
                    <p class="text-[10px] font-semibold uppercase tracking-wider text-emerald-600">
                        {{ t('serials.pickModal.eyebrow') }}
                    </p>
                    <h3 class="text-base font-semibold text-slate-900 truncate">
                        {{ product?.name }}
                    </h3>
                    <p class="text-xs text-slate-500 font-mono truncate">
                        {{ product?.sku }}
                    </p>
                </div>
            </div>
        </template>

        <div class="p-3 sm:p-5 space-y-3">

            <!-- ── KPI summary ──────────────────────────────────────────── -->
            <div class="grid grid-cols-3 gap-2.5">
                <KpiTile :label="t('serials.pickModal.available')" :value="available.length" tone="slate" />
                <KpiTile :label="t('serials.pickModal.picked')"    :value="picked.size"      :tone="picked.size > 0 ? 'emerald' : 'slate'" />
                <KpiTile :label="t('serials.pickModal.qty')"       :value="picked.size"      tone="indigo" />
            </div>

            <!-- ── Scan-or-search box ───────────────────────────────────── -->
            <div class="relative">
                <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 pointer-events-none" />
                <input
                    ref="searchInputRef"
                    v-model="search"
                    @keydown.enter.prevent="onScannerEnter"
                    type="search"
                    inputmode="text"
                    autocomplete="off"
                    spellcheck="false"
                    :placeholder="t('serials.pickModal.scanPlaceholder')"
                    class="w-full pl-9 pr-3 py-2 text-sm border border-slate-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500"
                />
                <p class="mt-1 text-[11px] text-slate-500">
                    {{ t('serials.pickModal.scanHint') }}
                </p>
            </div>

            <!-- ── Error banner ─────────────────────────────────────────── -->
            <div v-if="error" class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">
                {{ error }}
            </div>

            <!-- ── Loading skeleton ─────────────────────────────────────── -->
            <div v-if="loading" class="space-y-1.5">
                <div v-for="i in 4" :key="i" class="h-11 rounded-lg bg-slate-100 animate-pulse"></div>
            </div>

            <!-- ── Empty state ──────────────────────────────────────────── -->
            <div v-else-if="!available.length"
                 class="rounded-xl border border-slate-200 bg-white py-10 text-center">
                <div class="w-12 h-12 rounded-2xl bg-slate-100 text-slate-400 grid place-items-center mx-auto mb-3">
                    <InboxIcon class="w-6 h-6" />
                </div>
                <h4 class="text-sm font-semibold text-slate-700">{{ t('serials.pickModal.emptyTitle') }}</h4>
                <p class="text-xs text-slate-500 mt-1">{{ t('serials.pickModal.emptyDescription') }}</p>
            </div>

            <!-- ── List of available serials ────────────────────────────── -->
            <div v-else class="rounded-xl border border-slate-200 bg-white overflow-hidden">
                <!-- Toolbar -->
                <div class="flex items-center justify-between gap-2 px-3 py-2 border-b border-slate-100 bg-slate-50">
                    <span class="text-[11px] uppercase tracking-wider font-semibold text-slate-500">
                        {{ t('serials.pickModal.results', { n: filtered.length }) }}
                    </span>
                    <div class="flex items-center gap-1.5">
                        <button type="button" @click="selectAllFiltered"
                                class="text-[11px] font-semibold text-emerald-700 hover:underline">
                            {{ t('serials.pickModal.selectAll') }}
                        </button>
                        <span class="text-slate-300">·</span>
                        <button type="button" @click="clearSelection"
                                class="text-[11px] font-semibold text-slate-600 hover:underline">
                            {{ t('serials.pickModal.clear') }}
                        </button>
                    </div>
                </div>

                <!-- Virtual scroll container — caps render at 400 rows; the
                     backend already caps at 500, anything larger should use
                     the search box to narrow down. -->
                <ul class="max-h-[44vh] overflow-y-auto divide-y divide-slate-100">
                    <li v-for="row in filtered.slice(0, 400)" :key="row.id"
                        @click="toggle(row.id)"
                        :class="['flex items-center gap-3 px-3 py-2.5 cursor-pointer transition-colors',
                                 picked.has(row.id) ? 'bg-emerald-50' : 'hover:bg-slate-50']">
                        <span :class="['flex-shrink-0 w-5 h-5 rounded-md border-2 grid place-items-center transition-colors',
                                       picked.has(row.id)
                                         ? 'bg-emerald-600 border-emerald-600 text-white'
                                         : 'border-slate-300 bg-white']">
                            <CheckIcon v-if="picked.has(row.id)" class="w-3.5 h-3.5" />
                        </span>
                        <div class="flex-1 min-w-0">
                            <p class="font-mono text-sm font-semibold text-slate-900 truncate">
                                {{ row.serial_number }}
                            </p>
                            <p class="text-[11px] text-slate-500">
                                <span v-if="row.warranty_expiry_date">{{ t('serials.col.warranty') }}: {{ row.warranty_expiry_date }}</span>
                                <span v-else class="text-slate-400">{{ t('serials.pickModal.noWarranty') }}</span>
                            </p>
                        </div>
                    </li>
                </ul>
                <p v-if="filtered.length > 400" class="px-3 py-2 text-[11px] text-slate-500 bg-slate-50 border-t border-slate-100">
                    {{ t('serials.pickModal.truncated', { shown: 400, total: filtered.length }) }}
                </p>
            </div>
        </div>

        <!-- Sticky bottom confirm bar — large tap area for mobile -->
        <template #footer>
            <div class="flex items-center justify-between gap-3 px-5 py-3 border-t border-slate-100 bg-slate-50/60 pb-safe">
                <button type="button" @click="close" class="action-btn-ghost">
                    {{ t('common.cancel') }}
                </button>
                <button
                    type="button"
                    @click="confirm"
                    :disabled="picked.size === 0"
                    class="action-btn-primary"
                >
                    <CheckIcon class="w-4 h-4" />
                    {{ t('serials.pickModal.confirm', { n: picked.size }) }}
                </button>
            </div>
        </template>
    </Modal>
</template>

<script setup>
import { ref, computed, reactive, watch, nextTick, h } from 'vue';
import { useI18n } from 'vue-i18n';
import {
    CheckBadgeIcon, CheckIcon, MagnifyingGlassIcon, InboxIcon,
} from '@heroicons/vue/24/outline';
import Modal from '@/components/ui/Modal.vue';
import { serialService } from '@/services/serialService';

const props = defineProps({
    open:      { type: Boolean, required: true },
    product:   { type: Object,  default: null },
    branchId:  { type: [Number, String, null], default: null },
    initialIds:{ type: Array,   default: () => [] },
});

const emit = defineEmits(['close', 'confirm']);
const { t } = useI18n();

// ── State ──────────────────────────────────────────────────────────────────
const available = ref([]);
const picked    = reactive(new Set());
const search    = ref('');
const loading   = ref(false);
const error     = ref('');
const searchInputRef = ref(null);

// Hot-reset every open so the parent's quantity changes never bleed
// between two different line items.
watch(() => props.open, async (isOpen) => {
    if (!isOpen) return;
    available.value = [];
    picked.clear();
    (props.initialIds || []).forEach(id => picked.add(id));
    search.value = '';
    error.value  = '';
    await fetchAvailable();
    nextTick(() => searchInputRef.value?.focus());
});

async function fetchAvailable() {
    if (!props.product?.id) return;
    loading.value = true;
    error.value   = '';
    try {
        const { data } = await serialService.availableForSale(props.product.id, props.branchId);
        available.value = data.data ?? [];
    } catch (err) {
        error.value = err?.response?.data?.message ?? t('common.unexpectedError');
        available.value = [];
    } finally {
        loading.value = false;
    }
}

// ── Filter + selection ────────────────────────────────────────────────────
const filtered = computed(() => {
    const q = search.value.trim().toUpperCase();
    if (!q) return available.value;
    return available.value.filter(r => r.serial_number.toUpperCase().includes(q));
});

function toggle(id) {
    picked.has(id) ? picked.delete(id) : picked.add(id);
}

function selectAllFiltered() {
    filtered.value.slice(0, 400).forEach(r => picked.add(r.id));
}

function clearSelection() { picked.clear(); }

/**
 * Barcode-scanner workflow: most USB scanners type the value + Enter as
 * a single burst. We try to match the entered text against an available
 * serial — if exactly one matches we toggle it on and clear the box.
 */
function onScannerEnter() {
    const q = search.value.trim().toUpperCase();
    if (!q) return;
    const exact = available.value.find(r => r.serial_number.toUpperCase() === q);
    if (exact) {
        picked.add(exact.id);
        search.value = '';
        return;
    }
    const matches = filtered.value;
    if (matches.length === 1) {
        picked.add(matches[0].id);
        search.value = '';
    }
}

// ── Emit ──────────────────────────────────────────────────────────────────

function close() { emit('close'); }

function confirm() {
    if (picked.size === 0) return;
    // Map back to enriched objects so the parent can preview serials
    // in the line item without an extra fetch.
    const byId = new Map(available.value.map(r => [r.id, r]));
    const items = [...picked].map(id => byId.get(id)).filter(Boolean);
    emit('confirm', {
        ids: items.map(r => r.id),
        serials: items.map(r => r.serial_number),
    });
}

// ── Inline KPI tile ───────────────────────────────────────────────────────
const KpiTile = (p) => h('div', { class: 'rounded-lg border border-slate-200 bg-white px-2.5 py-2' }, [
    h('p', { class: 'text-[10px] uppercase tracking-wider font-semibold text-slate-500' }, p.label),
    h('p', { class: `text-base font-bold mt-0.5 tabular-nums ${{
        slate: 'text-slate-900', emerald: 'text-emerald-600', indigo: 'text-indigo-600',
    }[p.tone] || 'text-slate-900'}` }, String(p.value ?? '—')),
]);
KpiTile.props = ['label', 'value', 'tone'];
</script>

<style scoped>
@reference '../../../css/app.css';

.action-btn-ghost   { @apply inline-flex items-center gap-1.5 px-4 py-2 text-sm font-semibold text-slate-700 bg-white hover:bg-slate-50 border border-slate-300 rounded-lg transition-colors; }
.action-btn-primary { @apply inline-flex items-center gap-1.5 px-5 py-2 text-sm font-semibold text-white bg-emerald-600 hover:bg-emerald-700 disabled:opacity-40 disabled:cursor-not-allowed rounded-lg shadow-sm transition-colors; }
</style>
