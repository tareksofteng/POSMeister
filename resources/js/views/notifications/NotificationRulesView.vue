<template>
    <!--
        NotificationRulesView — Phase AB Round 3 admin page.

        Lists every configured notification rule and lets the admin add,
        edit, or remove one. The rule shape mirrors NotificationRule
        (Eloquent) exactly: enabled flag + 4 numeric overrides + 2
        severity caps + audience role + branch filter + notes.

        Discoverable codes: the "Add rule" picker reads
        /api/notifications/rules/codes which returns every distinct code
        that fired in the past 90 days, so the admin never types codes
        by hand.

        Behavioural model: a missing column on the form (NULL) means
        "use detector default" — only the fields the admin sets actually
        override.
    -->
    <div class="p-3 sm:p-6 lg:p-8 space-y-4 sm:space-y-6 max-w-6xl mx-auto anim-fade-in">

        <header class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3 sm:gap-4 anim-fade-up">
            <div>
                <p class="t-overline text-indigo-500 mb-1.5">{{ t('notifications.module') }}</p>
                <h1 class="h1-display">{{ t('notifications.rules.title', 'Notification Rules') }}</h1>
                <p class="mt-1.5 t-body">
                    {{ t('notifications.rules.subtitle', 'Tune cooldown, severity thresholds and audience for each detector code. Empty fields fall back to the detector default.') }}
                </p>
            </div>
            <Button variant="primary" :leading-icon="PlusIcon" @click="openCreate">
                {{ t('notifications.rules.addRule', 'Add rule') }}
            </Button>
        </header>

        <!-- Loading + rules list -->
        <section class="card overflow-hidden">
            <div v-if="loading" class="p-4 space-y-3">
                <Skeleton v-for="i in 4" :key="i" variant="row" />
            </div>

            <EmptyState
                v-else-if="!rules.length"
                size="md"
                tone="indigo"
                :icon="AdjustmentsHorizontalIcon"
                :title="t('notifications.rules.emptyTitle', 'No rules configured')"
                :description="t('notifications.rules.emptyDesc', 'Detectors are running with their built-in defaults. Add a rule to override cooldown or severity for any code.')"
            >
                <template #action>
                    <Button variant="primary" :leading-icon="PlusIcon" @click="openCreate">
                        {{ t('notifications.rules.addRule', 'Add rule') }}
                    </Button>
                </template>
            </EmptyState>

            <ul v-else class="divide-y divide-slate-100 dark:divide-slate-800">
                <li v-for="rule in rules" :key="rule.id" class="rule-row">
                    <div class="flex items-center gap-3 flex-1 min-w-0">
                        <span :class="['rule-state-dot', rule.enabled ? 'is-on' : 'is-off']" />
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2 flex-wrap">
                                <p class="rule-code">{{ rule.code }}</p>
                                <span v-if="!rule.enabled" class="status-pill status-pill-neutral">{{ t('notifications.rules.disabled', 'Disabled') }}</span>
                            </div>
                            <p class="t-caption mt-0.5">{{ summarizeRule(rule) }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-1 flex-shrink-0">
                        <button class="row-action row-action-indigo" :title="t('common.edit')" @click="openEdit(rule)">
                            <PencilSquareIcon class="w-4 h-4" />
                        </button>
                        <button class="row-action row-action-danger" :title="t('common.delete')" @click="confirmDelete(rule)">
                            <TrashIcon class="w-4 h-4" />
                        </button>
                    </div>
                </li>
            </ul>
        </section>

        <!-- Discovery — codes the system has emitted recently but admin
             hasn't tuned yet. One-click to start a new rule from each. -->
        <section v-if="undonecodes.length" class="card overflow-hidden">
            <header class="dash-list-head">
                <div>
                    <p class="t-overline">{{ t('notifications.rules.discoverTitle', 'Codes seen recently') }}</p>
                    <p class="t-caption mt-0.5">{{ t('notifications.rules.discoverHint', 'Detector codes the system emitted in the past 90 days that you haven\'t configured.') }}</p>
                </div>
            </header>
            <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                <li v-for="c in undonecodes" :key="c.code" class="rule-row">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <p class="rule-code">{{ c.code }}</p>
                            <span :class="['status-pill', sevPill(c.last_severity)]">{{ c.last_severity }}</span>
                            <span class="t-caption">×{{ c.occurrences }} {{ t('notifications.rules.in90d', 'in 90 days') }}</span>
                        </div>
                        <p class="t-caption mt-0.5">{{ c.last_title }}</p>
                    </div>
                    <Button variant="secondary" size="sm" :leading-icon="PlusIcon" @click="openCreate(c.code)">
                        {{ t('notifications.rules.tune', 'Tune') }}
                    </Button>
                </li>
            </ul>
        </section>

        <!-- ── Edit / Create modal ────────────────────────────────────── -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="modalOpen" class="form-modal-overlay">
                    <div class="absolute inset-0" @click="modalOpen = false" />

                    <div class="form-modal-shell">
                        <header class="form-modal-header">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-xl bg-indigo-50 dark:bg-indigo-900/30 flex items-center justify-center flex-shrink-0">
                                    <AdjustmentsHorizontalIcon class="w-5 h-5 text-indigo-600 dark:text-indigo-300" />
                                </div>
                                <div>
                                    <h2 class="text-base font-semibold text-slate-900 dark:text-slate-100 leading-tight">
                                        {{ isEdit ? t('notifications.rules.editRule', 'Edit rule') : t('notifications.rules.addRule', 'Add rule') }}
                                    </h2>
                                    <p class="t-caption mt-0.5">
                                        {{ form.code || t('notifications.rules.modalSubtitle', 'Per-code override — empty fields fall back to the detector default.') }}
                                    </p>
                                </div>
                            </div>
                            <button type="button" @click="modalOpen = false" class="row-action" :aria-label="t('common.close', 'Close')">
                                <XMarkIcon class="w-5 h-5" />
                            </button>
                        </header>

                        <form @submit.prevent="save" class="form-modal-body">
                            <FormSection
                                :title="t('notifications.rules.section.identity', 'Identity')"
                                :description="t('notifications.rules.section.identityDesc', 'Which detector code does this rule apply to?')"
                            >
                                <FormField id="rule-code" :label="t('notifications.rules.code', 'Code')" :error="formError('code')" required>
                                    <input
                                        id="rule-code"
                                        v-model="form.code"
                                        type="text"
                                        :disabled="isEdit"
                                        placeholder="inventory.low_stock"
                                        :class="['form-input', { 'is-invalid': formError('code') }]"
                                    />
                                </FormField>

                                <div class="form-toggle">
                                    <div>
                                        <p class="text-sm font-medium text-slate-700 dark:text-slate-200">{{ t('notifications.rules.enabled', 'Enabled') }}</p>
                                        <p class="t-caption mt-0.5">{{ form.enabled ? t('notifications.rules.enabledHint', 'Detector emits this alert.') : t('notifications.rules.disabledHint', 'Alert is silenced — no row inserted.') }}</p>
                                    </div>
                                    <button
                                        type="button"
                                        :class="['form-switch', { 'is-on': form.enabled }]"
                                        :aria-pressed="form.enabled"
                                        :aria-label="t('notifications.rules.enabled', 'Enabled')"
                                        @click="form.enabled = !form.enabled"
                                    />
                                </div>
                            </FormSection>

                            <FormSection
                                :title="t('notifications.rules.section.cooldown', 'Cooldown')"
                                :description="t('notifications.rules.section.cooldownDesc', 'How long to wait before the same alert can be re-raised. Leave empty to use the detector default.')"
                            >
                                <FormField :label="t('notifications.rules.cooldownMinutes', 'Cooldown (minutes)')" :error="formError('cooldown_minutes')">
                                    <input
                                        v-model.number="form.cooldown_minutes"
                                        type="number" min="1" max="43200"
                                        placeholder="240"
                                        :class="['form-input', { 'is-invalid': formError('cooldown_minutes') }]"
                                    />
                                </FormField>
                            </FormSection>

                            <FormSection
                                :title="t('notifications.rules.section.thresholds', 'Severity thresholds')"
                                :description="t('notifications.rules.section.thresholdsDesc', 'For count-based detectors, raise severity when meta.count crosses these values. Each threshold is independent.')"
                                cols="3"
                            >
                                <FormField :label="t('notifications.severity.warning')" :error="formError('warning_threshold')">
                                    <input v-model.number="form.warning_threshold" type="number" min="0" placeholder="5" :class="['form-input', { 'is-invalid': formError('warning_threshold') }]" />
                                </FormField>
                                <FormField :label="t('notifications.severity.danger')" :error="formError('danger_threshold')">
                                    <input v-model.number="form.danger_threshold" type="number" min="0" placeholder="20" :class="['form-input', { 'is-invalid': formError('danger_threshold') }]" />
                                </FormField>
                                <FormField :label="t('notifications.severity.critical')" :error="formError('critical_threshold')">
                                    <input v-model.number="form.critical_threshold" type="number" min="0" placeholder="50" :class="['form-input', { 'is-invalid': formError('critical_threshold') }]" />
                                </FormField>
                            </FormSection>

                            <FormSection
                                :title="t('notifications.rules.section.caps', 'Severity caps')"
                                :description="t('notifications.rules.section.capsDesc', 'Floor drops alerts below the chosen level. Ceiling clips noisy detectors to a maximum.')"
                                cols="2"
                            >
                                <FormField :label="t('notifications.rules.minSeverity', 'Minimum severity')">
                                    <select v-model="form.min_severity" class="form-input">
                                        <option :value="null">{{ t('notifications.rules.noFloor', '— No floor —') }}</option>
                                        <option value="info">{{ t('notifications.severity.info') }}</option>
                                        <option value="warning">{{ t('notifications.severity.warning') }}</option>
                                        <option value="danger">{{ t('notifications.severity.danger') }}</option>
                                        <option value="critical">{{ t('notifications.severity.critical') }}</option>
                                    </select>
                                </FormField>
                                <FormField :label="t('notifications.rules.maxSeverity', 'Maximum severity')">
                                    <select v-model="form.max_severity" class="form-input">
                                        <option :value="null">{{ t('notifications.rules.noCeiling', '— No ceiling —') }}</option>
                                        <option value="info">{{ t('notifications.severity.info') }}</option>
                                        <option value="warning">{{ t('notifications.severity.warning') }}</option>
                                        <option value="danger">{{ t('notifications.severity.danger') }}</option>
                                        <option value="critical">{{ t('notifications.severity.critical') }}</option>
                                    </select>
                                </FormField>
                            </FormSection>

                            <FormSection
                                :title="t('notifications.rules.section.audience', 'Audience')"
                                :description="t('notifications.rules.section.audienceDesc', 'Override who receives this alert. Empty = detector default (usually admin).')"
                            >
                                <FormField :label="t('notifications.rules.audienceRole', 'Audience role')">
                                    <select v-model="form.audience_role" class="form-input">
                                        <option :value="null">{{ t('notifications.rules.useDetectorDefault', '— Detector default —') }}</option>
                                        <option value="admin">admin</option>
                                        <option value="manager">manager</option>
                                        <option value="cashier">cashier</option>
                                    </select>
                                </FormField>
                            </FormSection>

                            <FormSection :title="t('notifications.rules.section.notes', 'Notes')">
                                <FormField :label="t('notifications.rules.notes', 'Notes')" :hint="t('notifications.rules.notesHint', 'Why this rule exists — visible to other admins.')">
                                    <textarea v-model="form.notes" rows="2" class="form-textarea" :placeholder="t('notifications.rules.notesPlaceholder', 'e.g. Reduced cooldown after Q3 stock-out incident.')" />
                                </FormField>
                            </FormSection>

                            <div v-if="serverError" class="card card-alert card-alert-danger text-sm">{{ serverError }}</div>
                        </form>

                        <footer class="form-modal-footer">
                            <Button variant="secondary" @click="modalOpen = false">
                                {{ t('common.cancel') }}
                            </Button>
                            <Button
                                variant="primary"
                                :loading="saving"
                                :leading-icon="saving ? null : CheckIcon"
                                @click="save"
                            >
                                {{ saving ? t('common.saving') : (isEdit ? t('common.saveChanges') : t('notifications.rules.addRule', 'Add rule')) }}
                            </Button>
                        </footer>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import {
    AdjustmentsHorizontalIcon, CheckIcon, PencilSquareIcon, PlusIcon,
    TrashIcon, XMarkIcon,
} from '@heroicons/vue/24/outline';
import { notificationService } from '@/services/notificationService';
import { useAlert } from '@/composables/useAlert';
import Skeleton    from '@/components/ui/Skeleton.vue';
import EmptyState  from '@/components/ui/EmptyState.vue';
import Button      from '@/components/ui/Button.vue';
import FormField   from '@/components/ui/FormField.vue';
import FormSection from '@/components/ui/FormSection.vue';

const { t } = useI18n();
const { toast, confirm } = useAlert();

const loading = ref(true);
const rules = ref([]);
const seenCodes = ref([]);

async function load() {
    loading.value = true;
    try {
        const [r, c] = await Promise.all([
            notificationService.rules(),
            notificationService.ruleCodes().catch(() => ({ data: { data: { codes: [], configured: [] } } })),
        ]);
        rules.value = r.data?.data ?? [];
        seenCodes.value = c.data?.data?.codes ?? [];
    } finally {
        loading.value = false;
    }
}
onMounted(load);

const undonecodes = computed(() => {
    const configured = new Set(rules.value.map(r => r.code));
    return seenCodes.value.filter(c => !configured.has(c.code));
});

// ── Modal state ──────────────────────────────────────────────────────────
const modalOpen = ref(false);
const isEdit = ref(false);
const editId = ref(null);
const saving = ref(false);
const errors = ref({});
const serverError = ref('');

const blankForm = () => ({
    code: '', enabled: true,
    cooldown_minutes: null,
    warning_threshold: null, danger_threshold: null, critical_threshold: null,
    min_severity: null, max_severity: null,
    audience_role: null, branch_ids: null, notes: null,
});
const form = ref(blankForm());

function openCreate(presetCode = '') {
    isEdit.value = false;
    editId.value = null;
    errors.value = {};
    serverError.value = '';
    form.value = blankForm();
    if (typeof presetCode === 'string' && presetCode) form.value.code = presetCode;
    modalOpen.value = true;
}

function openEdit(rule) {
    isEdit.value = true;
    editId.value = rule.id;
    errors.value = {};
    serverError.value = '';
    // Spread the row into the form — null columns stay null so the
    // "use detector default" placeholders re-appear in the inputs.
    form.value = {
        code:               rule.code,
        enabled:            rule.enabled,
        cooldown_minutes:   rule.cooldown_minutes,
        warning_threshold:  rule.warning_threshold,
        danger_threshold:   rule.danger_threshold,
        critical_threshold: rule.critical_threshold,
        min_severity:       rule.min_severity,
        max_severity:       rule.max_severity,
        audience_role:      rule.audience_role,
        branch_ids:         rule.branch_ids,
        notes:              rule.notes,
    };
    modalOpen.value = true;
}

async function save() {
    errors.value = {};
    serverError.value = '';
    saving.value = true;
    try {
        // Strip out empty-string code on create (validator catches it
        // but the error is friendlier coming from the input directly).
        const payload = { ...form.value };
        if (typeof payload.code === 'string') payload.code = payload.code.trim();
        if (!payload.code) {
            errors.value.code = t('notifications.rules.codeRequired', 'Code is required.');
            saving.value = false;
            return;
        }
        if (isEdit.value) await notificationService.updateRule(editId.value, payload);
        else              await notificationService.saveRule(payload);
        toast('success', t('common.savedSuccess', 'Saved.'));
        modalOpen.value = false;
        await load();
    } catch (err) {
        const data = err.response?.data;
        if (data?.errors) {
            Object.entries(data.errors).forEach(([k, v]) => {
                errors.value[k] = Array.isArray(v) ? v[0] : v;
            });
        } else {
            serverError.value = data?.message ?? t('common.unexpectedError');
        }
    } finally {
        saving.value = false;
    }
}

async function confirmDelete(rule) {
    const ok = await confirm({
        title: t('common.deleteConfirmTitle'),
        text:  t('notifications.rules.deleteConfirm', 'Delete the rule for "{code}"? The detector will revert to its built-in defaults.', { code: rule.code }),
        confirmText: t('common.delete'),
        danger: true,
    });
    if (!ok) return;
    try {
        await notificationService.deleteRule(rule.id);
        toast('success', t('common.deletedSuccess'));
        await load();
    } catch (err) {
        toast('error', err.response?.data?.message ?? t('common.unexpectedError'));
    }
}

function formError(field) {
    return errors.value?.[field] ?? '';
}

// ── Display helpers ──────────────────────────────────────────────────────

function summarizeRule(r) {
    const parts = [];
    if (r.cooldown_minutes != null) parts.push(`cooldown ${r.cooldown_minutes}m`);
    if (r.warning_threshold != null || r.danger_threshold != null || r.critical_threshold != null) {
        const t = [];
        if (r.warning_threshold != null)  t.push(`w≥${r.warning_threshold}`);
        if (r.danger_threshold != null)   t.push(`d≥${r.danger_threshold}`);
        if (r.critical_threshold != null) t.push(`c≥${r.critical_threshold}`);
        parts.push(`thresholds: ${t.join(' / ')}`);
    }
    if (r.max_severity)  parts.push(`cap ${r.max_severity}`);
    if (r.min_severity)  parts.push(`floor ${r.min_severity}`);
    if (r.audience_role) parts.push(`audience: ${r.audience_role}`);
    if (Array.isArray(r.branch_ids) && r.branch_ids.length) parts.push(`branches: ${r.branch_ids.join(', ')}`);
    return parts.length
        ? parts.join(' · ')
        : t('notifications.rules.allDefaults', 'All defaults — only the on/off switch differs.');
}

function sevPill(s) {
    return ({
        info:     'status-pill-info',
        success:  'status-pill-success',
        warning:  'status-pill-warning',
        danger:   'status-pill-danger',
        critical: 'status-pill-danger',
    })[s] || 'status-pill-neutral';
}
</script>

<style scoped>
@reference '../../../css/app.css';

.rule-row {
    display: flex;
    align-items: center;
    gap: 0.875rem;
    padding: 0.875rem 1.125rem;
    transition: background-color var(--motion-fast) var(--motion-out);
}
.rule-row:hover { background: rgb(248 250 252); }
html.dark .rule-row:hover { background: rgb(30 41 59 / 0.4); }

.rule-state-dot {
    width: 10px; height: 10px;
    border-radius: 999px;
    flex-shrink: 0;
}
.rule-state-dot.is-on  {
    background: rgb(16 185 129);
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.18);
}
.rule-state-dot.is-off {
    background: rgb(148 163 184);
    box-shadow: 0 0 0 3px rgba(148, 163, 184, 0.18);
}

.rule-code {
    font-family: ui-monospace, SF Mono, Menlo, monospace;
    font-size: 0.8125rem;
    font-weight: 600;
    color: var(--text-primary);
}

/* Modal enter/leave timing — same vocabulary as SupplierFormModal. */
.modal-enter-active, .modal-leave-active { transition: opacity var(--motion-base) var(--motion-out); }
.modal-enter-from, .modal-leave-to { opacity: 0; }
.modal-enter-active .form-modal-shell,
.modal-leave-active .form-modal-shell { transition: transform var(--motion-base) var(--motion-spring); }
.modal-enter-from .form-modal-shell,
.modal-leave-to   .form-modal-shell { transform: scale(0.97) translateY(8px); }
</style>
