<template>
    <div class="p-6 lg:p-8 max-w-5xl mx-auto space-y-6">

        <div class="flex items-center gap-3">
            <RouterLink :to="{ name: 'hrm-employees' }" class="p-2 text-slate-400 hover:text-slate-700 hover:bg-slate-100 rounded-lg">
                <ArrowLeftIcon class="w-5 h-5" />
            </RouterLink>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">{{ t('hrm.profile.title') }}</h1>
        </div>

        <div v-if="loading" class="text-center py-16 text-slate-400">
            <div class="w-8 h-8 border-2 border-indigo-500 border-t-transparent rounded-full animate-spin mx-auto mb-3"></div>
            <p class="text-sm">{{ t('common.loading') }}</p>
        </div>

        <template v-else-if="employee">
            <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
                <div class="bg-gradient-to-r from-indigo-50 to-slate-50 px-6 py-6 border-b border-slate-100">
                    <div class="flex flex-col sm:flex-row items-start sm:items-center gap-5">
                        <EmployeeAvatar :src="employee.photo_url" :name="employee.full_name" size="xl" />
                        <div class="flex-1 min-w-0">
                            <h2 class="text-2xl font-bold text-slate-900">{{ employee.full_name }}</h2>
                            <p class="text-sm text-slate-600 mt-1">
                                {{ employee.designation?.title ?? '—' }}
                                <span v-if="employee.department"> · {{ employee.department.name }}</span>
                            </p>
                            <div class="flex flex-wrap items-center gap-2 mt-2">
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs bg-white border border-slate-200 text-slate-600 font-mono">
                                    {{ employee.employee_id }}
                                </span>
                                <EmployeeStatusBadge :status="employee.status" />
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs bg-indigo-50 text-indigo-700">
                                    {{ t('hrm.employment.' + employee.employment_type) }}
                                </span>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 self-stretch sm:self-auto">
                            <RouterLink :to="{ name: 'hrm-employee-edit', params: { id: employee.id } }" class="btn-soft">
                                <PencilSquareIcon class="w-4 h-4" />
                                {{ t('common.edit') }}
                            </RouterLink>
                            <button @click="cycleStatus" class="btn-primary" :disabled="updating">
                                <ArrowsRightLeftIcon v-if="!updating" class="w-4 h-4" />
                                <ArrowPathIcon v-else class="w-4 h-4 animate-spin" />
                                {{ t('hrm.profile.changeStatus') }}
                            </button>
                        </div>
                    </div>
                </div>

                <div class="px-6 pt-4 border-b border-slate-100">
                    <nav class="flex gap-6 -mb-px">
                        <button
                            v-for="tab in tabs" :key="tab.key"
                            @click="activeTab = tab.key"
                            :disabled="tab.disabled"
                            :class="[
                                'pb-3 pt-1 text-sm font-medium border-b-2 transition-colors',
                                activeTab === tab.key
                                    ? 'border-indigo-600 text-indigo-700'
                                    : tab.disabled
                                        ? 'border-transparent text-slate-300 cursor-not-allowed'
                                        : 'border-transparent text-slate-500 hover:text-slate-700',
                            ]"
                        >
                            {{ tab.label }}
                            <span v-if="tab.disabled" class="ml-1 text-[10px] uppercase tracking-wider text-slate-400">{{ t('common.soon') }}</span>
                        </button>
                    </nav>
                </div>

                <div v-if="activeTab === 'overview'" class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="info-block">
                        <h3>{{ t('hrm.profile.personalInfo') }}</h3>
                        <dl>
                            <Row :label="t('hrm.fields.gender')"          :value="t('hrm.gender.' + employee.gender)" />
                            <Row :label="t('hrm.fields.date_of_birth')"   :value="formatDate(employee.date_of_birth)" />
                            <Row :label="t('hrm.fields.blood_group')"     :value="employee.blood_group" />
                            <Row :label="t('hrm.fields.marital_status')"  :value="employee.marital_status ? t('hrm.marital.' + employee.marital_status) : ''" />
                            <Row :label="t('hrm.fields.nationality')"     :value="employee.nationality" />
                            <Row :label="t('hrm.fields.religion')"        :value="employee.religion" />
                            <Row :label="t('hrm.fields.email')"           :value="employee.email" />
                            <Row :label="t('hrm.fields.phone')"           :value="employee.phone" />
                            <Row :label="t('hrm.fields.emergency_contact')" :value="employee.emergency_contact" />
                            <Row :label="t('hrm.fields.address')"         :value="formatAddress(employee)" />
                        </dl>
                    </div>

                    <div class="info-block">
                        <h3>{{ t('hrm.profile.employmentInfo') }}</h3>
                        <dl>
                            <Row :label="t('hrm.fields.employee_id')"     :value="employee.employee_id" mono />
                            <Row :label="t('hrm.fields.joining_date')"    :value="formatDate(employee.joining_date)" />
                            <Row :label="t('hrm.fields.employment_type')" :value="t('hrm.employment.' + employee.employment_type)" />
                            <Row :label="t('hrm.fields.branch')"          :value="employee.branch?.name" />
                            <Row :label="t('hrm.fields.department')"      :value="employee.department?.name" />
                            <Row :label="t('hrm.fields.designation')"     :value="employee.designation?.title" />
                            <Row :label="t('hrm.fields.shift')"           :value="shiftLabel" />
                            <Row :label="t('hrm.fields.basic_salary')"    :value="formatSalary(employee.basic_salary)" />
                            <Row :label="t('hrm.fields.national_id')"     :value="employee.national_id" />
                            <Row :label="t('hrm.fields.passport_number')" :value="employee.passport_number" />
                            <Row :label="t('hrm.fields.work_permit_no')"  :value="employee.work_permit_no" />
                        </dl>
                    </div>

                    <div class="info-block md:col-span-2" v-if="employee.notes">
                        <h3>{{ t('hrm.form.notes') }}</h3>
                        <p class="text-sm text-slate-700 whitespace-pre-line">{{ employee.notes }}</p>
                    </div>
                </div>

                <div v-else class="p-12 text-center text-sm text-slate-400">
                    {{ t('hrm.profile.tabPlaceholder') }}
                </div>
            </div>
        </template>

        <div v-else class="text-center py-16">
            <p class="text-slate-500">{{ t('common.unexpectedError') }}</p>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, h } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute, RouterLink } from 'vue-router';
