import { beforeEach, describe, expect, it, vi } from 'vitest';
import { apiClient } from '@/lib/api';
import { getStoreTheme } from '@/lib/services/store-theme.service';

vi.mock('@/lib/api', () => ({
  apiClient: { get: vi.fn() },
}));

vi.mock('@/lib/services', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@/lib/services')>();
  return { ...actual, USE_MOCKS: false };
});

describe('getStoreTheme', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('revalide le cache HTTP pour appliquer rapidement le thème de la supérette active', async () => {
    vi.spyOn(Date, 'now').mockReturnValue(1234567890);
    vi.mocked(apiClient.get).mockResolvedValue({
      data: {
        '--color-primary': '#600e77',
        '--color-secondary': '#336da3',
        '--font-family': 'Roboto',
      },
    });

    const theme = await getStoreTheme('store-1');

    expect(apiClient.get).toHaveBeenCalledWith('/api/stores/store-1/theme', {
      skipAuthRedirect: true,
      params: { _theme_ts: 1234567890 },
    });
    expect(theme).toEqual({
      primaryColor: '#600e77',
      secondaryColor: '#336da3',
      fontFamily: 'Roboto',
    });
  });
});
