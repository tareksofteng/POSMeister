<template>
    <!--
        Two-mode table:
          mode="stack"  — phones: convert to stacked cards via .table-stack CSS
          mode="scroll" — phones: keep tabular layout, allow horizontal scroll
        Both modes use a normal table at sm+.
    -->
    <div :class="['rounded-xl border border-slate-200 dark:border-slate-800', surface && 'bg-white dark:bg-slate-900 shadow-sm']">
        <div class="responsive-table">
            <table :class="['w-full text-sm', mode === 'stack' && 'table-stack']">
                <thead class="bg-slate-50 dark:bg-slate-800/60">
                    <tr>
                        <th
                            v-for="(col, i) in columns"
                            :key="col.key || i"
                            :class="['text-[10px] uppercase tracking-wider font-bold text-slate-500 px-3 sm:px-4 py-2.5', col.align === 'right' && 'text-right', col.align === 'center' && 'text-center', !col.align && 'text-left']"
                        >
                            {{ col.label }}
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr v-for="(row, ri) in rows" :key="rowKey(row, ri)" class="hover:bg-slate-50/60 dark:hover:bg-slate-800/40 transition-colors">
                        <td
                            v-for="(col, ci) in columns"
                            :key="col.key || ci"
                            :data-label="col.label"
                            :class="['px-3 sm:px-4 py-2.5 text-slate-700 dark:text-slate-200', col.align === 'right' && 'text-right', col.align === 'center' && 'text-center', col.cellClass]"
                        >
                            <slot :name="`cell-${col.key}`" :row="row" :col="col" :value="resolve(row, col.key)">
                                {{ resolve(row, col.key) }}
                            </slot>
                        </td>
                    </tr>
                    <tr v-if="!rows.length">
                        <td :colspan="columns.length" class="px-4 py-10 text-center text-sm text-slate-500">
                            <slot name="empty">{{ emptyText }}</slot>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<script setup>
const props = defineProps({
    /** [{ key, label, align?, cellClass? }] */
    columns:   { type: Array,  required: true },
    rows:      { type: Array,  default: () => [] },
    /** Resolver function or "id" key to identify row in v-for */
    rowKeyFn:  { type: Function, default: null },
    rowKeyAttr:{ type: String, default: 'id' },
    /** "stack" linearises on phones, "scroll" allows horizontal scrolling */
    mode:      { type: String, default: 'stack' },
    surface:   { type: Boolean, default: true },
    emptyText: { type: String, default: '—' },
});

function rowKey(row, i) {
    if (props.rowKeyFn) return props.rowKeyFn(row);
    return row?.[props.rowKeyAttr] ?? i;
}

function resolve(row, key) {
    if (!key) return '';
    if (key.includes('.')) return key.split('.').reduce((o, k) => o?.[k], row);
    return row?.[key];
}
</script>
