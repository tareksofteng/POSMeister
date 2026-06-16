<template>
    <!--
        Push platform health for admins. Pulls from /api/push/analytics
        (admin-only endpoint — non-admins get 403 and the widget hides
        itself). Auto-refreshes every two minutes to stay roughly in sync
        with the bell + dashboard alerts widget without being chatty.

        Four KPI tiles + a top-clicked codes strip + a small per-platform
        breakdown. Designed to fit between the dashboard alerts widget
        and the executive snapshot grid.
    -->
    <section v-if="visible" class="card push-widget anim-fade-up">
        <header class="pw-head">
            <div class="min-w-0">
                <p class="t-overline">{{ t('dashboard.push.title', 'Push platform') }}</p>
                <p class="t-caption mt-0.5">{{ t('dashboard.push.subtitle', 'Reach, engagement and delivery health for browser alerts.') }}</p>
            </div>
            <RouterLink :to="{ name: 'notification-devices' }" class="pw-link">
                {{ t('dashboard.push.manageDevices', 'Manage devices') }}
                <ArrowLongRightIcon class="w-3.5 h-3.5" />
            </RouterLink>
        </header>

        <!-- KPI tiles -->
        <div class="pw-tiles">
            <div class="pw-tile">
                <div class="pw-tile-icon pw-tile-icon-indigo">
                    <DevicePhoneMobileIcon class="w-4 h-4" />
                </div>
                <div class="min-w-0">
                    <p class="t-overline">{{ t('dashboard.push.connectedDevices', 'Connected devices') }}</p>
                    <p class="t-kpi mt-0.5">{{ loading ? '—' : data?.devices_total ?? 0 }}</p>
                    <p v-if="!loading && data?.devices_inactive > 0" class="t-caption">
                        +{{ data.devices_inactive }} {{ t('dashboard.push.inactive', 'inactive') }}
                    </p>
                </div>
            </div>

            <div class="pw-tile">
                <div class="pw-tile-icon pw-tile-icon-emerald">
                    <UsersIcon class="w-4 h-4" />
                </div>
                <div class="min-w-0">
                    <p class="t-overline">{{ t('dashboard.push.usersOptedIn', 'Users opted in') }}</p>
                    <p class="t-kpi mt-0.5">{{ loading ? '—' : data?.users_with_push ?? 0 }}</p>
                    <p v-if="!loading && data?.devices_seen_24h > 0" class="t-caption">
                        {{ data.devices_seen_24h }} {{ t('dashboard.push.seenToday', 'seen today') }}
                    </p>
                </div>
            </div>

            <div class="pw-tile">
                <div class="pw-tile-icon pw-tile-icon-sky">
                    <BellAlertIcon class="w-4 h-4" />
                </div>
                <div class="min-w-0">
                    <p class="t-overline">{{ t('dashboard.push.sentToday', 'Sent today') }}</p>
                    <p class="t-kpi mt-0.5">{{ loading ? '—' : data?.sent_today ?? 0 }}</p>
                    <p v-if="!loading" class="t-caption">
                        {{ data?.sent_week ?? 0 }} {{ t('dashboard.push.last7d', 'last 7d') }}
                    </p>
                </div>
            </div>

            <div :class="['pw-tile', ctrTone]">
                <div class="pw-tile-icon pw-tile-icon-amber">
                    <CursorArrowRaysIcon class="w-4 h-4" />
                </div>
                <div class="min-w-0">
                    <p class="t-overline">{{ t('dashboard.push.clickRate', 'Click rate') }}</p>
                    <p class="t-kpi mt-0.5">
                        <template v-if="loading">—</template>
                        <template v-else-if="data?.ctr_today_pct != null">{{ data.ctr_today_pct }}%</template>
                        <template v-else>—</template>
                    </p>
                    <p v-if="!loading && data?.ctr_week_pct != null" class="t-caption">
                        {{ data.ctr_week_pct }}% · {{ t('dashboard.push.last7d', 'last 7d') }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Top clicked codes (only renders when at least one click was logged) -->
        <div v-if="!loading && (data?.top_clicked?.length || 0) > 0" class="pw-top-clicked">
            <p class="t-overline mb-2">{{ t('dashboard.push.topClicked', 'Top clicked alerts · last 7 days') }}</p>
            <ul class="pw-list">
                <li v-for="(row, i) in data.top_clicked" :key="row.code" class="pw-row">
                    <span class="pw-rank">#{{ i + 1 }}</span>
                    <p class="pw-code">{{ row.code }}</p>
                    <span class="pw-count">{{ row.clicks }} {{ t('dashboard.push.clicks', 'clicks') }}</span>
                </li>
            </ul>
        </div>

        <!-- Platform / browser breakdown — flattens into a single chip row -->
        <div v-if="!loading && breakdown.length" class="pw-breakdown">
            <span v-for="b in breakdown" :key="b.label" class="pw-chip">
                <component :is="b.icon" class="w-3 h-3" />
                {{ b.label }}
                <span class="pw-chip-count">{{ b.count }}</span>
            </span>
        </div>

        <!-- Loading state -->
        <div v-if="loading && !data" class="pw-loading">
            <Skeleton v-for="i in 3" :key="i" variant="row" />
        </div>
    </section>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { RouterLink } from 'vue-router';
import {
    ArrowLongRightIcon, BellAlertIcon, ComputerDesktopIcon,
    CursorArrowRaysIcon, DevicePhoneMobileIcon, DeviceTabletIcon, UsersIcon,
} from '@heroicons/vue/24/outline';
import { useAuthStore } from '@/stores/auth';
import { pushService } from '@/services/pushService';
import Skeleton from '@/components/ui/Skeleton.vue';

const { t } = useI18n();
const auth = useAuthStore();

const data    = ref(null);
const loading = ref(true);
const visible = ref(true);   // hidden when the user is not an admin (403)

const ctrTone = computed(() => {
    const pct = data.value?.ctr_today_pct;
    if (pct == null) return '';
    if (pct >= 30) return 'is-positive';
    if (pct <  5)  return 'is-warning';
    return '';
});

// Flat row of chips combining platform + browser counts. Keeps the
// widget visually compact instead of two separate breakdown blocks.
const breakdown = computed(() => {
    if (!data.value) return [];
    const platformIcon = {
        Windows:  ComputerDesktopIcon,
        macOS:    ComputerDesktopIcon,
        Linux:    ComputerDesktopIcon,
        Android:  DevicePhoneMobileIcon,
        iOS:      DevicePhoneMobileIcon,
        Desktop:  ComputerDesktopIcon,
    };
    const platformChips = Object.entries(data.value.by_platform || {})
        .map(([p, count]) => ({
            label: p === 'unknown' ? t('dashboard.push.unknownPlatform', 'Unknown') : p,
            count,
            icon:  platformIcon[p] || DeviceTabletIcon,
        }));
    return platformChips
        .sort((a, b) => b.count - a.count)
        .slice(0, 6);
});

async function load() {
    if (auth.userRole !== 'admin') {
        visible.value = false;
        return;
    }
    loading.value = true;
    try {
        const res = await pushService.analytics();
        data.value = res.data?.data ?? null;
    } catch (e) {
        // 403 for non-admin (race after role change) or 500 — fail soft
        if (e.response?.status === 403) visible.value = false;
    } finally {
        loading.value = false;
    }
}

let pollTimer = null;
onMounted(() => {
    load();
    pollTimer = setInterval(load, 120_000);
});
onUnmounted(() => { if (pollTimer) clearInterval(pollTimer); });
</script>

<style scoped>
@reference '../../../css/app.css';

.push-widget {
    padding: 1rem 1.125rem 1.125rem;
    display: flex;
    flex-direction: column;
    gap: 0.875rem;
}

.pw-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.5rem;
}
.pw-link {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.25rem 0.5rem;
    border-radius: 0.375rem;
    font-size: 0.6875rem;
    font-weight: 600;
    color: rgb(67 56 202);
    flex-shrink: 0;
    transition: background-color var(--motion-fast) var(--motion-out);
}
.pw-link:hover { background: rgb(238 242 255); }
html.dark .pw-link { color: rgb(165 180 252); }
html.dark .pw-link:hover { background: rgb(67 56 202 / 0.18); }

