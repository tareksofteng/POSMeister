<template>
    <!--
        Bulk serial-number capture, opened from PurchaseFormView whenever
        the cashier picks a serialized product.

        Inputs are generated 1:1 against the line quantity. Paste of a
        multi-line block (one serial per line, comma- or tab-separated)
        auto-distributes into the remaining empty slots — designed for
        the IMEI label sheets shops actually scan in bulk.

        The modal NEVER hits the server. It only collects validated
        serials into the parent line's `_serials` array. The actual POST
        to /api/serials/attach-purchase happens after the purchase is
        saved, so a half-filled modal can't accidentally create rows.
    -->
    <Modal :open="open" size="lg" @close="close">
        <template #title>
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 grid place-items-center text-white flex-shrink-0">
                    <CpuChipIcon class="w-5 h-5" />
                </div>
                <div class="min-w-0">
                    <p class="text-[10px] font-semibold uppercase tracking-wider text-indigo-600">
                        {{ t('serials.addModal.eyebrow') }}
                    </p>
                    <h3 class="text-base font-semibold text-slate-900 truncate">
                        {{ product?.name }}
                    </h3>
                    <p class="text-xs text-slate-500 font-mono truncate">
                        {{ product?.sku }} · {{ t('serials.addModal.qty', { n: quantity }) }}
                    </p>
                </div>
            </div>
        </template>

        <div class="p-3 sm:p-5 space-y-4">

            <!-- ── Helper bar ───────────────────────────────────────────── -->
            <div class="flex flex-col sm:flex-row gap-2.5 sm:items-end">
                <div class="flex-1 grid grid-cols-3 gap-2.5">
                    <KpiTile :label="t('serials.addModal.expected')" :value="quantity" tone="slate" />
                    <KpiTile :label="t('serials.addModal.entered')"  :value="enteredCount" :tone="enteredCount === quantity ? 'emerald' : 'indigo'" />
                    <KpiTile :label="t('serials.addModal.warranty')" :value="warrantyMonths ? `${warrantyMonths}m` : '—'" tone="amber" />
                </div>
                <div class="flex gap-2">
                    <button type="button" @click="autofillPrefix" class="action-btn">
                        <SparklesIcon class="w-3.5 h-3.5" />
                        {{ t('serials.addModal.autofill') }}
                    </button>
                    <button type="button" @click="clearAll" class="action-btn-danger">
                        <TrashIcon class="w-3.5 h-3.5" />
                        {{ t('serials.addModal.clear') }}
                    </button>
                </div>
            </div>

            <!-- ── Warranty input + autofill prefix ─────────────────────── -->
            <div class="grid grid-cols-2 gap-2.5">
                <label class="block">
                    <span class="text-[10px] uppercase tracking-wider font-semibold text-slate-500">
                        {{ t('serials.addModal.warrantyMonths') }}
                    </span>
                    <input
                        v-model.number="warrantyMonths"
                        type="number" min="0" max="240"
                        :placeholder="t('serials.addModal.warrantyPlaceholder')"
                        class="mt-1 w-full px-3 py-2 text-sm border border-slate-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                </label>
                <label class="block">
                    <span class="text-[10px] uppercase tracking-wider font-semibold text-slate-500">
                        {{ t('serials.addModal.autofillPrefix') }}
                    </span>
                    <input
                        v-model="autofillBase"
                        type="text"
                        :placeholder="t('serials.addModal.autofillPlaceholder')"
                        class="mt-1 w-full px-3 py-2 text-sm font-mono border border-slate-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                </label>
            </div>

            <!-- ── Per-slot serial inputs ──────────────────────────────── -->
            <div class="rounded-xl border border-slate-200 bg-white max-h-[40vh] overflow-y-auto">
                <ol class="divide-y divide-slate-100">
                    <li v-for="(_, idx) in serials" :key="idx"
                        class="flex items-center gap-2 px-3 py-2">
                        <span class="text-[10px] font-mono w-6 text-slate-400 text-right">{{ idx + 1 }}</span>
                        <input
                            v-model="serials[idx]"
                            @paste="onPaste($event, idx)"
                            @input="normalize(idx)"
                            type="text"
                            :placeholder="t('serials.addModal.slotPlaceholder', { n: idx + 1 })"
                            :class="['flex-1 px-2.5 py-1.5 text-sm font-mono border rounded-lg bg-white focus:outline-none focus:ring-2',
                                     duplicateIndexes.includes(idx)
                                       ? 'border-rose-400 focus:ring-rose-400'
                                       : 'border-slate-300 focus:ring-indigo-500']"
                            spellcheck="false"
                            autocomplete="off"
                        />
                        <CheckCircleIcon v-if="serials[idx] && !duplicateIndexes.includes(idx)" class="w-4 h-4 text-emerald-500" />
                        <ExclamationCircleIcon v-else-if="duplicateIndexes.includes(idx)" class="w-4 h-4 text-rose-500" />
                    </li>
                </ol>
            </div>

            <!-- ── Inline error ─────────────────────────────────────────── -->
            <p v-if="errorMessage" class="text-sm text-rose-600">
                {{ errorMessage }}
            </p>

            <!-- ── Helper hint ──────────────────────────────────────────── -->
            <p class="text-xs text-slate-500">
                <span class="font-semibold">{{ t('serials.addModal.tip') }}:</span>
                {{ t('serials.addModal.tipBody') }}
            </p>
        </div>

        <template #footer>
            <div class="flex items-center justify-between gap-3 px-5 py-3 border-t border-slate-100 bg-slate-50/60">
                <button type="button" @click="close" class="action-btn-ghost">
                    {{ t('common.cancel') }}
                </button>
                <button
                    type="button"
                    @click="confirm"
                    :disabled="!canConfirm"
                    class="action-btn-primary"
                >
                    <CheckIcon class="w-4 h-4" />
                    {{ t('serials.addModal.confirm', { n: enteredCount, total: quantity }) }}
                </button>
            </div>
        </template>
    </Modal>
