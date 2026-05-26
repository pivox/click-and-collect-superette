import { apiClient } from '@/lib/api';
import type {
  CreateSlotRulePayload,
  GenerateSlotsResult,
  MerchantPickupSlotRule,
  MerchantPickupSlotRuleCollection,
  PatchSlotRulePayload,
} from '@/lib/types/merchant-slots.types';

export async function listMerchantSlotRules(
  storeId: string,
): Promise<MerchantPickupSlotRuleCollection> {
  const { data } = await apiClient.get<MerchantPickupSlotRuleCollection>(
    `/api/merchant/stores/${storeId}/pickup-slot-rules`,
  );
  return data;
}

export async function createMerchantSlotRule(
  storeId: string,
  payload: CreateSlotRulePayload,
): Promise<MerchantPickupSlotRule> {
  const { data } = await apiClient.post<MerchantPickupSlotRule>(
    `/api/merchant/stores/${storeId}/pickup-slot-rules`,
    payload,
  );
  return data;
}

export async function patchMerchantSlotRule(
  storeId: string,
  ruleId: string,
  payload: PatchSlotRulePayload,
): Promise<MerchantPickupSlotRule> {
  const { data } = await apiClient.patch<MerchantPickupSlotRule>(
    `/api/merchant/stores/${storeId}/pickup-slot-rules/${ruleId}`,
    payload,
  );
  return data;
}

export async function deleteMerchantSlotRule(
  storeId: string,
  ruleId: string,
): Promise<void> {
  await apiClient.delete(
    `/api/merchant/stores/${storeId}/pickup-slot-rules/${ruleId}`,
  );
}

export async function generateMerchantSlots(
  storeId: string,
): Promise<GenerateSlotsResult> {
  const { data } = await apiClient.post<GenerateSlotsResult>(
    `/api/merchant/stores/${storeId}/pickup-slot-rules/generate`,
    {},
  );
  return data;
}
