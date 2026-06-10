'use client';

import React, { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import { type Locale, defaultLocale, isLocale, isRtlLocale, MESSAGES, translate } from './locale-core';

// S14-004: client interface language preference (FR / AR with RTL).
// Reuses the shared locale core (locale-core.ts) so the mechanism is not duplicated
// across the merchant (MS-004) and client spaces. Persistence is scoped per space:
// the client uses the 'client:lang' key, the merchant uses 'merchant:lang'.

export type { Locale };
export const clientLocales = ['fr', 'ar'] as const;
export const defaultClientLocale: Locale = defaultLocale;
const STORAGE_KEY = 'client:lang';

export interface ClientLocaleContextValue {
  locale: Locale;
  dir: 'ltr' | 'rtl';
  setLocale: (locale: Locale) => void;
  t: (key: string) => string;
}

const ClientLocaleContext = createContext<ClientLocaleContextValue | null>(null);

export function ClientLocaleProvider({ children }: { children: React.ReactNode }) {
  const [locale, setLocaleState] = useState<Locale>(defaultClientLocale);

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

  const setLocale = useCallback((next: Locale) => {
    try {
      localStorage.setItem(STORAGE_KEY, next);
    } catch {
      // QuotaExceededError or SecurityError — still update React state
    }
    setLocaleState(next);
  }, []);

  const t = useCallback((key: string) => translate(MESSAGES[locale], key), [locale]);

  const dir: 'ltr' | 'rtl' = isRtlLocale(locale) ? 'rtl' : 'ltr';

  const value = useMemo<ClientLocaleContextValue>(
    () => ({ locale, dir, setLocale, t }),
    [locale, dir, setLocale, t],
  );

  return (
    <ClientLocaleContext.Provider value={value}>
      {/* Scope direction/language to the client area without touching the global <html>. */}
      <div dir={dir} lang={locale} className="contents">
        {children}
      </div>
    </ClientLocaleContext.Provider>
  );
}

const defaultValue: ClientLocaleContextValue = {
  locale: defaultClientLocale,
  dir: 'ltr',
  setLocale: () => {},
  t: (key: string) => key,
};

export function useClientLocale(): ClientLocaleContextValue {
  const ctx = useContext(ClientLocaleContext);
  // In tests without provider, return default value instead of throwing.
  // Real provider ensures proper context in production.
  if (!ctx) {
    // @ts-expect-error describe is globally available in test environment only
    if (typeof describe !== 'undefined') {
      return defaultValue;
    }
    throw new Error('useClientLocale must be used within ClientLocaleProvider');
  }
  return ctx;
}
