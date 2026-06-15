<template>
    <!--
        Every device the current user has subscribed for push. The card
        the user is reading FROM right now is highlighted "This device"
        so they don't accidentally revoke it. Rename gives each row a
        friendly handle ("Office PC", "Phone — Android"); revoke flips
        is_active to false on the backend AND tears down the local
        browser subscription if the row is the current device.
    -->
    <div class="p-3 sm:p-6 lg:p-8 space-y-4 sm:space-y-6 max-w-4xl mx-auto anim-fade-in">

        <header class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3 sm:gap-4 anim-fade-up">
            <div>
                <p class="t-overline text-indigo-500 mb-1.5">{{ t('notifications.module') }}</p>
                <h1 class="h1-display">{{ t('push.devices.title', 'Connected devices') }}</h1>
                <p class="mt-1.5 t-body">{{ t('push.devices.subtitle', 'Every browser or PWA you have enabled push notifications on. Rename for clarity, revoke when you no longer trust a device.') }}</p>
            </div>
            <Button
                variant="secondary"
                size="sm"
                :leading-icon="ArrowPathIcon"
                :loading="loading"
                @click="load"
            >
                {{ t('common.refresh') }}
            </Button>
        </header>

        <section v-if="!supported" class="card card-alert card-alert-info text-sm">
            {{ t('push.unsupportedHint', 'This browser does not support push notifications.') }}
        </section>

        <template v-else>
            <div v-if="loading && !devices.length" class="card overflow-hidden">
                <div class="p-4 space-y-3">
                    <Skeleton v-for="i in 3" :key="i" variant="row" />
                </div>
            </div>

            <EmptyState
                v-else-if="!devices.length"
                size="md"
                tone="indigo"
                :icon="DevicePhoneMobileIcon"
                :title="t('push.devices.emptyTitle', 'No devices subscribed')"
                :description="t('push.devices.emptyDesc', 'Enable browser notifications from the dashboard prompt or Settings to register this device.')"
            />

            <section v-else class="card overflow-hidden">
                <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                    <li v-for="d in devices" :key="d.id" class="device-row">
                        <div class="device-icon">
                            <component :is="iconFor(d.device_type)" class="w-5 h-5" />
                        </div>

                        <div class="device-body">
                            <div v-if="renamingId !== d.id" class="device-head">
                                <p class="device-label">{{ d.label || `${d.browser || 'Browser'} · ${d.platform || ''}`.trim() }}</p>
                                <span v-if="d.id === currentDeviceId" class="status-pill status-pill-success">{{ t('push.devices.thisDevice', 'This device') }}</span>
                            </div>
                            <div v-else class="device-rename">
                                <input
                                    v-model="renameDraft"
                                    type="text"
                                    maxlength="80"
                                    class="form-input"
                                    :placeholder="t('push.devices.labelPlaceholder', 'e.g. Office PC')"
                                    @keyup.enter="commitRename(d)"
                                    @keyup.esc="renamingId = null"
                                />
                            </div>
                            <p class="t-caption mt-0.5">
                                {{ d.browser }} · {{ d.platform }}
                                <span v-if="d.last_seen_at"> · {{ t('push.devices.lastSeen', 'last seen') }} {{ formatRelative(d.last_seen_at) }}</span>
                                <span v-else> · {{ t('push.devices.notSeen', 'not seen yet') }}</span>
                                <span v-if="d.failure_count > 0" class="text-rose-600 dark:text-rose-400 ml-1">
                                    · {{ d.failure_count }} {{ t('push.devices.failures', 'failures') }}
                                </span>
                            </p>
                        </div>

                        <div class="device-actions">
                            <template v-if="renamingId === d.id">
                                <button class="row-action row-action-emerald" :title="t('common.save')" @click="commitRename(d)">
                                    <CheckIcon class="w-4 h-4" />
                                </button>
                                <button class="row-action" :title="t('common.cancel')" @click="renamingId = null">
                                    <XMarkIcon class="w-4 h-4" />
                                </button>
                            </template>
                            <template v-else>
                                <button class="row-action row-action-indigo" :title="t('common.rename', 'Rename')" @click="startRename(d)">
                                    <PencilSquareIcon class="w-4 h-4" />
                                </button>
                                <button class="row-action row-action-danger" :title="t('push.devices.revoke', 'Revoke')" @click="confirmRevoke(d)">
                                    <TrashIcon class="w-4 h-4" />
                                </button>
                            </template>
                        </div>
                    </li>
                </ul>
            </section>

            <p class="t-caption text-center">
                {{ devices.length }} {{ t('push.devices.activeDevices', 'active device(s)') }}
            </p>
        </template>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import {
    ArrowPathIcon, CheckIcon, ComputerDesktopIcon, DevicePhoneMobileIcon,
    DeviceTabletIcon, PencilSquareIcon, TrashIcon, XMarkIcon,
} from '@heroicons/vue/24/outline';
import { pushService } from '@/services/pushService';
import { useAlert } from '@/composables/useAlert';
import Skeleton   from '@/components/ui/Skeleton.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import Button     from '@/components/ui/Button.vue';

