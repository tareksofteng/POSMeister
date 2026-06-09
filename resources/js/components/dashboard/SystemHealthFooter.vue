<template>
    <!--
        A compact status strip at the bottom of the dashboard. Six pulse
        widgets the operator can scan in a glance: connectivity, install,
        sync, notifications, branch, locale. Anything that warrants
        attention surfaces as an amber or rose pill; otherwise the whole
        strip reads emerald and quiet.
    -->
    <section class="card sys-footer">
        <div class="sys-grid">
            <div class="sys-cell">
                <span :class="['sys-dot', online ? 'is-on' : 'is-off']" />
                <div class="sys-text">
                    <p class="t-overline">{{ t('dashboard.health.connectivity', 'Connectivity') }}</p>
                    <p :class="['sys-value', online ? 'text-emerald-700 dark:text-emerald-300' : 'text-rose-600 dark:text-rose-400']">
                        {{ online ? t('common.online', 'Online') : t('common.offline', 'Offline') }}
                    </p>
                </div>
            </div>

            <div class="sys-cell">
                <span :class="['sys-dot', pwaInstalled ? 'is-on' : 'is-pending']" />
                <div class="sys-text">
                    <p class="t-overline">{{ t('dashboard.health.pwa', 'PWA') }}</p>
                    <p class="sys-value">
                        {{ pwaInstalled
                            ? t('dashboard.health.installed', 'Installed')
                            : t('dashboard.health.browser', 'Browser') }}
                    </p>
                </div>
            </div>

            <div class="sys-cell">
                <span :class="['sys-dot', syncStatus.tone]" />
                <div class="sys-text">
                    <p class="t-overline">{{ t('dashboard.health.sync', 'Offline sync') }}</p>
                    <p class="sys-value">{{ syncStatus.label }}</p>
                </div>
            </div>

            <div class="sys-cell">
                <span :class="['sys-dot', notifTone]" />
                <div class="sys-text">
                    <p class="t-overline">{{ t('dashboard.health.notifications', 'Notifications') }}</p>
                    <p class="sys-value">
                        {{ notif.unread > 0
                            ? `${notif.unread} ${t('dashboard.health.unread', 'unread')}`
                            : t('dashboard.health.clear', 'Clear') }}
                    </p>
                </div>
            </div>

            <div class="sys-cell">
                <BuildingOffice2Icon class="sys-icon" />
                <div class="sys-text">
                    <p class="t-overline">{{ t('dashboard.health.workspace', 'Workspace') }}</p>
                    <p class="sys-value">{{ branchLabel }}</p>
                </div>
            </div>

            <div class="sys-cell">
                <GlobeAltIcon class="sys-icon" />
                <div class="sys-text">
                    <p class="t-overline">{{ t('dashboard.health.locale', 'Locale') }}</p>
                    <p class="sys-value">{{ localeLabel }} · v{{ appVersion }}</p>
                </div>
            </div>
        </div>
    </section>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { BuildingOffice2Icon, GlobeAltIcon } from '@heroicons/vue/24/outline';
import { useNotificationsStore } from '@/stores/notifications';

const { t, locale } = useI18n();
const notif = useNotificationsStore();

const appVersion = '2.0';

// ── Connectivity ─────────────────────────────────────────────────────────
const online = ref(navigator.onLine);
const onOnline  = () => { online.value = true;  };
const onOffline = () => { online.value = false; };
onMounted(() => {
    window.addEventListener('online',  onOnline);
    window.addEventListener('offline', onOffline);
});
onUnmounted(() => {
    window.removeEventListener('online',  onOnline);
    window.removeEventListener('offline', onOffline);
});

// ── PWA install state ────────────────────────────────────────────────────
const pwaInstalled = computed(() =>
    window.matchMedia?.('(display-mode: standalone)').matches
    || window.navigator.standalone === true,
);

// ── Offline sync queue size — best-effort. Reads the IDB queue if the
//    offlineQueue helper exposes a count, otherwise just shows "Ready". ──
const syncQueueSize = ref(0);
async function readQueueSize() {
    try {
        const mod = await import('@/offline/offlineSales');
        if (typeof mod.pendingCount === 'function') {
            syncQueueSize.value = await mod.pendingCount();
        }
    } catch { /* offline module not present */ }
}
let syncTimer = null;
onMounted(() => {
    readQueueSize();
    syncTimer = setInterval(readQueueSize, 30_000);
});
onUnmounted(() => { if (syncTimer) clearInterval(syncTimer); });

const syncStatus = computed(() => {
    if (!online.value) {
        return { tone: 'is-off', label: t('dashboard.health.syncOffline', 'Offline — queuing') };
    }
    if (syncQueueSize.value > 0) {
        return { tone: 'is-pending', label: `${syncQueueSize.value} ${t('dashboard.health.pending', 'pending')}` };
    }
    return { tone: 'is-on', label: t('dashboard.health.ready', 'Ready') };
});

// ── Notification engine status pill ──────────────────────────────────────
const notifTone = computed(() => {
    if (notif.unread > 10) return 'is-pending';
    if (notif.unread > 0)  return 'is-on';
    return 'is-on';
});

// ── Workspace label — pulled from the branch switcher's localStorage cache
//    if available, otherwise generic. ──
const branchLabel = computed(() => {
    try {
        const raw = localStorage.getItem('pos_branch_name');
        if (raw) return raw;
    } catch { /* no-op */ }
    try {
        const id = localStorage.getItem('pos_branch_id');
        if (id === null || id === '') return t('branchSwitcher.allBranches', 'All branches');
    } catch { /* no-op */ }
    return t('branchSwitcher.allBranches', 'All branches');
});

// ── Locale display ───────────────────────────────────────────────────────
const localeLabel = computed(() => ({
    en: 'EN',
    bn: 'বাং',
    ar: 'AR',
    de: 'DE',
})[locale.value] || (locale.value || 'EN').toUpperCase());
</script>

<style scoped>
@reference '../../../css/app.css';

.sys-footer { padding: 0.875rem 1rem; }

.sys-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0.875rem 1.25rem;
}
@media (min-width: 640px)  { .sys-grid { grid-template-columns: repeat(3, 1fr); } }
@media (min-width: 1024px) { .sys-grid { grid-template-columns: repeat(6, 1fr); } }

.sys-cell {
    display: flex;
    align-items: center;
    gap: 0.625rem;
    min-width: 0;
}

.sys-dot {
    width: 8px; height: 8px;
    border-radius: 999px;
    flex-shrink: 0;
}
.sys-dot.is-on {
    background: rgb(16 185 129);
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.2);
}
.sys-dot.is-pending {
    background: rgb(245 158 11);
    box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.2);
}
.sys-dot.is-off {
    background: rgb(244 63 94);
    box-shadow: 0 0 0 3px rgba(244, 63, 94, 0.18);
}

.sys-icon {
    width: 14px; height: 14px;
    color: var(--text-tertiary);
    flex-shrink: 0;
}

.sys-text { min-width: 0; }
.sys-value {
    margin-top: 0.125rem;
    font-size: 0.8125rem;
    font-weight: 600;
    color: var(--text-primary);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
</style>
