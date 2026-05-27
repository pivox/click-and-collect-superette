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
  const { data } = await apiClient.get<{
    store_id: string;
    items: Array<{
      id: string;
      starts_at: string;
      ends_at: string;
      capacity: number;
      available_count: number;
    }>;
  }>(
    `/api/stores/${shopId}/pickup-slots`,
    { params: { date } },
  );
  return (data.items ?? []).map((item) => ({
    id: item.id,
    startsAt: item.starts_at,
    endsAt: item.ends_at,
    capacity: item.capacity,
    available: item.available_count > 0,
  }));
}