</template>

<script setup>
import { ref, computed, watch, h, nextTick } from 'vue';
import { useI18n } from 'vue-i18n';
import {
    CpuChipIcon, CheckCircleIcon, ExclamationCircleIcon,
    SparklesIcon, TrashIcon, CheckIcon,
} from '@heroicons/vue/24/outline';
import Modal from '@/components/ui/Modal.vue';

const props = defineProps({
    open:           { type: Boolean, required: true },
    product:        { type: Object,  default: null },
    quantity:       { type: Number,  default: 1 },
    initialSerials: { type: Array,   default: () => [] },
    initialWarrantyMonths: { type: Number, default: null },
});

const emit = defineEmits(['close', 'confirm']);
const { t } = useI18n();

// ── State ──────────────────────────────────────────────────────────────────
const serials      = ref([]);
const autofillBase = ref('');
const warrantyMonths = ref(null);
const errorMessage = ref('');

function syncSlots() {
    // Grow / shrink the inputs to match the parent line's quantity.
    const desired = Math.max(1, Math.floor(props.quantity || 0));
    if (serials.value.length === desired) return;
    if (serials.value.length < desired) {
        while (serials.value.length < desired) serials.value.push('');
    } else {
        serials.value.length = desired;
    }
}

watch(() => props.open, (open) => {
    if (!open) return;
    serials.value = props.initialSerials?.length
        ? [...props.initialSerials]
        : [];
    warrantyMonths.value = props.initialWarrantyMonths ?? null;
    autofillBase.value = '';
    errorMessage.value = '';
    syncSlots();
});
watch(() => props.quantity, () => { if (props.open) syncSlots(); });

// ── Derived ────────────────────────────────────────────────────────────────
const enteredCount = computed(() =>
    serials.value.filter(s => (s || '').trim().length > 0).length
);

// Indexes that collide with another non-empty slot — highlighted in red.
const duplicateIndexes = computed(() => {
    const seen = new Map();
    const dupes = [];
    serials.value.forEach((s, i) => {
        const v = (s || '').trim().toUpperCase();
        if (!v) return;
        if (seen.has(v)) {
            dupes.push(i);
            if (!dupes.includes(seen.get(v))) dupes.push(seen.get(v));
        } else {
            seen.set(v, i);
        }
    });
    return dupes;
});

