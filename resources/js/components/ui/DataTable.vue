<template>
    <!--
        DataTable — Phase AA upgrade. The component API is unchanged
        (every existing prop, slot, and emit works exactly as before)
        but the visual rhythm is rebuilt on the new design system:

        - Premium chrome via .surface-raised + .elev-1
        - Sticky header (now visually pinned during long-list scroll)
        - .t-overline header text — single typography across the product
        - <Skeleton variant="row"> loading state (replaces the random-width
          shimmer that re-rolled every render and looked glitchy)
        - <EmptyState> primitive for "no records" with iconogram + tones
        - Refined pagination — same algorithm, restyled buttons

        Compatibility: backwards compatible. Old views passing
        :empty-title and :empty-message still work; new views can pass
        :empty-tone, :empty-icon, or a #empty slot for full custom.
    -->
    <div class="data-table-shell">

        <!-- Table wrapper — at sm+ this scrolls horizontally if needed.
             On phones the .table-stack rules linearise rows into cards. -->
        <div class="responsive-table">
            <table :class="['data-table table-premium', stackOnMobile && 'table-stack', zebra && 'zebra']">

                <thead>
                    <tr>
                        <th
                            v-for="col in columns"
                            :key="col.key"
                            :style="col.width ? `width: ${col.width}` : ''"
                            :class="[
                                col.align === 'right'  ? 'text-right'  : '',
                                col.align === 'center' ? 'text-center' : '',
                                col.sortable ? 'is-sortable' : '',
                            ]"
                            @click="col.sortable ? $emit('sort', col.key) : null"
                        >
                            <span class="flex items-center gap-1" :class="col.align === 'right' ? 'justify-end' : ''">
                                {{ col.label }}
                                <template v-if="col.sortable">
                                    <ChevronUpDownIcon
                                        v-if="sortKey !== col.key"
                                        class="w-3.5 h-3.5 text-slate-400"
                                    />
                                    <ChevronUpIcon
                                        v-else-if="sortDir === 'asc'"
                                        class="w-3.5 h-3.5 text-indigo-500"
                                    />
                                    <ChevronDownIcon
                                        v-else
                                        class="w-3.5 h-3.5 text-indigo-500"
                                    />
                                </template>
                            </span>
                        </th>
                    </tr>
                </thead>

                <tbody>
                    <!-- Loading — Skeleton primitive rows so size/colour match
                         the rest of the product's loading vocabulary. -->
                    <template v-if="loading">
                        <tr v-for="n in skeletonRows" :key="`sk-${n}`" class="is-loading">
                            <td
                                v-for="(col, i) in columns"
                                :key="col.key"
                                :class="[
                                    col.align === 'right'  ? 'text-right'  : '',
                                    col.align === 'center' ? 'text-center' : '',
                                ]"
                            >
                                <Skeleton :height="'12px'" :width="skeletonWidthFor(i, n)" />
                            </td>
                        </tr>
                    </template>

                    <!-- Empty — premium EmptyState primitive spans the table. -->
                    <template v-else-if="!rows.length">
                        <tr>
                            <td :colspan="columns.length" class="empty-cell">
                                <slot name="empty">
                                    <EmptyState
                                        size="sm"
                                        :tone="emptyTone"
                                        :icon="emptyIcon || InboxIcon"
                                        :title="emptyTitle"
                                        :description="emptyMessage"
                                    >
                                        <template v-if="$slots['empty-action']" #action>
                                            <slot name="empty-action" />
                                        </template>
                                    </EmptyState>
                                </slot>
                            </td>
                        </tr>
                    </template>

                    <!-- Data rows -->
                    <template v-else>
                        <tr
                            v-for="(row, index) in rows"
                            :key="row.id ?? index"
                            :class="{ 'is-selected': row.__selected }"
                        >
                            <td
                                v-for="col in columns"
                                :key="col.key"
                                :data-label="col.label"
                                :class="[
                                    col.align === 'right'  ? 'text-right'  : '',
                                    col.align === 'center' ? 'text-center' : '',
                                ]"
                            >
                                <!-- Actions column — right-aligned by default,
                                     compact gap matched to .btn-icon size. -->
                                <template v-if="col.type === 'actions'">
                                    <div class="flex items-center justify-end gap-1">
                                        <slot name="row-actions" :row="row" />
                                    </div>
                                </template>

                                <!-- Badge column -->
                                <template v-else-if="col.type === 'badge'">
                                    <slot :name="`cell(${col.key})`" :value="row[col.key]" :row="row">
                                        <StatusBadge :active="row[col.key]" />
                                    </slot>
                                </template>

                                <!-- Role badge -->
                                <template v-else-if="col.type === 'role'">
                                    <span :class="roleBadgeClass(row[col.key])">
                                        {{ row[col.key] }}
                                    </span>
                                </template>

                                <!-- Currency -->
                                <template v-else-if="col.type === 'currency'">
                                    <span class="font-mono">{{ formatCurrency(row[col.key]) }}</span>
                                </template>

                                <!-- Percent -->
                                <template v-else-if="col.type === 'percent'">
                                    <span class="text-slate-600 dark:text-slate-300">{{ row[col.key] }}%</span>
                                </template>

                                <!-- Custom slot or plain text. The custom variant
                                     lets a parent inject its own JSX via #cell-X. -->
                                <template v-else>
                                    <slot :name="`cell-${col.key}`" :value="row[col.key]" :row="row">
                                        <slot :name="`cell(${col.key})`" :value="row[col.key]" :row="row">
                                            <span :class="col.bold ? 'font-semibold text-slate-900 dark:text-slate-100' : 'text-slate-600 dark:text-slate-300'">
                                                {{ col.format ? col.format(row[col.key], row) : (row[col.key] ?? '—') }}
                                            </span>
                                        </slot>
                                    </slot>
                                </template>
                            </td>
                        </tr>
                    </template>

                </tbody>
            </table>
        </div>

        <!-- Pagination footer — refined surface + button primitives -->
        <div
            v-if="meta && meta.last_page > 1"
            class="data-table-pagination"
        >
            <p class="t-caption">
                {{ t('common.showing') }}
                <span class="font-medium text-slate-700 dark:text-slate-200">{{ meta.from }}</span>–<span class="font-medium text-slate-700 dark:text-slate-200">{{ meta.to }}</span>
                {{ t('common.of') }}
                <span class="font-medium text-slate-700 dark:text-slate-200">{{ meta.total }}</span>
            </p>

            <div class="flex items-center gap-1">
                <button
                    @click="$emit('page-change', meta.current_page - 1)"
                    :disabled="meta.current_page === 1"
                    class="page-btn"
                    :aria-label="t('common.previous')"
                >
                    <ChevronLeftIcon class="w-4 h-4" />
                </button>

                <template v-for="page in visiblePages" :key="page">
                    <span v-if="page === '...'" class="page-ellipsis">···</span>
                    <button
                        v-else
                        @click="$emit('page-change', page)"
                        :class="['page-btn', page === meta.current_page && 'is-current']"
                    >
                        {{ page }}
                    </button>
                </template>

                <button
                    @click="$emit('page-change', meta.current_page + 1)"
                    :disabled="meta.current_page === meta.last_page"
                    class="page-btn"
                    :aria-label="t('common.next')"
                >
                    <ChevronRightIcon class="w-4 h-4" />
                </button>
            </div>
        </div>

    </div>
