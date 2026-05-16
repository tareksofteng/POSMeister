<template>
    <div class="p-6 lg:p-8 space-y-6">

        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 tracking-tight">{{ t('hrm.payroll.title') }}</h1>
                <p class="mt-1 text-sm text-slate-500">{{ t('hrm.payroll.subtitle') }}</p>
            </div>
            <button @click="openCreate" class="btn-primary">
                <PlusIcon class="w-4 h-4" />
                {{ t('hrm.payroll.addPeriod') }}
            </button>
        </div>

        <div v-if="errorMsg" class="rounded-lg bg-rose-50 border border-rose-200 px-4 py-3 text-sm text-rose-700">{{ errorMsg }}</div>

        <div v-if="loading" class="text-center py-16 text-slate-400">
            <div class="w-8 h-8 border-2 border-indigo-500 border-t-transparent rounded-full animate-spin mx-auto mb-3"></div>
            <p class="text-sm">{{ t('common.loading') }}</p>
        </div>

        <div v-else-if="periods.length === 0" class="bg-white rounded-xl border border-dashed border-slate-300 py-16 text-center">
            <BanknotesIcon class="w-10 h-10 text-slate-300 mx-auto mb-2" />
            <p class="text-sm text-slate-500">{{ t('hrm.payroll.empty') }}</p>
            <button @click="openCreate" class="btn-primary mt-4 inline-flex">
                <PlusIcon class="w-4 h-4" />
                {{ t('hrm.payroll.addPeriod') }}
            </button>
        </div>

        <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <RouterLink
                v-for="p in periods" :key="p.id"
                :to="{ name: 'hrm-payroll-period', params: { id: p.id } }"
                class="group bg-white border border-slate-200 hover:border-indigo-300 hover:shadow-md rounded-xl shadow-sm p-5 transition-all"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-10 h-10 rounded-lg bg-indigo-50 group-hover:bg-indigo-100 flex items-center justify-center flex-shrink-0">
                            <BanknotesIcon class="w-5 h-5 text-indigo-600" />
                        </div>
                        <div class="min-w-0">
                            <h3 class="font-semibold text-slate-900 truncate">{{ p.label }}</h3>
                            <p class="text-xs text-slate-500 mt-0.5">
                                {{ formatDate(p.period_start) }} - {{ formatDate(p.period_end) }}
                            </p>
                        </div>
                    </div>
                    <StatusBadge :status="p.status" />
                </div>

                <div class="mt-4 grid grid-cols-3 gap-2 text-center">
                    <div class="px-2 py-2 bg-slate-50/70 rounded-lg">
                        <p class="text-[10px] uppercase tracking-wider text-slate-500">{{ t('hrm.payroll.payslips') }}</p>
                        <p class="text-base font-bold text-slate-800 mt-0.5">{{ p.payslips_count ?? 0 }}</p>
                    </div>
                    <div class="px-2 py-2 bg-emerald-50/70 rounded-lg">
                        <p class="text-[10px] uppercase tracking-wider text-emerald-600">{{ t('hrm.payroll.paid') }}</p>
                        <p class="text-base font-bold text-emerald-700 mt-0.5">{{ p.paid_count ?? 0 }}</p>
                    </div>
                    <div class="px-2 py-2 bg-indigo-50/70 rounded-lg">
                        <p class="text-[10px] uppercase tracking-wider text-indigo-600">{{ t('hrm.payroll.netTotal') }}</p>
                        <p class="text-sm font-bold text-indigo-700 mt-0.5 font-mono">{{ fmtCurrency(p.net_total ?? 0) }}</p>
                    </div>
                </div>
            </RouterLink>
        </div>

        <Modal v-model="modalOpen" :title="t('hrm.payroll.createPeriodTitle')" size="md">
            <form @submit.prevent="save" class="space-y-4" id="period-form">
                <div>
                    <label class="lbl">{{ t('hrm.payroll.label') }} <span class="text-rose-500">*</span></label>
                    <input v-model="form.label" type="text" class="ctrl" placeholder="z. B. Mai 2026" required />
                    <p v-if="errors.label" class="err">{{ errors.label }}</p>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="lbl">{{ t('hrm.payroll.startDate') }} <span class="text-rose-500">*</span></label>
                        <input v-model="form.period_start" @change="autofillLabel" type="date" class="ctrl" required />
                        <p v-if="errors.period_start" class="err">{{ errors.period_start }}</p>
                    </div>
                    <div>
                        <label class="lbl">{{ t('hrm.payroll.endDate') }} <span class="text-rose-500">*</span></label>
                        <input v-model="form.period_end" type="date" class="ctrl" required />
                        <p v-if="errors.period_end" class="err">{{ errors.period_end }}</p>
                    </div>
                </div>
                <div>
                    <label class="lbl">{{ t('hrm.fields.branch') }}</label>
                    <select v-model.number="form.branch_id" class="ctrl">
                        <option :value="null">{{ t('hrm.payroll.allBranches') }}</option>
                        <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                    </select>
                    <p class="text-[11px] text-slate-400 mt-1">{{ t('hrm.payroll.branchHint') }}</p>
                </div>
                <div>
                    <label class="lbl">{{ t('hrm.fields.description') }}</label>
                    <textarea v-model="form.notes" rows="2" class="ctrl resize-none"></textarea>
                </div>
            </form>
            <template #footer>
                <button @click="modalOpen = false" class="btn-soft">{{ t('common.cancel') }}</button>
                <button @click="save" :disabled="saving" type="submit" form="period-form" class="btn-primary">
                    <CheckIcon v-if="!saving" class="w-4 h-4" />
                    <ArrowPathIcon v-else class="w-4 h-4 animate-spin" />
                    {{ saving ? t('common.saving') : t('common.create') }}
                </button>
            </template>
        </Modal>
    </div>
