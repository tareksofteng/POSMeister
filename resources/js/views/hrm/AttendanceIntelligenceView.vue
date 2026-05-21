<template>
    <div class="p-6 lg:p-8 space-y-5 max-w-7xl mx-auto">

        <header class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 tracking-tight">{{ t('hrm.attIntel.title') }}</h1>
                <p class="mt-1 text-sm text-slate-500">{{ t('hrm.attIntel.subtitle') }}</p>
            </div>
            <div class="flex items-end gap-2">
                <div>
                    <label class="lbl">{{ t('common.dateFrom') }}</label>
                    <input v-model="from" @change="load" type="date" class="ctrl w-40" />
                </div>
                <div>
                    <label class="lbl">{{ t('common.dateTo') }}</label>
                    <input v-model="to" @change="load" type="date" class="ctrl w-40" />
                </div>
            </div>
        </header>

        <section v-if="breaks" class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <KpiCard :label="t('hrm.attIntel.avgWorked')" :value="hours(breaks.avg_worked_minutes)" tone="indigo" />
            <KpiCard :label="t('hrm.attIntel.avgBreak')"  :value="hours(breaks.avg_break_minutes)" tone="amber" />
            <KpiCard :label="t('hrm.attIntel.overtimeTotal')" :value="overtimeTotalHours + ' h'" tone="emerald" />
            <KpiCard :label="t('hrm.attIntel.workforce')" :value="(scores ?? []).length" tone="slate" />
        </section>

        <section class="card">
            <h3 class="card-title">{{ t('hrm.attIntel.heatmapTitle') }}</h3>
            <p class="text-xs text-slate-500 mb-3">{{ t('hrm.attIntel.heatmapHint') }}</p>
            <div class="overflow-x-auto">
                <table class="text-xs">
                    <thead>
                        <tr>
                            <th class="px-2 py-1"></th>
                            <th v-for="h in 24" :key="h" class="px-1 py-1 font-mono text-slate-400">{{ String(h - 1).padStart(2, '0') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, i) in heatmap" :key="i">
                            <td class="px-2 py-1 text-slate-700 font-medium">{{ weekDays[i] }}</td>
                            <td v-for="(v, j) in row" :key="j"
                                :class="['w-6 h-6', heatColor(v)]"
                                :title="v + ' late check-ins'"></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section v-if="overtime.length" class="card">
            <h3 class="card-title">{{ t('hrm.attIntel.overtimeTrend') }}</h3>
            <div class="flex items-end gap-1 h-32">
                <div v-for="d in overtime" :key="d.date" class="flex-1 flex flex-col items-center gap-1">
                    <div class="w-full bg-emerald-500 rounded-t" :style="{ height: otBar(d.overtime_hours) + '%' }"
                         :title="d.date + ': ' + d.overtime_hours + 'h'"></div>
                </div>
            </div>
        </section>

        <section class="card overflow-hidden p-0">
            <h3 class="card-title px-5 py-3 mb-0">{{ t('hrm.attIntel.scores') }}</h3>
            <table class="w-full text-sm">
                <thead class="bg-slate-50/70">
                    <tr class="text-left text-[11px] text-slate-500 uppercase tracking-wide border-b border-slate-100">
                        <th class="px-4 py-2.5">{{ t('hrm.approval.employee') }}</th>
                        <th class="px-4 py-2.5 text-right">{{ t('hrm.attIntel.days') }}</th>
                        <th class="px-4 py-2.5 text-right">{{ t('hrm.attIntel.present') }}</th>
                        <th class="px-4 py-2.5 text-right">{{ t('hrm.attIntel.absent') }}</th>
                        <th class="px-4 py-2.5 text-right">{{ t('hrm.attIntel.late') }}</th>
                        <th class="px-4 py-2.5 text-right">{{ t('hrm.attIntel.workedHours') }}</th>
                        <th class="px-4 py-2.5 text-right w-24">{{ t('hrm.attIntel.score') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <tr v-for="s in scores" :key="s.employee_id">
                        <td class="px-4 py-2 text-slate-800">{{ s.name }}</td>
                        <td class="px-4 py-2 text-right font-mono">{{ s.days }}</td>
                        <td class="px-4 py-2 text-right font-mono text-emerald-700">{{ s.present }}</td>
                        <td class="px-4 py-2 text-right font-mono text-rose-700">{{ s.absent }}</td>
                        <td class="px-4 py-2 text-right font-mono text-amber-700">{{ s.late_days }}</td>
                        <td class="px-4 py-2 text-right font-mono">{{ s.worked_hours }}</td>
                        <td class="px-4 py-2 text-right">
                            <span class="font-mono font-bold" :class="scoreColor(s.score)">{{ s.score }}</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>

    </div>
</template>

<script setup>
import { ref, computed, h, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { attendanceIntelligenceService } from '@/services/hrmService';

const { t } = useI18n();

const today = new Date().toISOString().slice(0, 10);
const monthStart = (() => { const d = new Date(); return new Date(d.getFullYear(), d.getMonth(), 1).toISOString().slice(0, 10); })();

const from = ref(monthStart);
const to = ref(today);

const scores = ref([]);
const heatmap = ref(Array.from({ length: 7 }, () => Array(24).fill(0)));
const overtime = ref([]);
const breaks = ref(null);

const weekDays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

const overtimeTotalHours = computed(() =>
    Math.round(overtime.value.reduce((s, d) => s + (d.overtime_hours || 0), 0))
);

function hours(min) { return Math.round((min || 0) / 60 * 10) / 10 + ' h'; }

function heatColor(v) {
    if (v <= 0)  return 'bg-slate-50';
    if (v < 2)   return 'bg-amber-100';
    if (v < 5)   return 'bg-amber-300';
    if (v < 10)  return 'bg-rose-400';
    return 'bg-rose-600';
}
function scoreColor(s) {
    if (s >= 80) return 'text-emerald-700';
    if (s >= 60) return 'text-amber-700';
    return 'text-rose-700';
}
function otBar(h) {
    if (!overtime.value.length) return 2;
    const max = Math.max(...overtime.value.map(d => d.overtime_hours || 0), 1);
    return Math.max(4, (h / max) * 100);
}

const KpiCard = (props) => {
    const palette = {
        emerald: 'border-emerald-200',
        rose:    'border-rose-200',
        indigo:  'border-indigo-200',
        amber:   'border-amber-200',
    }[props.tone] ?? 'border-slate-200';
    return h('div', { class: `bg-white border ${palette} rounded-xl shadow-sm px-4 py-3 hover:shadow-md transition-shadow` }, [
        h('p', { class: 'text-[11px] uppercase tracking-wide text-slate-500 font-medium' }, props.label),
        h('p', { class: 'text-xl font-bold text-slate-900 mt-1 font-mono' }, String(props.value)),
    ]);
};
KpiCard.props = ['label', 'value', 'tone'];

async function load() {
    const params = { from: from.value, to: to.value };
    const [{ data: s }, { data: hm }, { data: ot }, { data: br }] = await Promise.all([
        attendanceIntelligenceService.scores(params),
        attendanceIntelligenceService.lateHeatmap(params),
        attendanceIntelligenceService.overtimeTrend(params),
        attendanceIntelligenceService.breaks(params),
    ]);
    scores.value = s.data ?? [];
    heatmap.value = hm.data ?? heatmap.value;
    overtime.value = ot.data ?? [];
    breaks.value = br.data;
}

onMounted(load);
</script>

<style scoped>
@reference '../../../css/app.css';
.card        { @apply bg-white border border-slate-200 rounded-xl shadow-sm p-5; }
.card-title  { @apply text-xs font-bold text-slate-500 uppercase tracking-wider mb-3; }
.lbl         { @apply block text-xs font-medium text-slate-600 mb-1; }
.ctrl        { @apply px-3 py-2 text-sm border border-slate-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent; }
</style>
