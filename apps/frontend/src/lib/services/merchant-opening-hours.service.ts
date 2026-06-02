import { apiClient } from "@/lib/api";
import { MOCK_OPENING_HOURS } from "@/lib/mock/merchant-opening-hours.mock";
import type { OpeningHours } from "@/lib/types/merchant-slots.types";
import { USE_MOCKS, mockDelay } from "./index";

let mockHours: OpeningHours = { ...MOCK_OPENING_HOURS };

export async function getMerchantOpeningHours(storeId: string): Promise<OpeningHours> {
  if (USE_MOCKS) return mockDelay({ ...mockHours });
  const { data } = await apiClient.get<OpeningHours>(
    `/api/merchant/stores/${storeId}/opening-hours`,
  );
  return data;
}

export async function patchMerchantOpeningHours(
  storeId: string,
  payload: Partial<OpeningHours>,
): Promise<OpeningHours> {
  if (USE_MOCKS) {
    mockHours = { ...mockHours, ...payload };
    return mockDelay({ ...mockHours });
  }
  const { data } = await apiClient.patch<OpeningHours>(
    `/api/merchant/stores/${storeId}/opening-hours`,
    payload,
  );
  return data;
}
