import arMessages from '@/messages/ar.json';
import frMessages from '@/messages/fr.json';

// Shared i18n core for the lightweight client/merchant locale contexts (MS-004, S14-004).
// Full next-intl routing is intentionally not used here — these contexts only need a
// locale, a direction and a translation lookup against the existing message catalogs.

export const locales = ['fr', 'ar'] as const;
export type Locale = (typeof locales)[number];
export const defaultLocale: Locale = 'fr';

export const MESSAGES: Record<Locale, Record<string, unknown>> = {
  fr: frMessages as Record<string, unknown>,
  ar: arMessages as Record<string, unknown>,
};

export function isRtlLocale(locale: Locale): boolean {
  return locale === 'ar';
}

export function isLocale(value: unknown): value is Locale {
  return value === 'fr' || value === 'ar';
}

/** Resolve a dot-path key (e.g. "client.nav.home") against a messages tree. */
export function translate(messages: Record<string, unknown>, key: string): string {
  const value = key.split('.').reduce<unknown>((acc, part) => {
    if (acc && typeof acc === 'object' && part in (acc as Record<string, unknown>)) {
      return (acc as Record<string, unknown>)[part];
    }
    return undefined;
  }, messages);

  // Fallback to the key itself when a translation is missing — keeps the UI usable.
  return typeof value === 'string' ? value : key;
}
