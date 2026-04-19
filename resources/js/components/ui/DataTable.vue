<template>
    <div class="flex flex-col bg-white rounded-xl border border-gray-200 overflow-hidden">

        <!-- Table wrapper (horizontal scroll on small screens) -->
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">

                <!-- Header -->
                <thead class="bg-gray-50">
                    <tr>
                        <th
                            v-for="col in columns"
                            :key="col.key"
                            :style="col.width ? `width: ${col.width}` : ''"
                            :class="[
                                'px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 select-none',
                                col.align === 'right' ? 'text-right' : '',
                                col.align === 'center' ? 'text-center' : '',
                                col.sortable ? 'cursor-pointer hover:text-gray-700 hover:bg-gray-100 transition-colors' : '',
                            ]"
                            @click="col.sortable ? $emit('sort', col.key) : null"
                        >
                            <span class="flex items-center gap-1" :class="col.align === 'right' ? 'justify-end' : ''">
                                {{ col.label }}
                                <template v-if="col.sortable">
                                    <ChevronUpDownIcon
                                        v-if="sortKey !== col.key"
                                        class="w-3.5 h-3.5 text-gray-400"
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

                <!-- Body -->
                <tbody class="divide-y divide-gray-100">

                    <!-- Loading skeleton -->
                    <template v-if="loading">
                        <tr v-for="n in skeletonRows" :key="`sk-${n}`" class="animate-pulse">
                            <td
                                v-for="col in columns"
                                :key="col.key"
                                class="px-4 py-3"
                            >
                                <div class="h-4 bg-gray-200 rounded" :style="{ width: skeletonWidth() }" />
                            </td>
                        </tr>
                    </template>

                    <!-- Empty state -->
                    <template v-else-if="!rows.length">
                        <tr>
                            <td :colspan="columns.length" class="px-4 py-16 text-center">
                                <div class="flex flex-col items-center gap-3">
                                    <div class="w-12 h-12 rounded-full bg-gray-100 flex items-center justify-center">
                                        <InboxIcon class="w-6 h-6 text-gray-400" />
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-500">{{ emptyTitle }}</p>
                                        <p class="text-xs text-gray-400 mt-0.5">{{ emptyMessage }}</p>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </template>

                    <!-- Data rows -->
                    <template v-else>
                        <tr
                            v-for="(row, index) in rows"
                            :key="row.id ?? index"
                            class="hover:bg-gray-50/70 transition-colors"
                        >
                            <td
                                v-for="col in columns"
                                :key="col.key"
                                :class="[
                                    'px-4 py-3 text-sm',
                                    col.align === 'right'  ? 'text-right'  : '',
                                    col.align === 'center' ? 'text-center' : '',
                                ]"
                            >
                                <!-- Actions column -->
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
                                    <span class="text-gray-700 font-mono text-sm">
                                        {{ formatCurrency(row[col.key]) }}
                                    </span>
                                </template>

                                <!-- Percent -->
                                <template v-else-if="col.type === 'percent'">
                                    <span class="text-gray-600 text-sm">{{ row[col.key] }}%</span>
                                </template>

                                <!-- Custom slot or plain text -->
                                <template v-else>
                                    <slot :name="`cell(${col.key})`" :value="row[col.key]" :row="row">
                                        <span :class="col.bold ? 'font-medium text-gray-900' : 'text-gray-600'">
                                            {{ row[col.key] ?? '—' }}
                                        </span>
                                    </slot>
                                </template>
                            </td>
                        </tr>
                    </template>

                </tbody>
            </table>
        </div>

        <!-- Pagination footer -->
        <div
            v-if="meta && meta.last_page > 1"
            class="flex items-center justify-between px-4 py-3 border-t border-gray-100 bg-gray-50/50 text-sm flex-wrap gap-3"
        >
            <!-- Info -->
            <p class="text-xs text-gray-500">
                Showing <span class="font-medium text-gray-700">{{ meta.from }}</span>–<span class="font-medium text-gray-700">{{ meta.to }}</span>
                of <span class="font-medium text-gray-700">{{ meta.total }}</span> results
            </p>

            <!-- Pages -->
            <div class="flex items-center gap-1">
                <!-- Prev -->
                <button
                    @click="$emit('page-change', meta.current_page - 1)"
                    :disabled="meta.current_page === 1"
                    class="flex items-center justify-center w-8 h-8 rounded-lg text-gray-500 hover:bg-gray-200 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
                >
                    <ChevronLeftIcon class="w-4 h-4" />
                </button>

                <!-- Page numbers -->
                <template v-for="page in visiblePages" :key="page">
                    <span v-if="page === '...'" class="w-8 h-8 flex items-center justify-center text-gray-400 text-xs">
                        ···
                    </span>
                    <button
                        v-else
                        @click="$emit('page-change', page)"
                        :class="[
                            'flex items-center justify-center w-8 h-8 rounded-lg text-xs font-medium transition-colors',
                            page === meta.current_page
                                ? 'bg-indigo-600 text-white shadow-sm'
                                : 'text-gray-600 hover:bg-gray-200',
                        ]"
                    >
                        {{ page }}
                    </button>
                </template>

                <!-- Next -->
                <button
                    @click="$emit('page-change', meta.current_page + 1)"
                    :disabled="meta.current_page === meta.last_page"
                    class="flex items-center justify-center w-8 h-8 rounded-lg text-gray-500 hover:bg-gray-200 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
                >
                    <ChevronRightIcon class="w-4 h-4" />
                </button>
            </div>
        </div>

    </div>
