import { apiClient } from '@/lib/api';
import type {
  Store,
  StoreListResponse,
  StoreFilters,
  CreateStorePayload,
  UpdateStorePayload,
  StoreQrCode,
} from '@/lib/types/admin/stores.types';

export async function listStores(filters: StoreFilters = {}): Promise<StoreListResponse> {
  const params: Record<string, unknown> = {
    page: filters.page ?? 1,
    limit: filters.limit ?? 20,
  };
  if (filters.isActive !== undefined) {
    params.is_active = filters.isActive;
  }
  const { data } = await apiClient.get<StoreListResponse>('/api/admin/stores', { params });
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

export async function activateStore(id: string): Promise<void> {
  await apiClient.patch(`/api/admin/stores/${id}/activate`, {});
}

export async function deactivateStore(id: string): Promise<void> {
  await apiClient.patch(`/api/admin/stores/${id}/deactivate`, {});
}

export async function getStoreQrCode(id: string): Promise<StoreQrCode> {
  const { data } = await apiClient.get<StoreQrCode>(`/api/admin/stores/${id}/qr-code`);
  return data;
}

export async function regenerateStoreQrCode(id: string): Promise<StoreQrCode> {
  const { data } = await apiClient.post<StoreQrCode>(
    `/api/admin/stores/${id}/regenerate-qr`,
    {},
  );
  return data;
}
