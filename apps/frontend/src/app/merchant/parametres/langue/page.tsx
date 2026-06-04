'use client';

import Link from 'next/link';
import { useState } from 'react';
import { ArrowLeft, Check } from 'lucide-react';
import { cn } from '@/lib/cn';
import {
  type MerchantLocale,
  merchantLocales,
  useMerchantLocale,
} from '@/lib/i18n/MerchantLocaleContext';

// MS-004 (MVP-4): merchant chooses the interface language (FR / AR with RTL).
// The choice is persisted in localStorage ('merchant:lang') and applied immediately.

// Secondary hint shown under each option — intentionally not translated so each
// language is recognisable in its own script regardless of the active locale.
const LANGUAGE_HINTS: Record<MerchantLocale, string> = {
  fr: 'Français',
  ar: 'العربية · RTL',
};

export default function MerchantLanguagePage() {
  const { locale, setLocale, t } = useMerchantLocale();
  const [saved, setSaved] = useState(false);

  const handleSelect = (value: MerchantLocale) => {
    setLocale(value);
    setSaved(true);
  };

  return (
    <div className="mx-auto max-w-2xl space-y-6">
      <div className="space-y-2">
        <Link
          href="/merchant/parametres"
          className="inline-flex items-center gap-1 text-sm font-bold text-primary hover:underline"
        >
          <ArrowLeft className="h-4 w-4" aria-hidden="true" />
          {t('merchant.settings.language.back')}
        </Link>
        <h1 className="text-2xl font-black text-ink">{t('merchant.settings.language.title')}</h1>
        <p className="text-sm text-muted">{t('merchant.settings.language.pageSubtitle')}</p>
      </div>

      <div
        role="radiogroup"
        aria-label={t('merchant.settings.language.title')}
        className="space-y-3 rounded-lg border border-line bg-card p-4"
      >
        {merchantLocales.map((value) => {
          const selected = value === locale;
          const label =
            value === 'fr'
              ? t('merchant.settings.language.french')
              : t('merchant.settings.language.arabic');
          return (
            <button
              key={value}
              type="button"
              role="radio"
              aria-checked={selected}
              onClick={() => handleSelect(value)}
              className={cn(
                'flex w-full items-center justify-between gap-3 rounded-md border px-4 py-3 text-start transition-colors',
                selected
                  ? 'border-primary bg-soft'
                  : 'border-line hover:border-primary/60 hover:bg-soft/50',
              )}
            >
              <span className="min-w-0">
                <span className="block font-bold text-ink">{label}</span>
                <span className="block text-xs text-muted">{LANGUAGE_HINTS[value]}</span>
              </span>
              {selected && (
                <Check className="h-5 w-5 shrink-0 text-primary" aria-hidden="true" />
              )}
            </button>
          );
        })}
      </div>

      {saved && (
        <p className="text-sm text-green-700" role="status">
          {t('merchant.settings.language.saved')}
        </p>
      )}
    </div>
  );
}
