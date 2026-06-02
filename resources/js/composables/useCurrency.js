import { computed } from 'vue';
import { useSettingsStore } from '@/stores/settings';
import { useLocale } from '@/composables/useLocale';

export function useCurrency() {
    const settingsStore = useSettingsStore();
    const { intlLocale } = useLocale();

    const currencyCode   = computed(() => settingsStore.settings?.currency_code   ?? 'EUR');
    const currencySymbol = computed(() => settingsStore.settings?.currency_symbol ?? '€');

    // Number formatting follows the currently active UI locale, so
    //   English locale → 10,000.00         (BD / US style)
    //   German locale  → 10.000,00         (DE style)
    //   Bangla locale  → ১০,০০০.০০          (Bengali numerals)
    //   Arabic locale  → ١٠٬٠٠٠٫٠٠         (Eastern Arabic numerals)
    function fmtCurrency(val) {
        return new Intl.NumberFormat(intlLocale.value || 'en-US', {
            style:    'currency',
            currency: currencyCode.value,
        }).format(val ?? 0);
    }

    /** Plain number, no currency code/symbol — same locale-aware grouping. */
    function fmtNumber(val, decimals = 2) {
        return new Intl.NumberFormat(intlLocale.value || 'en-US', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals,
        }).format(val ?? 0);
    }

    return { fmtCurrency, fmtNumber, currencyCode, currencySymbol };
}
