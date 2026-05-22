import { getRequestConfig } from 'next-intl/server';

export const locales = ['fr', 'ar'] as const;
export type Locale = (typeof locales)[number];
export const defaultLocale: Locale = 'fr';

export const rtlLocales: Locale[] = ['ar'];

export function isRtl(locale: Locale): boolean {
  return rtlLocales.includes(locale);
}

export default getRequestConfig(async ({ locale }) => ({
  locale: locale!,
  messages: (await import(`../messages/${locale}.json`)).default,
}));
