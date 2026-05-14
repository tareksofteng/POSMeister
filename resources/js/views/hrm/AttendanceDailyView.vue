<template>
    <div class="p-6 lg:p-8 space-y-6">

        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 tracking-tight">{{ t('hrm.attendance.dailyTitle') }}</h1>
                <p class="mt-1 text-sm text-slate-500">{{ t('hrm.attendance.dailySubtitle') }}</p>
            </div>
            <div class="flex items-end gap-2">
                <div>
                    <label class="lbl">{{ t('common.date') }}</label>
                    <input v-model="date" @change="fetchSheet" type="date" class="ctrl w-44" />
                </div>
                <button @click="save" :disabled="saving || !dirty" class="btn-primary">
                    <CheckIcon v-if="!saving" class="w-4 h-4" />
                    <ArrowPathIcon v-else class="w-4 h-4 animate-spin" />
                    {{ saving ? t('common.saving') : t('hrm.attendance.save') }}
                </button>
            </div>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-3">
            <StatChip :label="t('hrm.attendance.total')"     :value="stats.total"     dot="bg-slate-400" />
            <StatChip :label="t('hrm.attendance.marked')"    :value="stats.marked"    dot="bg-indigo-400" />
            <StatChip :label="t('hrm.attendance.unmarked')"  :value="stats.unmarked"  dot="bg-slate-300" />
            <StatChip :label="t('hrm.attendance.status_present')"  :value="stats.present"  dot="bg-emerald-500" />
            <StatChip :label="t('hrm.attendance.status_late')"     :value="stats.late"     dot="bg-orange-500" />
            <StatChip :label="t('hrm.attendance.status_absent')"   :value="stats.absent"   dot="bg-rose-500" />
            <StatChip :label="t('hrm.attendance.status_leave')"    :value="stats.leave"    dot="bg-amber-500" />
        </div>

        <div v-if="errorMsg" class="rounded-lg bg-rose-50 border border-rose-200 px-4 py-3 text-sm text-rose-700">{{ errorMsg }}</div>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
            <div class="px-5 py-3 border-b border-slate-100 flex items-center justify-between flex-wrap gap-3">
                <p class="text-sm text-slate-600">
                    {{ t('hrm.attendance.bulkHelp') }}
                </p>
                <div class="flex items-center gap-2">
                    <button @click="bulkSet('present')"  class="quick-btn quick-emerald">{{ t('hrm.attendance.markAllPresent') }}</button>
                    <button @click="bulkSet('absent')"   class="quick-btn quick-rose">{{ t('hrm.attendance.markAllAbsent') }}</button>
                </div>
            </div>

            <div v-if="loading" class="py-16 text-center text-slate-400">
                <div class="w-6 h-6 border-2 border-indigo-500 border-t-transparent rounded-full animate-spin mx-auto mb-2"></div>
                {{ t('common.loading') }}
            </div>

            <div v-else-if="rows.length === 0" class="py-16 text-center text-sm text-slate-500">
                {{ t('hrm.attendance.noEmployees') }}
            </div>

            <div v-else class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50/70 border-b border-slate-100">
                        <tr>
                            <th class="th">{{ t('hrm.employees.name') }}</th>
                            <th class="th">{{ t('hrm.fields.shift') }}</th>
                            <th class="th">{{ t('common.status') }}</th>
                            <th class="th w-24">{{ t('hrm.attendance.checkIn') }}</th>
                            <th class="th w-24">{{ t('hrm.attendance.checkOut') }}</th>
                            <th class="th">{{ t('hrm.attendance.remarks') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="row in rows" :key="row.employee_id" :class="row.status ? '' : 'bg-slate-50/30'">
                            <td class="td">
                                <div class="flex items-center gap-2.5">
                                    <EmployeeAvatar :src="row.photo_url" :name="row.employee_name" size="sm" />
                                    <div class="min-w-0">
                                        <p class="font-medium text-slate-900 truncate">{{ row.employee_name }}</p>
                                        <p class="text-xs text-slate-500 font-mono">{{ row.employee_code }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="td text-xs text-slate-600">
                                <span v-if="row.shift_name">
                                    {{ row.shift_name }}
                                    <span class="text-slate-400">({{ row.shift_start }}-{{ row.shift_end }})</span>
                                </span>
                                <span v-else class="text-slate-300">—</span>
                            </td>
                            <td class="td">
                                <div class="flex items-center gap-1">
                                    <button
                                        v-for="opt in statusOptions" :key="opt.value"
                                        @click="setStatus(row, opt.value)"
                                        :class="[
                                            'px-2.5 py-1 rounded-md text-[11px] font-semibold border transition-colors',
                                            row.status === opt.value ? opt.activeClass : 'border-slate-200 text-slate-500 hover:bg-slate-50',
                                        ]"
                                        :title="t(`hrm.attendance.status_${opt.value}`)"
                                    >
                                        {{ opt.short }}
                                    </button>
                                </div>
                            </td>
                            <td class="td">
                                <input
                                    v-model="row.check_in"
                                    @input="markDirty(row)"
                                    type="time"
                                    class="ctrl-sm font-mono"
                                />
                            </td>
                            <td class="td">
                                <input
                                    v-model="row.check_out"
                                    @input="markDirty(row)"
                                    type="time"
                                    class="ctrl-sm font-mono"
                                />
                            </td>
                            <td class="td">
                                <input
                                    v-model="row.remarks"
                                    @input="markDirty(row)"
                                    type="text"
                                    class="ctrl-sm"
                                    :placeholder="t('hrm.attendance.remarksPh')"
                                />
                            </td>
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
import { attendanceService } from '@/services/hrmService';
import { useAlert } from '@/composables/useAlert';
import EmployeeAvatar from '@/components/hrm/EmployeeAvatar.vue';
import { CheckIcon, ArrowPathIcon } from '@heroicons/vue/24/outline';

const { t } = useI18n();
const { toast } = useAlert();

const today = new Date().toISOString().slice(0, 10);
const date = ref(today);

const rows = ref([]);
const stats = ref({ total: 0, marked: 0, unmarked: 0, present: 0, absent: 0, leave: 0, late: 0, half_day: 0 });
const loading = ref(false);
const saving = ref(false);
const dirty = ref(false);
const errorMsg = ref('');

const statusOptions = computed(() => [
    { value: 'present',  short: t('hrm.attendance.short_present'),  activeClass: 'bg-emerald-100 text-emerald-700 border-emerald-300' },
    { value: 'late',     short: t('hrm.attendance.short_late'),     activeClass: 'bg-orange-100 text-orange-700 border-orange-300' },
    { value: 'half_day', short: t('hrm.attendance.short_half_day'), activeClass: 'bg-indigo-100 text-indigo-700 border-indigo-300' },
    { value: 'leave',    short: t('hrm.attendance.short_leave'),    activeClass: 'bg-amber-100 text-amber-700 border-amber-300' },
    { value: 'absent',   short: t('hrm.attendance.short_absent'),   activeClass: 'bg-rose-100 text-rose-700 border-rose-300' },
]);

const StatChip = (props) => h('div', { class: 'bg-white border border-slate-200 rounded-lg px-3 py-2 flex items-center justify-between' }, [
    h('span', { class: 'inline-flex items-center gap-2' }, [
        h('span', { class: `w-1.5 h-1.5 rounded-full ${props.dot}` }),
        h('span', { class: 'text-[11px] uppercase tracking-wide text-slate-500 font-medium' }, props.label),
    ]),
    h('span', { class: 'text-lg font-bold text-slate-900 font-mono tabular-nums' }, String(props.value ?? 0)),
]);
StatChip.props = ['label', 'value', 'dot'];

function setStatus(row, value) {
    row.status = value;
    markDirty(row);
}

function bulkSet(value) {
    for (const r of rows.value) {
        r.status = value;
    }
    dirty.value = true;
}

function markDirty(row) {
    dirty.value = true;
    // mirror stats locally so chips update without round-trip
    recomputeLocalStats();
}

function recomputeLocalStats() {
    const next = { total: rows.value.length, marked: 0, unmarked: 0, present: 0, absent: 0, leave: 0, late: 0, half_day: 0 };
    for (const r of rows.value) {
        if (r.status) {
            next.marked++;
            next[r.status] = (next[r.status] ?? 0) + 1;
        }
    }
    next.unmarked = next.total - next.marked;
    stats.value = next;
}

async function fetchSheet() {
    loading.value = true;
    errorMsg.value = '';
    try {
        const { data } = await attendanceService.daily({ date: date.value });
        rows.value = data.rows ?? [];
        stats.value = data.stats ?? stats.value;
        dirty.value = false;
    } catch (err) {
        errorMsg.value = err.response?.data?.message ?? t('common.unexpectedError');
    } finally {
        loading.value = false;
    }
}

async function save() {
    saving.value = true;
    try {
        const payload = rows.value
            .filter(r => r.status)
            .map(r => ({
                employee_id: r.employee_id,
                status:      r.status,
                check_in:    r.check_in  || null,
                check_out:   r.check_out || null,
                remarks:     r.remarks   || null,
            }));
        if (payload.length === 0) {
            toast('info', t('hrm.attendance.nothingToSave'));
            saving.value = false;
            return;
        }
        const { data } = await attendanceService.bulkMark(date.value, payload);
        rows.value = data.rows ?? [];
        stats.value = data.stats ?? stats.value;
        dirty.value = false;
        toast('success', t('hrm.attendance.savedSuccess'));
    } catch (err) {
        toast('error', err.response?.data?.message ?? t('common.unexpectedError'));
    } finally {
        saving.value = false;
    }
}

onMounted(fetchSheet);
</script>

<style scoped>
@reference '../../../css/app.css';
.btn-primary { @apply inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg transition-colors shadow-sm disabled:opacity-50 disabled:cursor-not-allowed; }
.lbl         { @apply block text-xs font-medium text-slate-600 mb-1.5; }
.ctrl        { @apply px-3 py-2 text-sm border border-slate-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent; }
.ctrl-sm     { @apply w-full px-2 py-1 text-xs border border-slate-200 rounded-md bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent; }
.th          { @apply px-4 py-2.5 text-left text-[11px] font-semibold text-slate-500 uppercase tracking-wide; }
.td          { @apply px-4 py-2.5 align-middle; }

.quick-btn   { @apply px-3 py-1.5 text-xs font-semibold rounded-md transition-colors; }
.quick-emerald { @apply bg-emerald-50 text-emerald-700 hover:bg-emerald-100 border border-emerald-200; }
.quick-rose    { @apply bg-rose-50 text-rose-700 hover:bg-rose-100 border border-rose-200; }
</style>