</template>

<script setup>
import { ref, onMounted, h } from 'vue';
import { useI18n } from 'vue-i18n';
import { RouterLink, useRouter } from 'vue-router';
import { payrollPeriodService } from '@/services/hrmService';
import { branchService } from '@/services/branchService';
import { useAlert } from '@/composables/useAlert';
import { useCurrency } from '@/composables/useCurrency';
import Modal from '@/components/ui/Modal.vue';
import { PlusIcon, BanknotesIcon, CheckIcon, ArrowPathIcon } from '@heroicons/vue/24/outline';

const { t } = useI18n();
const router = useRouter();
const { toast } = useAlert();
const { fmtCurrency } = useCurrency();

const periods = ref([]);
const branches = ref([]);
const loading = ref(false);
const errorMsg = ref('');

const modalOpen = ref(false);
const saving = ref(false);
const errors = ref({});
const form = ref({ label: '', period_start: '', period_end: '', branch_id: null, notes: '' });

const StatusBadge = (props) => {
    const palette = {
        draft:     { tone: 'bg-slate-100 text-slate-700', dot: 'bg-slate-400' },
        generated: { tone: 'bg-blue-100 text-blue-700',   dot: 'bg-blue-500' },
        finalized: { tone: 'bg-amber-100 text-amber-700', dot: 'bg-amber-500' },
        closed:    { tone: 'bg-emerald-100 text-emerald-700', dot: 'bg-emerald-500' },
    }[props.status] ?? { tone: 'bg-slate-100 text-slate-700', dot: 'bg-slate-400' };

    return h('span', { class: `inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[11px] font-medium ${palette.tone}` }, [
        h('span', { class: `w-1.5 h-1.5 rounded-full ${palette.dot}` }),
        t('hrm.payroll.status_' + props.status),
    ]);
};
StatusBadge.props = ['status'];

function formatDate(s) {
    return s ? new Date(s + 'T00:00:00').toLocaleDateString('de-DE') : '';
}

function autofillLabel() {
    if (form.value.label || !form.value.period_start) return;
    const d = new Date(form.value.period_start);
    form.value.label = d.toLocaleDateString('de-DE', { month: 'long', year: 'numeric' });
    // also default the end to end-of-month if empty
    if (!form.value.period_end) {
        const eom = new Date(d.getFullYear(), d.getMonth() + 1, 0);
        form.value.period_end = eom.toISOString().slice(0, 10);
    }
}

function openCreate() {
    form.value = {
        label: '', period_start: '', period_end: '', branch_id: null, notes: '',
    };
    errors.value = {};
    modalOpen.value = true;
}

async function save() {
    errors.value = {};
    saving.value = true;
    try {
        const { data } = await payrollPeriodService.store(form.value);
        toast('success', t('common.createdSuccess'));
        modalOpen.value = false;
        const id = (data.data ?? data).id;
        router.push({ name: 'hrm-payroll-period', params: { id } });
    } catch (err) {
        const data = err.response?.data;
        if (data?.errors) {
            Object.entries(data.errors).forEach(([k, v]) => {
                errors.value[k] = Array.isArray(v) ? v[0] : v;
            });
        } else {
            toast('error', data?.message ?? t('common.unexpectedError'));
        }
    } finally {
        saving.value = false;
    }
}

async function fetchPeriods() {
    loading.value = true;
    errorMsg.value = '';
    try {
        const { data } = await payrollPeriodService.index();
        periods.value = data.data ?? [];
    } catch (err) {
        errorMsg.value = err.response?.data?.message ?? t('common.unexpectedError');
    } finally {
        loading.value = false;
    }
}

async function fetchBranches() {
    try {
        const { data } = await branchService.all();
        branches.value = data.data ?? [];
    } catch {
        branches.value = [];
    }
}

onMounted(async () => {
    await Promise.all([fetchPeriods(), fetchBranches()]);
});
</script>

<style scoped>
@reference '../../../css/app.css';
.btn-primary { @apply inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg transition-colors shadow-sm disabled:opacity-50; }
.btn-soft    { @apply inline-flex items-center px-4 py-2 rounded-lg border border-slate-300 text-slate-700 text-sm hover:bg-slate-50 transition-colors; }
.lbl         { @apply block text-xs font-medium text-slate-600 mb-1.5; }
.ctrl        { @apply w-full px-3 py-2 text-sm border border-slate-200 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent; }
.err         { @apply text-xs text-rose-600 mt-1; }
</style>
