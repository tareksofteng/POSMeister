import { defineStore } from 'pinia';
import { ref, watch } from 'vue';

const STORAGE_KEY = 'posmeister:theme';

export const useThemeStore = defineStore('theme', () => {
    const mode = ref(loadInitial());

    function loadInitial() {
        try {
            const saved = localStorage.getItem(STORAGE_KEY);
            if (saved === 'dark' || saved === 'light') return saved;
        } catch { /* private mode */ }
        if (typeof window !== 'undefined' && window.matchMedia) {
            return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        }
        return 'light';
    }

    function apply(value) {
        if (typeof document === 'undefined') return;
        const root = document.documentElement;
        if (value === 'dark') root.classList.add('dark');
        else root.classList.remove('dark');
    }

    function toggle() {
        mode.value = mode.value === 'dark' ? 'light' : 'dark';
    }

    function set(value) {
        if (value === 'dark' || value === 'light') mode.value = value;
    }

    // Apply once on init and on every change.
    apply(mode.value);
    watch(mode, (v) => {
        apply(v);
        try { localStorage.setItem(STORAGE_KEY, v); } catch { /* ignore */ }
    });

    return { mode, toggle, set };
});
