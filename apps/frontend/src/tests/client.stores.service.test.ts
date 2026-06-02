import { beforeEach, describe, expect, it, vi } from 'vitest';
import { apiClient } from '@/lib/api';
import {
  recordStoreVisit,
  listMyStores,
  removeStore,
  toggleFavorite,
} from '@/lib/services/stores.service';
import { resetMockMyShops } from '@/lib/mock/shops.mock';

vi.mock('@/lib/api', () => ({
  apiClient: {
    post: vi.fn(),
    get: vi.fn(),
    delete: vi.fn(),
    patch: vi.fn(),
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
    resetMockMyShops();
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

  it('listMyStores retourne la liste personnelle depuis l\'API', async () => {
    vi.mocked(apiClient.get).mockResolvedValue({
      data: [
        { store_id: 'abc', name: 'Aziza', slug: 'aziza', city: 'Tunis', is_active: true, is_favorite: true },
      ],
    });

    const result = await listMyStores();

    expect(apiClient.get).toHaveBeenCalledWith('/api/me/stores');
    expect(result).toHaveLength(1);
    expect(result[0].id).toBe('abc');
    expect(result[0].isFavorite).toBe(true);
  });

  it('removeStore appelle DELETE /api/me/stores/{shopId}', async () => {
    vi.mocked(apiClient.delete).mockResolvedValue({ data: null });

    await removeStore('store-xyz');

    expect(apiClient.delete).toHaveBeenCalledWith('/api/me/stores/store-xyz');
  });

  it('toggleFavorite appelle PATCH avec is_favorite', async () => {
    vi.mocked(apiClient.patch).mockResolvedValue({ data: {} });

    await toggleFavorite('store-xyz', true);

    expect(apiClient.patch).toHaveBeenCalledWith(
      '/api/me/stores/store-xyz/favorite',
      { is_favorite: true },
    );
  });
});
