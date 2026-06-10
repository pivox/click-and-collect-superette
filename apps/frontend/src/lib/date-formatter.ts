/**
 * Localized date formatting for client pages.
 * Supports French (fr-FR) and Arabic (ar-TN).
 */

export type Locale = 'fr' | 'ar';

export function formatDate(iso: string, locale: Locale = 'fr'): string {
  const localeCode = locale === 'ar' ? 'ar-TN' : 'fr-FR';
  return new Date(iso).toLocaleDateString(localeCode, {
    day: 'numeric',
    month: 'short',
    timeZone: 'Africa/Tunis',
  });
}

export function formatDateTime(iso: string, locale: Locale = 'fr'): string {
  const localeCode = locale === 'ar' ? 'ar-TN' : 'fr-FR';
  return new Date(iso).toLocaleString(localeCode, {
    day: 'numeric',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
    timeZone: 'Africa/Tunis',
  });
}

export function formatSlotRange(startIso: string, endIso: string, locale: Locale = 'fr'): string {
  const localeCode = locale === 'ar' ? 'ar-TN' : 'fr-FR';
  const start = new Date(startIso);
  const end = new Date(endIso);

  const dateStr = start.toLocaleDateString(localeCode, {
    day: 'numeric',
    month: 'short',
    timeZone: 'Africa/Tunis',
  });
  const startTime = start.toLocaleTimeString(localeCode, {
    hour: '2-digit',
    minute: '2-digit',
    timeZone: 'Africa/Tunis',
  });
  const endTime = end.toLocaleTimeString(localeCode, {
    hour: '2-digit',
    minute: '2-digit',
    timeZone: 'Africa/Tunis',
  });

  return `${dateStr} ${startTime} — ${endTime}`;
}
