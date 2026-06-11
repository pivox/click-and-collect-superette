import { apiClient } from '@/lib/api';
import type {
  CreateProductGroupItemPayload,
  CreateProductGroupPayload,
  ProductGroup,
  ProductGroupFilters,
  ProductGroupItem,
  ProductGroupListResponse,
  UpdateProductGroupItemPayload,
  UpdateProductGroupPayload,
} from '@/lib/types/admin/referentiel.types';

export async function listProductGroups(
  filters: ProductGroupFilters = {},
): Promise<ProductGroupListResponse> {
  const { data } = await apiClient.get<ProductGroupListResponse>(
    '/api/admin/product-groups',
    {
      params: {
        page: filters.page ?? 1,
        limit: filters.limit ?? 20,
        ...(filters.q ? { q: filters.q } : {}),
        ...(filters.status ? { status: filters.status } : {}),
        ...(filters.marketCountry ? { market_country: filters.marketCountry } : {}),
      },
    },
  );
  return data;
}

export async function getProductGroup(id: string): Promise<ProductGroup> {
  const { data } = await apiClient.get<ProductGroup>(`/api/admin/product-groups/${id}`);
  return data;
}

export async function createProductGroup(
  payload: CreateProductGroupPayload,
): Promise<ProductGroup> {
  const { data } = await apiClient.post<ProductGroup>('/api/admin/product-groups', payload);
  return data;
}

export async function updateProductGroup(
  id: string,
  payload: UpdateProductGroupPayload,
): Promise<ProductGroup> {
  const { data } = await apiClient.patch<ProductGroup>(`/api/admin/product-groups/${id}`, payload);
  return data;
}

export async function publishProductGroup(id: string): Promise<ProductGroup> {
  const { data } = await apiClient.patch<ProductGroup>(`/api/admin/product-groups/${id}/publish`, {});
  return data;
}

export async function archiveProductGroup(id: string): Promise<ProductGroup> {
  const { data } = await apiClient.patch<ProductGroup>(`/api/admin/product-groups/${id}/archive`, {});
  return data;
}

export async function addProductGroupItem(
  groupId: string,
  payload: CreateProductGroupItemPayload,
): Promise<ProductGroupItem> {
  const { data } = await apiClient.post<ProductGroupItem>(
    `/api/admin/product-groups/${groupId}/items`,
    payload,
  );
  return data;
}

export async function updateProductGroupItem(
  groupId: string,
  itemId: string,
  payload: UpdateProductGroupItemPayload,
): Promise<ProductGroupItem> {
  const { data } = await apiClient.patch<ProductGroupItem>(
    `/api/admin/product-groups/${groupId}/items/${itemId}`,
    payload,
  );
  return data;
}

export async function deleteProductGroupItem(groupId: string, itemId: string): Promise<void> {
  await apiClient.delete(`/api/admin/product-groups/${groupId}/items/${itemId}`);
}
