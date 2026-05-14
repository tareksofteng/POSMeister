<template>
    <div class="p-6 lg:p-8 space-y-6">

        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 tracking-tight">{{ t('hrm.attendance.monthlyTitle') }}</h1>
                <p class="mt-1 text-sm text-slate-500">{{ t('hrm.attendance.monthlySubtitle') }}</p>
            </div>
        </div>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-4 flex flex-wrap gap-3 items-end">
            <div>
                <label class="lbl">{{ t('hrm.attendance.month') }}</label>
                <select v-model.number="month" @change="fetchMatrix" class="ctrl w-40">
                    <option v-for="(n, i) in monthNames" :key="i + 1" :value="i + 1">{{ n }}</option>
                </select>
            </div>
            <div>
                <label class="lbl">{{ t('hrm.attendance.year') }}</label>
                <select v-model.number="year" @change="fetchMatrix" class="ctrl w-28">
                    <option v-for="y in yearRange" :key="y" :value="y">{{ y }}</option>
                </select>
            </div>
            <div>
                <label class="lbl">{{ t('hrm.fields.department') }}</label>
                <select v-model.number="departmentId" @change="fetchMatrix" class="ctrl w-48">
                    <option :value="null">{{ t('hrm.filters.allDepartments') }}</option>
                    <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
                </select>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2 text-xs">
            <span class="text-slate-500 font-medium mr-1">{{ t('hrm.attendance.legend') }}:</span>
            <Legend code="P" label="status_present"  bg="bg-emerald-500" />
            <Legend code="L" label="status_late"     bg="bg-orange-500" />
            <Legend code="H" label="status_half_day" bg="bg-indigo-500" />
            <Legend code="U" label="status_leave"    bg="bg-amber-500" />
            <Legend code="A" label="status_absent"   bg="bg-rose-500" />
        </div>

        <div v-if="errorMsg" class="rounded-lg bg-rose-50 border border-rose-200 px-4 py-3 text-sm text-rose-700">{{ errorMsg }}</div>

        <div v-if="loading" class="py-16 text-center text-slate-400">
            <div class="w-6 h-6 border-2 border-indigo-500 border-t-transparent rounded-full animate-spin mx-auto mb-2"></div>
            {{ t('common.loading') }}
        </div>

        <div v-else-if="rows.length === 0" class="py-16 text-center bg-white rounded-xl border border-dashed border-slate-300">
            <p class="text-sm text-slate-500">{{ t('hrm.attendance.noEmployees') }}</p>
        </div>

        <div v-else class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="text-xs">
                    <thead class="bg-slate-50 border-b border-slate-100">
                        <tr>
                            <th class="sticky left-0 z-10 bg-slate-50 px-3 py-2 text-left text-[10px] font-semibold text-slate-500 uppercase tracking-wide min-w-[200px]">
                                {{ t('hrm.employees.name') }}
                            </th>
                            <th
                                v-for="d in daysInMonth" :key="d"
                                class="px-1 py-2 text-center font-semibold text-slate-500 w-7"
                            >
                                {{ d }}
                            </th>
                            <th class="px-3 py-2 text-center font-semibold text-emerald-600">P</th>
                            <th class="px-3 py-2 text-center font-semibold text-orange-600">L</th>
                            <th class="px-3 py-2 text-center font-semibold text-rose-600">A</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="r in rows" :key="r.employee_id" class="hover:bg-slate-50/40">
                            <td class="sticky left-0 z-10 bg-white hover:bg-slate-50/40 px-3 py-2 align-middle">
                                <p class="font-medium text-slate-900">{{ r.employee_name }}</p>
                                <p class="font-mono text-[10px] text-slate-500">{{ r.employee_code }}</p>
                            </td>
                            <td
                                v-for="d in daysInMonth" :key="d"
                                class="px-1 py-1 text-center"
                            >
                                <span
                                    v-if="r.days[d]"
                                    :class="['inline-flex items-center justify-center w-6 h-6 rounded font-bold text-[10px] text-white', cellClass(r.days[d])]"
                                    :title="t('hrm.attendance.status_' + r.days[d].status)"
                                >
                                    {{ cellLabel(r.days[d]) }}
                                </span>
                                <span v-else class="text-slate-300 text-xs">·</span>
                            </td>
                            <td class="px-3 py-1 text-center font-mono font-semibold text-emerald-700">
                                {{ r.summary.present + r.summary.late + r.summary.half_day }}
                            </td>
                            <td class="px-3 py-1 text-center font-mono font-semibold text-orange-700">{{ r.summary.late }}</td>
                            <td class="px-3 py-1 text-center font-mono font-semibold text-rose-700">{{ r.summary.absent }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, h } from 'vue';
import { useI18n } from 'vue-i18n';
import { attendanceService, departmentService } from '@/services/hrmService';

const { t, locale } = useI18n();

const today = new Date();
const year   = ref(today.getFullYear());
const month  = ref(today.getMonth() + 1);
const departmentId = ref(null);

const rows = ref([]);
const daysInMonth = ref(31);
const departments = ref([]);
const loading = ref(false);
const errorMsg = ref('');

const yearRange = computed(() => {
    const current = today.getFullYear();
    const out = [];
    for (let y = current - 3; y <= current + 1; y++) out.push(y);
    return out;
});

const monthNames = computed(() => {
    // Use the current i18n locale's month names via Intl
    const fmt = new Intl.DateTimeFormat(locale.value || 'de-DE', { month: 'long' });
    return Array.from({ length: 12 }, (_, i) => fmt.format(new Date(2025, i, 1)));
});

const Legend = (props) => h('span', { class: 'inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md bg-white border border-slate-200' }, [
    h('span', { class: `w-4 h-4 rounded text-white font-bold text-[10px] flex items-center justify-center ${props.bg}` }, props.code),
    h('span', { class: 'text-slate-700' }, t('hrm.attendance.' + props.label)),
]);
Legend.props = ['code', 'label', 'bg'];

function cellLabel(cell) {
    return {
        present:  'P',
        late:     'L',
        half_day: 'H',
        leave:    'U',
        absent:   'A',
    }[cell.status] ?? '?';
}

function cellClass(cell) {
    return {
        present:  'bg-emerald-500',
        late:     'bg-orange-500',
        half_day: 'bg-indigo-500',
        leave:    'bg-amber-500',
        absent:   'bg-rose-500',
    }[cell.status] ?? 'bg-slate-400';
}

async function fetchMatrix() {
    loading.value = true;
    errorMsg.value = '';
    try {
        const params = { year: year.value, month: month.value };
        if (departmentId.value) params.department_id = departmentId.value;
        const { data } = await attendanceService.monthly(params);
        rows.value = data.rows ?? [];
        daysInMonth.value = data.days_in_month ?? 31;
    } catch (err) {
        errorMsg.value = err.response?.data?.message ?? t('common.unexpectedError');
    } finally {
        loading.value = false;
    }
}

async function fetchDepartments() {
    try {
        const { data } = await departmentService.all();
        departments.value = data.data ?? [];
    } catch {
        departments.value = [];
    }
}

onMounted(async () => {
    await fetchDepartments();
    fetchMatrix();
});
</script>

<style scoped>
@reference '../../../css/app.css';
.lbl  { @apply block text-xs font-medium text-slate-600 mb-1.5; }
.ctrl { @apply px-3 py-2 text-sm border border-slate-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent; }
</style>
