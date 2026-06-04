<template>
    <!--
        Fallback "How to install" modal for browsers that don't expose
        beforeinstallprompt (Samsung Internet, Firefox Android, Safari
        iOS) or where the user dismissed the native prompt and Chrome
        won't re-fire it. Each browser variant gets concrete, numbered
        steps so a non-technical cashier can still install the app.
    -->
    <Teleport to="body">
        <div
            v-if="open"
            class="install-modal-backdrop"
            role="dialog"
            aria-modal="true"
            :aria-labelledby="titleId"
            @click.self="$emit('close')"
        >
            <div class="install-modal-card">
                <header class="install-modal-head">
                    <div class="install-modal-logo">
                        <ArrowDownTrayIcon class="w-5 h-5" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <h2 :id="titleId" class="install-modal-title">{{ t('pwa.instructions.title') }}</h2>
                        <p class="install-modal-sub">{{ t('pwa.instructions.subtitle') }}</p>
                    </div>
                    <button @click="$emit('close')" class="install-modal-close" :aria-label="t('common.close')">
                        <XMarkIcon class="w-5 h-5" />
                    </button>
                </header>

                <div class="install-modal-body">
                    <p class="text-xs uppercase tracking-wider font-semibold text-slate-500 mb-2">
                        {{ browserLabel }}
                    </p>
                    <ol class="install-steps">
                        <li v-for="(step, idx) in steps" :key="idx">
                            <span class="step-num">{{ idx + 1 }}</span>
                            <span class="step-text" v-html="step" />
                        </li>
                    </ol>

                    <p class="install-modal-hint">
                        {{ t('pwa.instructions.hint') }}
                    </p>
                </div>

                <footer class="install-modal-foot">
                    <button @click="$emit('close')" class="install-modal-action">
                        {{ t('pwa.instructions.gotIt') }}
                    </button>
                </footer>
            </div>
        </div>
    </Teleport>
</template>

<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { ArrowDownTrayIcon, XMarkIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    open:    { type: Boolean, required: true },
    browser: { type: String,  default: 'chrome' },  // chrome | samsung | firefox | safari-ios | edge | chrome-ios
});

defineEmits(['close']);

const { t } = useI18n();
const titleId = 'pwa-install-instructions-title';

// Per-browser step list — translations live under pwa.instructions.steps.<browser>
const browserLabel = computed(() => t('pwa.instructions.browser.' + props.browser));
const steps = computed(() => {
    // Each translation key is an array — vue-i18n exposes them as t() over an
    // explicit indexed path. We resolve eagerly so the template doesn't have
    // to know how many steps each browser has.
    const list = [];
    for (let i = 0; i < 6; i++) {
        const key = `pwa.instructions.steps.${props.browser}.${i}`;
        const val = t(key);
        if (!val || val === key) break;
        list.push(val);
    }
    return list;
});
</script>

<style scoped>
@reference '../../css/app.css';

.install-modal-backdrop {
    position: fixed; inset: 0; z-index: 60;
    background: rgba(15, 23, 42, 0.55);
    backdrop-filter: blur(2px);
    display: flex; align-items: flex-end; justify-content: center;
    padding: 16px;
    animation: bdr-fade 160ms ease;
}
@media (min-width: 640px) {
    .install-modal-backdrop { align-items: center; }
}
@keyframes bdr-fade {
    from { opacity: 0; }
    to   { opacity: 1; }
}

.install-modal-card {
    background: white;
    border-radius: 20px 20px 0 0;
    width: 100%; max-width: 480px;
    box-shadow: 0 20px 60px -10px rgba(15, 23, 42, 0.45);
    overflow: hidden;
    animation: sheet-up 280ms cubic-bezier(0.22, 1, 0.36, 1);
}
@media (min-width: 640px) {
    .install-modal-card { border-radius: 20px; }
}
@keyframes sheet-up {
    from { transform: translateY(24px); opacity: 0.5; }
    to   { transform: translateY(0);    opacity: 1; }
}

.install-modal-head {
    @apply flex items-center gap-3 p-5 border-b border-slate-100;
}
.install-modal-logo {
    @apply w-10 h-10 rounded-xl grid place-items-center text-white flex-shrink-0;
    background: linear-gradient(135deg, #6366f1, #818cf8);
    box-shadow: 0 6px 16px -6px rgba(99, 102, 241, 0.55);
}
.install-modal-title {
    @apply text-base sm:text-lg font-semibold text-slate-900;
}
.install-modal-sub {
    @apply text-xs sm:text-sm text-slate-500 mt-0.5;
}
.install-modal-close {
    @apply text-slate-400 hover:text-slate-700 hover:bg-slate-100 rounded-lg p-1.5 transition-colors;
}

.install-modal-body { @apply p-5; }

.install-steps {
    @apply space-y-3;
}
.install-steps li {
    @apply flex items-start gap-3;
}
.step-num {
    @apply flex-shrink-0 w-6 h-6 rounded-full bg-indigo-50 text-indigo-700 font-semibold text-xs grid place-items-center mt-0.5;
}
.step-text {
    @apply text-sm text-slate-700 leading-relaxed;
}
:deep(.step-text strong) { @apply font-semibold text-slate-900; }
:deep(.step-text code)   { @apply px-1.5 py-0.5 rounded bg-slate-100 text-slate-800 text-xs font-mono; }

.install-modal-hint {
    @apply mt-4 text-xs text-slate-500 bg-slate-50 border border-slate-100 rounded-lg px-3 py-2;
}

.install-modal-foot {
    @apply p-5 pt-2 flex justify-end;
}
.install-modal-action {
    @apply inline-flex items-center justify-center px-5 py-2.5 text-sm font-semibold
           text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg shadow-sm transition-colors;
    min-width: 96px;
}
</style>
