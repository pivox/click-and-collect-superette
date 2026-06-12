import { beforeEach, describe, expect, it, vi } from 'vitest';
import { apiClient } from '@/lib/api';
import {
  listFavoriteProducts,
  toggleProductFavorite,
} from '@/lib/services/product-favorites.service';

vi.mock('@/lib/api', () => ({
  apiClient: { get: vi.fn(), patch: vi.fn() },
}));

vi.mock('@/lib/services', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@/lib/services')>();
  return { ...actual, USE_MOCKS: false };
});

describe('client product favorites service', () => {
  beforeEach(() => vi.clearAllMocks());

  it('liste les favoris du magasin et mappe en ProductOffer', async () => {
    vi.mocked(apiClient.get).mockResolvedValue({
      data: {
        store_id: 'store-1',
        items: [
          {
            id: 'mp-1',
            product_reference_id: 'ref-1',
            local_product_id: null,
            name_fr: 'Harissa Sicam',
            name_ar: null,
            brand: 'Sicam',
            category: 'Épicerie',
            category_ar: null,
            category_slug: 'epicerie',
            volume: null,
            unit: 'piece',
            price_tnd: '1.500',
            is_available: false,
          },
        ],
        total: 1,
      },
    });

    const result = await listFavoriteProducts('store-1');

    expect(apiClient.get).toHaveBeenCalledWith('/api/me/stores/store-1/favorite-products', {
      skipAuthRedirect: true,
    });
    expect(result).toHaveLength(1);
    expect(result[0]).toMatchObject({
      id: 'mp-1',
      nameFr: 'Harissa Sicam',
      isAvailable: false,
    });
  });

  it('envoie le PATCH favori avec le bon payload', async () => {
    vi.mocked(apiClient.patch).mockResolvedValue({ data: {} });

    await toggleProductFavorite('mp-1', true);

    expect(apiClient.patch).toHaveBeenCalledWith('/api/me/products/mp-1/favorite', {
      is_favorite: true,
    });

    await toggleProductFavorite('mp-1', false);

    expect(apiClient.patch).toHaveBeenLastCalledWith('/api/me/products/mp-1/favorite', {
      is_favorite: false,
    });
  });
});