import { employeeService } from '@/services/hrmService';
import { useCurrency } from '@/composables/useCurrency';
import { useAlert } from '@/composables/useAlert';
import EmployeeAvatar from '@/components/hrm/EmployeeAvatar.vue';
import EmployeeStatusBadge from '@/components/hrm/EmployeeStatusBadge.vue';
import {
    ArrowLeftIcon, PencilSquareIcon, ArrowsRightLeftIcon, ArrowPathIcon,
} from '@heroicons/vue/24/outline';

const { t } = useI18n();
const route = useRoute();
const { fmtCurrency } = useCurrency();
const { toast, confirm } = useAlert();

const employee = ref(null);
const loading  = ref(true);
const updating = ref(false);
const activeTab = ref('overview');

const tabs = computed(() => [
    { key: 'overview',   label: t('hrm.profile.overview'),    disabled: false },
    { key: 'documents',  label: t('hrm.profile.documents'),   disabled: true },
    { key: 'attendance', label: t('hrm.profile.attendance'),  disabled: true },
    { key: 'payroll',    label: t('hrm.profile.payroll'),     disabled: true },
]);

const shiftLabel = computed(() => {
    const s = employee.value?.shift;
    if (!s) return '';
    return `${s.name} (${s.start_time} - ${s.end_time})`;
});

function formatDate(s) {
    if (!s) return '';
    return new Date(s + 'T00:00:00').toLocaleDateString('de-DE');
}

function formatSalary(amount) {
    if (!amount) return '';
    return fmtCurrency(amount);
}

function formatAddress(e) {
    const parts = [e.address, e.postal_code && e.city ? `${e.postal_code} ${e.city}` : (e.postal_code || e.city), e.country];
    return parts.filter(Boolean).join(', ');
}

const Row = (props) => h('div', { class: 'row' }, [
    h('dt', props.label),
    h('dd', { class: props.mono ? 'font-mono' : '' }, props.value || '—'),
]);
Row.props = ['label', 'value', 'mono'];

async function cycleStatus() {
    const nextMap = { active: 'inactive', inactive: 'active', terminated: 'active', resigned: 'active' };
    const next = nextMap[employee.value.status] ?? 'active';
    const ok = await confirm({
        title:       t('hrm.profile.statusConfirmTitle'),
        text:        t('hrm.profile.statusConfirmMessage', {
            from: t('hrm.status_' + employee.value.status),
            to:   t('hrm.status_' + next),
        }),
        confirmText: t('common.confirm'),
    });
    if (!ok) return;
    updating.value = true;
    try {
        const { data } = await employeeService.setStatus(employee.value.id, next);
        employee.value = data.data ?? data;
        toast('success', t('hrm.profile.statusUpdated'));
    } catch (err) {
        toast('error', err.response?.data?.message ?? t('common.unexpectedError'));
    } finally {
        updating.value = false;
    }
}

onMounted(async () => {
    try {
        const { data } = await employeeService.show(route.params.id);
        employee.value = data.data ?? data;
    } catch {
        employee.value = null;
    } finally {
        loading.value = false;
    }
});
</script>

<style scoped>
@reference '../../../css/app.css';

.btn-primary { @apply inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold disabled:opacity-50 disabled:cursor-not-allowed transition-colors; }
.btn-soft    { @apply inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-slate-300 text-slate-700 text-sm hover:bg-slate-50 transition-colors; }

.info-block       { @apply bg-slate-50/40 rounded-lg p-5 border border-slate-100; }
.info-block h3    { @apply text-[11px] font-semibold uppercase tracking-wider text-indigo-600 mb-3; }
.info-block dl    { @apply space-y-2; }
.info-block .row  { @apply flex items-baseline gap-4 text-sm; }
.info-block dt    { @apply text-slate-500 w-40 flex-shrink-0; }
.info-block dd    { @apply text-slate-800 font-medium flex-1; }
</style>
