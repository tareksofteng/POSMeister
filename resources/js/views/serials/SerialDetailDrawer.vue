<template>
    <!--
        Right-side drawer on desktop, full-screen sheet on phones.
        Opens from SerialInventoryModal when the user taps a row.
        Backed by GET /api/serials/{serial} which already returns the
        full movement timeline (created in Round 1).
    -->
    <Teleport to="body">
        <Transition
            enter-active-class="transition-opacity duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-150"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="open"
                 class="fixed inset-0 z-[60] bg-slate-900/55 backdrop-blur-[1px]"
                 @click.self="$emit('close')"
                 aria-hidden="true" />
        </Transition>

        <Transition
            enter-active-class="transition-transform duration-300 ease-out"
            enter-from-class="translate-x-full sm:translate-x-full translate-y-full sm:translate-y-0"
            enter-to-class="translate-x-0 translate-y-0"
            leave-active-class="transition-transform duration-200 ease-in"
            leave-from-class="translate-x-0 translate-y-0"
            leave-to-class="translate-x-full sm:translate-x-full translate-y-full sm:translate-y-0"
        >
            <aside v-if="open"
                   class="drawer"
                   role="dialog"
                   aria-modal="true"
                   :aria-labelledby="titleId">
                <!-- ── Header ───────────────────────────────────────────── -->
                <header class="drawer-head">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 grid place-items-center text-white flex-shrink-0">
                            <CpuChipIcon class="w-5 h-5" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p :id="titleId" class="font-mono text-base font-bold text-slate-900 truncate">
                                {{ serial?.serial_number || '—' }}
                            </p>
                            <p class="text-xs text-slate-500 truncate">
                                {{ serial?.product_name || (loading ? t('common.loading') : '—') }}
                            </p>
                        </div>
                        <button @click="$emit('close')" class="close-btn" :aria-label="t('common.close')">
                            <XMarkIcon class="w-5 h-5" />
                        </button>
                    </div>

                    <!-- Quick facts strip -->
                    <div v-if="serial" class="mt-3 grid grid-cols-2 sm:grid-cols-4 gap-2">
                        <Fact :label="t('serials.detail.status')">
                            <StatusBadge :status="serial.status" />
                        </Fact>
                        <Fact :label="t('serials.detail.branch')" :value="serial.branch_name || '—'" />
                        <Fact :label="t('serials.detail.received')" :value="serial.purchase_date || '—'" />
                        <Fact :label="t('serials.detail.warranty')">
                            <WarrantyPill :expiry="serial.warranty_expiry_date" :remaining="serial.warranty_remaining_days" />
                        </Fact>
                    </div>
                </header>

                <!-- ── Timeline body ────────────────────────────────────── -->
                <section class="drawer-body">
                    <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-500 mb-3">
                        {{ t('serials.detail.timeline') }}
                    </p>

                    <!-- Loading skeleton -->
                    <div v-if="loading" class="space-y-3">
                        <div v-for="i in 4" :key="i"
                             class="h-16 rounded-xl bg-slate-100 dark:bg-slate-800 animate-pulse"></div>
                    </div>

                    <!-- Empty state -->
                    <div v-else-if="!timeline.length"
                         class="text-center py-10 text-sm text-slate-500">
                        {{ t('serials.detail.emptyTimeline') }}
                    </div>

                    <!-- Timeline events — newest first -->
                    <ol v-else class="timeline">
                        <li v-for="(ev, i) in timeline" :key="ev.id" class="timeline-item">
                            <span :class="['timeline-dot', dotToneClass(ev.movement_type)]">
                                <component :is="movementIcon(ev.movement_type)" class="w-3.5 h-3.5" />
                            </span>
                            <div class="timeline-content">
                                <div class="flex items-center justify-between gap-2 flex-wrap">
                                    <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">
                                        {{ t('serials.movement.' + ev.movement_type) }}
                                    </p>
                                    <time class="text-[11px] text-slate-500 font-mono whitespace-nowrap">
                                        {{ formatDate(ev.created_at) }}
                                    </time>
                                </div>
                                <p v-if="ev.reference_type || ev.reference_id" class="mt-0.5 text-xs text-slate-500 font-mono">
                                    {{ shortRefType(ev.reference_type) }}<span v-if="ev.reference_id"> #{{ ev.reference_id }}</span>
                                </p>
                                <p v-if="ev.from_branch_id || ev.to_branch_id" class="mt-0.5 text-xs text-slate-500">
                                    {{ branchTransferLabel(ev) }}
                                </p>
                                <p v-if="ev.remarks" class="mt-1 text-xs text-slate-600 dark:text-slate-400 italic">
                                    "{{ ev.remarks }}"
                                </p>
                            </div>
                        </li>
                    </ol>
                </section>
            </aside>
        </Transition>
    </Teleport>
