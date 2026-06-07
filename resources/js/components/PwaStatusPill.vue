<template>
    <div class="flex items-center gap-1">
        <!-- ─── Install / Installing / How-to-install button ──────────────
             Stays visible until the app is genuinely installed (either the
             appinstalled event fired or display-mode flipped to standalone).
             Older code hid this the moment userChoice = 'accepted' arrived,
             which on Android Chrome happens BEFORE the install finishes —
             users were seeing the button vanish but no app on home screen.
        -->
        <!-- Show the install button ONLY when the browser has actually
             surfaced an installable state (event captured) or is mid-
             install. Older builds also rendered a question-mark fallback
             when the state landed on `unsupported` — which on Chrome
             Android happened whenever the app was already installed and
             the prompt never re-fired, confusing users into thinking
             they had to follow a manual guide they didn't need. Now the
             button stays hidden in that case, matching Chrome's own
             behaviour where it only surfaces "Install app" when truly
             available. -->
        <button
            v-if="showInstallButton"
            type="button"
            @click="handleClick"
            :disabled="isInstalling"
            :class="['pill-btn', 'pill-install', { 'is-loading': isInstalling }]"
            :title="installTitle"
            :aria-label="installTitle"
        >
            <ArrowPathIcon v-if="isInstalling" class="w-4 h-4 animate-spin" />
            <ArrowDownTrayIcon v-else class="w-4 h-4" />
            <span class="hidden sm:inline">{{ installLabel }}</span>
        </button>

        <!-- ─── Installed badge ──────────────────────────────────────────
             Only shown while we're NOT actually running standalone — i.e.
             the user installed the app but is currently looking at the
             website in their normal browser tab. Reassures them the
             install completed; tap to open the standalone shell.
        -->
        <span
            v-if="isInstalled && !runningStandalone"
            class="pill-btn pill-installed"
            :title="t('pwa.installedTitle')"
        >
            <CheckBadgeIcon class="w-4 h-4" />
            <span class="hidden sm:inline">{{ t('pwa.installed') }}</span>
        </span>

        <!-- ─── Update available ─────────────────────────────────────────
             A new SW is waiting; tapping reloads with the latest assets.
        -->
        <button
            v-if="updateReady"
            type="button"
            @click="applyUpdate"
            class="pill-btn pill-update"
            :title="t('pwa.updateAvailable')"
            :aria-label="t('pwa.updateAvailable')"
        >
            <ArrowPathIcon class="w-4 h-4" />
            <span class="hidden sm:inline">{{ t('pwa.update') }}</span>
        </button>

        <!-- ─── Connection / sync pill ──────────────────────────────── -->
        <button
            :class="[
                'pill-btn',
                online
                    ? 'pill-online'
                    : 'pill-offline'
            ]"
            :title="online ? t('pwa.online') : t('pwa.offline')"
            @click="forceSync"
        >
            <span :class="['w-2 h-2 rounded-full', online ? 'bg-emerald-500' : 'bg-rose-500 animate-pulse']" />
            <span class="hidden sm:inline">{{ online ? t('pwa.online') : t('pwa.offline') }}</span>
            <span v-if="pendingCount > 0" class="ml-0.5 px-1.5 rounded-full text-[10px] bg-white/70 dark:bg-slate-900/40">
                {{ pendingCount }}
            </span>
        </button>

    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useI18n } from 'vue-i18n';
import {
    ArrowDownTrayIcon, ArrowPathIcon, CheckBadgeIcon,
} from '@heroicons/vue/24/outline';
import { countQueue } from '@/pwa/offlineQueue';
import { syncNow } from '@/pwa/syncWorker';
import { applyUpdateNow } from '@/pwa/register';
import { installState, browserHint, triggerInstall } from '@/pwa/install';
import { useAlert } from '@/composables/useAlert';

const { t } = useI18n();
const { toast } = useAlert();

const online        = ref(typeof navigator !== 'undefined' ? navigator.onLine : true);
const updateReady   = ref(false);
const pendingCount  = ref(0);

// State-machine reactive flags
const isInstalling  = computed(() => installState.value === 'installing');
const isInstalled   = computed(() => installState.value === 'installed');
const isAvailable   = computed(() => installState.value === 'available');
const isUnsupported = computed(() => installState.value === 'unsupported');

// True when the page is currently rendered as a standalone PWA. We hide the
// "Installed" badge in that case — there's nothing to "open", they're already
// inside the installed app.
const runningStandalone = computed(() => {
    if (typeof window === 'undefined') return false;
    try { return window.matchMedia('(display-mode: standalone)').matches; }
    catch { return false; }
});

