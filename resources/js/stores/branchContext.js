import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import { branchContextService } from '@/services/branchContextService';

/*
 |--------------------------------------------------------------------------
 | Centralised branch workspace store
 |--------------------------------------------------------------------------
 |
 | The current branch id is the SINGLE source of truth for "which workspace
 | am I in?" — every page (dashboard, lists, forms) reads it via this
 | store, and every write defaults to it server-side via BranchContextService.
 |
 | Persistence: the active branch id is mirrored into localStorage so a PWA
 | reload (online OR offline) lands in the same workspace.
 |
 | Axios sync: the axios interceptor in services/api.js reads
 | `localStorage.getItem('pos_branch_id')` on every outbound request and
 | injects it as `X-Branch-Id`. That means a branch switch just writes
 | localStorage; the next API call automatically uses the new context.
 */

const LS_KEY = 'pos_branch_id';

function readLocal() {
    try {
        const v = localStorage.getItem(LS_KEY);
        if (v === null || v === '') return null;
        const n = parseInt(v, 10);
        return Number.isFinite(n) ? n : null;
    } catch { return null; }
}

function writeLocal(branchId) {
    try {
        if (branchId === null || branchId === undefined) localStorage.removeItem(LS_KEY);
        else localStorage.setItem(LS_KEY, String(branchId));
    } catch { /* private mode / quota — ignore */ }
}

export const useBranchContextStore = defineStore('branchContext', () => {
    // ── State ──────────────────────────────────────────────────────────────
    const branchId       = ref(readLocal());
    const branchName     = ref(null);
    const branchCode     = ref(null);
    const isMainBranch   = ref(false);
    const mainBranchId   = ref(1);
    const availableList  = ref([]);
    const loading        = ref(false);
    const switching      = ref(false);

    // ── Getters ────────────────────────────────────────────────────────────
    const isAllBranches = computed(() => branchId.value === null);
    const displayLabel  = computed(() => {
        if (isAllBranches.value) return null;             // caller decides "All branches" copy
        return branchName.value || (branchId.value ? `Branch #${branchId.value}` : null);
    });

    // ── Actions ────────────────────────────────────────────────────────────

    /** Read the server-side truth and sync local cache. Safe to call any time. */
    async function refreshCurrent() {
        loading.value = true;
        try {
            const { data } = await branchContextService.current();
            const payload = data.data ?? {};
            branchId.value     = payload.branch_id ?? null;
            branchName.value   = payload.branch_name ?? null;
            branchCode.value   = payload.branch_code ?? null;
            isMainBranch.value = !!payload.is_main_branch;
            mainBranchId.value = payload.main_branch_id ?? 1;
            writeLocal(branchId.value);
        } catch {
            // 401 etc — leave whatever we had cached; the auth flow
            // handles its own redirect.
        } finally {
            loading.value = false;
        }
    }

    /** Fetch the list of branches the user can switch to. Caches in store. */
    async function loadAvailable(force = false) {
        if (!force && availableList.value.length) return availableList.value;
        try {
            const { data } = await branchContextService.available();
            availableList.value = data.data ?? [];
        } catch {
            availableList.value = [];
        }
        return availableList.value;
    }

    /**
     * Switch the workspace. The server-side ACL guard is authoritative —
     * we never decide here whether the user is "allowed" beyond a quick
     * presence check.
     *
     * Returns a boolean so the caller (BranchSwitcher) can show a toast
     * + reload-the-page on success / a denial message on 403.
     */
    async function switchTo(newBranchId) {
        if (newBranchId === branchId.value) return true;     // no-op
        switching.value = true;
        try {
            // Optimistically update localStorage so the very next outbound
            // request (the switch endpoint itself) already carries the new
            // header. If the server rejects we roll back below.
            const previous = branchId.value;
            writeLocal(newBranchId);
            try {
                const { data } = await branchContextService.switchTo(newBranchId);
                const payload = data.data ?? {};
                branchId.value     = payload.branch_id ?? null;
                branchName.value   = payload.branch_name ?? null;
                branchCode.value   = payload.branch_code ?? null;
                isMainBranch.value = !!payload.is_main_branch;
                writeLocal(branchId.value);
                return true;
            } catch (err) {
                writeLocal(previous);
                branchId.value = previous;
                throw err;
            }
        } finally {
            switching.value = false;
        }
    }

    /**
     * Wipe the cached context (called from auth.logout so the next login
     * doesn't accidentally land in a stranger's branch).
     */
    function reset() {
        branchId.value = null;
        branchName.value = null;
        branchCode.value = null;
        isMainBranch.value = false;
        availableList.value = [];
        writeLocal(null);
    }

    return {
        branchId, branchName, branchCode, isMainBranch, mainBranchId,
        availableList, loading, switching,
        isAllBranches, displayLabel,
        refreshCurrent, loadAvailable, switchTo, reset,
    };
});
