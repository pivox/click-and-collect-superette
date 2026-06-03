import { useEffect } from 'react';
import { getStoreTheme } from '@/lib/services';

// Derives --primary-dark by reducing HSL lightness by 15 percentage points.
function darkenHex(hex: string, amount = 0.15): string {
  const r = parseInt(hex.slice(1, 3), 16) / 255;
  const g = parseInt(hex.slice(3, 5), 16) / 255;
  const b = parseInt(hex.slice(5, 7), 16) / 255;

  const max = Math.max(r, g, b);
  const min = Math.min(r, g, b);
  const l = (max + min) / 2;
  let h = 0;
  let s = 0;

  if (max !== min) {
    const d = max - min;
    s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
    if (max === r) h = ((g - b) / d + (g < b ? 6 : 0)) / 6;
    else if (max === g) h = ((b - r) / d + 2) / 6;
    else h = ((r - g) / d + 4) / 6;
  }

  const newL = Math.max(0, l - amount);

  const hue2rgb = (p: number, q: number, t: number): number => {
    const tt = t < 0 ? t + 1 : t > 1 ? t - 1 : t;
    if (tt < 1 / 6) return p + (q - p) * 6 * tt;
    if (tt < 1 / 2) return q;
    if (tt < 2 / 3) return p + (q - p) * (2 / 3 - tt) * 6;
    return p;
  };

  let rOut: number;
  let gOut: number;
  let bOut: number;

  if (s === 0) {
    rOut = gOut = bOut = newL;
  } else {
    const q = newL < 0.5 ? newL * (1 + s) : newL + s - newL * s;
    const p = 2 * newL - q;
    rOut = hue2rgb(p, q, h + 1 / 3);
    gOut = hue2rgb(p, q, h);
    bOut = hue2rgb(p, q, h - 1 / 3);
  }

  const toHex = (n: number) => Math.round(n * 255).toString(16).padStart(2, '0');
  return `#${toHex(rOut)}${toHex(gOut)}${toHex(bOut)}`;
}

export function useStoreTheme(shopId: string): void {
  useEffect(() => {
    let cancelled = false;

    void getStoreTheme(shopId).then((theme) => {
      if (cancelled || !theme) return;
      const root = document.documentElement;
      root.style.setProperty('--primary', theme.primaryColor);
      root.style.setProperty('--primary-dark', darkenHex(theme.primaryColor));
      root.style.setProperty('--secondary', theme.secondaryColor);
    });

    return () => {
      cancelled = true;
      const root = document.documentElement;
      root.style.removeProperty('--primary');
      root.style.removeProperty('--primary-dark');
      root.style.removeProperty('--secondary');
    };
  }, [shopId]);
}
