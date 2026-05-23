/*
 * Registers the service worker and exposes a tiny event channel for
 * the rest of the app:
 *
 *   window.dispatchEvent('posmeister:pwa:installable', { e: BeforeInstallPromptEvent })
 *   window.dispatchEvent('posmeister:pwa:installed')
 *   window.dispatchEvent('posmeister:pwa:update-ready')
 *
 * Components listen, store the event, and call .prompt() when the user
 * clicks an "Install" button.
 */
export function registerPwa() {
    if (typeof window === 'undefined') return;

    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        window.__posmeisterInstallEvent = e;
        window.dispatchEvent(new CustomEvent('posmeister:pwa:installable', { detail: { event: e } }));
    });

    window.addEventListener('appinstalled', () => {
        window.__posmeisterInstallEvent = null;
        window.dispatchEvent(new CustomEvent('posmeister:pwa:installed'));
    });

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
        }).catch(() => {});
    });
}

export function applyUpdateNow() {
    if (!('serviceWorker' in navigator)) return;
    navigator.serviceWorker.getRegistration().then((reg) => {
        if (reg?.waiting) reg.waiting.postMessage('SKIP_WAITING');
    });
    navigator.serviceWorker.addEventListener('controllerchange', () => window.location.reload());
}
