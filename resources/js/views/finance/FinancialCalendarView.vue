<template>
    <div class="p-6 lg:p-8 space-y-6 max-w-7xl mx-auto">

        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 tracking-tight">{{ t('finance.calendar.title') }}</h1>
                <p class="mt-1 text-sm text-slate-500">{{ t('finance.calendar.subtitle') }}</p>
            </div>
            <div class="flex items-center gap-2">
                <button @click="navigate(-1)" class="btn-soft">
                    <ChevronLeftIcon class="w-4 h-4" />
                </button>
                <span class="text-sm font-semibold text-slate-800 min-w-[140px] text-center">
                    {{ monthLabel }}
                </span>
                <button @click="navigate(1)" class="btn-soft">
                    <ChevronRightIcon class="w-4 h-4" />
                </button>
                <button @click="goToday" class="btn-soft">
                    {{ t('finance.calendar.today') }}
                </button>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2 text-xs">
            <span class="text-slate-500 font-medium mr-1">{{ t('finance.calendar.legend') }}:</span>
            <LegendItem tone="indigo"  :label="t('finance.calendar.legend_recurring')" />
            <LegendItem tone="rose"    :label="t('finance.calendar.legend_highExpense')" />
            <LegendItem tone="emerald" :label="t('finance.calendar.legend_payroll')" />
            <LegendItem tone="amber"   :label="t('finance.calendar.legend_budgetEnd')" />
        </div>

        <div v-if="loading" class="text-center py-16 text-slate-400">
            <div class="w-6 h-6 border-2 border-indigo-500 border-t-transparent rounded-full animate-spin mx-auto mb-2"></div>
            {{ t('common.loading') }}
        </div>

        <div v-else class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
            <div class="grid grid-cols-7 text-center text-[11px] font-semibold text-slate-500 uppercase tracking-wide border-b border-slate-100 bg-slate-50">
                <div v-for="(d, i) in weekDayNames" :key="i" class="py-2">{{ d }}</div>
            </div>
            <div class="grid grid-cols-7">
                <div v-for="cell in cells" :key="cell.key"
                     :class="['min-h-[110px] border-r border-b border-slate-100 p-2 relative',
                              cell.inMonth ? '' : 'bg-slate-50/50',
                              cell.isToday ? 'bg-indigo-50/30' : '']"
                >
                    <div class="flex items-center justify-between mb-1">
                        <span :class="['text-xs font-mono',
                                       cell.inMonth ? (cell.isToday ? 'text-indigo-700 font-bold' : 'text-slate-700') : 'text-slate-300']">
                            {{ cell.day }}
                        </span>
                        <span v-if="cell.events.length > 0" class="text-[10px] font-mono text-slate-400">{{ cell.events.length }}</span>
                    </div>
                    <ul class="space-y-0.5">
                        <li v-for="(ev, i) in cell.events.slice(0, 3)" :key="i"
                            :class="['text-[11px] truncate rounded px-1.5 py-0.5 cursor-default',
                                     toneClass(ev.tone)]"
                            :title="`${ev.title}${ev.amount !== null ? ' · ' + fmtCurrency(ev.amount) : ''}`"
                        >
                            {{ ev.title }}
                        </li>
                        <li v-if="cell.events.length > 3" class="text-[10px] text-slate-500 italic">
                            + {{ cell.events.length - 3 }} {{ t('finance.calendar.more') }}
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, h } from 'vue';
import { useI18n } from 'vue-i18n';
import { financialCalendarService } from '@/services/financeService';
import { useCurrency } from '@/composables/useCurrency';
import { ChevronLeftIcon, ChevronRightIcon } from '@heroicons/vue/24/outline';

const { t, locale } = useI18n();
const { fmtCurrency } = useCurrency();

const today = new Date();
const year  = ref(today.getFullYear());
const month = ref(today.getMonth() + 1);

const eventsByDate = ref({});
const loading = ref(false);

