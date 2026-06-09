<template>
    <!--
        HealthRingsRow — Phase AC Round 1.

        Four small circular-progress cards condensing the sub-score
        narrative into glanceable percentages:

          Sales Trend     — % progress relative to the 25 pt cap
          Profit Margin   — gross margin %
          Inventory Health— inverse of low-stock pressure (100 - load)
          Cash Flow       — runway score scaled to 0–100

        Each ring takes its colour from the same tier palette as the
        Business Health Card so the dashboard reads as one design.
    -->
    <section v-if="rings.length" class="rings-row anim-fade-up anim-stagger">
        <div
            v-for="r in rings"
            :key="r.key"
            :class="['card health-ring-card', `tier-${r.tier}`]"
        >
            <div class="ring-mini-wrap">
                <svg viewBox="0 0 100 100" class="ring-mini-svg">
                    <circle cx="50" cy="50" r="42" stroke-width="9" fill="none" class="ring-mini-bg" />
                    <circle
                        cx="50" cy="50" r="42" stroke-width="9" fill="none"
                        class="ring-mini-fg"
                        stroke-linecap="round"
                        transform="rotate(-90 50 50)"
                        :stroke-dasharray="circumference"
                        :stroke-dashoffset="circumference * (1 - r.pct / 100)"
                    />
                </svg>
                <div class="ring-mini-center">
                    <p class="ring-mini-pct">{{ Math.round(r.pct) }}%</p>
                </div>
            </div>
            <div class="ring-mini-body">
                <p class="t-overline">{{ r.label }}</p>
                <p class="ring-mini-note">{{ r.note }}</p>
            </div>
        </div>
    </section>
</template>

<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    health: { type: Object, default: null },
});

const { t } = useI18n();

const circumference = 2 * Math.PI * 42;

function tierFor(pct) {
    if (pct >= 90) return 'emerald';
    if (pct >= 70) return 'sky';
    if (pct >= 50) return 'amber';
    return 'rose';
}

const rings = computed(() => {
    const s = props.health?.subscores;
    if (!s) return [];

    const out = [];

    // Sales Trend — score normalised to its 25pt cap → 0–100%.
    if (s.sales) {
        const pct = Math.max(0, Math.min(100, ((+s.sales.score) / 25) * 100));
        out.push({
            key:  'sales',
            label: t('dashboard.rings.salesTrend', 'Sales Trend'),
            pct,
            tier: tierFor(pct),
            note: s.sales.note || '—',
        });
    }

    // Profit Margin — clamp the raw margin to 0..100 for the ring.
    if (s.profit && s.profit.margin_pct != null) {
        const pct = Math.max(0, Math.min(100, +s.profit.margin_pct));
        out.push({
            key:  'profit',
            label: t('dashboard.rings.profitMargin', 'Profit Margin'),
            pct,
            tier: tierFor(pct >= 30 ? 95 : pct >= 15 ? 75 : pct >= 5 ? 55 : 30),
            note: s.profit.note || `${pct.toFixed(1)}%`,
        });
    }

    // Inventory Health — "risk" sub-score normalised to its 20pt cap.
    if (s.risk) {
        const pct = Math.max(0, Math.min(100, ((+s.risk.score) / 20) * 100));
        out.push({
            key:  'inventory',
            label: t('dashboard.rings.inventoryHealth', 'Inventory Health'),
            pct,
            tier: tierFor(pct),
            note: s.risk.note || '—',
        });
    }

    // Cash Flow — uses the cash sub-score (20pt cap) as the indicator.
    if (s.cash) {
        const pct = Math.max(0, Math.min(100, ((+s.cash.score) / 20) * 100));
        out.push({
            key:  'cash',
            label: t('dashboard.rings.cashFlow', 'Cash Flow'),
            pct,
            tier: tierFor(pct),
            note: s.cash.note || '—',
        });
    }

    return out;
});
</script>

<style scoped>
@reference '../../../css/app.css';

.rings-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.625rem;
}
@media (min-width: 768px) {
    .rings-row { grid-template-columns: repeat(4, 1fr); gap: 1rem; }
}

.health-ring-card {
    padding: 0.875rem 0.875rem;
    display: flex;
    align-items: center;
    gap: 0.875rem;
    min-width: 0;
    position: relative;
    overflow: hidden;
    isolation: isolate;
    transition:
        transform var(--motion-fast) var(--motion-out),
        box-shadow var(--motion-fast) var(--motion-out);
}
.health-ring-card:hover {
    transform: translateY(-1px);
    box-shadow: var(--elev-2);
}

.ring-mini-wrap {
    position: relative;
    width: 56px; height: 56px;
    flex-shrink: 0;
}
.ring-mini-svg { width: 100%; height: 100%; }
.ring-mini-bg { stroke: var(--border-default); }
.ring-mini-fg { transition: stroke-dashoffset 600ms var(--motion-spring); }
.tier-emerald .ring-mini-fg { stroke: rgb(16 185 129); }
.tier-sky     .ring-mini-fg { stroke: rgb(14 165 233); }
.tier-amber   .ring-mini-fg { stroke: rgb(245 158 11); }
.tier-rose    .ring-mini-fg { stroke: rgb(244 63 94); }

.ring-mini-center {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
}
.ring-mini-pct {
    font-size: 0.8125rem;
    font-weight: 800;
    color: var(--text-primary);
    font-variant-numeric: tabular-nums;
    letter-spacing: -0.02em;
}

.ring-mini-body { flex: 1 1 0; min-width: 0; }
.ring-mini-note {
    margin-top: 0.125rem;
    font-size: 0.75rem;
    color: var(--text-secondary);
    overflow: hidden;
    text-overflow: ellipsis;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
}
</style>
