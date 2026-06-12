import { beforeEach, describe, expect, it, vi } from 'vitest';
import { apiClient } from '@/lib/api';
import { getStoreSuggestions } from '@/lib/services/suggestions.service';

vi.mock('@/lib/api', () => ({
  apiClient: { get: vi.fn() },
}));

vi.mock('@/lib/services', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@/lib/services')>();
  return { ...actual, USE_MOCKS: false };
});

const RAW_ITEM = {
  id: 'mp-1',
  product_reference_id: 'ref-1',
  local_product_id: null,
  name_fr: 'Lait Vitalait',
  name_ar: null,
  brand: 'Vitalait',
  category: 'Laitages',
  category_ar: null,
  category_slug: 'laitages',
  volume: '1.000',
  unit: 'litre',
  price_tnd: '3.000',
  promotion_price_tnd: null,
  promotion_ends_on: null,
  promotion_active: false,
  effective_price_tnd: '3.000',
  is_available: true,
};

describe('client suggestions service', () => {
  beforeEach(() => vi.clearAllMocks());

  it('appelle le endpoint suggestions avec le seed kadhia et mappe les deux sections', async () => {
    vi.mocked(apiClient.get).mockResolvedValue({
      data: {
        store_id: 'store-1',
        frequently_bought_together: [RAW_ITEM],
        recently_ordered: [{ ...RAW_ITEM, id: 'mp-2' }],
      },
    });

    const result = await getStoreSuggestions('store-1', 'kadhia-1');

    expect(apiClient.get).toHaveBeenCalledWith('/api/me/stores/store-1/suggestions', {
      params: { kadhia_id: 'kadhia-1' },
      skipAuthRedirect: true,
    });
    expect(result.frequentlyBoughtTogether).toHaveLength(1);
    expect(result.frequentlyBoughtTogether[0]).toMatchObject({
      id: 'mp-1',
      nameFr: 'Lait Vitalait',
      priceTnd: '3.000',
      category: 'laitages',
      isAvailable: true,
    });
    expect(result.recentlyOrdered[0].id).toBe('mp-2');
  });

  it('omet kadhia_id quand absent et tolère des sections vides', async () => {
    vi.mocked(apiClient.get).mockResolvedValue({
      data: { store_id: 'store-1', frequently_bought_together: [], recently_ordered: [] },
    });

    const result = await getStoreSuggestions('store-1');

    expect(apiClient.get).toHaveBeenCalledWith('/api/me/stores/store-1/suggestions', {
      params: { kadhia_id: undefined },
      skipAuthRedirect: true,
    });
    expect(result.frequentlyBoughtTogether).toEqual([]);
    expect(result.recentlyOrdered).toEqual([]);
  });
});
