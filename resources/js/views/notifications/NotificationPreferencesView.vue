<template>
    <div class="p-3 sm:p-6 lg:p-8 space-y-4 sm:space-y-6 max-w-3xl mx-auto anim-fade-in">

        <header class="anim-fade-up">
            <p class="t-overline text-indigo-500 mb-1.5">{{ t('notifications.module') }}</p>
            <h1 class="h1-display">{{ t('notifications.preferences') }}</h1>
            <p class="mt-1.5 t-body">{{ t('notifications.preferencesSubtitle') }}</p>
        </header>

        <section class="card p-5 sm:p-6">

            <!-- Muted categories — premium pill chip grid. Single click toggles
                 muted state. Visual state mirrors the centre filter chips. -->
            <FormSection
                :title="t('notifications.muteCategories')"
                :description="t('notifications.muteCategoriesHint', 'Notifications from muted categories are still recorded but hidden from the bell, the centre, and the daily digest.')"
            >
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="c in categories"
                        :key="c.key"
                        type="button"
                        @click="toggleMute(c.key)"
                        :class="['pref-chip', isMuted(c.key) && 'is-muted']"
                    >
                        <span class="pref-chip-dot" />
                        {{ c.label }}
                        <span v-if="isMuted(c.key)" class="pref-chip-muted-tag">{{ t('notifications.muted', 'Muted') }}</span>
                    </button>
                </div>
            </FormSection>

            <FormSection
                :title="t('notifications.minSeverity')"
                :description="t('notifications.minSeverityHint')"
            >
                <FormField id="min-severity">
                    <select id="min-severity" v-model="form.min_severity" class="form-input max-w-xs">
                        <option value="info">{{ t('notifications.severity.info') }}</option>
                        <option value="warning">{{ t('notifications.severity.warning') }}</option>
                        <option value="danger">{{ t('notifications.severity.danger') }}</option>
                        <option value="critical">{{ t('notifications.severity.critical') }}</option>
                    </select>
                </FormField>
            </FormSection>

            <FormSection
                :title="t('notifications.quietHours')"
                :description="t('notifications.quietHoursHint')"
            >
                <FormField>
                    <div class="flex items-center gap-2 flex-wrap">
                        <input v-model="quietFrom" type="time" class="form-input" style="width: auto;" />
                        <ArrowLongRightIcon class="w-4 h-4 text-slate-400" />
                        <input v-model="quietTo" type="time" class="form-input" style="width: auto;" />
                        <button
                            v-if="quietFrom || quietTo"
                            type="button"
                            @click="quietFrom = ''; quietTo = ''"
                            class="text-xs text-slate-500 hover:underline ml-2"
                        >
                            {{ t('common.clear') }}
                        </button>
                    </div>
                </FormField>
            </FormSection>

            <FormSection
                :title="t('notifications.digestTitle', 'Digest')"
                :description="t('notifications.digestHint', 'Receive a periodic summary so quiet days do not generate a constant stream of small alerts.')"
            >
                <div class="form-toggle">
                    <div>
                        <p class="text-sm font-medium text-slate-700 dark:text-slate-200">{{ t('notifications.dailyDigest') }}</p>
                        <p class="t-caption mt-0.5">{{ t('notifications.dailyDigestHint', 'A single rollup email at 8am every workday.') }}</p>
                    </div>
                    <button
                        type="button"
                        @click="form.digest.daily = !form.digest.daily"
                        :class="['form-switch', { 'is-on': form.digest.daily }]"
                        :aria-pressed="form.digest.daily"
                        :aria-label="t('notifications.dailyDigest')"
                    />
                </div>
            </FormSection>

            <!-- Phase AD — Web Push per-priority. Hidden when the browser
                 doesn't support Web Push or hasn't been granted permission. -->
            <FormSection
                :title="t('push.section.title', 'Browser notifications')"
                :description="t('push.section.desc', 'Per-priority toggles for push notifications. Critical alerts always ship — they bypass mute windows.')"
            >
                <div v-if="pushSupportState === 'unsupported'" class="card card-alert card-alert-info text-sm">
                    {{ t('push.unsupportedHint', 'This browser does not support push notifications.') }}
                </div>
                <div v-else-if="pushSupportState === 'denied'" class="card card-alert card-alert-warning text-sm">
                    {{ t('push.deniedHint', 'Your browser blocked notifications. Allow them in site settings to enable.') }}
                </div>
                <div v-else>
                    <div class="form-toggle">
                        <div>
                            <p class="text-sm font-medium text-slate-700 dark:text-slate-200">{{ t('push.receive', 'Receive push notifications') }}</p>
                            <p class="t-caption mt-0.5">{{ pushEnabled ? t('push.enabledHint', 'Subscribed on this device.') : t('push.notEnabledHint', 'Click to subscribe this device.') }}</p>
                        </div>
                        <button
                            type="button"
                            :class="['form-switch', { 'is-on': pushEnabled }]"
                            :aria-pressed="pushEnabled"
                            :disabled="pushBusy"
                            @click="togglePush"
                        />
                    </div>

                    <div v-if="pushEnabled" class="push-priority-grid mt-3">
                        <label v-for="bucket in priorityBuckets" :key="bucket.key" class="push-priority-row">
                            <input
                                type="checkbox"
                                :checked="(form.channels.web_push?.[bucket.key]) ?? bucket.default"
                                @change="togglePriority(bucket.key, $event.target.checked)"
                                :disabled="bucket.key === 'critical'"
                            />
                            <div>
                                <p class="push-priority-label">
                                    {{ bucket.label }}
                                    <span v-if="bucket.key === 'critical'" class="push-priority-locked">{{ t('push.alwaysOn', 'always on') }}</span>
                                </p>
                                <p class="t-caption">{{ bucket.hint }}</p>
                            </div>
                        </label>
                    </div>
                </div>
            </FormSection>

            <div class="flex items-center justify-end gap-2 pt-5 mt-5 border-t border-slate-100 dark:border-slate-800">
                <Button
                    variant="primary"
                    :loading="saving"
                    :leading-icon="saving ? null : CheckIcon"
                    @click="save"
                >
                    {{ saving ? t('common.saving') : t('common.save') }}
                </Button>
            </div>
        </section>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { ArrowLongRightIcon, CheckIcon } from '@heroicons/vue/24/outline';
