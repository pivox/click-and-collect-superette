import { apiClient } from '@/lib/api';
import { USE_MOCKS, mockDelay } from './index';
import type {
  AddMerchantCatalogProductPayload,
  BulkLocalProductCreatedOutput,
  CreateBulkLocalProductPayload,
  CreateMerchantCategoryPayload,
  CreateMerchantLocalProductPayload,
  CreateProductProposalPayload,
  GlobalBrand,
  GlobalCategory,
  MerchantBulkAvailabilityPayload,
  MerchantBulkAvailabilityResult,
  MerchantCategory,
  MerchantCatalogListOptions,
  MerchantCatalogListResult,
  MerchantCatalogCsvImportResult,
  MerchantCatalogPhotoImportCommitPayload,
  MerchantCatalogPhotoImportPreviewResult,
  MerchantCatalogPhotoImportSourceType,
  MerchantCatalogProduct,
  MerchantProductGroup,
  MerchantProductGroupListResponse,
  MerchantLocalProductOutput,
  MerchantProductPriceHistoryResult,
  MerchantProductReferenceSearchOptions,
  MerchantProductReferenceSearchResult,
  UpdateMerchantCatalogProductPayload,
} from '@/lib/types/merchant-catalog.types';

const MOCK_PRICE_HISTORY_ITEMS: MerchantProductPriceHistoryResult['priceHistory'] = [
  {
    oldPrice: '2.200',
    newPrice: '2.500',
    currency: 'TND',
    changeType: 'manual_update',
    source: 'merchant_dashboard',
    reason: 'Ajustement prix fournisseur',
    changedByUserId: null,
    changedAt: '2026-05-15T10:30:00Z',
  },
  {
    oldPrice: '2.000',
    newPrice: '2.200',
    currency: 'TND',
    changeType: 'manual_update',
    source: 'merchant_dashboard',
    reason: null,
    changedByUserId: null,
    changedAt: '2026-04-01T09:00:00Z',
  },
  {
    oldPrice: null,
    newPrice: '2.000',
    currency: 'TND',
    changeType: 'initial',
    source: 'merchant_dashboard',
    reason: null,
    changedByUserId: null,
    changedAt: '2026-02-10T08:00:00Z',
  },
];

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
  options: MerchantCatalogListOptions & { page?: number; limit?: number } = {},
): Promise<MerchantCatalogListResult> {
  const { data } = await apiClient.get<MerchantCatalogListResult>(
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

export async function listMerchantCategories(storeId: string): Promise<MerchantCategory[]> {
  const { data } = await apiClient.get<MerchantCategory[]>(
    `/api/merchant/stores/${storeId}/categories`,
  );

  return data;
}

export async function listMerchantProductGroups(
  storeId: string,
): Promise<MerchantProductGroupListResponse> {
  const { data } = await apiClient.get<MerchantProductGroupListResponse>(
    `/api/merchant/stores/${storeId}/product-groups`,
  );

  return data;
}

export async function getMerchantProductGroup(
  storeId: string,
  groupId: string,
): Promise<MerchantProductGroup> {
  const { data } = await apiClient.get<MerchantProductGroup>(
    `/api/merchant/stores/${storeId}/product-groups/${groupId}`,
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
        barcode: options.barcode,
        brandId: options.brandId,
        categorySlug: options.categorySlug,
        page: options.page,
        limit: options.limit,
      }),
    },
  );

  return data;
}

const CATALOG_CSV_TEMPLATE_HEADER = [
  'name_fr',
  'brand',
  'volume',
  'unit',
  'price_tnd',
  'is_available',
  'is_visible',
  'barcode',
  'category',
  'name_ar',
  'variant_fr',
  'merchant_note',
  'pack_quantity',
].join(',');

const CATALOG_CSV_TEMPLATE_SAMPLE = [
  'Lait demi-écrémé',
  'Vitalait',
  '1',
  'litre',
  '1.650',
  'true',
  'true',
  '6191234567890',
  'Lait & produits laitiers',
  'حليب نصف دسم',
  'Demi-écrémé',
  '',
  '1',
].join(',');

export function buildMerchantCatalogCsvTemplate(): string {
  return [CATALOG_CSV_TEMPLATE_HEADER, CATALOG_CSV_TEMPLATE_SAMPLE].join('\n');
}

export function downloadMerchantCatalogCsvTemplate(): void {
  const blob = new Blob([buildMerchantCatalogCsvTemplate()], {
    type: 'text/csv;charset=utf-8',
  });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = 'modele-catalogue-kadhia.csv';
  a.click();
  URL.revokeObjectURL(url);
}

export async function importMerchantCatalogCsv(
  storeId: string,
  csv: string,
): Promise<MerchantCatalogCsvImportResult> {
  const { data } = await apiClient.post<MerchantCatalogCsvImportResult>(
    `/api/merchant/stores/${storeId}/catalog/import-csv`,
    csv,
    {
      headers: {
        'Content-Type': 'text/csv; charset=utf-8',
        Accept: 'application/json',
      },
    },
  );

  return data;
}

export async function previewMerchantCatalogPhotoImport(
  storeId: string,
  photo: File,
  sourceType: MerchantCatalogPhotoImportSourceType,
): Promise<MerchantCatalogPhotoImportPreviewResult> {
  const formData = new FormData();
  formData.append('photo', photo);
  formData.append('source_type', sourceType);

  const { data } = await apiClient.post<MerchantCatalogPhotoImportPreviewResult>(
    `/api/merchant/stores/${storeId}/catalog/photo-import/preview`,
    formData,
    { headers: { Accept: 'application/json', 'Content-Type': undefined } },
  );

  return data;
}

export async function commitMerchantCatalogPhotoImport(
  storeId: string,
  payload: MerchantCatalogPhotoImportCommitPayload,
): Promise<MerchantCatalogCsvImportResult> {
  const { data } = await apiClient.post<MerchantCatalogCsvImportResult>(
    `/api/merchant/stores/${storeId}/catalog/photo-import/commit`,
    payload,
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

export async function createBulkMerchantLocalProducts(
  storeId: string,
  payload: CreateBulkLocalProductPayload,
): Promise<BulkLocalProductCreatedOutput> {
  const { data } = await apiClient.post<BulkLocalProductCreatedOutput>(
    `/api/merchant/stores/${storeId}/local-products/bulk`,
    payload,
  );

  return data;
}

export async function createProductProposal(
  storeId: string,
  payload: CreateProductProposalPayload,
): Promise<void> {
  await apiClient.post(`/api/merchant/stores/${storeId}/product-proposals`, payload);
}

export async function fetchGlobalCategories(): Promise<GlobalCategory[]> {
  const { data } = await apiClient.get<GlobalCategory[]>('/api/categories');

  return data;
}

export async function fetchGlobalBrands(): Promise<GlobalBrand[]> {
  const { data } = await apiClient.get<GlobalBrand[]>('/api/brands');

  return data;
}

export async function getMerchantProductPriceHistory(
  merchantProductId: string,
): Promise<MerchantProductPriceHistoryResult> {
  if (USE_MOCKS) {
    return mockDelay({
      merchantProductId,
      currentPrice: '2.500',
      currency: 'TND',
      priceHistory: MOCK_PRICE_HISTORY_ITEMS,
    });
  }

  const { data } = await apiClient.get<MerchantProductPriceHistoryResult>(
    `/api/merchant/products/${merchantProductId}/price-history`,
  );

  return data;
}
