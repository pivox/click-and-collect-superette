import { useEffect } from 'react';
import { getStoreTheme } from '@/lib/services';

const THEME_PROPERTIES = [
  '--primary',
  '--secondary',
  '--accent',
  '--ink',
  '--bg',
  '--font-family',
  '--font-size-base',
] as const;

export function useStoreTheme(shopId: string | null): void {
  useEffect(() => {
    if (!shopId) return;
    let cancelled = false;

    void getStoreTheme(shopId).then((theme) => {
      if (cancelled || !theme) return;
      const root = document.documentElement;
      root.style.setProperty('--primary', theme.primaryColor);
      root.style.setProperty('--secondary', theme.secondaryColor);
      root.style.setProperty('--accent', theme.accentColor);
      root.style.setProperty('--ink', theme.textColor);
      root.style.setProperty('--bg', theme.backgroundColor);
      root.style.setProperty('--font-family', theme.fontFamily);
      root.style.setProperty('--font-size-base', theme.baseFontSize);
    });

    return () => {
      cancelled = true;
      const root = document.documentElement;
      THEME_PROPERTIES.forEach((property) => root.style.removeProperty(property));
    };
  }, [shopId]);
}
