'use client';

import React, { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import { type Locale, defaultLocale, isLocale, isRtlLocale, MESSAGES, translate } from './locale-core';

// MS-004 (MVP-4): lightweight merchant interface language preference (FR/AR).
// Scoped to the merchant area — full app i18n is delivered incrementally by S14-004.
// Follows the project pattern: useState + useEffect + useCallback + localStorage.

export type MerchantLocale = Locale;
export const merchantLocales = ['fr', 'ar'] as const;
export const defaultMerchantLocale: MerchantLocale = defaultLocale;
const STORAGE_KEY = 'merchant:lang';

export { isRtlLocale };

export interface MerchantLocaleContextValue {
  locale: MerchantLocale;
  dir: 'ltr' | 'rtl';
  setLocale: (locale: MerchantLocale) => void;
  t: (key: string) => string;
}

const MerchantLocaleContext = createContext<MerchantLocaleContextValue | null>(null);

export function MerchantLocaleProvider({ children }: { children: React.ReactNode }) {
  const [locale, setLocaleState] = useState<MerchantLocale>(defaultMerchantLocale);

  useEffect(() => {
    try {
      const raw = localStorage.getItem(STORAGE_KEY);
      if (isLocale(raw)) {
        setLocaleState(raw);
      }
    } catch {
      // SecurityError (privacy mode) — keep the default locale
    }
  }, []);

  const setLocale = useCallback((next: MerchantLocale) => {
    try {
      localStorage.setItem(STORAGE_KEY, next);
    } catch {
      // QuotaExceededError or SecurityError — still update React state
    }
    setLocaleState(next);
  }, []);

  const t = useCallback((key: string) => translate(MESSAGES[locale], key), [locale]);

  const dir: 'ltr' | 'rtl' = isRtlLocale(locale) ? 'rtl' : 'ltr';

  const value = useMemo<MerchantLocaleContextValue>(
    () => ({ locale, dir, setLocale, t }),
    [locale, dir, setLocale, t],
  );

  return (
    <MerchantLocaleContext.Provider value={value}>
      {/* Scope the direction/language to the merchant area without touching the global <html>. */}
      <div dir={dir} lang={locale} className="contents">
        {children}
      </div>
    </MerchantLocaleContext.Provider>
  );
}

export function useMerchantLocale(): MerchantLocaleContextValue {
  const ctx = useContext(MerchantLocaleContext);
  if (!ctx) throw new Error('useMerchantLocale must be used within MerchantLocaleProvider');
  return ctx;
}