</template>

<script setup>
import { ref, watch, h } from 'vue';
import { useI18n } from 'vue-i18n';
import {
    CpuChipIcon, XMarkIcon,
    ShoppingCartIcon, ArrowDownTrayIcon, ArrowUturnLeftIcon,
    ArrowUturnRightIcon, ArrowsRightLeftIcon, BookmarkIcon,
    BookmarkSlashIcon, ExclamationTriangleIcon, MagnifyingGlassMinusIcon,
} from '@heroicons/vue/24/outline';
import { serialService } from '@/services/serialService';

const props = defineProps({
    open:     { type: Boolean, required: true },
    serialId: { type: [Number, String, null], default: null },
});

defineEmits(['close']);

const { t } = useI18n();
const titleId = 'serial-detail-title';

const loading  = ref(false);
const serial   = ref(null);
const timeline = ref([]);

watch(() => [props.open, props.serialId], async ([open, id]) => {
    if (!open || !id) return;
    loading.value = true;
    serial.value  = null;
    timeline.value = [];
    try {
        const { data } = await serialService.show(id);
        serial.value   = data.data ?? null;
        timeline.value = data.timeline ?? [];
    } catch {
        serial.value = null;
        timeline.value = [];
    } finally {
        loading.value = false;
    }
});

// ── Helpers ───────────────────────────────────────────────────────────────

function formatDate(iso) {
    if (!iso) return '';
    try {
        const d = new Date(iso);
        return d.toLocaleString(undefined, {
            year: 'numeric', month: 'short', day: '2-digit',
            hour: '2-digit', minute: '2-digit',
        });
    } catch { return iso; }
}

function shortRefType(t) {
    if (!t) return '';
    // App\Modules\Sales\Models\Sale → Sale
    const parts = String(t).split('\\');
    return parts[parts.length - 1] || t;
}

function branchTransferLabel(ev) {
    if (ev.from_branch_id && ev.to_branch_id) {
        return `Branch ${ev.from_branch_id} → ${ev.to_branch_id}`;
    }
    if (ev.to_branch_id) return `→ Branch ${ev.to_branch_id}`;
    if (ev.from_branch_id) return `From Branch ${ev.from_branch_id}`;
    return '';
}

const iconMap = {
    purchase:         ArrowDownTrayIcon,
    sale:             ShoppingCartIcon,
    sales_return:     ArrowUturnLeftIcon,
    purchase_return:  ArrowUturnRightIcon,
    transfer:         ArrowsRightLeftIcon,
    reserve:          BookmarkIcon,
    unreserve:        BookmarkSlashIcon,
    damage:           ExclamationTriangleIcon,
    lost:             MagnifyingGlassMinusIcon,
};
function movementIcon(type) { return iconMap[type] || ShoppingCartIcon; }

const toneClassMap = {
    purchase:         'bg-emerald-500',
    sale:             'bg-indigo-500',
    sales_return:     'bg-sky-500',
    purchase_return:  'bg-slate-500',
    transfer:         'bg-violet-500',
    reserve:          'bg-amber-500',
    unreserve:        'bg-slate-400',
    damage:           'bg-rose-500',
    lost:             'bg-rose-600',
};
function dotToneClass(type) {
    return toneClassMap[type] || 'bg-slate-400';
}

// ── Tiny inline components ────────────────────────────────────────────────
const Fact = (p, { slots }) => h('div', { class: 'rounded-lg bg-slate-50 dark:bg-slate-800/60 border border-slate-100 dark:border-slate-700 px-2.5 py-2' }, [
    h('p', { class: 'text-[10px] uppercase tracking-wider font-semibold text-slate-500' }, p.label),
    h('div', { class: 'mt-0.5 text-sm font-semibold text-slate-900 dark:text-slate-100' },
        slots.default ? slots.default() : (p.value ?? '—')),
]);
Fact.props = ['label', 'value'];

