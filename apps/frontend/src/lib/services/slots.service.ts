import type { PickupSlot } from "@/types";
import { MOCK_SLOTS_TODAY } from "@/lib/mock/slots.mock";
import { apiClient } from "@/lib/api";
import { USE_MOCKS, mockDelay } from "./index";

export async function listSlotsForShop(
  shopId: string,
  date: "today" | "tomorrow" | string = "today",
): Promise<PickupSlot[]> {
  if (USE_MOCKS) {
    // Tomorrow / other days are placeholders for now
    return mockDelay(MOCK_SLOTS_TODAY);
  }
  const { data } = await apiClient.get<PickupSlot[]>(
    `/api/stores/${shopId}/pickup-slots`,
    { params: { date } },
  );
  return data;
}
