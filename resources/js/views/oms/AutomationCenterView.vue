<template>
    <div class="p-6 lg:p-8 space-y-5 max-w-7xl mx-auto">

        <header class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 tracking-tight">{{ t('oms.automation.title') }}</h1>
                <p class="mt-1 text-sm text-slate-500">{{ t('oms.automation.subtitle') }}</p>
            </div>
            <div class="flex gap-2">
                <button @click="runAll" :disabled="busy" class="btn-soft">
                    <PlayIcon class="w-4 h-4" /> {{ t('oms.automation.runAll') }}
                </button>
                <button @click="openCreate" class="btn-primary">
                    <PlusIcon class="w-4 h-4" /> {{ t('oms.automation.newRule') }}
                </button>
            </div>
        </header>

        <div class="card overflow-hidden p-0">
            <table class="w-full text-sm">
                <thead class="bg-slate-50/70">
                    <tr class="text-left text-[11px] text-slate-500 uppercase tracking-wide border-b border-slate-100">
                        <th class="px-4 py-2.5">{{ t('oms.automation.name') }}</th>
                        <th class="px-4 py-2.5">{{ t('oms.automation.trigger') }}</th>
                        <th class="px-4 py-2.5">{{ t('oms.automation.action') }}</th>
                        <th class="px-4 py-2.5 text-right">{{ t('oms.automation.runs') }}</th>
                        <th class="px-4 py-2.5 text-right">{{ t('oms.automation.matches') }}</th>
                        <th class="px-4 py-2.5">{{ t('oms.automation.lastRun') }}</th>
                        <th class="px-4 py-2.5 text-center">{{ t('common.status') }}</th>
                        <th class="px-4 py-2.5 text-right w-40"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <tr v-for="r in rules" :key="r.id" class="hover:bg-slate-50/60">
                        <td class="px-4 py-2 font-medium text-slate-800">{{ r.name }}</td>
                        <td class="px-4 py-2 text-xs font-mono text-slate-600">{{ r.trigger }}</td>
                        <td class="px-4 py-2 text-xs">{{ r.action_type }}</td>
                        <td class="px-4 py-2 text-right font-mono">{{ r.run_count }}</td>
                        <td class="px-4 py-2 text-right font-mono">{{ r.match_count }}</td>
                        <td class="px-4 py-2 text-xs text-slate-600">{{ formatDate(r.last_run_at) }}</td>
                        <td class="px-4 py-2 text-center">
                            <span :class="['inline-block w-2 h-2 rounded-full', r.is_active ? 'bg-emerald-500' : 'bg-slate-300']"></span>
                        </td>
                        <td class="px-4 py-2 text-right text-xs space-x-2">
                            <button @click="run(r)" class="text-emerald-600 hover:underline">{{ t('oms.automation.runNow') }}</button>
                            <button @click="openEdit(r)" class="text-indigo-600 hover:underline">{{ t('common.edit') }}</button>
                            <button @click="destroy(r)" class="text-rose-600 hover:underline">{{ t('common.delete') }}</button>
                        </td>
                    </tr>
                    <tr v-if="!loading && rules.length === 0">
                        <td colspan="8" class="py-10 text-center text-sm text-slate-400">{{ t('oms.automation.empty') }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <section v-if="logs.length" class="card">
            <h3 class="card-title">{{ t('oms.automation.recentLogs') }}</h3>
            <ul class="space-y-1.5">
                <li v-for="l in logs.slice(0, 12)" :key="l.id" class="grid grid-cols-12 gap-3 items-center text-xs py-1.5 border-b border-slate-50 last:border-0">
                    <span class="col-span-3 text-slate-600 font-mono">{{ formatDate(l.triggered_at) }}</span>
                    <span class="col-span-3 text-slate-800">{{ l.rule?.name ?? '—' }}</span>
                    <span class="col-span-2 font-mono text-slate-500">{{ l.rule?.trigger }}</span>
                    <span class="col-span-2">
                        <span :class="logBadge(l.status)" class="text-[10px] uppercase tracking-wider px-2 py-0.5 rounded-md font-bold">
                            {{ t('oms.automation.logStatus.' + l.status) }}
                        </span>
                    </span>
                    <span class="col-span-2 text-right text-slate-700 font-mono">{{ l.matched_count }} matched</span>
                </li>
            </ul>
        </section>

        <!-- Rule form modal -->
        <div v-if="formOpen" class="fixed inset-0 bg-slate-900/40 flex items-center justify-center z-50 p-4">
            <div class="bg-white w-full max-w-lg rounded-xl shadow-xl">
                <header class="px-5 py-3 border-b border-slate-100 flex items-center justify-between">
                    <h3 class="text-base font-bold text-slate-900">
                        {{ editing?.id ? t('oms.automation.editRule') : t('oms.automation.newRule') }}
                    </h3>
                    <button @click="formOpen = false"><XMarkIcon class="w-5 h-5 text-slate-500" /></button>
                </header>
                <div class="px-5 py-4 space-y-3">
                    <div>
                        <label class="lbl">{{ t('oms.automation.name') }}</label>
                        <input v-model="form.name" class="ctrl w-full" />
                    </div>
                    <div>
                        <label class="lbl">{{ t('oms.automation.trigger') }}</label>
                        <select v-model="form.trigger" class="ctrl w-full">
                            <option value="stock.low">stock.low</option>
                            <option value="stock.dead">stock.dead</option>
                            <option value="customer.inactive">customer.inactive</option>
                            <option value="payment.overdue">payment.overdue</option>
                            <option value="supplier.delay">supplier.delay</option>
                            <option value="inventory.negative_risk">inventory.negative_risk</option>
                        </select>
                    </div>
                    <div>
                        <label class="lbl">{{ t('oms.automation.action') }}</label>
                        <select v-model="form.action_type" class="ctrl w-full">
                            <option value="notify">notify</option>
                            <option value="reorder_suggest">reorder_suggest</option>
                            <option value="task">task</option>
                            <option value="risk_flag">risk_flag</option>
                        </select>
                    </div>
                    <div>
                        <label class="lbl">{{ t('oms.automation.conditionHint') }}</label>
                        <textarea v-model="conditionJson" rows="3" class="ctrl w-full font-mono text-xs" placeholder='{"days": 90}'></textarea>
                    </div>
                    <div>
                        <label class="lbl">{{ t('oms.automation.actionConfigHint') }}</label>
                        <textarea v-model="actionConfigJson" rows="3" class="ctrl w-full font-mono text-xs" placeholder='{"recipient_type":"user","recipient_id":1,"channel":"in_app"}'></textarea>
                    </div>
                    <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" v-model="form.is_active" class="rounded border-slate-300" />
                        {{ t('common.active') }}
                    </label>
                </div>
                <footer class="px-5 py-3 border-t border-slate-100 flex justify-end gap-2">
                    <button @click="formOpen = false" class="btn-soft">{{ t('common.cancel') }}</button>
                    <button @click="save" :disabled="saving" class="btn-primary">{{ t('common.save') }}</button>
                </footer>
            </div>
        </div>

    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { automationService } from '@/services/omsService';
import { useAlert } from '@/composables/useAlert';
import { PlayIcon, PlusIcon, XMarkIcon } from '@heroicons/vue/24/outline';

const { t, locale } = useI18n();
const { toast, confirm } = useAlert();

const rules = ref([]);
const logs = ref([]);
const loading = ref(false);
const busy = ref(false);

const formOpen = ref(false);
const saving = ref(false);
const editing = ref(null);
const form = reactive({ name: '', trigger: 'stock.low', action_type: 'notify', is_active: true });
const conditionJson = ref('{}');
const actionConfigJson = ref('{}');

function formatDate(d) {
    if (!d) return '—';
    return new Intl.DateTimeFormat(locale.value || 'de-DE',
        { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' }
    ).format(new Date(d));
}

function logBadge(status) {
    return {
        matched:       'bg-emerald-100 text-emerald-800',
        no_match:      'bg-slate-100 text-slate-700',
        action_failed: 'bg-amber-100 text-amber-800',
        error:         'bg-rose-100 text-rose-800',
    }[status] ?? 'bg-slate-100 text-slate-700';
}

function openCreate() {
    editing.value = null;
    Object.assign(form, { name: '', trigger: 'stock.low', action_type: 'notify', is_active: true });
    conditionJson.value = '{}';
    actionConfigJson.value = '{}';
    formOpen.value = true;
}

function openEdit(r) {
    editing.value = r;
    Object.assign(form, {
        name: r.name, trigger: r.trigger, action_type: r.action_type, is_active: !!r.is_active,
    });
    conditionJson.value = JSON.stringify(r.condition ?? {}, null, 2);
    actionConfigJson.value = JSON.stringify(r.action_config ?? {}, null, 2);
    formOpen.value = true;
}

async function load() {
    loading.value = true;
    try {
        const [{ data: rs }, { data: ls }] = await Promise.all([
            automationService.rules(),
            automationService.logs({ per_page: 20 }),
        ]);
        rules.value = rs.data ?? [];
        logs.value  = ls.data ?? [];
    } finally {
        loading.value = false;
    }
}

async function save() {
    saving.value = true;
    try {
        const payload = { ...form };
        try { payload.condition     = JSON.parse(conditionJson.value     || '{}'); } catch { payload.condition = {}; }
        try { payload.action_config = JSON.parse(actionConfigJson.value || '{}'); } catch { payload.action_config = {}; }

        if (editing.value) {
            await automationService.update(editing.value.id, payload);
            toast.success(t('common.updated'));
        } else {
            await automationService.store(payload);
            toast.success(t('common.created'));
        }
        formOpen.value = false;
        await load();
    } finally {
        saving.value = false;
    }
}

async function destroy(r) {
    if (!(await confirm(t('oms.automation.deleteConfirm', { name: r.name })))) return;
    await automationService.destroy(r.id);
    toast.success(t('common.deleted'));
    load();
}

async function run(r) {
    busy.value = true;
    try {
        await automationService.run(r.id);
        toast.success(t('oms.automation.ran'));
        load();
    } finally {
        busy.value = false;
    }
}

async function runAll() {
    busy.value = true;
    try {
        await automationService.runAll();
        toast.success(t('oms.automation.ranAll'));
        load();
    } finally {
        busy.value = false;
    }
}

onMounted(load);
</script>

<style scoped>
@reference '../../../css/app.css';
.card        { @apply bg-white border border-slate-200 rounded-xl shadow-sm p-5; }
.card-title  { @apply text-xs font-bold text-slate-500 uppercase tracking-wider mb-3; }
.btn-soft    { @apply inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-slate-300 text-slate-700 text-sm hover:bg-slate-50 transition-colors disabled:opacity-50; }
.btn-primary { @apply inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700 transition-colors disabled:opacity-50; }
.lbl         { @apply block text-xs font-medium text-slate-600 mb-1; }
.ctrl        { @apply px-3 py-2 text-sm border border-slate-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent; }
</style>
