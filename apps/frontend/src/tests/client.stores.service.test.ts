import { beforeEach, describe, expect, it, vi } from 'vitest';
import { apiClient } from '@/lib/api';
import { recordStoreVisit } from '@/lib/services/stores.service';

vi.mock('@/lib/api', () => ({
  apiClient: {
    post: vi.fn(),
  },
}));

vi.mock('@/lib/services', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@/lib/services')>();
  return { ...actual, USE_MOCKS: false };
});

describe('client stores service', () => {
  beforeEach(() => {
    localStorage.clear();
    vi.clearAllMocks();
  });

  it('enregistre une visite QR pour un client connecté', async () => {
    localStorage.setItem('jwt_token', 'client-token');
    vi.mocked(apiClient.post).mockResolvedValue({ data: {} });

    await recordStoreVisit('store-1');

    expect(apiClient.post).toHaveBeenCalledWith(
      '/api/me/stores/store-1/visit',
      { source: 'qr_code' },
      { skipAuthRedirect: true },
    );
  });

  it("n'appelle pas l'API en mode visiteur", async () => {
    await recordStoreVisit('store-1');

    expect(apiClient.post).not.toHaveBeenCalled();
  });
});
