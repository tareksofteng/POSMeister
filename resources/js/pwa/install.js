/*
 * PWA install state machine
 * -------------------------
 * Single source of truth for the install button. Replaces the older
 * `installable.value = true/false` toggle that hid the button on
 * `userChoice = accepted` — which on Android meant the UI claimed
 * the install succeeded before it actually finished, leaving users
 * staring at a vanished button with no app on their home screen.
 *
 * State machine
 * -------------
 *   unknown      → page just loaded, we don't know yet
 *   available    → beforeinstallprompt fired, prompt() will work
 *   installing   → user clicked Install, waiting for the OS dialog
 *                  and the appinstalled event
 *   installed    → either appinstalled fired, or we detected we're
 *                  already running as a standalone PWA
 *   unsupported  → browser does NOT expose beforeinstallprompt
 *                  (Samsung Internet, Firefox Android, Safari iOS,
 *                  some older webviews) — surface a how-to modal
 *                  so the user can still get the app onto their
 *                  home screen via the browser menu.
 *
 * Analytics
 * ---------
 * Every transition dispatches a `posmeister:pwa:<event>` CustomEvent
 * on window with a tiny detail payload. Hook your analytics layer or
 * `console.info` listener into these names:
 *
 *   install_prompt_available    — event captured, button visible
 *   install_prompt_shown        — user clicked, native dialog opening
 *   install_prompt_accepted     — user said yes in the OS dialog
 *   install_prompt_dismissed    — user said no / closed the dialog
 *   app_installed               — appinstalled fired (canonical)
 *   app_installed_assumed       — installed-watchdog fired after
 *                                 4s of silence post-accept (Chrome
 *                                 occasionally omits appinstalled)
 *   install_unsupported         — no event after 4s + not standalone
 *
 * The module is a singleton — listeners are wired once via
 * setupInstallStateMachine() from pwa/register.js, and every Vue
 * component imports the reactive refs.
 */
import { ref, readonly } from 'vue';

const ACCEPTED_WATCHDOG_MS  = 4000;

// Browsers that genuinely don't expose beforeinstallprompt — they need
// the manual "Add to Home Screen" menu route. Marked unsupported on
// boot so the UI can react immediately without waiting for a timeout.
const NEVER_FIRES_BROWSERS = ['samsung', 'firefox', 'safari-ios', 'chrome-ios'];

const _state       = ref('unknown');
const _browserHint = ref('chrome');
let   _deferredPrompt = null;
let   _booted = false;

export const installState = readonly(_state);
export const browserHint  = readonly(_browserHint);

// ── Helpers ───────────────────────────────────────────────────────────────

function isStandalone() {
    if (typeof window === 'undefined') return false;
    try {
        if (window.matchMedia('(display-mode: standalone)').matches) return true;
        if (window.matchMedia('(display-mode: minimal-ui)').matches) return true;
    } catch { /* matchMedia may not exist in very old webviews */ }
    // iOS Safari adds this proprietary flag when launched from home screen.
    return window.navigator.standalone === true;
}

