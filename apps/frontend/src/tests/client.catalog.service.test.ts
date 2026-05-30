import { beforeEach, describe, expect, it, vi } from 'vitest';
import { apiClient } from '@/lib/api';
import { listCatalog } from '@/lib/services/catalog.service';

vi.mock('@/lib/api', () => ({
  apiClient: { get: vi.fn() },
}));

vi.mock('@/lib/services', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@/lib/services')>();
  return { ...actual, USE_MOCKS: false };
});

const RAW_CATALOG = {
  items: [
    {
      id: 'mp-1',
      product_reference_id: 'ref-1',
      local_product_id: null,
      name_fr: 'Coca Cola',
      name_ar: null,
      brand: 'Coca Cola',
      category: 'Alimentaire',
      category_ar: null,
      category_slug: 'alimentaire',
      volume: '1.500',
      unit: 'litre',
      price_tnd: '2.411',
      is_available: true,
    },
  ],
};

describe('client catalog service', () => {
  beforeEach(() => vi.clearAllMocks());

  it('transmet le slug catégorie backend et conserve son libellé', async () => {
    vi.mocked(apiClient.get).mockResolvedValue({ data: RAW_CATALOG });

    const products = await listCatalog({
      shopId: 'store-1',
      category: 'alimentaire',
      search: 'coca',
    });

    expect(apiClient.get).toHaveBeenCalledWith('/api/stores/store-1/catalog', {
      params: { category: 'alimentaire', query: 'coca' },
    });
    expect(products[0]).toMatchObject({
      category: 'alimentaire',
      categoryNameFr: 'Alimentaire',
    });
  });
});
