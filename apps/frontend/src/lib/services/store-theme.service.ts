import { apiClient } from '@/lib/api';
import { USE_MOCKS, mockDelay } from './index';
import type { StoreTheme } from '@/types';

interface StoreThemeApiResponse {
  '--color-primary'?: string;
  '--color-secondary'?: string;
  '--color-accent'?: string;
  '--color-text'?: string;
  '--color-background'?: string;
  '--font-family'?: string;
  '--font-size-base'?: string;
  [key: string]: string | undefined;
}

export async function getStoreTheme(shopId: string): Promise<StoreTheme | null> {
  if (USE_MOCKS) return mockDelay(null);
  try {
    const { data } = await apiClient.get<StoreThemeApiResponse>(
      `/api/stores/${shopId}/theme`,
      {
        skipAuthRedirect: true,
        params: { _theme_ts: Date.now() },
      },
    );
    const primary = data['--color-primary'];
    const secondary = data['--color-secondary'];
    if (!primary && !secondary) return null;
    return {
      primaryColor: primary ?? '#1f7a4d',
      secondaryColor: secondary ?? '#ffcf5a',
      accentColor: data['--color-accent'] ?? '#7ed957',
      textColor: data['--color-text'] ?? '#172018',
      backgroundColor: data['--color-background'] ?? '#f6f7f2',
      fontFamily: data['--font-family'] ?? 'Inter',
      baseFontSize: data['--font-size-base'] ?? '16px',
    };
  } catch {
    return null;
  }
}