function detectBrowser() {
    if (typeof navigator === 'undefined') return 'chrome';
    const ua = navigator.userAgent || '';
    if (/SamsungBrowser/i.test(ua))             return 'samsung';
    if (/FxiOS/i.test(ua))                       return 'firefox';      // Firefox on iOS
    if (/Firefox/i.test(ua))                     return 'firefox';
    if (/EdgA|EdgiOS|Edg\//i.test(ua))           return 'edge';
    if (/iPhone|iPad|iPod/i.test(ua) && /Safari/i.test(ua) && !/CriOS|FxiOS|EdgiOS/i.test(ua)) return 'safari-ios';
    if (/CriOS/i.test(ua))                       return 'chrome-ios';
    return 'chrome';
}

function emit(name, detail = {}) {
    try { console.info('[pwa]', name, detail); } catch { /* noop */ }
    try {
        window.dispatchEvent(new CustomEvent('posmeister:pwa:' + name, { detail }));
    } catch { /* CustomEvent unsupported in ancient browsers */ }
}

function transition(next, extra = {}) {
    const prev = _state.value;
    if (prev === next) return;
    _state.value = next;
    emit('state', { from: prev, to: next, ...extra });
}

// ── Boot ──────────────────────────────────────────────────────────────────

export function setupInstallStateMachine() {
    if (_booted || typeof window === 'undefined') return;
    _booted = true;

    _browserHint.value = detectBrowser();

    if (isStandalone()) {
        transition('installed', { reason: 'standalone-on-boot' });
        return;
    }

    // Browsers that don't expose beforeinstallprompt at all (Safari iOS,
    // Firefox Android, Samsung Internet, Chrome iOS) are marked unsupported
    // right away so the UI hides the install button instead of waiting.
    if (NEVER_FIRES_BROWSERS.includes(_browserHint.value)) {
        transition('unsupported', { reason: 'browser-never-fires' });
        emit('install_unsupported', { browser: _browserHint.value });
        return;
    }

    window.addEventListener('beforeinstallprompt', (e) => {
        // Capture the event so we can fire prompt() later from our own
        // button click. The browser's native mini-infobar is suppressed
        // for the lifetime of this tab.
        e.preventDefault();
        _deferredPrompt = e;
        if (_state.value === 'installed') return;
        transition('available');
        emit('install_prompt_available');
    });

    window.addEventListener('appinstalled', () => {
        _deferredPrompt = null;
        transition('installed', { source: 'appinstalled-event' });
        emit('app_installed');
    });

    // Watch the display mode at runtime — if the user installs through
    // the browser menu (Add to Home Screen) and re-opens the standalone
    // shortcut, we want to flip into 'installed' immediately.
    try {
        const mm = window.matchMedia('(display-mode: standalone)');
        const handler = (ev) => {
            if (ev.matches && _state.value !== 'installed') {
                transition('installed', { source: 'display-mode-change' });
                emit('app_installed', { via: 'display-mode' });
            }
        };
        if (mm.addEventListener) mm.addEventListener('change', handler);
        else if (mm.addListener) mm.addListener(handler);  // Safari ≤13
    } catch { /* matchMedia unsupported */ }

    // Intentionally no timeout here. On Chromium browsers,
    // beforeinstallprompt is fired only AFTER the engagement heuristic
    // is satisfied — sometimes seconds, sometimes minutes after the
    // page loads, and never if the app is already installed. A short
    // grace window used to transition `unknown → unsupported`, which
    // surfaced a confusing "How to install" question-mark button on
    // Chrome Android (especially on devices where the app was already
    // installed). Now we stay in `unknown` indefinitely — the UI hides
    // the install button until the event actually arrives, mirroring
    // Chrome's own behaviour where it only shows the "Install app"
    // option once it's truly available.
}

// ── Public action: triggered from the install button ─────────────────────

/**
 * Show the native install prompt if available; otherwise return an
 * outcome the caller can use to show a fallback instructions modal.
 *
 *   { outcome: 'accepted' }         user agreed, OS is installing now
 *   { outcome: 'dismissed' }        user closed the OS dialog
 *   { outcome: 'unsupported' }      browser never fired the event
 *   { outcome: 'already-installed'} state already 'installed'
 *   { outcome: 'error', error }     prompt() threw
 *
 * IMPORTANT: callers must invoke this synchronously from inside the
 * user-gesture click handler — no `await` before this call — otherwise
 * Android Chrome will silently swallow the prompt.
 */
export async function triggerInstall() {
    if (_state.value === 'installed') return { outcome: 'already-installed' };

    if (!_deferredPrompt) {
        transition('unsupported', { reason: 'no-deferred-prompt' });
        emit('install_prompt_unavailable', { browser: _browserHint.value });
        return { outcome: 'unsupported' };
    }

    emit('install_prompt_shown');
    transition('installing');

    try {
        // Fire prompt() synchronously to preserve the user gesture.
        // Some browsers' prompt() returns a Promise, some return void —
        // userChoice is the canonical wait point either way.
        const maybePromise = _deferredPrompt.prompt();
        if (maybePromise && typeof maybePromise.then === 'function') {
            await maybePromise;
        }

        const choice = await _deferredPrompt.userChoice;

        if (choice.outcome === 'accepted') {
            emit('install_prompt_accepted', { platform: choice.platform });

            // Keep the UI in 'installing' until appinstalled lands. Some
            // builds of Android Chrome forget to fire it; the watchdog
            // promotes us to 'installed' after the grace window so the
            // UI doesn't get stuck on a spinner forever.
            setTimeout(() => {
                if (_state.value === 'installing') {
                    transition('installed', { source: 'accepted-watchdog' });
                    emit('app_installed_assumed');
                }
            }, ACCEPTED_WATCHDOG_MS);

            return { outcome: 'accepted' };
        }

        // User dismissed — the event has been consumed and Chrome
        // will NOT re-fire it for this tab. We fall back to the
        // "How to install" modal so they still have a recovery path.
        emit('install_prompt_dismissed', { platform: choice.platform });
        _deferredPrompt = null;
        transition('unsupported', { reason: 'dismissed-event-consumed' });
        return { outcome: 'dismissed' };
    } catch (err) {
        // Something threw — most likely an Android quirk where prompt()
        // was called outside the gesture. Allow retry if we still have
        // the event, otherwise fall back to instructions.
        try { console.warn('[pwa] install prompt threw', err); } catch {}
        if (_deferredPrompt) {
            transition('available', { reason: 'prompt-threw-retain-event' });
        } else {
            transition('unsupported', { reason: 'prompt-threw-no-event' });
        }
        return { outcome: 'error', error: err };
    }
}

// ── Debug / test hooks ────────────────────────────────────────────────────

/** Read-only peek at internal state — handy from the JS console. */
export function _debug() {
    return {
        state: _state.value,
        browserHint: _browserHint.value,
        hasDeferredPrompt: !!_deferredPrompt,
        isStandalone: isStandalone(),
        ua: typeof navigator !== 'undefined' ? navigator.userAgent : '',
    };
}
if (typeof window !== 'undefined') {
    window.__pwaInstall = { state: installState, debug: _debug, trigger: triggerInstall };
}
