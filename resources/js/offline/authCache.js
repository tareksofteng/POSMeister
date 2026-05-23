/*
 * Offline auth snapshot.
 *
 * After every successful API authentication (login or /me), we persist a
 * snapshot of the session into IndexedDB so a page reload while offline
 * does NOT kick the cashier back to /login. The token itself stays in
 * localStorage for the axios interceptor (that file is already in place);
 * IndexedDB just holds the *expanded* session payload + the moment we
 * last verified it with the server.
 *
 * Grace policy: an offline session is considered valid for OFFLINE_TTL_MS
 * (default 7 days) from the last successful online verification. Past that,
 * the cashier must reconnect and re-authenticate.
 */
import { get, put, del } from './db';

const KEY = 'current';
const STORE = 'auth_session';
export const OFFLINE_TTL_MS = 7 * 24 * 60 * 60 * 1000;   // 7 days

export async function saveAuthSnapshot({ token, user, permissions }) {
    await put(STORE, {
        k: KEY,
        token,
        user,
        permissions: permissions || [],
        verifiedAt: new Date().toISOString(),
    });
}

export async function loadAuthSnapshot() {
    const row = await get(STORE, KEY).catch(() => null);
    if (!row) return null;

    // Hard expiry check
    const verifiedAt = row.verifiedAt ? new Date(row.verifiedAt).getTime() : 0;
    if (Date.now() - verifiedAt > OFFLINE_TTL_MS) return null;

    return row;
}

export async function clearAuthSnapshot() {
    await del(STORE, KEY).catch(() => {});
}

export function isOnline() {
    return typeof navigator === 'undefined' || navigator.onLine !== false;
}
