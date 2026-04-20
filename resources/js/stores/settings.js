import { defineStore } from 'pinia';
import { ref } from 'vue';
import { settingsService } from '@/services/settingsService';

/**
 * Global app settings store — loaded once on boot, used everywhere
 * (e.g. currency symbol in invoices, company name in header).
 */
export const useSettingsStore = defineStore('settings', () => {

    const settings = ref(null);
    const loading  = ref(false);

    async function load() {
        if (settings.value) return; // already loaded
        loading.value = true;
        try {
            const { data } = await settingsService.get();
            settings.value = data.data ?? data;
        } finally {
            loading.value = false;
        }
    }

    function patch(updated) {
        settings.value = updated;
    }

    return { settings, loading, load, patch };
});