</template>

<script setup>
import { computed } from 'vue';
import {
    ChevronUpDownIcon,
    ChevronUpIcon,
    ChevronDownIcon,
    ChevronLeftIcon,
    ChevronRightIcon,
    InboxIcon,
} from '@heroicons/vue/24/outline';
import StatusBadge from './StatusBadge.vue';

const props = defineProps({
    columns:      { type: Array,   required: true },
    rows:         { type: Array,   default: () => [] },
    loading:      { type: Boolean, default: false },
    meta:         { type: Object,  default: null },   // Laravel pagination meta
    sortKey:      { type: String,  default: null },
    sortDir:      { type: String,  default: 'asc' },
    skeletonRows: { type: Number,  default: 6 },
    emptyTitle:   { type: String,  default: 'No records found' },
    emptyMessage: { type: String,  default: 'Try adjusting your search or filters.' },
});

defineEmits(['page-change', 'sort']);

// ── Pagination ────────────────────────────────────────────────────────────
const visiblePages = computed(() => {
    if (!props.meta) return [];

    const { current_page: cur, last_page: last } = props.meta;
    if (last <= 7) return Array.from({ length: last }, (_, i) => i + 1);

    const pages = [];

    if (cur <= 4) {
        pages.push(1, 2, 3, 4, 5, '...', last);
    } else if (cur >= last - 3) {
        pages.push(1, '...', last - 4, last - 3, last - 2, last - 1, last);
    } else {
        pages.push(1, '...', cur - 1, cur, cur + 1, '...', last);
    }

    return pages;
});

// ── Skeleton ──────────────────────────────────────────────────────────────
function skeletonWidth() {
    const widths = ['40%', '55%', '70%', '45%', '60%', '35%', '50%'];
    return widths[Math.floor(Math.random() * widths.length)];
}

function formatCurrency(value) {
    if (value == null) return '—';
    return new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR' }).format(value);
}

// ── Role badge ────────────────────────────────────────────────────────────
function roleBadgeClass(role) {
    const map = {
        admin:    'inline-flex items-center rounded-full bg-indigo-100 px-2.5 py-0.5 text-xs font-medium text-indigo-700',
        manager:  'inline-flex items-center rounded-full bg-violet-100 px-2.5 py-0.5 text-xs font-medium text-violet-700',
        cashier:  'inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600',
    };
    return map[role] ?? map.cashier;
}
</script>