</template>

<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useSettingsStore } from '@/stores/settings';
import {
    ChevronUpDownIcon,
    ChevronUpIcon,
    ChevronDownIcon,
    ChevronLeftIcon,
    ChevronRightIcon,
    InboxIcon,
} from '@heroicons/vue/24/outline';
import StatusBadge from './StatusBadge.vue';
import Skeleton    from './Skeleton.vue';
import EmptyState  from './EmptyState.vue';

const props = defineProps({
    columns:      { type: Array,   required: true },
    rows:         { type: Array,   default: () => [] },
    loading:      { type: Boolean, default: false },
    meta:         { type: Object,  default: null },
    sortKey:      { type: String,  default: null },
    sortDir:      { type: String,  default: 'asc' },
    skeletonRows: { type: Number,  default: 6 },
    emptyTitle:   { type: String,  default: 'No records found' },
    emptyMessage: { type: String,  default: 'Try adjusting your search or filters.' },
    emptyTone:    { type: String,  default: 'slate' },
    emptyIcon:    { type: [Object, Function], default: null },
    /** When true (default), rows linearise into stacked cards below 640px. */
    stackOnMobile:{ type: Boolean, default: true },
    /** Opt in to zebra striping for wide reports. */
    zebra:        { type: Boolean, default: false },
});

