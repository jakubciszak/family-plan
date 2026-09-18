import { getLocales } from 'expo-localization';
import { createInstance } from 'i18next';

import { initReactI18next } from 'react-i18next';

import en from './locales/en.json';
import pl from './locales/pl.json';

const i18n = createInstance();
const FALLBACK = 'pl';
const SUPPORTED = ['pl', 'en'] as const;

export type Language = (typeof SUPPORTED)[number];

const isSupported = (code: string): code is Language =>
  SUPPORTED.includes(code as Language);

export const deviceLanguage = (): Language => {
  const preferred = getLocales().find((locale) => isSupported(locale.languageCode ?? ''));

  return (preferred?.languageCode as Language) ?? FALLBACK;
};

void i18n.use(initReactI18next).init({
  resources: {
    en: { translation: en },
    pl: { translation: pl },
  },
  lng: deviceLanguage(),
  fallbackLng: FALLBACK,
  interpolation: { escapeValue: false },
});

export default i18n;
