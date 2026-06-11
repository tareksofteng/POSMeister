<template>
    <!--
        A two-step elegant prompt the owner sees ONCE — never on every
        page load, never in the middle of a sale. The card asks for
        consent before it asks the browser; the native permission popup
        only fires after the user clicks Enable, so a misclick doesn't
        burn the chance forever (the browser-level "denied" state is
        sticky on every major engine).

        Lifecycle:
          – component mounts → checks support + Notification.permission
          – already 'granted'  → component renders nothing
          – 'denied' / 'unsupported' / has localStorage 'dismissed' flag
            → component renders nothing (user has made their choice)
          – otherwise → renders the card
    -->
    <section v-if="show" class="card push-permission-card anim-fade-up">
        <div class="ppc-head">
            <div class="ppc-icon">
                <BellAlertIcon class="w-5 h-5" />
            </div>
            <div class="min-w-0">
                <p class="t-overline">{{ t('push.title', 'Stay informed anywhere') }}</p>
                <p class="ppc-headline">{{ t('push.headline', 'Enable browser notifications') }}</p>
            </div>
        </div>

        <p class="ppc-body">{{ t('push.body', 'POSmeister will alert you about issues that need attention — even when this tab is closed. You can turn it off anytime in Settings.') }}</p>

        <ul class="ppc-list">
            <li><CheckIcon class="w-4 h-4 text-emerald-600 dark:text-emerald-400" /> {{ t('push.bullets.critical', 'Critical stock and cash issues') }}</li>
            <li><CheckIcon class="w-4 h-4 text-emerald-600 dark:text-emerald-400" /> {{ t('push.bullets.due', 'Customer and supplier due reminders') }}</li>
            <li><CheckIcon class="w-4 h-4 text-emerald-600 dark:text-emerald-400" /> {{ t('push.bullets.system', 'Backup, sync and offline alerts') }}</li>
        </ul>

        <div class="ppc-actions">
            <button
                type="button"
                class="ppc-cta"
                :disabled="busy"
                @click="enable"
            >
                <ShieldCheckIcon class="w-4 h-4" />
                {{ busy ? t('push.enabling', 'Enabling…') : t('push.enable', 'Enable Notifications') }}
            </button>
            <button
                type="button"
                class="ppc-later"
                @click="dismiss"
                :disabled="busy"
            >
                {{ t('push.later', 'Maybe later') }}
            </button>
        </div>

        <p v-if="message" class="ppc-message" :class="messageTone">{{ message }}</p>
    </section>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { BellAlertIcon, CheckIcon, ShieldCheckIcon } from '@heroicons/vue/24/outline';
import { pushService } from '@/services/pushService';

const { t } = useI18n();

const DISMISS_KEY = 'pos_push_prompt_dismissed';

const show = ref(false);
const busy = ref(false);
const message = ref('');
const messageTone = ref('');

onMounted(async () => {
    const supported = await pushService.isSupported();
    if (!supported) return;

    const permission = await pushService.currentPermission();
    if (permission === 'granted' || permission === 'denied') return;

    // Honour the user's earlier "maybe later" for 14 days.
    try {
        const ts = parseInt(localStorage.getItem(DISMISS_KEY) || '0', 10);
        if (ts && Date.now() - ts < 14 * 24 * 3600 * 1000) return;
    } catch { /* private mode etc. */ }

    show.value = true;
});

async function enable() {
    busy.value = true;
    message.value = '';
    try {
        const res = await pushService.enable();
        if (res.ok) {
            messageTone.value = 'is-positive';
            message.value = t('push.success', 'Push notifications enabled.');
            setTimeout(() => { show.value = false; }, 1200);
            return;
        }
        messageTone.value = 'is-warning';
        message.value = explain(res.status);
        if (res.status === 'denied') {
            // The browser blocked it permanently — collapse the card so
            // the user isn't nagged.
            setTimeout(() => { show.value = false; }, 1500);
        }
    } catch (e) {
        messageTone.value = 'is-warning';
        message.value = t('push.error', 'Could not enable notifications. Try again from Settings.');
    } finally {
        busy.value = false;
    }
}

function dismiss() {
    try { localStorage.setItem(DISMISS_KEY, String(Date.now())); } catch { /* no-op */ }
    show.value = false;
}

function explain(status) {
    switch (status) {
        case 'denied':                return t('push.deniedHint', 'Your browser blocked notifications. Allow them in site settings to enable.');
        case 'server_not_configured': return t('push.serverHint', 'Push is not configured on the server. Ask your admin to run push:vapid.');
        case 'unsupported':           return t('push.unsupportedHint', 'This browser does not support push notifications.');
        default:                      return t('push.error', 'Could not enable notifications. Try again from Settings.');
    }
}
</script>

<style scoped>
@reference '../../../css/app.css';

.push-permission-card {
    padding: 1rem 1.125rem 1.125rem;
    background:
        radial-gradient(80% 60% at 100% 0%, rgba(99,102,241,0.10), transparent 60%),
        var(--surface-raised);
    border: 1px solid var(--border-default);
}

.ppc-head {
    display: flex;
    gap: 0.75rem;
    align-items: flex-start;
    margin-bottom: 0.5rem;
}
.ppc-icon {
    width: 38px; height: 38px;
    border-radius: 0.625rem;
    background: linear-gradient(135deg, rgb(224 231 255), rgb(199 210 254));
    color: rgb(67 56 202);
    display: grid; place-items: center;
    flex-shrink: 0;
}
html.dark .ppc-icon {
    background: linear-gradient(135deg, rgb(67 56 202 / 0.4), rgb(55 48 163 / 0.4));
    color: rgb(165 180 252);
}
.ppc-headline {
    font-size: 1rem;
    font-weight: 700;
    color: var(--text-primary);
    line-height: 1.3;
}

.ppc-body {
    font-size: 0.8125rem;
    color: var(--text-secondary);
    line-height: 1.5;
    margin-bottom: 0.625rem;
}

.ppc-list {
    list-style: none;
    margin: 0 0 0.75rem;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}
.ppc-list li {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.8125rem;
    color: var(--text-primary);
}

.ppc-actions {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}
.ppc-cta {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border-radius: 0.625rem;
    font-size: 0.8125rem;
    font-weight: 600;
    background: rgb(79 70 229);
    color: white;
    box-shadow: 0 1px 0 rgba(255,255,255,0.18) inset, var(--elev-1);
    transition: background-color var(--motion-fast) var(--motion-out), box-shadow var(--motion-fast) var(--motion-out);
}
.ppc-cta:hover:not(:disabled) { background: rgb(67 56 202); box-shadow: var(--elev-2); }
.ppc-cta:disabled { opacity: 0.6; cursor: not-allowed; }

.ppc-later {
    display: inline-flex;
    align-items: center;
    padding: 0.5rem 0.875rem;
    border-radius: 0.625rem;
    font-size: 0.8125rem;
    font-weight: 600;
    color: var(--text-secondary);
    transition: background-color var(--motion-fast) var(--motion-out), color var(--motion-fast) var(--motion-out);
}
.ppc-later:hover { background: rgb(241 245 249); color: var(--text-primary); }
html.dark .ppc-later:hover { background: rgb(30 41 59 / 0.6); }

.ppc-message {
    margin-top: 0.625rem;
    font-size: 0.75rem;
    font-weight: 600;
}
.ppc-message.is-positive { color: rgb(4 120 87); }
.ppc-message.is-warning  { color: rgb(146 64 14); }
html.dark .ppc-message.is-positive { color: rgb(110 231 183); }
html.dark .ppc-message.is-warning  { color: rgb(252 211 77); }
</style>