import { notificationService } from '@/services/notificationService';
import { pushService } from '@/services/pushService';
import FormField   from '@/components/ui/FormField.vue';
import FormSection from '@/components/ui/FormSection.vue';
import Button      from '@/components/ui/Button.vue';

const { t } = useI18n();
const saving = ref(false);
const form = reactive({
    muted_categories: [],
    min_severity: 'info',
    quiet_hours: null,
    digest: { daily: true, weekly: false },
    channels: {
        web_push: { critical: true, high: true, medium: false, low: false },
    },
});
const quietFrom = ref('');
const quietTo   = ref('');

// ── Push (Web Push) state ────────────────────────────────────────────────
const pushSupportState = ref('checking'); // 'supported' | 'denied' | 'unsupported' | 'checking'
const pushEnabled = ref(false);
const pushBusy    = ref(false);

const priorityBuckets = computed(() => [
    { key: 'critical', label: t('push.priority.critical', 'Critical'), hint: t('push.priority.criticalHint', 'Out of stock, sync failures, negative balance.'),    default: true  },
    { key: 'high',     label: t('push.priority.high',     'High'),     hint: t('push.priority.highHint',     'Reorder, supplier due, customer due, low cash.'),     default: true  },
    { key: 'medium',   label: t('push.priority.medium',   'Medium'),   hint: t('push.priority.mediumHint',   'Slow movers, sales targets, warranty expiring.'),     default: false },
    { key: 'low',      label: t('push.priority.low',      'Low'),      hint: t('push.priority.lowHint',      'New customer, daily and weekly summaries.'),          default: false },
]);