const canConfirm = computed(() =>
    enteredCount.value === props.quantity
    && duplicateIndexes.value.length === 0
);

// ── Handlers ───────────────────────────────────────────────────────────────

/** Normalise: trim + uppercase. Quietly so the cursor doesn't jump. */
function normalize(idx) {
    const raw = serials.value[idx] ?? '';
    const next = raw.trim().toUpperCase();
    if (next !== raw) serials.value[idx] = next;
}

/**
 * Multi-line paste support. If the user pastes "SN001\nSN002\nSN003" into
 * any slot, we distribute the chunks into this slot + the empty ones after
 * it. Plays nice with whichever line the cashier pastes into.
 */
function onPaste(event, startIdx) {
    const text = (event.clipboardData || window.clipboardData)?.getData('text') || '';
    if (!/[\n\r\t,;]/.test(text)) return;       // single value — let default paste happen
    event.preventDefault();
    const tokens = text
        .split(/[\n\r\t,;]+/)
        .map(s => s.trim().toUpperCase())
        .filter(Boolean);
    if (!tokens.length) return;

    let i = startIdx;
    for (const tok of tokens) {
        if (i >= serials.value.length) break;
        serials.value[i] = tok;
        i++;
    }
}

/** Autofill remaining empty slots with PREFIX-001, PREFIX-002, ... */
function autofillPrefix() {
    const prefix = autofillBase.value.trim();
    if (!prefix) {
        errorMessage.value = t('serials.addModal.autofillNeedsPrefix');
        nextTick(() => { errorMessage.value = ''; });
        return;
    }
    let counter = 1;
    for (let i = 0; i < serials.value.length; i++) {
        if (!(serials.value[i] || '').trim()) {
            serials.value[i] = `${prefix}-${String(counter).padStart(3, '0')}`;
        }
        counter++;
    }
}

function clearAll() {
    serials.value = serials.value.map(() => '');
}

function close() {
    emit('close');
}

function confirm() {
    if (!canConfirm.value) {
        errorMessage.value = enteredCount.value !== props.quantity
            ? t('serials.addModal.countMismatch')
            : t('serials.addModal.duplicates');
        return;
    }
    emit('confirm', {
        serials: serials.value.map(s => s.trim().toUpperCase()),
        warrantyMonths: warrantyMonths.value || null,
    });
}

// ── Inline KPI tile (kept local — used only here) ──────────────────────────
const KpiTile = (p) => h('div', { class: 'rounded-lg border border-slate-200 bg-white px-2.5 py-2' }, [
    h('p', { class: 'text-[10px] uppercase tracking-wider font-semibold text-slate-500' }, p.label),
    h('p', { class: `text-base font-bold mt-0.5 tabular-nums ${{
        slate: 'text-slate-900', emerald: 'text-emerald-600',
        indigo: 'text-indigo-600', amber: 'text-amber-600',
    }[p.tone] || 'text-slate-900'}` }, String(p.value ?? '—')),
]);
KpiTile.props = ['label', 'value', 'tone'];
</script>

<style scoped>
@reference '../../../css/app.css';

.action-btn        { @apply inline-flex items-center gap-1.5 px-2.5 py-1.5 text-xs font-semibold text-indigo-700 bg-indigo-50 hover:bg-indigo-100 border border-indigo-200 rounded-lg transition-colors; }
.action-btn-danger { @apply inline-flex items-center gap-1.5 px-2.5 py-1.5 text-xs font-semibold text-rose-700 bg-rose-50 hover:bg-rose-100 border border-rose-200 rounded-lg transition-colors; }
.action-btn-ghost  { @apply inline-flex items-center gap-1.5 px-4 py-2 text-sm font-semibold text-slate-700 bg-white hover:bg-slate-50 border border-slate-300 rounded-lg transition-colors; }
.action-btn-primary{ @apply inline-flex items-center gap-1.5 px-5 py-2 text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 disabled:opacity-40 disabled:cursor-not-allowed rounded-lg shadow-sm transition-colors; }
</style>
