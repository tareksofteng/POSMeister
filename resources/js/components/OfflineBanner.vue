<template>
    <!--
        Slim top-bar strip that surfaces offline / sync state. Three modes:
          - offline + no pending  → amber "Working offline"
          - online  + pending     → indigo "N sales waiting to sync" with retry CTA
          - offline + pending     → rose   "Offline · N sales queued locally"
    -->
    <div
        v-if="store.showBanner"
        :class="['relative z-10 flex items-center gap-2 px-3 py-1.5 text-xs font-semibold pt-safe', toneClasses]"
        role="status"
        aria-live="polite"
    >
        <span class="inline-block w-2 h-2 rounded-full" :class="dotClass" />
        <span class="flex-1 truncate">{{ message }}</span>
        <button
            v-if="store.pending > 0 && store.online"
            @click="store.forceSync()"
            :disabled="store.syncing"
            class="px-2 py-0.5 rounded bg-white/30 hover:bg-white/50 text-white text-[11px] font-bold disabled:opacity-50"
        >
            {{ store.syncing ? t('offline.syncing') : t('offline.retry') }}
        </button>
    </div>
</template>

<script setup>
import { computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useOfflineStore } from '@/stores/offline';

const { t } = useI18n();
const store = useOfflineStore();
onMounted(() => store.init());

const toneClasses = computed(() => {
    if (!store.online && store.pending > 0) return 'bg-rose-600 text-white';
    if (!store.online)                       return 'bg-amber-600 text-white';
    return 'bg-indigo-600 text-white';
});

const dotClass = computed(() => {
    if (store.syncing) return 'bg-white animate-pulse';
    if (!store.online) return 'bg-white';
    return 'bg-white/80';
});

const message = computed(() => {
    if (!store.online && store.pending > 0) return t('offline.offlineWithQueue', { n: store.pending });
    if (!store.online)                       return t('offline.offline');
    return t('offline.queue', { n: store.pending });
});
</script>
