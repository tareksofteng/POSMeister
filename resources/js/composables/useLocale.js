import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { SUPPORTED_LOCALES } from '@/plugins/i18n';

const STORAGE_KEY = 'pos_locale';

export function useLocale() {
    const { locale } = useI18n();

    const currentConfig = computed(() => SUPPORTED_LOCALES[locale.value] ?? SUPPORTED_LOCALES.en);

    const isRTL = computed(() => currentConfig.value.dir === 'rtl');

    const intlLocale = computed(() => currentConfig.value.intl);

    function setLocale(code) {
        if (!(code in SUPPORTED_LOCALES)) return;

        locale.value = code;
        localStorage.setItem(STORAGE_KEY, code);

        const config = SUPPORTED_LOCALES[code];
        document.documentElement.lang = code;
        document.documentElement.dir  = config.dir;
    }

    return {
        locale,
        currentConfig,
        isRTL,
        intlLocale,
        SUPPORTED_LOCALES,
        setLocale,
    };
}
