import { beforeEach, describe, expect, it, vi } from 'vitest';
import { apiClient } from '@/lib/api';
import {
  addMerchantCatalogProduct,
  bulkUpdateMerchantProductAvailability,
  filterMerchantCatalogProducts,
  listMerchantCatalog,
  searchMerchantProductReferences,
  updateMerchantCatalogProduct,
} from '@/lib/services/merchant-catalog.service';
import type {
  MerchantCatalogProduct,
  UpdateMerchantCatalogProductPayload,
} from '@/lib/types/merchant-catalog.types';

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

  it('lists merchant catalogue products without unsupported backend filters', async () => {
    vi.mocked(apiClient.get).mockResolvedValue({
      data: [
        {
          id: 'mp-1',
          product_reference_id: 'ref-1',
          name_fr: 'Lait Vitalait 1L',
          brand: 'Vitalait',
          category: 'lait',
          volume: '1',
          unit: 'litre',
          price_tnd: '1.700',
          is_available: true,
          is_visible: true,
          merchant_note: null,
        },
      ],
    });

    const products = await listMerchantCatalog('store-1', {
      q: 'lait',
      availability: 'available',
      visibility: 'visible',
      category: 'lait',
      page: 2,
      limit: 10,
    });

    expect(apiClient.get).toHaveBeenCalledWith('/api/merchant/stores/store-1/catalog');
    expect(products).toEqual([
      expect.objectContaining({
        id: 'mp-1',
        product_reference_id: 'ref-1',
        brand: 'Vitalait',
      }),
    ]);
  });

  it('filters merchant catalogue products locally', () => {
    const products: MerchantCatalogProduct[] = [
      {
        id: 'mp-1',
        product_reference_id: 'ref-1',
        name_fr: 'Lait Vitalait 1L',
        brand: 'Vitalait',
        category: 'Boissons',
        merchant_category_name: 'Lait',
        volume: '1',
        unit: 'litre',
        price_tnd: '1.700',
        is_available: true,
        is_visible: true,
        merchant_note: 'Rayon frais',
      },
      {
        id: 'mp-2',
        product_reference_id: 'ref-2',
        name_fr: 'Couscous fin',
        brand: 'Rose Blanche',
        category: 'Epicerie',
        volume: '1',
        unit: 'kg',
        price_tnd: '2.400',
        is_available: false,
        is_visible: true,
        merchant_note: 'Rupture fournisseur',
      },
      {
        id: 'mp-3',
        product_reference_id: 'ref-3',
        name_fr: 'Thon entier',
        brand: 'Sidi Daoud',
        category: 'Conserves',
        volume: '160',
        unit: 'g',
        price_tnd: '4.900',
        is_available: true,
        is_visible: false,
        merchant_note: null,
      },
    ];

    expect(filterMerchantCatalogProducts(products, { q: 'vitalait' })).toEqual([
      products[0],
    ]);
    expect(filterMerchantCatalogProducts(products, { q: 'rupture' })).toEqual([
      products[1],
    ]);
    expect(filterMerchantCatalogProducts(products, { availability: 'unavailable' })).toEqual([
      products[1],
    ]);
    expect(filterMerchantCatalogProducts(products, { visibility: 'hidden' })).toEqual([
      products[2],
    ]);
    expect(filterMerchantCatalogProducts(products, { category: 'Lait' })).toEqual([
      products[0],
    ]);
  });

  it('updates a merchant product', async () => {
    vi.mocked(apiClient.patch).mockResolvedValue({ data: null });

    const payload = {
      price_tnd: '1.700',
      is_available: false,
      is_visible: true,
      merchant_note: 'Rupture temporaire',
    } satisfies UpdateMerchantCatalogProductPayload;

    await updateMerchantCatalogProduct('mp-1', payload);

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
