import { apiClient } from '@/lib/api';
import type {
  CreateSlotPayload,
  MerchantPickupSlot,
  PatchSlotPayload,
} from '@/lib/types/merchant-slots.types';

export async function listMerchantSlots(storeId: string): Promise<MerchantPickupSlot[]> {
  const { data } = await apiClient.get<MerchantPickupSlot[]>(
    `/api/merchant/stores/${storeId}/pickup-slots`,
  );
  return data;
}

export async function createMerchantSlot(
  storeId: string,
  payload: CreateSlotPayload,
): Promise<void> {
  await apiClient.post(`/api/merchant/stores/${storeId}/pickup-slots`, payload);
}

export async function patchMerchantSlot(
  storeId: string,
  slotId: string,
  payload: PatchSlotPayload,
): Promise<void> {
  await apiClient.patch(
    `/api/merchant/stores/${storeId}/pickup-slots/${slotId}`,
    payload,
  );
}

export async function deleteMerchantSlot(storeId: string, slotId: string): Promise<void> {
  await apiClient.delete(`/api/merchant/stores/${storeId}/pickup-slots/${slotId}`);
}
