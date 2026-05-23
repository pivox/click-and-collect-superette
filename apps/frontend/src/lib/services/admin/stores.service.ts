import { apiClient } from '@/lib/api';
import type {
  Store,
  StoreListResponse,
  StoreFilters,
  CreateStorePayload,
  UpdateStorePayload,
} from '@/lib/types/admin/stores.types';

export async function listStores(filters: StoreFilters = {}): Promise<StoreListResponse> {
  const { data } = await apiClient.get<StoreListResponse>('/api/admin/stores', {
    params: {
      page: filters.page ?? 1,
      limit: filters.limit ?? 20,
      ...(filters.merchant ? { merchant: filters.merchant } : {}),
      ...(filters.status ? { status: filters.status } : {}),
    },
  });
  return data;
}

export async function createStore(payload: CreateStorePayload): Promise<Store> {
  const { data } = await apiClient.post<Store>('/api/admin/stores', payload);
  return data;
}

export async function updateStore(id: string, payload: UpdateStorePayload): Promise<Store> {
  const { data } = await apiClient.patch<Store>(`/api/admin/stores/${id}`, payload);
  return data;
}

export async function archiveStore(id: string): Promise<void> {
  await apiClient.patch(`/api/admin/stores/${id}/archive`, {});
}
