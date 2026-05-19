<template>
    <div>
        <div v-if="loading" class="py-6 text-center text-sm text-slate-400">
            <div class="w-5 h-5 border-2 border-sky-500 border-t-transparent rounded-full animate-spin mx-auto mb-2"></div>
            {{ t('common.loading') }}
        </div>
        <ul v-else-if="entries.length" class="relative space-y-3 pl-6">
            <span class="absolute top-1 bottom-1 left-2 w-px bg-slate-200"></span>
            <li v-for="entry in entries" :key="entry.id" class="relative">
                <span :class="['absolute -left-[18px] top-1 w-4 h-4 rounded-full border-2 border-white shadow', actionDot(entry.action)]"></span>
                <div class="flex items-baseline justify-between gap-2">
                    <p class="text-sm">
                        <span class="font-medium text-slate-900">{{ entry.user_name }}</span>
                        <span class="text-slate-500"> {{ t('expenses.audit.action_' + entry.action) }}</span>
                    </p>
                    <span class="text-[11px] text-slate-400 font-mono whitespace-nowrap">{{ entry.created_at }}</span>
                </div>
                <p v-if="entry.notes" class="text-xs text-slate-600 mt-0.5 italic">"{{ entry.notes }}"</p>
            </li>
        </ul>
        <p v-else class="text-sm text-slate-400 py-4 text-center">{{ t('expenses.audit.empty') }}</p>
    </div>
</template>

<script setup>
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { expenseService } from '@/services/expenseService';

const props = defineProps({
    expenseId: { type: [Number, String, null], default: null },
});

const { t } = useI18n();
const entries = ref([]);
const loading = ref(false);

function actionDot(action) {
    return {
        created:  'bg-slate-400',
        updated:  'bg-blue-400',
        approved: 'bg-indigo-500',
        rejected: 'bg-rose-500',
        paid:     'bg-emerald-500',
        reopened: 'bg-amber-500',
        deleted:  'bg-slate-700',
    }[action] ?? 'bg-slate-400';
}

async function load() {
    if (!props.expenseId) {
        entries.value = [];
        return;
    }
    loading.value = true;
    try {
        const { data } = await expenseService.auditLog(props.expenseId);
        entries.value = data.data ?? [];
    } catch {
        entries.value = [];
    } finally {
        loading.value = false;
    }
}

watch(() => props.expenseId, load, { immediate: true });

defineExpose({ reload: load });
</script>
