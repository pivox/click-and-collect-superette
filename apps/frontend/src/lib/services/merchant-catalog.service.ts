import { apiClient } from '@/lib/api';
import type {
  AddMerchantCatalogProductPayload,
  MerchantBulkAvailabilityPayload,
  MerchantBulkAvailabilityResult,
  MerchantCatalogList,
  MerchantCatalogListOptions,
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

export async function listMerchantCatalog(
  storeId: string,
  options: MerchantCatalogListOptions = {},
): Promise<MerchantCatalogList> {
  const { data } = await apiClient.get<MerchantCatalogList>(
    `/api/merchant/stores/${storeId}/catalog`,
    {
      params: cleanFilterParams({
        q: options.q,
        availability: options.availability,
        visibility: options.visibility,
        category: options.category,
        page: options.page,
        limit: options.limit,
      }),
    },
  );

  return data;
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