/* KPI tiles */
.pw-tiles {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.5rem;
}
@media (min-width: 768px) {
    .pw-tiles { grid-template-columns: repeat(4, 1fr); }
}
.pw-tile {
    display: flex;
    align-items: center;
    gap: 0.625rem;
    padding: 0.75rem 0.875rem;
    background: var(--surface-sunken);
    border: 1px solid var(--border-default);
    border-radius: 0.625rem;
    min-width: 0;
    position: relative;
    overflow: hidden;
    isolation: isolate;
    transition: border-color var(--motion-fast) var(--motion-out);
}
.pw-tile:hover { border-color: var(--border-strong); }
.pw-tile.is-positive {
    background: linear-gradient(180deg, rgb(236 253 245), rgb(209 250 229));
    border-color: rgb(167 243 208);
}
.pw-tile.is-warning {
    background: linear-gradient(180deg, rgb(255 251 235), rgb(254 243 199));
    border-color: rgb(253 230 138);
}
html.dark .pw-tile.is-positive {
    background: linear-gradient(180deg, rgb(6 95 70 / 0.18), rgb(6 95 70 / 0.12));
    border-color: rgb(16 185 129 / 0.4);
}
html.dark .pw-tile.is-warning {
    background: linear-gradient(180deg, rgb(180 83 9 / 0.22), rgb(180 83 9 / 0.16));
    border-color: rgb(245 158 11 / 0.4);
}

