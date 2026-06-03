import { apiClient } from '@/lib/api';
import { USE_MOCKS, mockDelay } from './index';
import type { StoreTheme } from '@/types';

interface StoreThemeApiResponse {
  '--color-primary'?: string;
  '--color-secondary'?: string;
  '--font-family'?: string;
  [key: string]: string | undefined;
}

export async function getStoreTheme(shopId: string): Promise<StoreTheme | null> {
  if (USE_MOCKS) return mockDelay(null);
  try {
    const { data } = await apiClient.get<StoreThemeApiResponse>(
      `/api/stores/${shopId}/theme`,
      { skipAuthRedirect: true },
    );
    const primary = data['--color-primary'];
    const secondary = data['--color-secondary'];
    if (!primary && !secondary) return null;
    return {
      primaryColor: primary ?? '#1f7a4d',
      secondaryColor: secondary ?? '#ffcf5a',
      fontFamily: data['--font-family'] ?? 'Inter',
    };
  } catch {
    return null;
  }
}
