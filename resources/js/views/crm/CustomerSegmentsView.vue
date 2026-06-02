<template>
    <div class="p-6 lg:p-8 space-y-5 max-w-7xl mx-auto">

        <header>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">{{ t('crm.segments.pageTitle') }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ t('crm.segments.pageSubtitle') }}</p>
        </header>

        <div class="flex flex-wrap gap-2">
            <button v-for="seg in segmentList" :key="seg.name" @click="activeSegment = seg.name; load()"
                    :class="['px-4 py-2 rounded-lg border text-sm font-medium transition-colors',
                             activeSegment === seg.name
                                ? 'bg-indigo-600 text-white border-indigo-600'
                                : 'bg-white border-slate-300 text-slate-700 hover:bg-slate-50']">
                {{ t('crm.segments.' + seg.name) }}
                <span class="ml-2 text-xs font-mono opacity-75">{{ counts[seg.name] ?? '' }}</span>
            </button>
        </div>

        <div class="card overflow-hidden p-0">
            <table class="w-full text-sm">
                <thead class="bg-slate-50/70">
                    <tr class="text-left text-[11px] text-slate-500 uppercase tracking-wide border-b border-slate-100">
                        <th class="px-4 py-2.5">{{ t('crm.fields.customer') }}</th>
                        <th class="px-4 py-2.5">{{ t('crm.fields.contact') }}</th>
                        <th class="px-4 py-2.5">{{ t('crm.fields.tier') }}</th>
                        <th class="px-4 py-2.5 text-right">{{ t('crm.fields.lifetimeSpent') }}</th>
                        <th class="px-4 py-2.5">{{ t('crm.fields.lastVisit') }}</th>
                        <th class="px-4 py-2.5 text-right w-20"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <tr v-for="c in rows" :key="c.customer_id" class="hover:bg-slate-50/60">
                        <td class="px-4 py-2 font-medium text-slate-800">{{ c.name }}</td>
                        <td class="px-4 py-2 text-xs text-slate-600">
                            <span v-if="c.phone">{{ c.phone }}</span>
                            <span v-if="c.email"> · {{ c.email }}</span>
                        </td>
                        <td class="px-4 py-2"><TierBadge :tier="c.tier" /></td>
                        <td class="px-4 py-2 text-right font-mono">{{ fmtCurrency(c.lifetime_spent) }}</td>
                        <td class="px-4 py-2 text-xs">
                            <span v-if="c.last_visit">{{ formatDate(c.last_visit) }} <span class="text-slate-400 ml-1">({{ c.days_since }}d)</span></span>
                            <span v-else class="text-slate-400">{{ t('crm.segments.never') }}</span>
                        </td>
                        <td class="px-4 py-2 text-right">
                            <RouterLink :to="{ name: 'crm-customer-profile', params: { id: c.customer_id } }"
                                        class="text-xs text-indigo-600 hover:underline">{{ t('common.view') }}</RouterLink>
                        </td>
                    </tr>
                    <tr v-if="!loading && rows.length === 0">
                        <td colspan="6" class="py-10 text-center text-sm text-slate-400">{{ t('crm.segments.empty') }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

    </div>
</template>

<script setup>
import { ref, h, onMounted } from 'vue';
import { useRoute, useRouter, RouterLink } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { customerIntelligenceService } from '@/services/crmService';
import { useCurrency } from '@/composables/useCurrency';

const { t, locale } = useI18n();
const { fmtCurrency } = useCurrency();
const route  = useRoute();
const router = useRouter();

const segmentList = [
    { name: 'vip' },
    { name: 'inactive' },
    { name: 'churn_risk' },
    { name: 'discount_sensitive' },
    { name: 'high_frequency' },
];

const activeSegment = ref(route.query.name ?? 'vip');
const counts = ref({});
const rows = ref([]);
const loading = ref(false);

function formatDate(d) {
    return new Intl.DateTimeFormat(locale.value || 'en-US', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(new Date(d));
}

const TierBadge = (props) => {
    const palette = {
        silver:   'bg-slate-100 text-slate-700',
        gold:     'bg-amber-100 text-amber-800',
        platinum: 'bg-indigo-100 text-indigo-800',
        vip:      'bg-rose-100 text-rose-800',
    }[props.tier] ?? 'bg-slate-100 text-slate-700';
    return h('span', { class: `text-[10px] uppercase tracking-wider px-2 py-0.5 rounded-md font-bold ${palette}` }, props.tier);
};
TierBadge.props = ['tier'];

async function load() {
    loading.value = true;
    router.replace({ query: { name: activeSegment.value } });
    try {
        const [{ data: list }, { data: c }] = await Promise.all([
            customerIntelligenceService.segmentList(activeSegment.value, { limit: 200 }),
            customerIntelligenceService.segments(),
        ]);
        rows.value = list.data ?? [];
        counts.value = c.data ?? {};
    } finally {
        loading.value = false;
    }
}

onMounted(load);
</script>

<style scoped>
@reference '../../../css/app.css';
.card { @apply bg-white border border-slate-200 rounded-xl shadow-sm; }
</style>
