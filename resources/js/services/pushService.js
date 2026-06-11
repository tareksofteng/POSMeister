import api from './api';

/*
 * Browser-side Web Push lifecycle. The shape of every function tries to
 * never throw — call sites can do `if (!await pushService.isSupported())`
 * without a try/catch.
 */
export const pushService = {
    async isSupported() {
        return (
            'serviceWorker'   in navigator &&
            'PushManager'     in window    &&
            'Notification'    in window
        );
    },

    async currentPermission() {
        if (!('Notification' in window)) return 'unsupported';
        return Notification.permission;   // 'granted' | 'denied' | 'default'
    },

    async vapidKey() {
        const res = await api.get('/push/vapid-key');
        return res.data?.data?.public_key || null;
    },

    /**
     * Reads the current subscription off the active SW registration. Used
     * by the permission card to render the "already enabled" state without
     * making the user click anything.
     */
    async existingSubscription() {
        if (!(await this.isSupported())) return null;
        const reg = await navigator.serviceWorker.ready;
        return reg.pushManager.getSubscription();
    },

    /**
     * Prompts the user for permission (browser-native), creates a Push
     * subscription with the server's VAPID public key, and POSTs the
     * subscription up to the backend.
     *
     * Returns: { ok: boolean, status: 'granted' | 'denied' | 'unsupported' | 'error' }
     */
    async enable() {
        if (!(await this.isSupported())) {
            return { ok: false, status: 'unsupported' };
        }

        const permission = await Notification.requestPermission();
        if (permission !== 'granted') {
            return { ok: false, status: permission };
        }

        const publicKey = await this.vapidKey();
        if (!publicKey) {
            return { ok: false, status: 'server_not_configured' };
        }

        const reg = await navigator.serviceWorker.ready;
        const existing = await reg.pushManager.getSubscription();
        if (existing) {
            await this._postSubscription(existing);
            return { ok: true, status: 'already_subscribed' };
        }

        const sub = await reg.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: urlBase64ToUint8Array(publicKey),
        });
        await this._postSubscription(sub);
        return { ok: true, status: 'granted' };
    },

    async disable() {
        const sub = await this.existingSubscription();
        if (!sub) return { ok: true };
        try { await sub.unsubscribe(); } catch { /* fall through */ }
        try { await api.post('/push/unsubscribe', { endpoint: sub.endpoint }); } catch { /* tolerable */ }
        return { ok: true };
    },

    async devices() {
        const res = await api.get('/push/devices');
        return res.data?.data ?? [];
    },

    async renameDevice(id, label) {
        return api.post(`/push/devices/${id}/rename`, { label });
    },

    async revokeDevice(id) {
        return api.delete(`/push/devices/${id}`);
    },

    async analytics() {
        return api.get('/push/analytics');
    },

    async _postSubscription(sub) {
        const ua = detectAgent();
        const json = sub.toJSON();
        return api.post('/push/subscribe', {
            endpoint:   json.endpoint,
            p256dh_key: json.keys?.p256dh,
            auth_token: json.keys?.auth,
            browser:    ua.browser,
            platform:   ua.platform,
            device_type: ua.deviceType,
            label:      ua.label,
        });
    },
};

// ── Helpers ─────────────────────────────────────────────────────────────

/**
 * Convert the URL-safe Base64 VAPID public key the server returns into
 * the Uint8Array the PushManager.subscribe() call needs.
 */
function urlBase64ToUint8Array(b64) {
    const padding = '='.repeat((4 - (b64.length % 4)) % 4);
    const base64  = (b64 + padding).replace(/-/g, '+').replace(/_/g, '/');
    const raw     = atob(base64);
    return new Uint8Array([...raw].map(c => c.charCodeAt(0)));
}

/**
 * Lightweight UA → browser/platform/device sniff. Used purely for the
 * "Connected devices" list — gives the user a recognisable label for each
 * registration. Resilient: when nothing matches, falls back to generic.
 */
function detectAgent() {
    const ua = (navigator.userAgent || '').toLowerCase();
    let browser = 'Browser';
    if      (ua.includes('edg/'))     browser = 'Edge';
    else if (ua.includes('opr/'))     browser = 'Opera';
    else if (ua.includes('firefox/')) browser = 'Firefox';
    else if (ua.includes('chrome/'))  browser = 'Chrome';
    else if (ua.includes('safari/'))  browser = 'Safari';

    let platform = 'Desktop';
    if (ua.includes('android'))                       platform = 'Android';
    else if (ua.includes('iphone') || ua.includes('ipad') || ua.includes('ipod')) platform = 'iOS';
    else if (ua.includes('windows'))                  platform = 'Windows';
    else if (ua.includes('macintosh') || ua.includes('mac os')) platform = 'macOS';
    else if (ua.includes('linux'))                    platform = 'Linux';

    let deviceType = 'desktop';
    if (ua.includes('android') && !ua.includes('tablet')) deviceType = 'mobile';
    else if (ua.includes('iphone') || ua.includes('ipod'))    deviceType = 'mobile';
    else if (ua.includes('ipad') || ua.includes('tablet'))    deviceType = 'tablet';

    return {
        browser,
        platform,
        deviceType,
        label: `${browser} · ${platform}`,
    };
}
