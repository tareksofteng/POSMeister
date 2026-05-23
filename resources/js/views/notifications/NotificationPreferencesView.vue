<template>
    <div class="p-4 sm:p-6 lg:p-8 space-y-5 max-w-3xl mx-auto">
        <header>
            <p class="text-xs font-medium text-indigo-600 uppercase tracking-wider mb-1">{{ t('notifications.module') }}</p>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">{{ t('notifications.preferences') }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ t('notifications.preferencesSubtitle') }}</p>
        </header>

        <section class="card space-y-5">
            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-200 mb-2">{{ t('notifications.muteCategories') }}</label>
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="c in categories"
                        :key="c.key"
                        @click="toggleMute(c.key)"
                        :class="['px-3 py-1.5 text-xs font-semibold rounded-lg border',
                            isMuted(c.key)
                                ? 'bg-slate-200 dark:bg-slate-700 text-slate-500 border-slate-300 line-through'
                                : 'bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-200 border-slate-300']"
                    >{{ c.label }}</button>
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-200 mb-2">{{ t('notifications.minSeverity') }}</label>
                <select v-model="form.min_severity" class="w-full max-w-xs px-3 py-2 border border-slate-300 rounded-lg bg-white dark:bg-slate-900">
                    <option value="info">{{ t('notifications.severity.info') }}</option>
                    <option value="warning">{{ t('notifications.severity.warning') }}</option>
                    <option value="danger">{{ t('notifications.severity.danger') }}</option>
                    <option value="critical">{{ t('notifications.severity.critical') }}</option>
                </select>
                <p class="mt-1 text-xs text-slate-500">{{ t('notifications.minSeverityHint') }}</p>
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-200 mb-2">{{ t('notifications.quietHours') }}</label>
                <div class="flex items-center gap-2">
                    <input v-model="quietFrom" type="time" class="px-3 py-2 border border-slate-300 rounded-lg bg-white dark:bg-slate-900" />
                    <span class="text-slate-500">→</span>
                    <input v-model="quietTo"   type="time" class="px-3 py-2 border border-slate-300 rounded-lg bg-white dark:bg-slate-900" />
                    <button v-if="quietFrom || quietTo" @click="quietFrom = ''; quietTo = ''" class="text-xs text-slate-500 hover:underline ml-2">{{ t('common.clear') }}</button>
                </div>
                <p class="mt-1 text-xs text-slate-500">{{ t('notifications.quietHoursHint') }}</p>
            </div>

            <div>
                <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
                    <input v-model="form.digest.daily" type="checkbox" class="rounded" />
                    {{ t('notifications.dailyDigest') }}
                </label>
            </div>

            <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100 dark:border-slate-800">
                <button @click="save" :disabled="saving" class="px-4 py-2 rounded-lg bg-indigo-600 text-white font-semibold hover:bg-indigo-700 disabled:opacity-50">
                    {{ saving ? t('common.saving') : t('common.save') }}
                </button>
            </div>
        </section>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { notificationService } from '@/services/notificationService';

const { t } = useI18n();
const saving = ref(false);
const form = reactive({
    muted_categories: [],
    min_severity: 'info',
    quiet_hours: null,
    digest: { daily: true, weekly: false },
});
const quietFrom = ref('');
const quietTo   = ref('');

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
        });
        if (form.quiet_hours) {
            quietFrom.value = form.quiet_hours.from || '';
            quietTo.value   = form.quiet_hours.to   || '';
        }
    } catch { /* defaults already set */ }
});
</script>

<style scoped>
@reference '../../../css/app.css';
.card { @apply bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-sm p-5; }
</style>