/**
 * Show the install button on Chromium browsers (Chrome, Edge,
 * desktop & Android) whenever the app isn't already installed AND
 * the browser isn't explicitly in the "no beforeinstallprompt ever"
 * group (Safari iOS, Firefox, Samsung Internet, Chrome iOS — those
 * are flagged at boot).
 *
 * On Chrome Android, beforeinstallprompt is delayed behind an
 * engagement heuristic — the user may load the page and not see the
 * event fire for a few seconds. Hiding the button until then made it
 * feel like the install was broken; now the button stays visible and
 * a click handler gracefully nudges the user if the event still
 * hasn't arrived.
 */
const showInstallButton = computed(() =>
    !isInstalled.value && !isUnsupported.value
);

const installLabel = computed(() => {
    if (isInstalling.value) return t('pwa.installing');
    return t('pwa.install');
});

const installTitle = computed(() => {
    if (isInstalling.value) return t('pwa.installingTitle');
    return t('pwa.install');
});

// ── Handlers ───────────────────────────────────────────────────────────────

async function handleClick() {
    // IMPORTANT — must call triggerInstall synchronously before any await,
    // otherwise Android Chrome loses the user gesture and silently
    // refuses to surface the OS dialog.
    const result = await triggerInstall();

    // Chromium delayed the prompt — give the user a quick nudge instead
    // of leaving them staring at a button that did nothing. Don't change
    // state; the button stays visible so the user can try again as soon
    // as the event arrives (which often happens seconds later).
    if (result.outcome === 'not-ready-yet') {
        toast('info', t('pwa.notReadyYet'));
    }
    // accepted   → state machine keeps the button in 'installing' until
    //              appinstalled or the watchdog flips us into 'installed'.
    // dismissed  → state goes back to 'available'; user can retry.
    // error      → logged; button stays visible for retry.
}

function applyUpdate() { applyUpdateNow(); }
function forceSync()  { syncNow(); }

// ── Network / sync wiring ──────────────────────────────────────────────────

function setOnline()  { online.value = true;  refreshPending(); }
function setOffline() { online.value = false; refreshPending(); }
function onUpdateReady() { updateReady.value = true; }
function onSyncState(e)  { pendingCount.value = e.detail?.pending ?? 0; }

async function refreshPending() {
    try { pendingCount.value = await countQueue(); }
    catch { /* IndexedDB unavailable */ }
}

let pollId = null;
onMounted(() => {
    window.addEventListener('online',  setOnline);
    window.addEventListener('offline', setOffline);
    window.addEventListener('posmeister:pwa:update-ready', onUpdateReady);
    window.addEventListener('posmeister:sync:state', onSyncState);
    refreshPending();
    pollId = setInterval(refreshPending, 5000);
});

onUnmounted(() => {
    window.removeEventListener('online',  setOnline);
    window.removeEventListener('offline', setOffline);
    window.removeEventListener('posmeister:pwa:update-ready', onUpdateReady);
    window.removeEventListener('posmeister:sync:state', onSyncState);
    if (pollId) clearInterval(pollId);
});
</script>

<style scoped>
@reference '../../css/app.css';

.pill-btn {
    @apply inline-flex items-center gap-1.5 px-2 sm:px-2.5 py-1.5 text-xs font-semibold
           rounded-lg border transition-colors;
}
.pill-btn:disabled { @apply opacity-70 cursor-default; }

.pill-install {
    @apply text-indigo-700 dark:text-indigo-300 bg-indigo-50 dark:bg-indigo-900/40
           hover:bg-indigo-100 dark:hover:bg-indigo-900/60 border-indigo-200 dark:border-indigo-800;
}
.pill-install.is-loading {
    @apply bg-indigo-100 dark:bg-indigo-900/60 cursor-wait;
}
.pill-install.is-unsupported {
    @apply text-slate-600 dark:text-slate-300 bg-slate-50 dark:bg-slate-800/40
           hover:bg-slate-100 dark:hover:bg-slate-800/60 border-slate-200 dark:border-slate-700;
}

.pill-installed {
    @apply text-emerald-700 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-900/30
           border-emerald-200 dark:border-emerald-800;
    cursor: default;
}

.pill-update {
    @apply text-amber-700 dark:text-amber-300 bg-amber-50 dark:bg-amber-900/40
           hover:bg-amber-100 border-amber-200 dark:border-amber-800;
}

.pill-online {
    @apply text-emerald-700 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-900/30
           border-emerald-200 dark:border-emerald-800;
}
.pill-offline {
    @apply text-rose-700 dark:text-rose-300 bg-rose-50 dark:bg-rose-900/30
           border-rose-200 dark:border-rose-800;
}
</style>
