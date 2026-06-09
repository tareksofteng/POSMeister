<template>
    <!--
        BranchLeaderboard — Phase AC Round 1.

        Only renders when the user is in the "All Branches" super-workspace
        AND there are at least 2 active branches. The backend already
        returns an empty array for single-branch workspaces, so we just
        guard against the empty case.

        Layout: leaderboard row per branch with rank, name, month KPIs
        and a horizontal bar showing the share of total month revenue.
        Top branch gets a "Top" badge.
    -->
    <section v-if="branches.length >= 2" class="card branch-leaderboard anim-fade-up">
        <header class="dash-list-head">
            <div>
                <p class="t-overline">{{ t('dashboard.leaderboard.title', 'Branch performance') }}</p>
                <p class="t-caption mt-0.5">{{ t('dashboard.leaderboard.subtitle', 'Month-to-date — switch a workspace to drill into a specific branch.') }}</p>
            </div>
            <span class="t-caption">{{ branches.length }} {{ t('dashboard.leaderboard.branches', 'branches') }}</span>
        </header>

        <ul class="leaderboard-list">
            <li v-for="(b, i) in branches" :key="b.branch_id" class="leaderboard-row">
                <div class="lb-rank">
                    <span :class="['rank-chip', i === 0 && 'is-top']">#{{ i + 1 }}</span>
                </div>
                <div class="lb-body">
                    <div class="flex items-center gap-2 flex-wrap">
                        <p class="lb-name">{{ b.name }}</p>
                        <span v-if="b.code" class="t-caption font-mono">{{ b.code }}</span>
                        <span v-if="i === 0" class="status-pill status-pill-success">{{ t('dashboard.leaderboard.top', 'Top') }}</span>
                    </div>
                    <div class="lb-bar mt-1.5">
                        <div class="lb-bar-fill" :style="{ width: barWidth(b) + '%' }" />
                    </div>
                </div>
                <div class="lb-kpis">
                    <div class="lb-kpi">
                        <span class="t-overline">{{ t('dashboard.leaderboard.sales', 'Sales') }}</span>
                        <span class="lb-kpi-value">{{ fmt(b.month_sales) }}</span>
                    </div>
                    <div class="lb-kpi">
                        <span class="t-overline">{{ t('dashboard.leaderboard.orders', 'Orders') }}</span>
                        <span class="lb-kpi-value">{{ b.month_count }}</span>
                    </div>
                    <div class="lb-kpi">
                        <span class="t-overline">{{ t('dashboard.leaderboard.customers', 'Customers') }}</span>
                        <span class="lb-kpi-value">{{ b.active_customers }}</span>
                    </div>
                </div>
            </li>
        </ul>
    </section>
</template>

<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useSettingsStore } from '@/stores/settings';
import { useLocale } from '@/composables/useLocale';

const props = defineProps({
    branches: { type: Array, default: () => [] },
});

const { t } = useI18n();
const { intlLocale } = useLocale();
const settingsStore = useSettingsStore();

const maxSales = computed(() => Math.max(...props.branches.map(b => +b.month_sales || 0), 1));

function fmt(value) {
    if (value == null) return '—';
    const code = settingsStore.settings?.currency_code ?? 'EUR';
    return new Intl.NumberFormat(intlLocale.value || 'en-US', { style: 'currency', currency: code, maximumFractionDigits: 0 })
        .format(Number(value) || 0);
}

function barWidth(b) {
    return Math.max(4, ((+b.month_sales || 0) / maxSales.value) * 100);
}
</script>

<style scoped>
@reference '../../../css/app.css';

.branch-leaderboard { padding: 0; overflow: hidden; }

.leaderboard-list {
    list-style: none;
    margin: 0;
    padding: 0;
}
.leaderboard-row {
    display: grid;
    grid-template-columns: auto 1fr auto;
    gap: 0.75rem;
    align-items: center;
    padding: 0.75rem 1.125rem;
    border-top: 1px solid var(--border-subtle);
}
.leaderboard-row:first-child { border-top: 0; }

.lb-rank { flex-shrink: 0; }
.rank-chip {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 30px; height: 30px;
    padding: 0 0.5rem;
    border-radius: 0.5rem;
    font-size: 0.75rem;
    font-weight: 700;
    background: var(--surface-sunken);
    color: var(--text-secondary);
    font-variant-numeric: tabular-nums;
}
.rank-chip.is-top {
    background: linear-gradient(180deg, rgb(238 242 255), rgb(224 231 255));
    color: rgb(67 56 202);
}
html.dark .rank-chip.is-top {
    background: rgb(67 56 202 / 0.25);
    color: rgb(165 180 252);
}

.lb-body { min-width: 0; }
.lb-name {
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--text-primary);
}
.lb-bar {
    width: 100%; height: 5px;
    border-radius: 999px;
    background: var(--surface-sunken);
    overflow: hidden;
}
.lb-bar-fill {
    height: 100%;
    background: linear-gradient(90deg, rgb(79 70 229), rgb(99 102 241));
    border-radius: 999px;
    transition: width 600ms var(--motion-spring);
}

.lb-kpis {
    display: flex;
    gap: 1.25rem;
    flex-shrink: 0;
}
.lb-kpi {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    min-width: 56px;     /* prevents "৳ 0" being cropped on dense Bangla labels */
}
.lb-kpi-value {
    font-size: 0.8125rem;
    font-weight: 700;
    color: var(--text-primary);
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
}

/* Mobile — drop the KPI strip to its own row so the leaderboard
   doesn't squeeze the bar to nothing. */
@media (max-width: 640px) {
    .leaderboard-row {
        grid-template-columns: auto 1fr;
    }
    .lb-kpis {
        grid-column: 1 / -1;
        margin-top: 0.5rem;
        justify-content: space-between;
        gap: 0.5rem;
    }
    .lb-kpi { align-items: flex-start; }
}
</style>