const StatusBadge = (p) => {
    const cfg = {
        in_stock:          { tone: 'emerald', label: t('serials.status.in_stock') },
        sold:              { tone: 'indigo',  label: t('serials.status.sold') },
        reserved:          { tone: 'amber',   label: t('serials.status.reserved') },
        sales_returned:    { tone: 'sky',     label: t('serials.status.sales_returned') },
        purchase_returned: { tone: 'slate',   label: t('serials.status.purchase_returned') },
        damaged:           { tone: 'rose',    label: t('serials.status.damaged') },
        lost:              { tone: 'rose',    label: t('serials.status.lost') },
    }[p.status] || { tone: 'slate', label: p.status };
    const map = {
        emerald: 'bg-emerald-50 text-emerald-700 border-emerald-200',
        indigo:  'bg-indigo-50 text-indigo-700 border-indigo-200',
        amber:   'bg-amber-50 text-amber-700 border-amber-200',
        sky:     'bg-sky-50 text-sky-700 border-sky-200',
        slate:   'bg-slate-50 text-slate-700 border-slate-200',
        rose:    'bg-rose-50 text-rose-700 border-rose-200',
    };
    return h('span', { class: `inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-[11px] font-semibold border ${map[cfg.tone]}` }, cfg.label);
};
StatusBadge.props = ['status'];

const WarrantyPill = (p) => {
    if (!p.expiry) return h('span', { class: 'text-xs text-slate-400' }, '—');
    let tone = 'emerald', label = t('serials.warranty.valid');
    if (p.remaining != null) {
        if (p.remaining < 0)       { tone = 'rose';    label = t('serials.warranty.expired'); }
        else if (p.remaining <= 30){ tone = 'amber';   label = t('serials.warranty.expiringSoon', { n: p.remaining }); }
        else                       { tone = 'emerald'; label = t('serials.warranty.daysLeft', { n: p.remaining }); }
    }
    const m = {
        emerald: 'bg-emerald-50 text-emerald-700 border-emerald-200',
        amber:   'bg-amber-50 text-amber-700 border-amber-200',
        rose:    'bg-rose-50 text-rose-700 border-rose-200',
    };
    return h('span', { class: `inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-[11px] font-semibold border ${m[tone]}` }, label);
};
WarrantyPill.props = ['expiry', 'remaining'];
</script>

<style scoped>
@reference '../../../css/app.css';

.drawer {
    position: fixed;
    z-index: 70;
    background: white;
    inset: 0;
    display: flex;
    flex-direction: column;
    box-shadow: 0 -20px 60px -20px rgba(15,23,42,0.4);
}
@media (min-width: 640px) {
    .drawer {
        inset: 0 0 0 auto;
        width: 100%;
        max-width: 480px;
        box-shadow: -20px 0 60px -20px rgba(15,23,42,0.4);
    }
}

.drawer-head {
    @apply px-4 sm:px-5 py-4 border-b border-slate-100 bg-white;
}
.drawer-body {
    @apply flex-1 overflow-y-auto px-4 sm:px-5 py-5 bg-slate-50;
}

.close-btn {
    @apply p-1.5 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-colors flex-shrink-0;
}

.timeline {
    position: relative;
    padding-left: 28px;
}
.timeline::before {
    content: '';
    position: absolute;
    left: 14px;
    top: 6px;
    bottom: 6px;
    width: 2px;
    background: linear-gradient(180deg, rgba(99,102,241,0.25), rgba(148,163,184,0.15));
    border-radius: 999px;
}
.timeline-item {
    position: relative;
    margin-bottom: 14px;
}
.timeline-item:last-child { margin-bottom: 0; }
.timeline-dot {
    position: absolute;
    left: -22px;
    top: 6px;
    width: 18px;
    height: 18px;
    border-radius: 999px;
    display: grid;
    place-items: center;
    color: white;
    box-shadow: 0 0 0 3px white, 0 4px 8px -2px rgba(15,23,42,0.15);
}
.timeline-content {
    @apply rounded-xl bg-white border border-slate-200 px-3 py-2.5;
}
</style>