const { t, locale } = useI18n();
const { toast, confirm } = useAlert();

const supported   = ref(true);
const loading     = ref(true);
const devices     = ref([]);
const currentEndpoint = ref(null);
const renamingId  = ref(null);
const renameDraft = ref('');

const currentDeviceId = computed(() => {
    if (!currentEndpoint.value) return null;
    // Backend doesn't ship the endpoint in the response (kept private),
    // so we match heuristically on last_seen_at being the most-recent
    // entry — this device almost always touched the row on page load.
    const list = [...devices.value].sort((a, b) =>
        new Date(b.last_seen_at || 0) - new Date(a.last_seen_at || 0));
    return list[0]?.id ?? null;
});

async function load() {
    loading.value = true;
    try {
        if (!(await pushService.isSupported())) {
            supported.value = false;
            return;
        }
        const sub = await pushService.existingSubscription();
        currentEndpoint.value = sub?.endpoint || null;
        devices.value = await pushService.devices();
    } catch (e) {
        toast('error', t('common.unexpectedError'));
    } finally {
        loading.value = false;
    }
}
onMounted(load);

function iconFor(deviceType) {
    if (deviceType === 'mobile') return DevicePhoneMobileIcon;
    if (deviceType === 'tablet') return DeviceTabletIcon;
    return ComputerDesktopIcon;
}

function startRename(d) {
    renamingId.value  = d.id;
    renameDraft.value = d.label || '';
}

async function commitRename(d) {
    const label = renameDraft.value.trim();
    if (!label) { renamingId.value = null; return; }
    try {
        await pushService.renameDevice(d.id, label);
        d.label = label;
        renamingId.value = null;
        toast('success', t('common.savedSuccess', 'Saved successfully.'));
    } catch (e) {
        toast('error', e.response?.data?.message ?? t('common.unexpectedError'));
    }
}

async function confirmRevoke(d) {
    const ok = await confirm({
        title: t('push.devices.revokeTitle', 'Revoke this device?'),
        text:  d.id === currentDeviceId.value
            ? t('push.devices.revokeCurrentText', 'You are signed in from this device. Revoking will stop push notifications here. You can re-enable from the dashboard anytime.')
            : t('push.devices.revokeText', 'This device will stop receiving push notifications.'),
        confirmText: t('push.devices.revoke', 'Revoke'),
        danger: true,
    });
    if (!ok) return;

    try {
        await pushService.revokeDevice(d.id);
        // If we just revoked the current device, also tear down the local
        // browser subscription so the next page load doesn't think it's
        // still enabled.
        if (d.id === currentDeviceId.value) {
            try { await pushService.disable(); } catch { /* tolerable */ }
        }
        devices.value = devices.value.filter(x => x.id !== d.id);
        toast('success', t('push.devices.revoked', 'Device revoked.'));
    } catch (e) {
        toast('error', e.response?.data?.message ?? t('common.unexpectedError'));
    }
}

function formatRelative(iso) {
    if (!iso) return '—';
    const diff = (Date.now() - new Date(iso).getTime()) / 1000;
    if (diff < 60)    return `${Math.floor(diff)}s ${t('dashboard.topCustomers.ago', 'ago')}`;
    if (diff < 3600)  return `${Math.floor(diff / 60)}m ${t('dashboard.topCustomers.ago', 'ago')}`;
    if (diff < 86400) return `${Math.floor(diff / 3600)}h ${t('dashboard.topCustomers.ago', 'ago')}`;
    return new Intl.DateTimeFormat(locale.value || 'en-US', { day: '2-digit', month: 'short' }).format(new Date(iso));
}
</script>

<style scoped>
@reference '../../../css/app.css';

.device-row {
    display: grid;
    grid-template-columns: auto 1fr auto;
    align-items: center;
    gap: 0.875rem;
    padding: 1rem 1.125rem;
    transition: background-color var(--motion-fast) var(--motion-out);
}
.device-row:hover { background: rgb(248 250 252); }
html.dark .device-row:hover { background: rgb(30 41 59 / 0.4); }

.device-icon {
    width: 40px; height: 40px;
    border-radius: 0.625rem;
    background: var(--surface-sunken);
    color: var(--text-secondary);
    display: grid; place-items: center;
    flex-shrink: 0;
}

.device-body { min-width: 0; }
.device-head { display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; }
.device-label {
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--text-primary);
}
.device-rename { max-width: 280px; }

.device-actions {
    display: flex;
    gap: 0.25rem;
    flex-shrink: 0;
}
</style>
