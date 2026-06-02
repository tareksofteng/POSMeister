<template>
    <div class="relative" ref="containerRef">
        <button
            @click="toggle"
            class="relative touch-target flex items-center justify-center text-gray-500 dark:text-slate-400 hover:text-gray-900 dark:hover:text-slate-200 hover:bg-gray-100 dark:hover:bg-slate-800 rounded-lg transition-colors"
            :aria-label="t('notifications.title')"
        >
            <BellIcon class="w-5 h-5" />
            <span
                v-if="store.unread > 0"
                class="absolute top-1.5 right-1.5 min-w-[16px] h-4 px-1 rounded-full bg-rose-500 text-white text-[10px] font-bold flex items-center justify-center"
            >
                {{ store.unread > 99 ? '99+' : store.unread }}
            </span>
        </button>

        <Transition
            enter-active-class="transition ease-out duration-150"
            enter-from-class="opacity-0 scale-95"
            enter-to-class="opacity-100 scale-100"
            leave-active-class="transition ease-in duration-100"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="opacity-0 scale-95"
        >
            <div v-if="open"
                class="absolute right-0 top-full mt-1.5 w-[22rem] max-w-[92vw] origin-top-right rounded-xl bg-white dark:bg-slate-900 shadow-2xl ring-1 ring-black/5 z-50 overflow-hidden"
            >
                <div class="px-3 py-2.5 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between gap-2">
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ t('notifications.title') }}</p>
                        <p class="text-[11px] text-slate-500">{{ t('notifications.unread', { n: store.unread }) }}</p>
                    </div>
                    <div class="flex items-center gap-2 flex-shrink-0">
                        <button v-if="store.unread > 0" @click="store.markAllRead()" class="text-[11px] font-semibold text-indigo-600 hover:underline">
                            {{ t('notifications.markAllRead') }}
                        </button>
                        <button v-if="store.items.length > 0" @click="onClearRead" class="text-[11px] font-semibold text-slate-500 hover:underline">
                            {{ t('notifications.clearRead') }}
                        </button>
                    </div>
                </div>

                <div class="max-h-[60vh] overflow-y-auto overscroll-contain">
                    <div v-if="!store.items.length" class="px-4 py-10 text-center">
                        <CheckCircleIcon class="w-8 h-8 text-emerald-400 mx-auto mb-2" />
                        <p class="text-sm text-slate-500">{{ t('notifications.empty') }}</p>
                    </div>

                    <ul v-else class="divide-y divide-slate-100 dark:divide-slate-800">
                        <li
                            v-for="n in store.items.slice(0, 20)"
                            :key="n.id"
                            :class="['p-3 hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors', !n.read_at && 'bg-indigo-50/40 dark:bg-indigo-900/10']"
                        >
                            <div class="flex items-start gap-2.5">
                                <span :class="['mt-1.5 w-2 h-2 rounded-full flex-shrink-0', sevDot(n.severity)]" />
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2">
                                        <p class="text-xs font-bold uppercase tracking-wider text-slate-500">{{ n.category }}</p>
                                        <span :class="['text-[9px] font-bold uppercase tracking-wider px-1.5 rounded', sevBadge(n.severity)]">
                                            {{ n.severity }}
                                        </span>
                                    </div>
                                    <p class="mt-0.5 text-sm font-semibold text-slate-900 dark:text-slate-100 leading-snug">{{ n.title }}</p>
                                    <p class="text-xs text-slate-600 dark:text-slate-300 mt-0.5 line-clamp-2">{{ n.message }}</p>

                                    <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
                                        <RouterLink
                                            v-for="(a, ai) in (n.actions || [])"
                                            :key="ai"
                                            :to="{ name: a.route, params: a.params }"
                                            @click="store.markRead(n.id); open = false;"
                                            :class="['text-[11px] font-semibold px-2 py-0.5 rounded',
                                                a.type === 'primary'
                                                    ? 'bg-indigo-600 text-white hover:bg-indigo-700'
                                                    : 'bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-300']"
                                        >
                                            {{ t(a.label) }}
                                        </RouterLink>
                                        <button v-if="!n.acked_at" @click="store.ack(n.id)" class="text-[11px] text-emerald-700 dark:text-emerald-400 hover:underline ml-auto">
                                            {{ t('notifications.ack') }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </li>
                    </ul>
                </div>

                <div class="px-3 py-2 border-t border-slate-200 dark:border-slate-800 text-center">
                    <RouterLink :to="{ name: 'notifications' }" @click="open = false" class="text-xs font-semibold text-indigo-600 hover:underline">
                        {{ t('notifications.viewAll') }}
                    </RouterLink>
                </div>
            </div>
        </Transition>
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { onClickOutside } from '@vueuse/core';
import { BellIcon, CheckCircleIcon } from '@heroicons/vue/24/outline';
import { useNotificationsStore } from '@/stores/notifications';

const { t } = useI18n();
const store = useNotificationsStore();
const open = ref(false);
const containerRef = ref(null);

function toggle() { open.value = !open.value; if (open.value) store.fetch(); }
onClickOutside(containerRef, () => { open.value = false; });

async function onClearRead() {
    if (!window.confirm(t('notifications.confirmClearRead'))) return;
    await store.clearRead();
}

onMounted(() => store.startPolling());
onUnmounted(() => store.stopPolling());

function sevDot(s) {
    return ({ info: 'bg-sky-500', success: 'bg-emerald-500', warning: 'bg-amber-500', danger: 'bg-rose-500', critical: 'bg-red-600 animate-pulse' })[s] || 'bg-slate-400';
}
function sevBadge(s) {
    return ({
        info:    'bg-sky-100 text-sky-700',
        success: 'bg-emerald-100 text-emerald-700',
        warning: 'bg-amber-100 text-amber-700',
        danger:  'bg-rose-100 text-rose-700',
        critical:'bg-red-200 text-red-900',
    })[s] || 'bg-slate-100 text-slate-700';
}
</script>
