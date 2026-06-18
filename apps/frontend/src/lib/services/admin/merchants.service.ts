import { apiClient } from '@/lib/api';
import type {
  Merchant,
  MerchantListResponse,
  MerchantTemporaryPasswordResponse,
  MerchantCrmStatus,
  CreateMerchantPayload,
  UpdateMerchantPayload,
  UpdateMerchantCrmPayload,
  CreateMerchantCrmContactPayload,
} from '@/lib/types/admin/merchants.types';

export async function listMerchants(
  page = 1,
  limit = 20,
  search?: string,
  crmStatus?: MerchantCrmStatus,
  crmOwner?: string,
): Promise<MerchantListResponse> {
  const { data } = await apiClient.get<MerchantListResponse>('/api/admin/merchants', {
    params: {
      page,
      limit,
      ...(search ? { search } : {}),
      ...(crmStatus ? { crm_status: crmStatus } : {}),
      ...(crmOwner ? { crm_owner: crmOwner } : {}),
    },
  });
  return data;
}

export async function getMerchant(id: string): Promise<Merchant> {
  const { data } = await apiClient.get<Merchant>(`/api/admin/merchants/${id}`);
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

export async function resetMerchantTemporaryPassword(id: string): Promise<MerchantTemporaryPasswordResponse> {
  const { data } = await apiClient.post<MerchantTemporaryPasswordResponse>(
    `/api/admin/merchants/${id}/temporary-password`,
    {},
  );
  return data;
}

// CRM mutations return the full merchant detail (crm block with contacts included)
export async function updateMerchantCrm(
  id: string,
  payload: UpdateMerchantCrmPayload,
): Promise<Merchant> {
  const { data } = await apiClient.patch<Merchant>(`/api/admin/merchants/${id}/crm`, payload);
  return data;
}

export async function addMerchantCrmContact(
  id: string,
  payload: CreateMerchantCrmContactPayload,
): Promise<Merchant> {
  const { data } = await apiClient.post<Merchant>(`/api/admin/merchants/${id}/crm/contacts`, payload);
  return data;
}

// Both suspend and activate are PATCH with input: false (body ignored by backend)
export async function suspendMerchant(id: string): Promise<void> {
  await apiClient.patch(`/api/admin/merchants/${id}/suspend`, {});
}

export async function activateMerchant(id: string): Promise<void> {
  await apiClient.patch(`/api/admin/merchants/${id}/activate`, {});
}
