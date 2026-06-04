'use client';

import { clientLocales, useClientLocale, type Locale } from '@/lib/i18n/ClientLocaleContext';
import { cn } from '@/lib/cn';

// S14-004: compact FR/AR language switch for the client area.
// Selecting a language persists the choice ('client:lang') and flips the layout
// direction (RTL for Arabic) immediately via ClientLocaleProvider.

const SHORT_LABEL: Record<Locale, string> = {
  fr: 'FR',
  ar: 'ع',
};

const FULL_LABEL: Record<Locale, string> = {
  fr: 'Français',
  ar: 'العربية',
};

export function LanguageToggle({ className }: { className?: string }) {
  const { locale, setLocale, t } = useClientLocale();

  return (
    <div
      role="radiogroup"
      aria-label={t('client.language.label')}
      className={cn(
        'inline-flex shrink-0 items-center gap-0.5 rounded-full bg-soft p-0.5',
        className,
      )}
    >
      {clientLocales.map((value) => {
        const selected = value === locale;
        return (
          <button
            key={value}
            type="button"
            role="radio"
            aria-checked={selected}
            aria-label={FULL_LABEL[value]}
            title={FULL_LABEL[value]}
            onClick={() => setLocale(value)}
            className={cn(
              'rounded-full px-2.5 py-1 text-xs font-extrabold transition-colors',
              selected
                ? 'bg-card text-primary-dark shadow-sm'
                : 'text-muted hover:text-primary-dark',
            )}
          >
            {SHORT_LABEL[value]}
          </button>
        );
      })}
    </div>
  );
}
