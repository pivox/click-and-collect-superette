import { apiClient } from '@/lib/api';
import type {
  Merchant,
  MerchantListResponse,
  CreateMerchantPayload,
  UpdateMerchantPayload,
} from '@/lib/types/admin/merchants.types';

export async function listMerchants(
  page = 1,
  limit = 20,
  search?: string,
): Promise<MerchantListResponse> {
  const { data } = await apiClient.get<MerchantListResponse>('/api/admin/merchants', {
    params: {
      page,
      limit,
      ...(search ? { search } : {}),
    },
  });
  return data;
}

export async function createMerchant(payload: CreateMerchantPayload): Promise<Merchant> {
  const { data } = await apiClient.post<Merchant>('/api/admin/merchants', payload);
  return data;
}

export async function updateMerchant(id: string, payload: UpdateMerchantPayload): Promise<Merchant> {
  const { data } = await apiClient.patch<Merchant>(`/api/admin/merchants/${id}`, payload);
  return data;
}

// Both suspend and activate are PATCH with input: false (body ignored by backend)
export async function suspendMerchant(id: string): Promise<void> {
  await apiClient.patch(`/api/admin/merchants/${id}/suspend`, {});
}

export async function activateMerchant(id: string): Promise<void> {
  await apiClient.patch(`/api/admin/merchants/${id}/activate`, {});
}
