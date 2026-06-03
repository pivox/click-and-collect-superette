import { useEffect } from 'react';
import { getStoreTheme } from '@/lib/services';

// --primary-dark is derived from --primary via color-mix() in globals.css,
// so only --primary and --secondary need to be injected / cleaned up.
export function useStoreTheme(shopId: string): void {
  useEffect(() => {
    let cancelled = false;

    void getStoreTheme(shopId).then((theme) => {
      if (cancelled || !theme) return;
      const root = document.documentElement;
      root.style.setProperty('--primary', theme.primaryColor);
      root.style.setProperty('--secondary', theme.secondaryColor);
      root.style.setProperty('--font-family', theme.fontFamily);
    });

    return () => {
      cancelled = true;
      const root = document.documentElement;
      root.style.removeProperty('--primary');
      root.style.removeProperty('--secondary');
      root.style.removeProperty('--font-family');
    };
  }, [shopId]);
}
