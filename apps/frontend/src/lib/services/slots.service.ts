import type { PickupSlot } from "@/types";
import { MOCK_SLOTS_TODAY } from "@/lib/mock/slots.mock";
import { apiClient } from "@/lib/api";
import { USE_MOCKS, mockDelay } from "./index";

// ORDER-LEAD-004: the public listing now carries the shop booking policy.
export interface SlotBookingPolicy {
  minimumPickupLeadTimeMinutes: number;
  /** Theoretical bound (serverNow + lead time) — not necessarily a real slot. */
  earliestBookableAt: string | null;
}

export interface SlotListing {
  slots: PickupSlot[];
  bookingPolicy: SlotBookingPolicy | null;
}

export async function listSlotsForShop(
  shopId: string,
  date: "today" | "tomorrow" | string = "today",
): Promise<SlotListing> {
  if (USE_MOCKS) {
    // Tomorrow / other days are placeholders for now
    return mockDelay({
      slots: MOCK_SLOTS_TODAY,
      bookingPolicy: { minimumPickupLeadTimeMinutes: 0, earliestBookableAt: null },
    });
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
    booking_policy?: {
      minimum_pickup_lead_time_minutes: number;
      earliest_bookable_at?: string | null;
    } | null;
  }>(
    `/api/stores/${shopId}/pickup-slots`,
    { params: { date } },
  );
  return {
    slots: (data.items ?? []).map((item) => ({
      id: item.id,
      startsAt: item.starts_at,
      endsAt: item.ends_at,
      capacity: item.capacity,
      available: item.available_count > 0,
    })),
    bookingPolicy: data.booking_policy
      ? {
          minimumPickupLeadTimeMinutes: data.booking_policy.minimum_pickup_lead_time_minutes,
          earliestBookableAt: data.booking_policy.earliest_bookable_at ?? null,
        }
      : null,
  };
}
