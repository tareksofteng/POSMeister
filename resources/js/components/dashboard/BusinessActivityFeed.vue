<template>
    <div class="activity-feed">
        <div v-if="!items?.length" class="text-center py-10">
            <ClockIcon class="w-8 h-8 text-slate-300 mx-auto mb-2" />
            <p class="text-sm text-slate-400">{{ t('dashboard.activity.empty') }}</p>
        </div>

        <ol v-else class="relative">
            <!-- Vertical timeline rail -->
            <div class="absolute left-[15px] top-2 bottom-2 w-px bg-slate-200 dark:bg-slate-800" aria-hidden="true" />

            <li
                v-for="(e, i) in items.slice(0, limit)"
                :key="e.id || i"
                class="relative pl-10 pr-2 py-2 activity-item"
                :style="{ animationDelay: (i * 40) + 'ms' }"
            >
                <span :class="['absolute left-2 top-3 w-4 h-4 rounded-full flex items-center justify-center', dotBg(e.type)]">
                    <component :is="dotIcon(e.type)" class="w-2.5 h-2.5 text-white" />
                </span>

                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0 flex-1">
                        <p class="text-[10px] uppercase tracking-wider font-bold" :class="dotText(e.type)">
                            {{ t('dashboard.activity.types.' + e.type, e.type) }}
                        </p>
                        <p class="text-sm font-medium text-slate-800 dark:text-slate-200 truncate">{{ e.title }}</p>
                        <p v-if="e.subtitle" class="text-xs text-slate-500 truncate">{{ e.subtitle }}</p>
                    </div>
                    <span class="text-[10px] text-slate-400 flex-shrink-0 mt-0.5">{{ formatRelative(e.at) }}</span>
                </div>
            </li>
        </ol>
    </div>
</template>

<script setup>
import { useI18n } from 'vue-i18n';
import {
    ShoppingCartIcon, TruckIcon, BanknotesIcon, UserPlusIcon,
    GiftIcon, CheckCircleIcon, ArrowUturnLeftIcon, ClockIcon,
} from '@heroicons/vue/24/outline';

defineProps({
    items: { type: Array, default: () => [] },
    limit: { type: Number, default: 10 },
});

const { t, locale } = useI18n();

function dotBg(type) {
    return ({
        sale:        'bg-emerald-500',
        purchase:    'bg-indigo-500',
        payment_in:  'bg-emerald-600',
        payment_out: 'bg-rose-500',
        customer:    'bg-sky-500',
        loyalty:     'bg-violet-500',
        delivery:    'bg-amber-500',
        return:      'bg-orange-500',
    })[type] || 'bg-slate-400';
}
function dotText(type) {
    return ({
        sale:        'text-emerald-600',
        purchase:    'text-indigo-600',
        payment_in:  'text-emerald-700',
        payment_out: 'text-rose-600',
        customer:    'text-sky-600',
        loyalty:     'text-violet-600',
        delivery:    'text-amber-600',
        return:      'text-orange-600',
    })[type] || 'text-slate-500';
}
function dotIcon(type) {
    return ({
        sale:        ShoppingCartIcon,
        purchase:    TruckIcon,
        payment_in:  BanknotesIcon,
        payment_out: BanknotesIcon,
        customer:    UserPlusIcon,
        loyalty:     GiftIcon,
        delivery:    CheckCircleIcon,
        return:      ArrowUturnLeftIcon,
    })[type] || CheckCircleIcon;
}

function formatRelative(iso) {
    if (!iso) return '';
    const diff = (Date.now() - new Date(iso).getTime()) / 1000;
    if (diff < 60)    return `${Math.round(diff)}s`;
    if (diff < 3600)  return `${Math.round(diff / 60)}m`;
    if (diff < 86400) return `${Math.round(diff / 3600)}h`;
    return `${Math.round(diff / 86400)}d`;
}
</script>

<style scoped>
.activity-item {
    animation: feed-fade-in 280ms ease-out both;
    opacity: 0;
}
@keyframes feed-fade-in {
    from { opacity: 0; transform: translateY(4px); }
    to   { opacity: 1; transform: translateY(0); }
}
@media (prefers-reduced-motion: reduce) {
    .activity-item { animation: none !important; opacity: 1; }
}
</style>
