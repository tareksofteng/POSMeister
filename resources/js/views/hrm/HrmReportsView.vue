<template>
    <div class="p-6 lg:p-8 space-y-8 max-w-7xl mx-auto">

        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 tracking-tight">{{ t('hrm.reports.title') }}</h1>
                <p class="mt-1 text-sm text-slate-500">{{ t('hrm.reports.subtitle') }}</p>
            </div>
            <button @click="refreshAll" :disabled="loading.dashboard || loading.attendance || loading.payroll" class="btn-soft">
                <ArrowPathIcon :class="['w-4 h-4', anyLoading && 'animate-spin']" />
                {{ t('hrm.reports.refresh') }}
            </button>
        </div>

        <section v-if="dashboard">
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3 mb-5">
                <KpiCard :label="t('hrm.reports.kpi_total')"        :value="dashboard.kpis.total_employees"      icon="users"   tone="slate" />
                <KpiCard :label="t('hrm.reports.kpi_active')"       :value="dashboard.kpis.active"               icon="check"   tone="emerald" />
                <KpiCard :label="t('hrm.reports.kpi_presentToday')" :value="dashboard.kpis.present_today"        icon="bolt"    tone="indigo" />
                <KpiCard :label="t('hrm.reports.kpi_absentToday')"  :value="dashboard.kpis.absent_today"         icon="alert"   tone="rose" />
                <KpiCard :label="t('hrm.reports.kpi_payrollMonth')" :value="fmtCurrency(dashboard.kpis.monthly_payroll_net)" :money="true" icon="cash" tone="amber" />
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                <div class="card">
                    <h3 class="card-title">{{ t('hrm.reports.byDepartment') }}</h3>
                    <BarList :rows="dashboard.by_department" name-key="name" value-key="count" tone="indigo" />
                </div>
                <div class="card">
                    <h3 class="card-title">{{ t('hrm.reports.byBranch') }}</h3>
                    <BarList :rows="dashboard.by_branch" name-key="name" value-key="count" tone="emerald" />
                </div>
                <div class="card">
                    <h3 class="card-title">{{ t('hrm.reports.byEmploymentType') }}</h3>
                    <BarList
                        :rows="employmentRows"
                        name-key="label"
                        value-key="count"
                        tone="amber"
                    />
                </div>
                <div class="card">
                    <h3 class="card-title">{{ t('hrm.reports.recentHires') }}</h3>
                    <ul v-if="dashboard.recent_hires.length" class="divide-y divide-slate-100">
                        <li v-for="h in dashboard.recent_hires" :key="h.id" class="py-2.5 flex items-center justify-between">
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-slate-900 truncate">{{ h.name }}</p>
                                <p class="text-xs text-slate-500 mt-0.5">
                                    <span class="font-mono">{{ h.employee_id }}</span>
                                    <span v-if="h.designation"> · {{ h.designation }}</span>
                                </p>
                            </div>
                            <span class="text-xs text-slate-600 font-mono">{{ formatDate(h.joining_date) }}</span>
                        </li>
                    </ul>
                    <p v-else class="text-sm text-slate-400 py-4 text-center">{{ t('hrm.reports.noRecentHires') }}</p>
                </div>
            </div>
        </section>

        <section>
            <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3 mb-4">
                <div>
                    <h2 class="text-lg font-bold text-slate-900">{{ t('hrm.reports.attendanceTitle') }}</h2>
                    <p class="text-xs text-slate-500 mt-0.5">{{ t('hrm.reports.attendanceSubtitle') }}</p>
                </div>
                <div class="flex items-end gap-2">
                    <div>
                        <label class="lbl">{{ t('common.dateFrom') }}</label>
                        <input v-model="attendanceFilters.from" @change="loadAttendance" type="date" class="ctrl w-36" />
                    </div>
                    <div>
                        <label class="lbl">{{ t('common.dateTo') }}</label>
                        <input v-model="attendanceFilters.to" @change="loadAttendance" type="date" class="ctrl w-36" />
                    </div>
                </div>
            </div>

            <div v-if="attendance" class="grid grid-cols-1 lg:grid-cols-3 gap-5">
                <div class="card">
                    <h3 class="card-title">{{ t('hrm.reports.attendanceTotals') }}</h3>
                    <BarList :rows="attendanceTotalsRows" name-key="label" value-key="value" :tone-by-key="true" />
                </div>

                <div class="card lg:col-span-2">
                    <h3 class="card-title">{{ t('hrm.reports.byDepartment') }}</h3>
                    <table v-if="attendance.by_department.length" class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-[11px] text-slate-500 uppercase tracking-wide border-b border-slate-100">
                                <th class="py-1.5">{{ t('hrm.fields.department') }}</th>
                                <th class="py-1.5 text-right text-emerald-600">{{ t('hrm.attendance.short_present') }}</th>
                                <th class="py-1.5 text-right text-orange-600">{{ t('hrm.attendance.short_late') }}</th>
                                <th class="py-1.5 text-right text-indigo-600">{{ t('hrm.attendance.short_half_day') }}</th>
                                <th class="py-1.5 text-right text-amber-600">{{ t('hrm.attendance.short_leave') }}</th>
                                <th class="py-1.5 text-right text-rose-600">{{ t('hrm.attendance.short_absent') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            <tr v-for="d in attendance.by_department" :key="d.department">
                                <td class="py-1.5 font-medium text-slate-800">{{ d.department }}</td>
                                <td class="py-1.5 text-right font-mono text-emerald-700">{{ d.present }}</td>
                                <td class="py-1.5 text-right font-mono text-orange-700">{{ d.late }}</td>
                                <td class="py-1.5 text-right font-mono text-indigo-700">{{ d.half_day }}</td>
                                <td class="py-1.5 text-right font-mono text-amber-700">{{ d.leave }}</td>
                                <td class="py-1.5 text-right font-mono text-rose-700">{{ d.absent }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-else class="text-sm text-slate-400 py-4 text-center">{{ t('hrm.reports.noData') }}</p>
                </div>

                <div class="card">
                    <h3 class="card-title">{{ t('hrm.reports.topPunctual') }}</h3>
                    <ul v-if="attendance.top_punctual.length" class="divide-y divide-slate-100">
                        <li v-for="(r, i) in attendance.top_punctual" :key="r.employee_id" class="py-2 flex items-center justify-between">
                            <span class="text-sm text-slate-700">
                                <span class="text-xs text-emerald-600 font-bold mr-2">#{{ i + 1 }}</span>
                                {{ r.name }}
                            </span>
                            <span class="text-xs text-emerald-700 font-mono">{{ r.days }} {{ t('hrm.reports.days') }}</span>
                        </li>
                    </ul>
                    <p v-else class="text-sm text-slate-400 py-4 text-center">{{ t('hrm.reports.noData') }}</p>
                </div>

                <div class="card lg:col-span-2">
                    <h3 class="card-title">{{ t('hrm.reports.topAbsent') }}</h3>
                    <ul v-if="attendance.top_absent.length" class="divide-y divide-slate-100">
                        <li v-for="(r, i) in attendance.top_absent" :key="r.employee_id" class="py-2 flex items-center justify-between">
                            <span class="text-sm text-slate-700">
                                <span class="text-xs text-rose-600 font-bold mr-2">#{{ i + 1 }}</span>
                                {{ r.name }}
                            </span>
                            <span class="text-xs text-rose-700 font-mono">{{ r.days }} {{ t('hrm.reports.days') }}</span>
                        </li>
                    </ul>
                    <p v-else class="text-sm text-slate-400 py-4 text-center">{{ t('hrm.reports.noData') }}</p>
                </div>
            </div>
        </section>

        <section>
            <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3 mb-4">
                <div>
                    <h2 class="text-lg font-bold text-slate-900">{{ t('hrm.reports.payrollTitle') }}</h2>
                    <p class="text-xs text-slate-500 mt-0.5">{{ t('hrm.reports.payrollSubtitle') }}</p>
                </div>
                <div>
                    <label class="lbl">{{ t('hrm.payroll.period') }}</label>
                    <select v-model.number="payrollFilters.period_id" @change="loadPayroll" class="ctrl w-56">
                        <option v-for="p in periods" :key="p.id" :value="p.id">{{ p.label }}</option>
                    </select>
                </div>
            </div>

            <div v-if="payroll && payroll.period" class="grid grid-cols-1 lg:grid-cols-3 gap-5">
                <div class="card lg:col-span-1">
                    <h3 class="card-title">{{ t('hrm.reports.payrollTotals') }}</h3>
                    <dl class="space-y-2 text-sm">
                        <Line :label="t('hrm.payroll.basicSalary')" :value="fmtCurrency(payroll.totals.basic)" />
                        <Line :label="t('hrm.payroll.allowances')"   :value="fmtCurrency(payroll.totals.allowances)" tone="emerald" />
                        <Line :label="t('hrm.payroll.bonuses')"      :value="fmtCurrency(payroll.totals.bonuses)" tone="emerald" />
                        <Line :label="t('hrm.payroll.overtime')"     :value="fmtCurrency(payroll.totals.overtime)" tone="emerald" />
                        <Line :label="t('hrm.payroll.deductions')"   :value="fmtCurrency(payroll.totals.deductions)" tone="rose" />
                        <Line :label="t('hrm.payroll.tax')"          :value="fmtCurrency(payroll.totals.tax)" tone="rose" />
                        <div class="pt-2 mt-1 border-t border-slate-200" />
                        <Line :label="t('hrm.payroll.gross')"        :value="fmtCurrency(payroll.totals.gross)" bold />
                        <Line :label="t('hrm.payroll.net')"          :value="fmtCurrency(payroll.totals.net)" bold tone="indigo" />
                        <Line :label="t('hrm.payroll.paid')"         :value="fmtCurrency(payroll.totals.paid)" tone="emerald" />
                    </dl>
                </div>

                <div class="card">
                    <h3 class="card-title">{{ t('hrm.reports.byDepartment') }}</h3>
                    <BarList
                        :rows="payroll.by_department"
                        name-key="department"
                        value-key="net"
                        :format-value="fmtCurrency"
                        tone="indigo"
                    />
                </div>

                <div class="card">
                    <h3 class="card-title">{{ t('hrm.reports.byBranch') }}</h3>
                    <BarList
                        :rows="payroll.by_branch"
                        name-key="branch"
                        value-key="net"
                        :format-value="fmtCurrency"
                        tone="emerald"
                    />
                </div>

                <div class="card lg:col-span-3">
                    <h3 class="card-title">{{ t('hrm.reports.topEarners') }}</h3>
                    <table v-if="payroll.top_earners.length" class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-[11px] text-slate-500 uppercase tracking-wide border-b border-slate-100">
                                <th class="py-1.5">#</th>
                                <th class="py-1.5">{{ t('hrm.reports.employee') }}</th>
                                <th class="py-1.5">{{ t('hrm.payroll.payslipNumber') }}</th>
                                <th class="py-1.5 text-right">{{ t('hrm.payroll.gross') }}</th>
                                <th class="py-1.5 text-right">{{ t('hrm.payroll.net') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            <tr v-for="(e, i) in payroll.top_earners" :key="e.id">
                                <td class="py-1.5 text-slate-400 text-xs">{{ i + 1 }}</td>
                                <td class="py-1.5 font-medium text-slate-900">
                                    {{ e.name }}
                                    <span class="text-xs text-slate-500 font-mono ml-2">{{ e.employee_id }}</span>
                                </td>
                                <td class="py-1.5 font-mono text-xs text-slate-500">{{ e.payslip_number }}</td>
                                <td class="py-1.5 text-right font-mono text-slate-700">{{ fmtCurrency(e.gross) }}</td>
                                <td class="py-1.5 text-right font-mono font-semibold text-indigo-700">{{ fmtCurrency(e.net) }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-else class="text-sm text-slate-400 py-4 text-center">{{ t('hrm.reports.noData') }}</p>
                </div>
            </div>

            <div v-else-if="!loading.payroll" class="card text-center text-sm text-slate-500 py-12">
                {{ t('hrm.reports.noPayrollPeriod') }}
            </div>
        </section>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, h } from 'vue';
import { useI18n } from 'vue-i18n';
import { hrmReportsService, payrollPeriodService } from '@/services/hrmService';
import { useCurrency } from '@/composables/useCurrency';
import { ArrowPathIcon } from '@heroicons/vue/24/outline';

const { t } = useI18n();
const { fmtCurrency } = useCurrency();

const dashboard  = ref(null);
const attendance = ref(null);
const payroll    = ref(null);
const periods    = ref([]);

const loading = ref({ dashboard: false, attendance: false, payroll: false });
const anyLoading = computed(() => loading.value.dashboard || loading.value.attendance || loading.value.payroll);

const today = new Date().toISOString().slice(0, 10);
const monthStart = (() => {
    const d = new Date();
    return new Date(d.getFullYear(), d.getMonth(), 1).toISOString().slice(0, 10);
})();

const attendanceFilters = ref({ from: monthStart, to: today });
const payrollFilters    = ref({ period_id: null });

const employmentRows = computed(() => {
    if (!dashboard.value) return [];
    return Object.entries(dashboard.value.by_employment_type ?? {}).map(([type, count]) => ({
        label: t('hrm.employment.' + type),
        count,
    }));
});

const attendanceTotalsRows = computed(() => {
    if (!attendance.value) return [];
    const m = attendance.value.totals;
    return [
        { key: 'present',  label: t('hrm.attendance.status_present'),  value: m.present },
        { key: 'late',     label: t('hrm.attendance.status_late'),     value: m.late },
        { key: 'half_day', label: t('hrm.attendance.status_half_day'), value: m.half_day },
        { key: 'leave',    label: t('hrm.attendance.status_leave'),    value: m.leave },
        { key: 'absent',   label: t('hrm.attendance.status_absent'),   value: m.absent },
    ];
});

// --- inline components --------------------------------------------------

const KpiCard = (props) => {
    const palette = {
        slate:   'border-slate-200 bg-white',
        emerald: 'border-emerald-200 bg-emerald-50/40',
        indigo:  'border-indigo-200 bg-indigo-50/40',
        rose:    'border-rose-200 bg-rose-50/40',
        amber:   'border-amber-200 bg-amber-50/40',
    }[props.tone] ?? 'border-slate-200 bg-white';

    return h('div', { class: `border rounded-xl shadow-sm px-4 py-3 ${palette}` }, [
        h('p', { class: 'text-[11px] uppercase tracking-wide text-slate-500 font-medium' }, props.label),
        h('p', { class: 'text-xl font-bold text-slate-900 mt-1 font-mono' }, props.money ? props.value : String(props.value ?? 0)),
    ]);
};
KpiCard.props = ['label', 'value', 'tone', 'money', 'icon'];

const BarList = (props) => {
    const rows = props.rows ?? [];
    if (rows.length === 0) {
        return h('p', { class: 'text-sm text-slate-400 py-4 text-center' }, t('hrm.reports.noData'));
    }
    const max = Math.max(...rows.map(r => Number(r[props.valueKey] ?? 0))) || 1;
    const palette = {
        indigo:  'bg-indigo-500',
        emerald: 'bg-emerald-500',
        amber:   'bg-amber-500',
        rose:    'bg-rose-500',
    };
    const toneByKey = {
        present:  'bg-emerald-500',
        late:     'bg-orange-500',
        half_day: 'bg-indigo-500',
        leave:    'bg-amber-500',
        absent:   'bg-rose-500',
    };

    return h('div', { class: 'space-y-2' }, rows.map((r, idx) => {
        const value = Number(r[props.valueKey] ?? 0);
        const pct   = max > 0 ? Math.max(2, (value / max) * 100) : 0;
        const tone  = props.toneByKey ? (toneByKey[r.key] ?? 'bg-slate-400') : (palette[props.tone] ?? 'bg-indigo-500');
        const display = props.formatValue ? props.formatValue(value) : value;
        return h('div', { key: r[props.nameKey] ?? idx, class: 'space-y-0.5' }, [
            h('div', { class: 'flex items-center justify-between text-xs' }, [
                h('span', { class: 'text-slate-700' }, String(r[props.nameKey] ?? '—')),
                h('span', { class: 'font-mono font-medium text-slate-800' }, String(display)),
            ]),
            h('div', { class: 'h-2 bg-slate-100 rounded-full overflow-hidden' }, [
                h('div', { class: `${tone} h-full rounded-full`, style: { width: pct + '%' } }),
            ]),
        ]);
    }));
};
BarList.props = ['rows', 'nameKey', 'valueKey', 'tone', 'toneByKey', 'formatValue'];

const Line = (props) => {
    const toneClass = {
        emerald: 'text-emerald-700',
        rose:    'text-rose-700',
        indigo:  'text-indigo-700',
    }[props.tone] ?? 'text-slate-700';
    return h('div', { class: 'flex items-center justify-between' }, [
        h('dt', { class: 'text-slate-600' + (props.bold ? ' font-semibold' : '') }, props.label),
        h('dd', { class: `font-mono ${props.bold ? 'font-bold' : ''} ${toneClass}` }, props.value),
    ]);
};
Line.props = ['label', 'value', 'tone', 'bold'];

function formatDate(s) {
    return s ? new Date(s + 'T00:00:00').toLocaleDateString('en-US') : '';
}

// --- data loading -------------------------------------------------------

async function loadDashboard() {
    loading.value.dashboard = true;
    try {
        const { data } = await hrmReportsService.dashboard();
        dashboard.value = data.data;
    } finally {
        loading.value.dashboard = false;
    }
}

async function loadAttendance() {
    loading.value.attendance = true;
    try {
        const { data } = await hrmReportsService.attendance(attendanceFilters.value);
        attendance.value = data.data;
    } finally {
        loading.value.attendance = false;
    }
}

async function loadPayroll() {
    loading.value.payroll = true;
    try {
        const params = payrollFilters.value.period_id ? { period_id: payrollFilters.value.period_id } : {};
        const { data } = await hrmReportsService.payroll(params);
        payroll.value = data.data;
        if (payroll.value?.period?.id && !payrollFilters.value.period_id) {
            payrollFilters.value.period_id = payroll.value.period.id;
        }
    } finally {
        loading.value.payroll = false;
    }
}

async function loadPeriods() {
    try {
        const { data } = await payrollPeriodService.index({ per_page: 50 });
        periods.value = data.data ?? [];
    } catch {
        periods.value = [];
    }
}

function refreshAll() {
    loadDashboard();
    loadAttendance();
    loadPayroll();
}

onMounted(async () => {
    await Promise.all([loadDashboard(), loadAttendance(), loadPeriods()]);
    await loadPayroll();
});
</script>

<style scoped>
@reference '../../../css/app.css';
.card        { @apply bg-white border border-slate-200 rounded-xl shadow-sm p-5; }
.card-title  { @apply text-xs font-bold text-slate-500 uppercase tracking-wider mb-3; }
.btn-soft    { @apply inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-slate-300 text-slate-700 text-sm hover:bg-slate-50 transition-colors disabled:opacity-50; }
.lbl         { @apply block text-xs font-medium text-slate-600 mb-1.5; }
.ctrl        { @apply px-3 py-2 text-sm border border-slate-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent; }
</style>
