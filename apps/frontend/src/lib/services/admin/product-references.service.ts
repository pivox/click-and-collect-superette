import { apiClient } from '@/lib/api';
import type {
  ProductReferenceListResponse,
  ProductReference,
  ProductReferenceFilters,
  CreateProductReferencePayload,
  UpdateProductReferencePayload,
} from '@/lib/types/admin/referentiel.types';

export async function listProductReferences(
  filters: ProductReferenceFilters = {},
): Promise<ProductReferenceListResponse> {
  const { data } = await apiClient.get<ProductReferenceListResponse>(
    '/api/admin/product-references',
    {
      params: {
        page: filters.page ?? 1,
        limit: filters.limit ?? 20,
        ...(filters.q ? { q: filters.q } : {}),
        ...(filters.brand ? { brand: filters.brand } : {}),
        ...(filters.category ? { category: filters.category } : {}),
        ...(filters.status ? { status: filters.status } : {}),
      },
    },
  );
  return data;
}

export async function createProductReference(
  payload: CreateProductReferencePayload,
): Promise<ProductReference> {
  const { data } = await apiClient.post<ProductReference>(
    '/api/admin/product-references',
    payload,
  );
  return data;
}

export async function updateProductReference(
  id: string,
  payload: UpdateProductReferencePayload,
): Promise<ProductReference> {
  const { data } = await apiClient.patch<ProductReference>(
    `/api/admin/product-references/${id}`,
    payload,
  );
  return data;
}

export async function archiveProductReference(id: string): Promise<ProductReference> {
  const { data } = await apiClient.patch<ProductReference>(
    `/api/admin/product-references/${id}/archive`,
    {},
  );
  return data;
}