.pw-tile-icon {
    width: 32px; height: 32px;
    border-radius: 0.5rem;
    display: grid; place-items: center;
    flex-shrink: 0;
}
.pw-tile-icon-indigo  { background: rgb(238 242 255); color: rgb(67 56 202); }
.pw-tile-icon-emerald { background: rgb(209 250 229); color: rgb(6 95 70); }
.pw-tile-icon-sky     { background: rgb(224 242 254); color: rgb(7 89 133); }
.pw-tile-icon-amber   { background: rgb(254 243 199); color: rgb(146 64 14); }
html.dark .pw-tile-icon-indigo  { background: rgb(67 56 202 / 0.25); color: rgb(165 180 252); }
html.dark .pw-tile-icon-emerald { background: rgb(5 150 105 / 0.25); color: rgb(110 231 183); }
html.dark .pw-tile-icon-sky     { background: rgb(2 132 199 / 0.25); color: rgb(186 230 253); }
html.dark .pw-tile-icon-amber   { background: rgb(180 83 9 / 0.3);   color: rgb(252 211 77); }

/* Top clicked codes */
.pw-top-clicked {
    border-top: 1px solid var(--border-subtle);
    padding-top: 0.75rem;
}
.pw-list { display: flex; flex-direction: column; gap: 0.375rem; margin: 0; padding: 0; list-style: none; }
.pw-row {
    display: grid;
    grid-template-columns: auto 1fr auto;
    align-items: center;
    gap: 0.625rem;
    padding: 0.375rem 0;
}
.pw-rank {
    display: inline-flex; align-items: center; justify-content: center;
    min-width: 22px; height: 22px;
    padding: 0 0.375rem;
    border-radius: 0.375rem;
    background: var(--surface-raised);
    color: var(--text-secondary);
    font-size: 0.625rem;
    font-weight: 700;
    font-variant-numeric: tabular-nums;
    border: 1px solid var(--border-default);
}
.pw-code {
    font-family: ui-monospace, SF Mono, Menlo, monospace;
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--text-primary);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.pw-count {
    font-size: 0.6875rem;
    font-weight: 700;
    padding: 0.125rem 0.5rem;
    border-radius: 999px;
    background: var(--surface-raised);
    color: var(--text-secondary);
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
}

/* Platform breakdown chips */
.pw-breakdown {
    border-top: 1px solid var(--border-subtle);
    padding-top: 0.75rem;
    display: flex;
    flex-wrap: wrap;
    gap: 0.375rem;
}
.pw-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    padding: 0.25rem 0.625rem;
    border-radius: 999px;
    background: var(--surface-sunken);
    color: var(--text-secondary);
    font-size: 0.6875rem;
    font-weight: 600;
}
.pw-chip-count {
    font-variant-numeric: tabular-nums;
    color: var(--text-primary);
}

.pw-loading { display: flex; flex-direction: column; gap: 0.5rem; }
</style>