async function refreshPushState() {
    if (!(await pushService.isSupported())) {
        pushSupportState.value = 'unsupported';
        return;
    }
    const perm = await pushService.currentPermission();
    if (perm === 'denied') {
        pushSupportState.value = 'denied';
        return;
    }
    pushSupportState.value = 'supported';
    const sub = await pushService.existingSubscription();
    pushEnabled.value = !!sub;
}

async function togglePush() {
    if (pushBusy.value) return;
    pushBusy.value = true;
    try {
        if (pushEnabled.value) {
            await pushService.disable();
            pushEnabled.value = false;
        } else {
            const res = await pushService.enable();
            if (res.ok) pushEnabled.value = true;
            else if (res.status === 'denied') pushSupportState.value = 'denied';
        }
    } finally {
        pushBusy.value = false;
    }
}

function togglePriority(key, on) {
    form.channels.web_push = { ...(form.channels.web_push || {}), [key]: on };
}

const categories = computed(() => [
    { key: 'inventory', label: t('notifications.category.inventory') },
    { key: 'sales',     label: t('notifications.category.sales') },
    { key: 'finance',   label: t('notifications.category.finance') },
    { key: 'hrm',       label: t('notifications.category.hrm') },
    { key: 'system',    label: t('notifications.category.system') },
]);

function isMuted(key) { return (form.muted_categories || []).includes(key); }
function toggleMute(key) {
    if (isMuted(key)) form.muted_categories = form.muted_categories.filter((k) => k !== key);
    else              form.muted_categories = [...(form.muted_categories || []), key];
}

watch([quietFrom, quietTo], () => {
    form.quiet_hours = quietFrom.value && quietTo.value ? { from: quietFrom.value, to: quietTo.value } : null;
});

async function save() {
    saving.value = true;
    try { await notificationService.savePrefs(form); }
    finally { saving.value = false; }
}

onMounted(async () => {
    try {
        const { data } = await notificationService.prefs();
        Object.assign(form, {
            muted_categories: data.data.muted_categories || [],
            min_severity:     data.data.min_severity     || 'info',
            quiet_hours:      data.data.quiet_hours      || null,
            digest:           data.data.digest           || { daily: true, weekly: false },
            channels:         data.data.channels         || form.channels,
        });
        if (!form.channels.web_push) {
            form.channels.web_push = { critical: true, high: true, medium: false, low: false };
        }
        if (form.quiet_hours) {
            quietFrom.value = form.quiet_hours.from || '';
            quietTo.value   = form.quiet_hours.to   || '';
        }
    } catch { /* defaults already set */ }
    await refreshPushState();
});
</script>

<style scoped>
@reference '../../../css/app.css';

/* Mute toggle chip — looks "engaged" when category is allowed (default),
   "switched off" when muted. Dot colour shifts so the user can scan the
   row in one glance and see which categories are silenced. */
.pref-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.375rem 0.75rem;
    border-radius: 999px;
    font-size: 0.8125rem;
    font-weight: 600;
    background: var(--surface-raised);
    color: var(--text-primary);
    border: 1px solid var(--border-default);
    transition:
        background-color var(--motion-fast) var(--motion-out),
        border-color     var(--motion-fast) var(--motion-out),
        color            var(--motion-fast) var(--motion-out);
}
.pref-chip:hover { border-color: var(--border-strong); }
.pref-chip-dot {
    width: 8px; height: 8px;
    border-radius: 999px;
    background: rgb(16 185 129);
    flex-shrink: 0;
}
.pref-chip.is-muted {
    background: var(--surface-sunken);
    color: var(--text-tertiary);
}
.pref-chip.is-muted .pref-chip-dot { background: rgb(148 163 184); }
.pref-chip-muted-tag {
    font-size: 0.625rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    padding: 0 0.375rem;
    border-radius: 999px;
    background: rgb(241 245 249);
    color: rgb(100 116 139);
}
html.dark .pref-chip-muted-tag {
    background: rgb(30 41 59);
    color: rgb(148 163 184);
}
</style>
