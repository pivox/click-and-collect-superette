import { beforeEach, describe, expect, it, vi } from 'vitest';
import { apiClient } from '@/lib/api';
import {
  addMerchantCatalogProduct,
  bulkUpdateMerchantProductAvailability,
  listMerchantCatalog,
  searchMerchantProductReferences,
  updateMerchantCatalogProduct,
} from '@/lib/services/merchant-catalog.service';

vi.mock('@/lib/api', () => ({
  apiClient: {
    get: vi.fn(),
    post: vi.fn(),
    patch: vi.fn(),
  },
}));

describe('merchant catalogue service', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('lists merchant catalogue products with filters', async () => {
    vi.mocked(apiClient.get).mockResolvedValue({
      data: { items: [], total: 0, page: 1, limit: 20 },
    });

    await listMerchantCatalog('store-1', {
      q: 'lait',
      availability: 'available',
      visibility: 'visible',
      category: 'lait',
      page: 2,
      limit: 10,
    });

    expect(apiClient.get).toHaveBeenCalledWith('/api/merchant/stores/store-1/catalog', {
      params: {
        q: 'lait',
        availability: 'available',
        visibility: 'visible',
        category: 'lait',
        page: 2,
        limit: 10,
      },
    });
  });

  it('omits empty catalogue filters', async () => {
    vi.mocked(apiClient.get).mockResolvedValue({
      data: { items: [], total: 0, page: 1, limit: 20 },
    });

    await listMerchantCatalog('store-1', {
      q: '',
      availability: 'all',
      visibility: 'all',
      category: undefined,
      page: 1,
      limit: 20,
    });

    expect(apiClient.get).toHaveBeenCalledWith('/api/merchant/stores/store-1/catalog', {
      params: {
        page: 1,
        limit: 20,
      },
    });
  });

  it('updates a merchant product', async () => {
    vi.mocked(apiClient.patch).mockResolvedValue({ data: null });

    await updateMerchantCatalogProduct('mp-1', {
      price_tnd: '1.700',
      is_available: false,
      is_visible: true,
      merchant_note: 'Rupture temporaire',
    });

    expect(apiClient.patch).toHaveBeenCalledWith('/api/merchant/catalog/mp-1', {
      price_tnd: '1.700',
      is_available: false,
      is_visible: true,
      merchant_note: 'Rupture temporaire',
    });
  });

  it('bulk updates availability for selected products', async () => {
    vi.mocked(apiClient.patch).mockResolvedValue({
      data: {
        updated_count: 2,
        is_available: false,
        merchant_note: 'Rupture',
        merchant_product_ids: ['mp-1', 'mp-2'],
      },
    });

    const result = await bulkUpdateMerchantProductAvailability('store-1', {
      merchant_product_ids: ['mp-1', 'mp-2'],
      is_available: false,
      merchant_note: 'Rupture',
    });

    expect(apiClient.patch).toHaveBeenCalledWith(
      '/api/merchant/stores/store-1/products/bulk-availability',
      {
        merchant_product_ids: ['mp-1', 'mp-2'],
        is_available: false,
        merchant_note: 'Rupture',
      },
    );
    expect(result.updated_count).toBe(2);
  });

  it('searches product references in the store context', async () => {
    vi.mocked(apiClient.get).mockResolvedValue({
      data: { items: [], total: 0, page: 1, limit: 20 },
    });

    await searchMerchantProductReferences('store-1', {
      q: 'vitalait',
      categorySlug: 'lait',
      page: 1,
      limit: 20,
    });

    expect(apiClient.get).toHaveBeenCalledWith(
      '/api/merchant/stores/store-1/product-references',
      {
        params: {
          q: 'vitalait',
          categorySlug: 'lait',
          page: 1,
          limit: 20,
        },
      },
    );
  });

  it('adds a product reference to the merchant catalogue', async () => {
    vi.mocked(apiClient.post).mockResolvedValue({ data: null });

    await addMerchantCatalogProduct('store-1', {
      product_reference_id: 'ref-1',
      price_tnd: '1.650',
      is_available: true,
      is_visible: true,
      merchant_note: null,
    });

    expect(apiClient.post).toHaveBeenCalledWith('/api/merchant/stores/store-1/catalog', {
      product_reference_id: 'ref-1',
      price_tnd: '1.650',
      is_available: true,
      is_visible: true,
      merchant_note: null,
    });
  });
});
