<template>
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <!-- Header -->
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-gray-900">{{ title }}</h2>
            <button @click="$emit('add')" class="btn-primary">
                <PlusIcon class="w-4 h-4" />
                {{ t('common.new') }}
            </button>
        </div>

        <!-- Error -->
        <div v-if="error" class="mx-6 mt-4 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
            {{ error }}
        </div>

        <!-- Loading -->
        <div v-if="loading" class="p-6 space-y-3">
            <div v-for="i in 4" :key="i" class="animate-pulse h-10 bg-gray-100 rounded-lg" />
        </div>

        <!-- Empty -->
        <div v-else-if="!rows.length" class="flex flex-col items-center justify-center py-14 text-center">
            <p class="text-sm text-gray-400">{{ t('common.noResults') }}</p>
            <button @click="$emit('add')" class="mt-3 text-xs font-semibold text-indigo-600 hover:underline">
                {{ t('common.new') }}
            </button>
        </div>

        <!-- Table -->
        <table v-else class="min-w-full divide-y divide-gray-100">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                        {{ t('common.name') }}
                    </th>
                    <th v-if="hasDescription" class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider hidden md:table-cell">
                        {{ t('common.description') }}
                    </th>
                    <th v-if="hasSymbol" class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider w-24">
                        {{ t('products.units.symbol') }}
                    </th>
                    <th v-if="hasStatus" class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider w-28">
                        {{ t('common.status') }}
                    </th>
                    <th class="px-6 py-3 w-24"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                <tr v-for="row in rows" :key="row.id" class="hover:bg-gray-50 transition-colors">
                    <td class="px-6 py-3 text-sm font-medium text-gray-900">{{ row.name }}</td>
                    <td v-if="hasDescription" class="px-6 py-3 text-sm text-gray-500 hidden md:table-cell max-w-xs truncate">
                        {{ row.description || '—' }}
                    </td>
                    <td v-if="hasSymbol" class="px-6 py-3 text-sm text-gray-600 font-mono">{{ row.symbol }}</td>
                    <td v-if="hasStatus" class="px-6 py-3">
                        <span :class="['inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium', row.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-500']">
                            <span :class="['w-1.5 h-1.5 rounded-full', row.is_active ? 'bg-emerald-500' : 'bg-gray-400']" />
                            {{ row.is_active ? t('common.active') : t('common.inactive') }}
                        </span>
                    </td>
                    <td class="px-6 py-3">
                        <div class="flex items-center justify-end gap-1">
                            <button @click="$emit('edit', row)" class="p-1.5 text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors">
                                <PencilSquareIcon class="w-4 h-4" />
                            </button>
                            <button v-if="hasStatus" @click="$emit('toggle', row)" class="p-1.5 text-gray-400 hover:text-amber-600 hover:bg-amber-50 rounded-lg transition-colors">
                                <NoSymbolIcon v-if="row.is_active" class="w-4 h-4" />
                                <CheckCircleIcon v-else class="w-4 h-4" />
                            </button>
                            <button @click="$emit('delete', row)" class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors">
                                <TrashIcon class="w-4 h-4" />
                            </button>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>

<script setup>
import { useI18n } from 'vue-i18n';
import { PlusIcon, PencilSquareIcon, TrashIcon, NoSymbolIcon, CheckCircleIcon } from '@heroicons/vue/24/outline';

const { t } = useI18n();

defineProps({
    title:          { type: String,  required: true },
    rows:           { type: Array,   default: () => [] },
    loading:        { type: Boolean, default: false },
    error:          { type: String,  default: '' },
    hasDescription: { type: Boolean, default: false },
    hasSymbol:      { type: Boolean, default: false },
    hasStatus:      { type: Boolean, default: false },
});

defineEmits(['add', 'edit', 'delete', 'toggle']);
</script>

<style scoped>
@reference '../../../css/app.css';
.btn-primary {
    @apply flex items-center gap-2 px-3 py-1.5 text-sm font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition-colors;
}
</style>
