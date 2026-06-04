/*
 * Registers the service worker and bootstraps the install state machine.
 *
 * Install-related events flow through the singleton in `./install.js`
 * (see that file for the full state diagram). This file only owns the
 * service-worker lifecycle and the update-available signal.
 *
 * Update-related events still travel through window.dispatch:
 *   posmeister:pwa:update-ready
 */
import { setupInstallStateMachine } from './install';

export function registerPwa() {
    if (typeof window === 'undefined') return;

    // Bootstrap the install state machine — captures beforeinstallprompt
    // and appinstalled exactly once, exposes reactive state to any
    // component that imports installState.
    setupInstallStateMachine();

    if (!('serviceWorker' in navigator)) return;
    if (location.protocol !== 'https:' && location.hostname !== 'localhost' && location.hostname !== '127.0.0.1') {
        // Service workers only register on https or localhost.
        return;
    }

    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js', { scope: '/' }).then((reg) => {
            if (reg.waiting) {
                window.dispatchEvent(new CustomEvent('posmeister:pwa:update-ready'));
            }
            reg.addEventListener('updatefound', () => {
                const sw = reg.installing;
                if (!sw) return;
                sw.addEventListener('statechange', () => {
                    if (sw.state === 'installed' && navigator.serviceWorker.controller) {
                        window.dispatchEvent(new CustomEvent('posmeister:pwa:update-ready'));
                    }
                });
            });
            nudgePrecache();
        }).catch(() => {});
    });

    // Re-nudge whenever the network flips back online: a freshly online
    // user is the perfect moment to top up any chunks the SW missed
    // during a flaky install or after a deploy.
    window.addEventListener('online', () => nudgePrecache());
}

function nudgePrecache() {
    if (!navigator.serviceWorker?.controller) return;
    try { navigator.serviceWorker.controller.postMessage('PRECACHE_ASSETS'); }
    catch { /* SW may still be activating — next boot will retry */ }
}

export function applyUpdateNow() {
    if (!('serviceWorker' in navigator)) return;
    navigator.serviceWorker.getRegistration().then((reg) => {
        if (reg?.waiting) reg.waiting.postMessage('SKIP_WAITING');
    });
    navigator.serviceWorker.addEventListener('controllerchange', () => window.location.reload());
}