defineEmits(['page-change', 'sort']);

const { t } = useI18n();
const settingsStore = useSettingsStore();

// ── Pagination ────────────────────────────────────────────────────────────
const visiblePages = computed(() => {
    if (!props.meta) return [];
    const { current_page: cur, last_page: last } = props.meta;
    if (last <= 7) return Array.from({ length: last }, (_, i) => i + 1);

    const pages = [];
    if (cur <= 4)              pages.push(1, 2, 3, 4, 5, '...', last);
    else if (cur >= last - 3)  pages.push(1, '...', last - 4, last - 3, last - 2, last - 1, last);
    else                        pages.push(1, '...', cur - 1, cur, cur + 1, '...', last);
    return pages;
});

// ── Skeleton — deterministic widths so cells don't visibly re-roll
//    on every render. The pattern still mimics the irregularity of real
//    data without the "blinking shimmer" the old Math.random() pass had.
const SKELETON_PATTERN = ['58%', '40%', '72%', '52%', '36%', '64%', '48%'];
function skeletonWidthFor(colIndex, rowIndex) {
    return SKELETON_PATTERN[(colIndex + rowIndex) % SKELETON_PATTERN.length];
}

function formatCurrency(value) {
    if (value == null) return '—';
    const code = settingsStore.settings?.currency_code ?? 'EUR';
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: code }).format(value);
}

function roleBadgeClass(role) {
    const map = {
        admin:    'inline-flex items-center rounded-full bg-indigo-100 dark:bg-indigo-900/30 px-2.5 py-0.5 text-xs font-medium text-indigo-700 dark:text-indigo-300',
        manager:  'inline-flex items-center rounded-full bg-violet-100 dark:bg-violet-900/30 px-2.5 py-0.5 text-xs font-medium text-violet-700 dark:text-violet-300',
        cashier:  'inline-flex items-center rounded-full bg-slate-100 dark:bg-slate-800 px-2.5 py-0.5 text-xs font-medium text-slate-600 dark:text-slate-300',
    };
    return map[role] ?? map.cashier;
}
</script>

<style scoped>
@reference '../../../css/app.css';

.data-table-shell {
    background: var(--surface-raised);
    border: 1px solid var(--border-default);
    border-radius: 0.875rem;
    box-shadow: var(--elev-1);
    overflow: hidden;
    display: flex; flex-direction: column;
}

/* Force a min-width on the table so columns don't collapse below tablet — the
   responsive-table wrapper handles horizontal scroll. */
.data-table { min-width: 100%; }

.data-table thead th.is-sortable {
    cursor: pointer;
    transition: color var(--motion-fast) var(--motion-out), background-color var(--motion-fast) var(--motion-out);
}
.data-table thead th.is-sortable:hover {
    color: var(--text-primary);
    background: rgb(241 245 249);
}
html.dark .data-table thead th.is-sortable:hover { background: rgb(30 41 59); }

.data-table tbody tr.is-loading {
    background: transparent !important;
}
.data-table tbody td.empty-cell {
    padding: 0 !important;
    border-bottom: 0 !important;
}

/* Pagination chrome */
.data-table-pagination {
    @apply flex items-center justify-between px-4 py-3 flex-wrap gap-3;
    background: var(--surface-sunken);
    border-top: 1px solid var(--border-default);
}
.page-btn {
    @apply inline-flex items-center justify-center w-8 h-8 rounded-lg text-xs font-medium;
    color: var(--text-secondary);
    transition:
        background-color var(--motion-fast) var(--motion-out),
        color            var(--motion-fast) var(--motion-out),
        transform        var(--motion-fast) var(--motion-out);
}
.page-btn:hover:not(:disabled):not(.is-current) {
    background: rgb(226 232 240);
    color: var(--text-primary);
}
html.dark .page-btn:hover:not(:disabled):not(.is-current) {
    background: rgb(30 41 59);
}
.page-btn:disabled { opacity: 0.4; cursor: not-allowed; }
.page-btn:active   { transform: scale(0.94); }
.page-btn.is-current {
    background: rgb(79 70 229);
    color: white;
    box-shadow: var(--elev-1);
}
.page-ellipsis {
    @apply w-8 h-8 inline-flex items-center justify-center text-slate-400 text-xs;
}
</style>
