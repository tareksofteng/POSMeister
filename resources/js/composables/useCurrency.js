import { computed } from 'vue';
import { useSettingsStore } from '@/stores/settings';

export function useCurrency() {
    const settingsStore = useSettingsStore();

    const currencyCode   = computed(() => settingsStore.settings?.currency_code   ?? 'EUR');
    const currencySymbol = computed(() => settingsStore.settings?.currency_symbol ?? '€');

    function fmtCurrency(val) {
        return new Intl.NumberFormat('de-DE', {
            style:    'currency',
            currency: currencyCode.value,
        }).format(val ?? 0);
    }

    return { fmtCurrency, currencyCode, currencySymbol };
}
