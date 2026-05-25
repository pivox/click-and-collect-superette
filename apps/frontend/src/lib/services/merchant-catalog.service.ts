import { apiClient } from '@/lib/api';
import type {
  AddMerchantCatalogProductPayload,
  CreateMerchantCategoryPayload,
  CreateMerchantLocalProductPayload,
  MerchantBulkAvailabilityPayload,
  MerchantBulkAvailabilityResult,
  MerchantCategory,
  MerchantCatalogListOptions,
  MerchantCatalogProduct,
  MerchantLocalProductOutput,
  MerchantProductReferenceSearchOptions,
  MerchantProductReferenceSearchResult,
  UpdateMerchantCatalogProductPayload,
} from '@/lib/types/merchant-catalog.types';

function cleanFilterParams<T extends Record<string, unknown>>(params: T): Partial<T> {
  return Object.fromEntries(
    Object.entries(params).filter(([, value]) => {
      return value !== undefined && value !== '' && value !== 'all';
    }),
  ) as Partial<T>;
}

function normalizeFilterValue(value: string | null | undefined): string {
  return value?.trim().toLowerCase() ?? '';
}

export async function listMerchantCatalog(
  storeId: string,
): Promise<MerchantCatalogProduct[]> {
  const { data } = await apiClient.get<MerchantCatalogProduct[]>(
    `/api/merchant/stores/${storeId}/catalog`,
  );

  return data;
}

export async function listMerchantCategories(storeId: string): Promise<MerchantCategory[]> {
  const { data } = await apiClient.get<MerchantCategory[]>(
    `/api/merchant/stores/${storeId}/categories`,
  );

  return data;
}

export async function createMerchantCategory(
  storeId: string,
  payload: CreateMerchantCategoryPayload,
): Promise<MerchantCategory> {
  const { data } = await apiClient.post<MerchantCategory>(
    `/api/merchant/stores/${storeId}/categories`,
    payload,
  );

  return data;
}

export function filterMerchantCatalogProducts(
  products: MerchantCatalogProduct[],
  options: MerchantCatalogListOptions = {},
): MerchantCatalogProduct[] {
  const query = normalizeFilterValue(options.q);
  const category = normalizeFilterValue(options.category);

  return products.filter((product) => {
    if (query) {
      const searchableFields = [
        product.name_fr,
        product.brand,
        product.category,
        product.merchant_category_name,
        product.merchant_note,
      ];
      const matchesQuery = searchableFields.some((value) => {
        return normalizeFilterValue(value).includes(query);
      });

      if (!matchesQuery) {
        return false;
      }
    }

    if (options.availability === 'available' && !product.is_available) {
      return false;
    }

    if (options.availability === 'unavailable' && product.is_available) {
      return false;
    }

    if (options.visibility === 'visible' && !product.is_visible) {
      return false;
    }

    if (options.visibility === 'hidden' && product.is_visible) {
      return false;
    }

    if (category) {
      const productCategory = normalizeFilterValue(
        product.merchant_category_name ?? product.category,
      );
      if (productCategory !== category) {
        return false;
      }
    }

    return true;
  });
}

export async function updateMerchantCatalogProduct(
  merchantProductId: string,
  payload: UpdateMerchantCatalogProductPayload,
): Promise<void> {
  await apiClient.patch(`/api/merchant/catalog/${merchantProductId}`, payload);
}

export async function bulkUpdateMerchantProductAvailability(
  storeId: string,
  payload: MerchantBulkAvailabilityPayload,
): Promise<MerchantBulkAvailabilityResult> {
  const { data } = await apiClient.patch<MerchantBulkAvailabilityResult>(
    `/api/merchant/stores/${storeId}/products/bulk-availability`,
    payload,
  );

  return data;
}

export async function searchMerchantProductReferences(
  storeId: string,
  options: MerchantProductReferenceSearchOptions = {},
): Promise<MerchantProductReferenceSearchResult> {
  const { data } = await apiClient.get<MerchantProductReferenceSearchResult>(
    `/api/merchant/stores/${storeId}/product-references`,
    {
      params: cleanFilterParams({
        q: options.q,
        brandId: options.brandId,
        categorySlug: options.categorySlug,
        page: options.page,
        limit: options.limit,
      }),
    },
  );

  return data;
}

export async function addMerchantCatalogProduct(
  storeId: string,
  payload: AddMerchantCatalogProductPayload,
): Promise<void> {
  await apiClient.post(`/api/merchant/stores/${storeId}/catalog`, payload);
}

export async function createMerchantLocalProduct(
  storeId: string,
  payload: CreateMerchantLocalProductPayload,
): Promise<MerchantLocalProductOutput> {
  const { data } = await apiClient.post<MerchantLocalProductOutput>(
    `/api/merchant/stores/${storeId}/local-products`,
    payload,
  );

  return data;
}
