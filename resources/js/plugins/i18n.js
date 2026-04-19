import { createI18n } from 'vue-i18n';
import en from '@/locales/en.json';
import de from '@/locales/de.json';
import bn from '@/locales/bn.json';
import ar from '@/locales/ar.json';

export const SUPPORTED_LOCALES = {
    en: { label: 'English', flag: '🇬🇧', dir: 'ltr', intl: 'en-US' },
    de: { label: 'Deutsch', flag: '🇩🇪', dir: 'ltr', intl: 'de-DE' },
    bn: { label: 'বাংলা',   flag: '🇧🇩', dir: 'ltr', intl: 'bn-BD' },
    ar: { label: 'العربية', flag: '🇸🇦', dir: 'rtl', intl: 'ar-SA' },
};

const STORAGE_KEY = 'pos_locale';
const DEFAULT     = 'en';

const savedLocale = localStorage.getItem(STORAGE_KEY) ?? DEFAULT;
const validLocale = savedLocale in SUPPORTED_LOCALES ? savedLocale : DEFAULT;

// Apply direction and lang on initial load (before Vue mounts)
document.documentElement.lang = validLocale;
document.documentElement.dir  = SUPPORTED_LOCALES[validLocale].dir;

export const i18n = createI18n({
    legacy:         false,   // Composition API mode
    locale:         validLocale,
    fallbackLocale: DEFAULT,
    messages:       { en, de, bn, ar },
    // Suppress "missing translation" warnings in production
    missingWarn:    import.meta.env.DEV,
    fallbackWarn:   import.meta.env.DEV,
});
