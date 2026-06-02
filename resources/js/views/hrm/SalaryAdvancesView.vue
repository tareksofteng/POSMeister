<template>
    <div class="p-6 lg:p-8 space-y-5 max-w-7xl mx-auto">

        <header class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 tracking-tight">{{ t('hrm.advance.title') }}</h1>
                <p class="mt-1 text-sm text-slate-500">{{ t('hrm.advance.subtitle') }}</p>
            </div>
            <button @click="openCreate" class="btn-primary">
                <PlusIcon class="w-4 h-4" /> {{ t('hrm.advance.add') }}
            </button>
        </header>

        <div class="card flex flex-wrap items-end gap-3">
            <div>
                <label class="lbl">{{ t('common.status') }}</label>
                <select v-model="statusFilter" @change="load" class="ctrl w-44">
                    <option value="">{{ t('common.all') }}</option>
                    <option value="outstanding">{{ t('hrm.advance.status.outstanding') }}</option>
                    <option value="partially_deducted">{{ t('hrm.advance.status.partially_deducted') }}</option>
                    <option value="settled">{{ t('hrm.advance.status.settled') }}</option>
                    <option value="cancelled">{{ t('hrm.advance.status.cancelled') }}</option>
                </select>
            </div>
        </div>

        <div class="card overflow-hidden p-0">
            <table class="w-full text-sm">
                <thead class="bg-slate-50/70">
                    <tr class="text-left text-[11px] text-slate-500 uppercase tracking-wide border-b border-slate-100">
                        <th class="px-4 py-2.5">{{ t('hrm.approval.employee') }}</th>
                        <th class="px-4 py-2.5">{{ t('hrm.advance.granted') }}</th>
                        <th class="px-4 py-2.5 text-right">{{ t('hrm.advance.amount') }}</th>
                        <th class="px-4 py-2.5 text-right">{{ t('hrm.advance.deducted') }}</th>
                        <th class="px-4 py-2.5 text-right">{{ t('hrm.advance.outstanding') }}</th>
                        <th class="px-4 py-2.5">{{ t('common.status') }}</th>
                        <th class="px-4 py-2.5 text-right w-24"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <tr v-for="a in rows" :key="a.id" class="hover:bg-slate-50/60">
                        <td class="px-4 py-2">
                            <p class="font-medium text-slate-800">{{ a.employee?.first_name }} {{ a.employee?.last_name }}</p>
                            <p class="text-[11px] font-mono text-slate-500">{{ a.employee?.employee_id }}</p>
                        </td>
                        <td class="px-4 py-2 text-xs text-slate-600">{{ formatDate(a.granted_on) }}</td>
                        <td class="px-4 py-2 text-right font-mono">{{ fmtCurrency(a.amount) }}</td>
                        <td class="px-4 py-2 text-right font-mono">{{ fmtCurrency(a.deducted_amount) }}</td>
                        <td class="px-4 py-2 text-right font-mono font-semibold text-amber-700">
                            {{ fmtCurrency(Math.max(0, a.amount - a.deducted_amount)) }}
                        </td>
                        <td class="px-4 py-2">
                            <span :class="badge(a.status)" class="text-[10px] uppercase tracking-wider px-2 py-0.5 rounded-md font-bold">
                                {{ t('hrm.advance.status.' + a.status) }}
                            </span>
                        </td>
                        <td class="px-4 py-2 text-right text-xs">
                            <button v-if="canCancel(a)" @click="cancel(a)" class="text-rose-600 hover:underline">{{ t('common.cancel') }}</button>
                        </td>
                    </tr>
                    <tr v-if="!loading && rows.length === 0">
                        <td colspan="7" class="py-12 text-center text-sm text-slate-400">{{ t('hrm.advance.empty') }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="formOpen" class="fixed inset-0 bg-slate-900/40 flex items-center justify-center z-50 p-4">
            <div class="bg-white w-full max-w-md rounded-xl shadow-xl">
                <header class="px-5 py-3 border-b border-slate-100 flex items-center justify-between">
                    <h3 class="text-base font-bold text-slate-900">{{ t('hrm.advance.add') }}</h3>
                    <button @click="formOpen = false"><XMarkIcon class="w-5 h-5 text-slate-500" /></button>
                </header>
                <div class="px-5 py-4 space-y-3">
                    <div>
                        <label class="lbl">{{ t('hrm.approval.employee') }}</label>
                        <SearchableSelect
                            :model-value="form.employee_id"
                            @update:model-value="v => form.employee_id = v"
                            :options="employeeOptions"
                            :placeholder="'— ' + t('common.search') + ' —'"
                            :search-placeholder="t('common.search') + '…'"
                            :empty-text="t('common.noResults')"
                            :clear-label="t('common.clear')"
                        />
                    </div>
                    <div>
                        <label class="lbl">{{ t('hrm.advance.amount') }}</label>
                        <input v-model.number="form.amount" type="number" step="0.01" min="0" class="ctrl w-full font-mono" />
                    </div>
                    <div>
                        <label class="lbl">{{ t('hrm.advance.grantedOn') }}</label>
                        <input v-model="form.granted_on" type="date" class="ctrl w-full" />
                    </div>
                    <div>
                        <label class="lbl">{{ t('hrm.advance.reason') }}</label>
                        <input v-model="form.reason" class="ctrl w-full" />
                    </div>
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
import { ref, reactive, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { salaryAdvanceService, employeeService } from '@/services/hrmService';
import { useCurrency } from '@/composables/useCurrency';
import { useAlert } from '@/composables/useAlert';
import { PlusIcon, XMarkIcon } from '@heroicons/vue/24/outline';
import SearchableSelect from '@/components/SearchableSelect.vue';

const { t, locale } = useI18n();
const { fmtCurrency } = useCurrency();
const { confirm, toast } = useAlert();

const rows = ref([]);
const employees = ref([]);
const employeeOptions = computed(() => employees.value.map(e => ({
    value: e.id,
    label: `${e.first_name} ${e.last_name}`,
    sub:   e.employee_id,
})));
const loading = ref(false);
const statusFilter = ref('');

const formOpen = ref(false);
const saving = ref(false);
const form = reactive({ employee_id: null, amount: 0, granted_on: new Date().toISOString().slice(0, 10), reason: '' });

function formatDate(d) {
    return new Intl.DateTimeFormat(locale.value || 'en-US', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(new Date(d));
}
function badge(s) {
    return {
        outstanding:        'bg-amber-100 text-amber-800',
        partially_deducted: 'bg-amber-100 text-amber-800',
        settled:            'bg-emerald-100 text-emerald-800',
        cancelled:          'bg-slate-100 text-slate-700',
    }[s] ?? 'bg-slate-100 text-slate-700';
}
function canCancel(a) { return ['outstanding', 'partially_deducted'].includes(a.status); }

async function load() {
    loading.value = true;
    try {
        const { data } = await salaryAdvanceService.index({ status: statusFilter.value || undefined, per_page: 50 });
        rows.value = data.data ?? [];
    } finally {
        loading.value = false;
    }
}

async function openCreate() {
    formOpen.value = true;
    if (employees.value.length === 0) {
        const { data } = await employeeService.index({ per_page: 200 });
        employees.value = data.data ?? [];
    }
}

async function save() {
    if (!form.employee_id || !form.amount) return;
    saving.value = true;
    try {
        await salaryAdvanceService.store(form);
        toast.success(t('common.created'));
        formOpen.value = false;
        load();
    } finally {
        saving.value = false;
    }
}

async function cancel(a) {
    const reason = window.prompt(t('hrm.advance.cancelPrompt'));
    if (!reason) return;
    await salaryAdvanceService.cancel(a.id, { reason });
    toast.success(t('common.updated'));
    load();
}

onMounted(load);
</script>

<style scoped>
@reference '../../../css/app.css';
.card        { @apply bg-white border border-slate-200 rounded-xl shadow-sm; }
.btn-soft    { @apply inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-slate-300 text-slate-700 text-sm hover:bg-slate-50 transition-colors; }
.btn-primary { @apply inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700 transition-colors disabled:opacity-50; }
.lbl         { @apply block text-xs font-medium text-slate-600 mb-1; }
.ctrl        { @apply px-3 py-2 text-sm border border-slate-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent; }
</style>
