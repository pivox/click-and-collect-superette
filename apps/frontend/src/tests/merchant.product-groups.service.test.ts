import { beforeEach, describe, expect, it, vi } from 'vitest';
import { apiClient } from '@/lib/api';
import {
  getMerchantProductGroup,
  listMerchantProductGroups,
} from '@/lib/services/merchant-catalog.service';

vi.mock('@/lib/api', () => ({
  apiClient: {
    get: vi.fn(),
  },
}));

describe('merchant product groups service', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('lists merchant product groups through the store-scoped endpoint', async () => {
    vi.mocked(apiClient.get).mockResolvedValue({
      data: {
        id: 'store-1',
        items: [
          {
            id: 'group-1',
            name_fr: 'Premières nécessités',
            name_ar: null,
            slug: 'premieres-necessites',
            description_fr: 'Base quotidienne',
            description_ar: null,
            market_country: 'TN',
            icon: 'shopping-basket',
            sort_order: 10,
            items_count: 2,
          },
        ],
      },
    });

    const result = await listMerchantProductGroups('store-1');

    expect(apiClient.get).toHaveBeenCalledWith('/api/merchant/stores/store-1/product-groups');
    expect(result.items[0].name_fr).toBe('Premières nécessités');
  });

  it('gets a merchant product group detail through the store-scoped endpoint', async () => {
    vi.mocked(apiClient.get).mockResolvedValue({
      data: {
        id: 'group-1',
        name_fr: 'Premières nécessités',
        name_ar: null,
        slug: 'premieres-necessites',
        description_fr: 'Base quotidienne',
        description_ar: null,
        market_country: 'TN',
        icon: 'shopping-basket',
        sort_order: 10,
        items_count: 1,
        items: [
          {
            id: 'item-1',
            sort_order: 10,
            importance: 'required',
            status: 'new',
            product_reference: {
              id: 'ref-1',
              name_fr: 'Lait UHT',
              name_ar: null,
              brand_name: 'Vitalait',
              category_name_fr: 'Lait',
              unit: 'litre',
              volume: '1.000',
              status: 'approved',
            },
          },
        ],
      },
    });

    const result = await getMerchantProductGroup('store-1', 'group-1');

    expect(apiClient.get).toHaveBeenCalledWith(
      '/api/merchant/stores/store-1/product-groups/group-1',
    );
    expect(result.items[0].status).toBe('new');
  });
});
