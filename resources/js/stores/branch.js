import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import { branchService } from '@/services/branchService';

/**
 * Branch store — two responsibilities:
 *  1. Manages the paginated list for BranchListView
 *  2. Provides `allActive` for branch dropdowns across the entire app
 */
export const useBranchStore = defineStore('branch', () => {

    // ── List state ────────────────────────────────────────────────────────
    const rows    = ref([]);
    const meta    = ref(null);
    const loading = ref(false);
    const error   = ref(null);
    const filters = ref({ search: '', page: 1, per_page: 20 });

    // ── Dropdown state ────────────────────────────────────────────────────
    const allActive        = ref([]);
    const allActiveLoaded  = ref(false);

    // ── Getters ───────────────────────────────────────────────────────────
    const branchOptions = computed(() =>
        allActive.value.map(b => ({ value: b.id, label: `${b.code} — ${b.name}` }))
    );

    // ── Actions ───────────────────────────────────────────────────────────

    async function fetch(overrides = {}) {
        Object.assign(filters.value, overrides);
        loading.value = true;
        error.value   = null;

        try {
            const { data } = await branchService.index(filters.value);
            rows.value = data.data;
            meta.value = data.meta;
        } catch (err) {
            error.value = err.response?.data?.message ?? 'Failed to load branches.';
        } finally {
            loading.value = false;
        }
    }

    async function fetchAllActive() {
        if (allActiveLoaded.value) return; // Cached — only load once per session
        try {
            const { data } = await branchService.all();
            allActive.value       = data.data;
            allActiveLoaded.value = true;
        } catch {
            // Non-critical — silently fail (dropdown will be empty)
        }
    }

    function invalidateCache() {
        allActiveLoaded.value = false;
    }

    return {
        rows, meta, loading, error, filters,
        allActive, branchOptions,
        fetch, fetchAllActive, invalidateCache,
    };
});