const monthLabel = computed(() => {
    const fmt = new Intl.DateTimeFormat(locale.value || 'en-US', { month: 'long', year: 'numeric' });
    return fmt.format(new Date(year.value, month.value - 1, 1));
});

const weekDayNames = computed(() => {
    const fmt = new Intl.DateTimeFormat(locale.value || 'en-US', { weekday: 'short' });
    // Monday-first
    return [1, 2, 3, 4, 5, 6, 0].map(i => fmt.format(new Date(2026, 0, 4 + i)));
});

const cells = computed(() => {
    const firstOfMonth = new Date(year.value, month.value - 1, 1);
    const lastOfMonth  = new Date(year.value, month.value, 0);
    const todayKey = (new Date()).toISOString().slice(0, 10);

    // Monday-first offset: JS getDay returns 0 (Sun) - 6 (Sat). We want 1=Mon..7=Sun -> grid start
    const dow = firstOfMonth.getDay(); // 0 sun .. 6 sat
    const leadingBlanks = (dow + 6) % 7; // 0 if Mon, 6 if Sun

    const cells = [];
    // leading days from previous month
    for (let i = leadingBlanks; i > 0; i--) {
        const d = new Date(year.value, month.value - 1, 1 - i);
        cells.push(makeCell(d, false));
    }
    for (let day = 1; day <= lastOfMonth.getDate(); day++) {
        const d = new Date(year.value, month.value - 1, day);
        cells.push(makeCell(d, true));
    }
    // trail to fill grid (multiples of 7)
    while (cells.length % 7 !== 0) {
        const last = cells[cells.length - 1].date;
        const d = new Date(last);
        d.setDate(d.getDate() + 1);
        cells.push(makeCell(d, false));
    }

    function makeCell(d, inMonth) {
        const key = d.toISOString().slice(0, 10);
        return {
            key,
            date: new Date(d),
            day: d.getDate(),
            inMonth,
            isToday: key === todayKey,
            events: eventsByDate.value[key] ?? [],
        };
    }

    return cells;
});

function toneClass(tone) {
    return {
        indigo:  'bg-indigo-100 text-indigo-700',
        rose:    'bg-rose-100 text-rose-700',
        emerald: 'bg-emerald-100 text-emerald-700',
        amber:   'bg-amber-100 text-amber-700',
    }[tone] ?? 'bg-slate-100 text-slate-700';
}

const LegendItem = (props) => {
    const dot = {
        indigo:  'bg-indigo-500',
        rose:    'bg-rose-500',
        emerald: 'bg-emerald-500',
        amber:   'bg-amber-500',
    }[props.tone];
    return h('span', { class: 'inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md bg-white border border-slate-200' }, [
        h('span', { class: `w-2 h-2 rounded-full ${dot}` }),
        h('span', { class: 'text-slate-700' }, props.label),
    ]);
};
LegendItem.props = ['tone', 'label'];

async function load() {
    loading.value = true;
    try {
        const { data } = await financialCalendarService.month({
            year: year.value,
            month: month.value,
        });
        const map = {};
        for (const day of data.data.days ?? []) {
            map[day.date] = day.events;
        }
        eventsByDate.value = map;
    } finally {
        loading.value = false;
    }
}

function navigate(delta) {
    let m = month.value + delta;
    let y = year.value;
    if (m < 1) { m = 12; y--; }
    if (m > 12) { m = 1; y++; }
    month.value = m;
    year.value  = y;
    load();
}

function goToday() {
    const now = new Date();
    year.value = now.getFullYear();
    month.value = now.getMonth() + 1;
    load();
}

onMounted(load);
</script>

<style scoped>
@reference '../../../css/app.css';
.btn-soft { @apply inline-flex items-center gap-1 px-3 py-1.5 rounded-lg border border-slate-300 text-slate-700 text-sm hover:bg-slate-50 transition-colors; }
</style>
